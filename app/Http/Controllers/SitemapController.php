<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Hop\Bio\Models\BioPage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /** Every indexable public page of this host, once each (the bare address). */
    public const PAGES = ['', 'qr', 'check', 'features', 'about', 'help', 'faq', 'docs', 'status', 'changelog', 'contact', 'terms', 'privacy'];

    public function index(): Response
    {
        // A sitemap may only list URLs on the host that serves it. This one is served by the app's own host
        // (APP_URL, link.hel.ink): its public pages are listed with that host, each with its nine language addresses
        // (en on the bare address, the other eight ?lang=) and x-default (App\Support\Seo, the family rule), and the bio pages (PUBLIC_URL/b/{slug}) only when
        // both are one host (a self-hosted install). On hel.ink the bio pages belong to the hub's sitemap.
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
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach (self::PAGES as $path) {
            $xml .= '<url>';
            $xml .= '<loc>'.e(Seo::url($path)).'</loc>';
            foreach (Seo::alternates($path) as $code => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.e($code).'" href="'.e($href).'"/>';
            }
            $xml .= '<changefreq>monthly</changefreq>';
            $xml .= '<priority>'.($path === '' ? '1.0' : '0.5').'</priority>';
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
