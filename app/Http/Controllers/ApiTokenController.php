<?php

namespace App\Http\Controllers;

/**
 * The API documentation and the ShareX config. Tokens themselves are made and
 * revoked at the hub (hub.hel.ink/settings) and read from the shared table.
 */
class ApiTokenController extends Controller
{
    /**
     * Show API documentation
     */
    public function docs()
    {
        return view('api-docs');
    }

    /**
     * Download ShareX configuration file
     */
    public function sharexConfig()
    {
        $config = [
            'Version' => '15.0.0',
            'Name' => 'hel.ink URL Shortener',
            'DestinationType' => 'URLShortener',
            'RequestMethod' => 'POST',
            'RequestURL' => url('/api/shorten'),
            'Headers' => [
                'Authorization' => 'Bearer YOUR_API_TOKEN_HERE',
                'Content-Type' => 'application/json',
            ],
            'Body' => 'JSON',
            'Data' => json_encode([
                'url' => '{input}',
            ]),
            'URL' => '{json:data.url}',
        ];
        $filename = 'helink-sharex-config.sxcu';
        $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($content)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
