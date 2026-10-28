<?php

namespace Hop\Bio\Support;

/**
 * Turns a pasted video or music link into an embeddable player URL.
 * Mirrors getVideoEmbed()/getMusicEmbed() in the editor preview so a block
 * that plays in the preview also plays on the public page (M6).
 */
class BioEmbed
{
    /**
     * @return array{src: string, kind: string, height: ?int}|null
     */
    public static function video(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~youtube\.com/embed/([A-Za-z0-9_-]+)~', $url, $m)
            || preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]+)~', $url, $m)) {
            return ['src' => 'https://www.youtube.com/embed/'.$m[1].'?rel=0', 'kind' => 'video', 'height' => null];
        }
        if (preg_match('~youtube\.com/playlist\?list=([A-Za-z0-9_-]+)~', $url, $m)) {
            return ['src' => 'https://www.youtube.com/embed/videoseries?list='.$m[1], 'kind' => 'video', 'height' => null];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return ['src' => 'https://player.vimeo.com/video/'.$m[1].'?dnt=1', 'kind' => 'video', 'height' => null];
        }
        if (preg_match('~tiktok\.com/(?:@[\w.]+/video|embed(?:/v2)?)/(\d+)~', $url, $m)) {
            return ['src' => 'https://www.tiktok.com/embed/v2/'.$m[1], 'kind' => 'tiktok', 'height' => 740];
        }

        return null;
    }

    /**
     * @return array{src: string, kind: string, height: ?int}|null
     */
    public static function music(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~spotify\.com/(?:intl-[a-z-]+/)?(?:embed/)?(track|album|playlist|artist|episode|show)/([A-Za-z0-9]+)~', $url, $m)) {
            $height = in_array($m[1], ['album', 'playlist'], true) ? 380 : 152;

            return ['src' => "https://open.spotify.com/embed/{$m[1]}/{$m[2]}?utm_source=generator&theme=0", 'kind' => 'music', 'height' => $height];
        }
        if (preg_match('~^https?://(?:www\.|m\.|on\.)?soundcloud\.com/~i', $url)) {
            $sets = str_contains($url, '/sets/');

            return [
                'src' => 'https://w.soundcloud.com/player/?url='.rawurlencode($url).'&color=%236366f1&auto_play=false&hide_related=true&show_comments=false&show_user=true&show_reposts=false&show_teaser=false&visual='.($sets ? 'true' : 'false'),
                'kind' => 'music',
                'height' => $sets ? 300 : 166,
            ];
        }
        if (preg_match('~music\.apple\.com/([a-z]{2})/(album|playlist|song)/[^/]+/([A-Za-z0-9.-]+)~i', $url, $m)) {
            return ['src' => "https://embed.music.apple.com/{$m[1]}/{$m[2]}/{$m[3]}", 'kind' => 'music', 'height' => 175];
        }
        if (str_contains($url, 'music.youtube.com') && preg_match('~[?&]v=([A-Za-z0-9_-]+)~', $url, $m)) {
            return ['src' => 'https://www.youtube.com/embed/'.$m[1].'?rel=0', 'kind' => 'video', 'height' => null];
        }

        return null;
    }

    /**
     * Hosts the public page and preview may frame (CSP frame-src).
     */
    public const FRAME_HOSTS = [
        'https://www.youtube.com',
        'https://player.vimeo.com',
        'https://www.tiktok.com',
        'https://open.spotify.com',
        'https://w.soundcloud.com',
        'https://embed.music.apple.com',
    ];
}
