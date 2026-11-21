<?php

declare(strict_types=1);

namespace VeloForum\Controller;

use PDO;

final class SeoController
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $baseUrl
    ) {
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        $base = rtrim($this->baseUrl, '/');
        echo "User-agent: *\n";
        echo "Disallow: /login\n";
        echo "Disallow: /migrate\n";
        echo "Disallow: /notifications\n";
        echo "Sitemap: {$base}/sitemap.xml\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $base = rtrim($this->baseUrl, '/');

        $forums = $this->db->query(
            'SELECT id, slug, last_post_at FROM vf_forums WHERE is_category = 0 ORDER BY id ASC LIMIT 2000'
        )->fetchAll();

        $topics = $this->db->query(
            'SELECT id, slug, last_post_at FROM vf_topics WHERE is_approved = 1 ORDER BY last_post_at DESC LIMIT 5000'
        )->fetchAll();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo "  <url><loc>" . htmlspecialchars($base . '/', ENT_XML1, 'UTF-8') . "</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";

        foreach ($forums as $f) {
            $loc = htmlspecialchars("{$base}/forum/{$f['id']}-{$f['slug']}", ENT_XML1, 'UTF-8');
            $lastmod = !empty($f['last_post_at']) ? '<lastmod>' . date('c', (int) $f['last_post_at']) . '</lastmod>' : '';
            echo "  <url><loc>{$loc}</loc>{$lastmod}<changefreq>daily</changefreq><priority>0.8</priority></url>\n";
        }

        foreach ($topics as $t) {
            $loc = htmlspecialchars("{$base}/topic/{$t['id']}-{$t['slug']}", ENT_XML1, 'UTF-8');
            $lastmod = '<lastmod>' . date('c', (int) $t['last_post_at']) . '</lastmod>';
            echo "  <url><loc>{$loc}</loc>{$lastmod}<changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
        }

        echo "</urlset>\n";
    }
}
