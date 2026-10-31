<?php

namespace App\Http\Controllers;

use Hop\Bio\Models\BioPage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // The home and marketing pages are the hub's (its own sitemap). This one lists
        // what the public reaches on PUBLIC_URL through this app: the link checker and bio pages.
        $staticPages = [
            ['loc' => public_url('check'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        // Public bio pages (hel.ink/b/{slug}); none when the bio package is off.
        $bioPages = ! bio_enabled() ? collect() : BioPage::query()
            ->where('is_published', true)
            ->where('allow_indexing', true)
            ->where('is_adult_content', false)
            ->where(function ($q) {
                $q->whereNull('content_warning')->orWhere('content_warning', 'none');
            })
            ->latest('updated_at')
            ->limit(1000)
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($staticPages as $page) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($page['loc']).'</loc>';
            $xml .= '<changefreq>'.$page['changefreq'].'</changefreq>';
            $xml .= '<priority>'.$page['priority'].'</priority>';
            $xml .= '</url>';
        }

        foreach ($bioPages as $bio) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars(public_url('b/'.$bio->slug)).'</loc>';
            $xml .= '<lastmod>'.$bio->updated_at->toW3cString().'</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.6</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
