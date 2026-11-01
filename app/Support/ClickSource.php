<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where a visit came from, told by ?src= on the short address: a QR code carries ?src=qr, an NFC tag ?src=nfc
 * (BioHop does the same). Anything else is a direct click. The redirect never passes the visitor's query on to the
 * destination, so ?src= stays ours.
 */
class ClickSource
{
    public const DIRECT = 'direct';

    public const QR = 'qr';

    /** What ?src= may say besides "direct". */
    public const ALLOWED = [self::QR, 'nfc'];

    public static function fromRequest(Request $request): string
    {
        $src = $request->query('src');
        $src = is_string($src) ? strtolower(trim($src)) : '';

        return in_array($src, self::ALLOWED, true) ? $src : self::DIRECT;
    }

    /** A short address as printed in a QR code: with ?src=qr (any other query is kept). */
    public static function tag(string $url, string $source = self::QR): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'src='.$source;
    }
}
