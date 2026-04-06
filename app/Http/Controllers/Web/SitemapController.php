<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Category\Models\Category;
use App\Domain\Product\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = mb_rtrim(config('app.url'), '/');
        $locales = config('app.locales');

        $products = Product::query()
            ->where('is_active', true)
            ->select('slug', 'updated_at')
            ->orderBy('slug')
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->select('slug')
            ->orderBy('slug')
            ->get();

        $urls = [];

        // Home pages per locale
        foreach ($locales as $locale) {
            $urls[] = [
                'loc' => "{$baseUrl}/{$locale}",
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];
        }

        // Product pages per locale
        foreach ($products as $product) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => "{$baseUrl}/{$locale}/products/{$product->slug}",
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        }

        // Category filter pages per locale
        foreach ($categories as $category) {
            foreach ($locales as $locale) {
                $urls[] = [
                    'loc' => "{$baseUrl}/{$locale}/products?category={$category->slug}",
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            }
        }

        $xml = $this->buildXml($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @param  array<int, array<string, string|null>>  $urls
     */
    private function buildXml(array $urls): string
    {
        $items = '';

        foreach ($urls as $url) {
            $items .= '  <url>'.PHP_EOL;
            $items .= '    <loc>'.e($url['loc']).'</loc>'.PHP_EOL;

            if (! empty($url['lastmod'])) {
                $items .= '    <lastmod>'.$url['lastmod'].'</lastmod>'.PHP_EOL;
            }

            $items .= '    <changefreq>'.$url['changefreq'].'</changefreq>'.PHP_EOL;
            $items .= '    <priority>'.$url['priority'].'</priority>'.PHP_EOL;
            $items .= '  </url>'.PHP_EOL;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
            .$items
            .'</urlset>';
    }
}
