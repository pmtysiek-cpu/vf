<?php

declare(strict_types=1);

namespace VeloForum\Service;

use VeloForum\Repository\MemberRepository;

/**
 * Rozwiązuje wzmianki (@użytkownik) oraz istniejące znaczniki WYSIWYG <a class="vf-mention" data-mention-id="...">
 * w treści postów, nadaje kolor grupy i zwraca kompletną listę wzmiankowanych członków do powiadomień.
 */
final class MentionService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /**
     * @return array{html: string, mentionedMembers: array<int, array<string, mixed>>}
     */
    public function process(string $sanitizedHtml): array
    {
        if ($sanitizedHtml === '' || (!str_contains($sanitizedHtml, '@') && !str_contains($sanitizedHtml, 'data-mention-id'))) {
            return ['html' => $sanitizedHtml, 'mentionedMembers' => []];
        }

        // 1. Zbierz ID ze wzmianek wstawionych przez edytor WYSIWYG (<a ... data-mention-id="123">)
        preg_match_all('/data-mention-id=["\'](\d+)["\']/i', $sanitizedHtml, $idMatches);
        $mentionIds = array_map('intval', $idMatches[1] ?? []);

        // 2. Zbierz kandydatów z czystego tekstu (@Nick lub @Nick Name przed znakami interpunkcji)
        $plain = html_entity_decode(strip_tags($sanitizedHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\u{00A0}", ' ', $plain);
        preg_match_all('/@([A-Za-z0-9_.\-ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]{2,40})/u', $plain, $matches);
        $candidates = array_values(array_unique($matches[1] ?? []));

        $byId = [];
        $byLowerName = [];

        if ($mentionIds !== []) {
            foreach ($this->members->findBriefWithGroupsByIds($mentionIds) as $member) {
                $mid = (int) $member['id'];
                $byId[$mid] = $member;
                $byLowerName[mb_strtolower((string) $member['username'])] = $member;
            }
        }

        if ($candidates !== []) {
            foreach ($this->members->findByUsernames($candidates) as $member) {
                $mid = (int) $member['id'];
                $byId[$mid] = $member;
                $byLowerName[mb_strtolower((string) $member['username'])] = $member;
            }
        }

        if ($byId === [] && $byLowerName === []) {
            return ['html' => $sanitizedHtml, 'mentionedMembers' => []];
        }

        $mentioned = [];

        // Znormalizuj istniejące linki wzmianek z edytora (dodaj kolor grupy i zarejestruj powiadomienie)
        $html = preg_replace_callback(
            '/<a\b([^>]*\bdata-mention-id=["\'](\d+)["\'][^>]*)>(.*?)<\/a>/is',
            static function (array $m) use ($byId, &$mentioned): string {
                $id = (int) $m[2];
                if (!isset($byId[$id])) {
                    return $m[0];
                }
                $member = $byId[$id];
                $mentioned[$id] = $member;
                $slug = htmlspecialchars((string) $member['slug'], ENT_QUOTES, 'UTF-8');
                $uname = htmlspecialchars((string) $member['username'], ENT_QUOTES, 'UTF-8');
                $color = self::safeHex((string) ($member['group_color'] ?? '#2563eb'));
                return '<a href="/member/' . $id . '-' . $slug . '" class="vf-mention" data-mention-id="' . $id . '" style="color:' . $color . '">@' . $uname . '</a>';
            },
            $sanitizedHtml
        ) ?? $sanitizedHtml;

        // Zamień zwykłe tekstowe @Nick poza <code>, <pre> i istniejącymi <a>
        $parts = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $inCode = 0;
        $inAnchor = 0;

        foreach ($parts as $idx => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('/^<(code|pre)\b/i', $part)) {
                    $inCode++;
                } elseif (preg_match('/^<\/(code|pre)>/i', $part)) {
                    $inCode = max(0, $inCode - 1);
                } elseif (preg_match('/^<a\b/i', $part)) {
                    $inAnchor++;
                } elseif (preg_match('/^<\/a>/i', $part)) {
                    $inAnchor = max(0, $inAnchor - 1);
                }
                continue;
            }

            if ($inCode > 0 || $inAnchor > 0 || !str_contains($part, '@')) {
                continue;
            }

            $parts[$idx] = preg_replace_callback(
                '/@([A-Za-z0-9_.\-ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]{2,40})/u',
                static function (array $m) use ($byLowerName, &$mentioned): string {
                    $key = mb_strtolower($m[1]);
                    if (!isset($byLowerName[$key])) {
                        return $m[0];
                    }
                    $member = $byLowerName[$key];
                    $id = (int) $member['id'];
                    $mentioned[$id] = $member;
                    $slug = htmlspecialchars((string) $member['slug'], ENT_QUOTES, 'UTF-8');
                    $uname = htmlspecialchars((string) $member['username'], ENT_QUOTES, 'UTF-8');
                    $color = self::safeHex((string) ($member['group_color'] ?? '#2563eb'));
                    return '<a href="/member/' . $id . '-' . $slug . '" class="vf-mention" data-mention-id="' . $id . '" style="color:' . $color . '">@' . $uname . '</a>';
                },
                $part
            ) ?? $part;
        }

        return [
            'html'             => implode('', $parts),
            'mentionedMembers' => array_values($mentioned),
        ];
    }

    private static function safeHex(string $color): string
    {
        $trimmed = trim($color);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $trimmed) === 1 ? $trimmed : '#2563eb';
    }
}
