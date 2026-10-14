<?php

/*
|--------------------------------------------------------------------------
| Abuse / spam detection
|--------------------------------------------------------------------------
|
| Used by App\Services\LinkSafety (checks at creation time) and the
| `abuse:scan` command (scheduled sweep). Categories listed in
| `block_categories` are rejected outright when a link is created; every
| category contributes `weights[category]` to a subject's score in scans.
|
*/

return [

    // Keyword patterns matched against URL + title/description text.
    'patterns' => [
        'csam' => '/\b(pthc|preteen|jailbait|lolicon|shotacon|pedophil\w*|hussyfan|babyj|opva|(child|kid)[\s_-]*porn|underage[\s_-]*(sex|porn|nude)|cp[\s_-]*(link|video|vid)s?)\b/i',
        'gambling' => '/(gacor|maxwin|togel|judi\s*online|sbobet|slot[\s_-]*(online|gacor|88|thailand|deposit|demo)|rtp[\s_-]*(live|slot)|toto[\s_-]*(macau|slot)|agen[\s_-]*(slot|togel|judi)|scatter[\s_-]*hitam|mahjong[\s_-]*ways)/i',
        'phishing' => '/(verify[-_\s]*account|account[-_\s]*verif|secure[-_\s]*login|wallet[-_\s]*connect|seed[-_\s]*phrase|claim[-_\s]*airdrop|airdrop[-_\s]*claim|dana[-_\s]*kaget|klaim[-_\s]*hadiah|verifikasi[-_\s]*(akun|rekening)|update[-_\s]*rekening)/i',
        'scam' => '/(double\s*your\s*(btc|crypto|money)|giveaway\s*(btc|eth|usdt)|investasi\s*(bodong|pasti\s*untung))/i',
        'adult' => '/\b(porn|xxx|bokep|ngentot|hentai|onlyfans\s*leak|colmek)\b/i',
    ],

    // Rejected at creation time and auto-actioned by abuse:scan.
    'block_categories' => ['csam', 'gambling', 'phishing', 'scam', 'malware', 'blacklisted', 'dangerous_scheme'],

    // Score contributed by each signal.
    'weights' => [
        'csam' => 100,
        'malware' => 100,
        'blacklisted' => 100,
        'dangerous_scheme' => 100,
        'phishing' => 80,
        'gambling' => 60,
        'scam' => 60,
        'executable_download' => 40,
        'adult' => 10,
        'chained_shortener' => 10,
        'file_host' => 20,
        'punycode' => 10,
        'ip_host' => 10,
        'risky_tld' => 5,
    ],

    // abuse:scan thresholds (per subject).
    'thresholds' => [
        'disable' => 60,       // deactivate the link / bio page
        'suspend_user' => 100, // owner's account is suspended
    ],

    // Owner is suspended when this many of their items are disabled in one scan.
    'suspend_after_disabled_items' => 3,

    // Items disabled for abuse are purged after this many days (never for csam).
    'purge_after_days' => 30,

    'shorteners' => ['bit.ly', 'tinyurl.com', 's.id', 'cutt.ly', 'shorturl.at', 'is.gd', 't.co', 'rebrand.ly', 'ow.ly', 'rb.gy', 'tiny.cc'],

    // Paste, anonymous file and video hosts often used to pass on abusive
    // material. A weak signal on its own; it adds up with other signals.
    'file_hosts' => [
        'justpaste.it', 'pastebin.com', 'paste.ee', 'ghostbin.com', 'rentry.co', 'rentry.org', 'privatebin.net',
        'zerobin.net', 'hastebin.com', 'dpaste.org', 'controlc.com', 'paste2.org', 'cl1p.net', '0bin.net', 'textbin.net',
        'telegra.ph', 'teletype.in', 'catbox.moe', 'litterbox.catbox.moe', 'anonfiles.com', 'anonfile.com', 'bayfiles.com',
        'mega.nz', 'mega.io', 'mediafire.com', 'zippyshare.com', 'file.io', 'gofile.io', 'sendspace.com', 'ufile.io',
        'uploadfiles.io', 'filepost.com', 'drop.sc', 'dropapk.to', 'dood.to', 'dood.la', 'dood.so', 'dood.watch',
        'doodstream.com', 'streamtape.com', 'mixdrop.co', 'upstream.to', 'streamlare.com', 'uqload.com', 'vidoza.net',
        // Video embed hosts common on pirated/adult re-uploads.
        'voe.sx', 'voe.to', 'filemoon.sx', 'filemoon.to', 'filemoon.in', 'streamwish.to', 'streamwish.com', 'wishembed.pro',
        'vidhide.com', 'vidhidepro.com', 'lulustream.com', 'luluvdo.com', 'd000d.com', 'd0000d.com', 'ds2play.com',
        'doods.pro', 'dood.yt', 'dood.re', 'dood.wf', 'mixdrop.to', 'mixdrop.sx', 'streamtape.to', 'vidguard.to',
        'mp4upload.com', 'vtube.to', 'streamvid.net', 'terabox.com', '1024terabox.com', 'teraboxapp.com',
        'krakenfiles.com', 'pixeldrain.com', 'bunkr.si', 'bunkr.ru', 'bunkrr.su', 'cyberdrop.me', 'saint.to', 'saint2.su',
    ],

    'risky_tlds' => ['zip', 'mov', 'top', 'xyz', 'click', 'icu', 'cfd', 'sbs', 'rest', 'lol', 'buzz', 'cyou', 'monster'],

    // Optional external reputation lookups (skipped when the key is empty).
    'providers' => [
        'safe_browsing_key' => env('SAFE_BROWSING_KEY'),
        'urlhaus_auth_key' => env('URLHAUS_AUTH_KEY'),
        // DNS lookup against dbl.spamhaus.org; needs a non-public DNS resolver on the server.
        'spamhaus_dbl' => (bool) env('SPAMHAUS_DBL', true),
        'timeout' => 5,
        'cache_hours' => 12,
    ],

    // Registrations allowed per IP per hour.
    'register_per_ip_per_hour' => (int) env('ABUSE_REGISTER_PER_HOUR', 3),
];
