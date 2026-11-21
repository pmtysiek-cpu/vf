<?php

declare(strict_types=1);

namespace VeloForum\Service;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Ścisły sanitizer HTML oparty na DOMDocument (biała lista tagów i atrybutów).
 * Obsługuje zarówno treść z nowoczesnego edytora blokowego WYSIWYG, jak i posty z IC5.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's',
        'a', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'h3', 'h4', 'hr', 'img', 'span', 'div', 'details', 'summary',
        'figure', 'figcaption', 'video', 'source', 'iframe',
    ];

    public static function clean(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            return nl2br(htmlspecialchars(strip_tags($trimmed), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__vf_root">'
            . $trimmed
            . '</div></body></html>';

        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('__vf_root');
        if (!$root) {
            return htmlspecialchars(strip_tags($trimmed), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Bezpiecznie przetwarza dane z formularza (HTML z edytora WYSIWYG lub zwykły tekst/markdown).
     */
    public static function sanitizeSubmittedContent(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/<(p|h3|h4|ul|ol|blockquote|pre|strong|em|b|i|u|s|a|span|div|br|hr|img|figure|video|iframe|details|summary)\b/i', $trimmed) === 1) {
            $cleaned = self::clean($trimmed);
            return $cleaned !== '' ? $cleaned : self::plainTextToHtml($trimmed);
        }

        return self::plainTextToHtml($trimmed);
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, ['script', 'style', 'object', 'embed', 'svg', 'form'], true)) {
                    $node->removeChild($child);
                    continue;
                }

                if ($tag === 'iframe') {
                    $src = trim($child->getAttribute('src'));
                    if (!self::isKlipyEmbedUrl($src)) {
                        $node->removeChild($child);
                        continue;
                    }
                }

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::sanitizeNode($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                self::sanitizeAttributes($child, $tag);
                self::sanitizeNode($child);

                if ($tag === 'video' && !self::hasValidKlipyVideoSource($child)) {
                    $node->removeChild($child);
                    continue;
                }

                if ($tag === 'source' && !self::isKlipyMediaUrl(trim($child->getAttribute('src')))) {
                    $node->removeChild($child);
                    continue;
                }
            }
        }
    }

    private static function sanitizeAttributes(DOMElement $el, string $tag): void
    {
        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[] = $attr->name;
        }

        foreach ($attrs as $name) {
            $lower = strtolower($name);
            if ($tag === 'a' && $lower === 'href') {
                $val = trim($el->getAttribute($name));
                if (!self::isSafeUrl($val)) {
                    $el->setAttribute('href', '#');
                }
                continue;
            }
            if (($tag === 'a' || $tag === 'span') && $lower === 'data-mention-id') {
                $id = (int) $el->getAttribute($name);
                if ($id > 0) {
                    $el->setAttribute('data-mention-id', (string) $id);
                    continue;
                }
            }
            if (($tag === 'a' || $tag === 'span') && $lower === 'class') {
                $cls = trim($el->getAttribute($name));
                if (str_contains($cls, 'vf-mention')) {
                    $el->setAttribute('class', 'vf-mention');
                    continue;
                }
            }
            if (($tag === 'a' || $tag === 'span') && $lower === 'style') {
                $cls = trim($el->getAttribute('class'));
                $style = trim($el->getAttribute($name));
                if (str_contains($cls, 'vf-mention') && preg_match('/^color\s*:\s*(#[0-9a-fA-F]{3,8})\s*;?$/i', $style, $sm)) {
                    $el->setAttribute('style', 'color:' . $sm[1]);
                    continue;
                }
            }
            if ($tag === 'div' && $lower === 'class') {
                $cls = trim($el->getAttribute($name));
                if (str_contains($cls, 'vf-callout')) {
                    $el->setAttribute('class', 'vf-callout');
                    continue;
                }
                if (str_contains($cls, 'vf-klipy-embed')) {
                    $el->setAttribute('class', 'vf-klipy-embed');
                    continue;
                }
            }
            if ($tag === 'figure' && $lower === 'class') {
                $cls = trim($el->getAttribute($name));
                if (str_contains($cls, 'vf-klipy-embed')) {
                    $el->setAttribute('class', 'vf-klipy-embed');
                    continue;
                }
            }
            if ($tag === 'img' && in_array($lower, ['src', 'alt', 'title', 'loading'], true)) {
                if ($lower === 'src' && !self::isSafeUrl(trim($el->getAttribute($name)))) {
                    $el->removeAttribute('src');
                }
                continue;
            }
            if ($tag === 'video' && in_array($lower, ['src', 'poster', 'controls', 'autoplay', 'loop', 'muted', 'playsinline', 'preload'], true)) {
                if ($lower === 'src' && !self::isKlipyMediaUrl(trim($el->getAttribute($name)))) {
                    $el->removeAttribute('src');
                }
                if ($lower === 'poster' && !self::isSafeUrl(trim($el->getAttribute($name)))) {
                    $el->removeAttribute('poster');
                }
                continue;
            }
            if ($tag === 'source' && in_array($lower, ['src', 'type'], true)) {
                if ($lower === 'src' && !self::isKlipyMediaUrl(trim($el->getAttribute($name)))) {
                    $el->removeAttribute('src');
                }
                if ($lower === 'type' && !in_array(strtolower(trim($el->getAttribute($name))), ['video/mp4', 'video/webm'], true)) {
                    $el->removeAttribute('type');
                }
                continue;
            }
            if ($tag === 'iframe' && in_array($lower, ['src', 'title', 'loading'], true)) {
                if ($lower === 'src' && !self::isKlipyEmbedUrl(trim($el->getAttribute($name)))) {
                    $el->removeAttribute('src');
                }
                continue;
            }
            $el->removeAttribute($name);
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $href = $el->getAttribute('href');
            if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
        if ($tag === 'img' && $el->hasAttribute('src')) {
            $el->setAttribute('loading', 'lazy');
        }
        if ($tag === 'video') {
            $el->setAttribute('controls', 'controls');
            $el->setAttribute('playsinline', 'playsinline');
            $el->setAttribute('loop', 'loop');
            $el->setAttribute('muted', 'muted');
        }
        if ($tag === 'iframe') {
            $el->setAttribute('loading', 'lazy');
            $el->setAttribute('referrerpolicy', 'no-referrer');
            $el->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups');
        }
    }

    private static function isKlipyEmbedUrl(string $url): bool
    {
        return preg_match('#^https://(www\.)?klipy\.com/embed/[a-zA-Z0-9_/?=&.%-]+$#i', $url) === 1;
    }

    private static function isKlipyMediaUrl(string $url): bool
    {
        return preg_match('#^https://static[0-9]*\.klipy\.com/[a-zA-Z0-9_./%-]+\.(mp4|webm)(\?.*)?$#i', $url) === 1;
    }

    private static function hasValidKlipyVideoSource(DOMElement $video): bool
    {
        if ($video->hasAttribute('src') && self::isKlipyMediaUrl(trim($video->getAttribute('src')))) {
            return true;
        }
        foreach ($video->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'source') {
                if ($child->hasAttribute('src') && self::isKlipyMediaUrl(trim($child->getAttribute('src')))) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    public static function plainTextToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [$text];
        $html = [];

        foreach ($paragraphs as $block) {
            $escaped = htmlspecialchars(trim($block), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ($escaped === '') {
                continue;
            }
            $escaped = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
            $escaped = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
            $escaped = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

            if (str_starts_with($escaped, '&gt; ')) {
                $inner = preg_replace('/^&gt;\s?/m', '', $escaped) ?? $escaped;
                $html[] = '<blockquote><p>' . nl2br($inner, false) . '</p></blockquote>';
            } else {
                $html[] = '<p>' . nl2br($escaped, false) . '</p>';
            }
        }

        return implode("\n", $html);
    }
}
