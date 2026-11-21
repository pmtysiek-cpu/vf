<?php

declare(strict_types=1);

namespace VeloForum\Service;

/**
 * Generuje metadane SEO: kanoniczne URL, meta description, OpenGraph, Twitter Cards
 * oraz dane strukturalne Schema.org JSON-LD (WebSite, BreadcrumbList, DiscussionForumPosting).
 */
final class SeoService
{
    public function __construct(
        private readonly string $siteName,
        private readonly string $baseUrl
    ) {
    }

    public function build(array $options): array
    {
        $title = trim((string) ($options['title'] ?? $this->siteName));
        $description = $this->truncate((string) ($options['description'] ?? 'Nowoczesna społeczność i forum dyskusyjne.'), 158);
        $path = '/' . ltrim((string) ($options['path'] ?? ''), '/');
        $canonical = rtrim($this->baseUrl, '/') . ($path === '/' ? '/' : $path);
        $type = (string) ($options['og_type'] ?? 'website');
        $robots = (string) ($options['robots'] ?? 'index, follow');

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'og_type'     => $type,
            'site_name'   => $this->siteName,
            'robots'      => $robots,
            'json_ld'     => $options['json_ld'] ?? [],
        ];
    }

    public function homeJsonLd(): array
    {
        $base = rtrim($this->baseUrl, '/');
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => $this->siteName,
            'url'             => $base . '/',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => $base . '/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $breadcrumbs
     */
    public function breadcrumbJsonLd(array $breadcrumbs): array
    {
        $base = rtrim($this->baseUrl, '/');
        $items = [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => $this->siteName,
                'item'     => $base . '/',
            ],
        ];

        $pos = 2;
        foreach ($breadcrumbs as $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => (string) $crumb['name'],
                'item'     => $base . '/forum/' . (int) $crumb['id'] . '-' . (string) $crumb['slug'],
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Schema.org DiscussionForumPosting zalecane przez Google dla wątków na forach.
     *
     * @param array<string, mixed> $topic
     * @param array<int, array<string, mixed>> $posts
     */
    public function topicJsonLd(array $topic, array $posts): array
    {
        $base = rtrim($this->baseUrl, '/');
        $url = $base . '/topic/' . (int) $topic['id'] . '-' . (string) $topic['slug'];
        $firstPost = $posts[0] ?? null;
        $bodyText = $firstPost ? $this->truncate(strip_tags((string) $firstPost['content_html']), 500) : '';

        $comments = [];
        foreach (array_slice($posts, 1, 10) as $reply) {
            $comments[] = [
                '@type'         => 'Comment',
                'text'          => $this->truncate(strip_tags((string) $reply['content_html']), 300),
                'datePublished' => date('c', (int) $reply['created_at']),
                'author'        => [
                    '@type' => 'Person',
                    'name'  => (string) ($reply['member_username'] ?? $reply['author_name']),
                ],
            ];
        }

        return [
            '@context'             => 'https://schema.org',
            '@type'                => 'DiscussionForumPosting',
            'headline'             => (string) $topic['title'],
            'text'                 => $bodyText,
            'url'                  => $url,
            'datePublished'        => date('c', (int) $topic['created_at']),
            'dateModified'         => date('c', (int) $topic['last_post_at']),
            'author'               => [
                '@type' => 'Person',
                'name'  => (string) $topic['author_name'],
            ],
            'interactionStatistic' => [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/CommentAction',
                'userInteractionCount' => max(0, (int) $topic['posts_count'] - 1),
            ],
            'comment'              => $comments,
        ];
    }

    private function truncate(string $text, int $limit): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }
        return rtrim(mb_substr($clean, 0, $limit - 1)) . '…';
    }
}
