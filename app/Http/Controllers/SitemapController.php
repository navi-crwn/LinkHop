<?php

namespace App\Http\Controllers;

use Hop\Bio\Models\BioPage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // A sitemap may only list URLs on the host that serves it. This one is served by the app's own host
        // (APP_URL, link.hel.ink): its public pages are listed with that host, and the bio pages (PUBLIC_URL/b/{slug})
        // only when both are one host (a self-hosted install). On hel.ink the bio pages belong to the hub's sitemap.
        $staticPages = collect(['', 'features', 'faq', 'about', 'help', 'qr', 'check', 'changelog', 'contact', 'terms', 'privacy'])
            ->map(fn (string $path) => ['loc' => url($path), 'priority' => $path === '' ? '1.0' : '0.5', 'changefreq' => 'monthly']);

        $sameHost = parse_url(url('/'), PHP_URL_HOST) === parse_url(public_url(), PHP_URL_HOST);
        $bioPages = (! bio_enabled() || ! $sameHost) ? collect() : BioPage::query()
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
