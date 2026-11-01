<?php

namespace App\Services;

use DeviceDetector\DeviceDetector;
use Hop\Bio\Contracts\UserAgentParser;

/**
 * User-agent parsing via matomo/device-detector (browsers, OS, device types, bots),
 * plus two checks it does not make for us: chat/social link previewers and HTTP tools
 * (never counted as visitors), and the built-in browsers of apps like Instagram or WeChat.
 */
class UserAgentService implements UserAgentParser
{
    /** Servers that fetch a link to build its preview card. Pattern => name. */
    private const PREVIEWERS = [
        '/WhatsApp\/\d/i' => 'WhatsApp',
        '/TelegramBot/i' => 'Telegram',
        '/Slackbot|Slack-ImgProxy/i' => 'Slack',
        '/Discordbot/i' => 'Discord',
        '/facebookexternalhit|facebot|meta-external(?:agent|fetcher)|facebookcatalog/i' => 'Facebook',
        '/Twitterbot/i' => 'X (Twitter)',
        '/LinkedInBot/i' => 'LinkedIn',
        '/Pinterest(?:bot)?\/\d.*bot|Pinterestbot/i' => 'Pinterest',
        '/SkypeUriPreview/i' => 'Skype',
        '/Snap URL Preview/i' => 'Snapchat',
        '/vkShare|VKRobot/i' => 'VK',
        '/redditbot/i' => 'Reddit',
        '/Mastodon\//i' => 'Mastodon',
        '/Cardyb/i' => 'Bluesky',
        '/Embedly|Iframely|January\/\d/i' => 'Link preview',
        '/Viber\/.*Preview|ViberPreview/i' => 'Viber',
        '/LineBot|Line-Preview/i' => 'LINE',
        '/Yahoo Link Preview/i' => 'Yahoo',
    ];

    /** Scripts, libraries and headless browsers: not people. */
    private const TOOLS = '/^(?:curl|wget|libwww-perl|python-(?:requests|urllib|httpx)|aiohttp|Go-http-client|Java\/|Apache-HttpClient|axios|node-fetch|undici|PostmanRuntime|insomnia|GuzzleHttp|Scrapy)|HeadlessChrome|PhantomJS|Lighthouse/i';

    /** Last resort for names of crawlers nobody listed: "bot" as a word, crawl, spider. Not "Cubot" phones. */
    private const GENERIC_BOT = '/(?:^|[\s;(\/_-])bot(?:$|[\s;)\/_\d-])|crawl|spider|slurp|linkexpanding|url-?preview|link-?preview/i';

    /** Built-in browsers of apps, most specific first. Pattern => name. */
    private const IN_APP = [
        '/FB_IAB\/MESSENGER|FBAN\/Messenger|Orca-Android|MessengerLite/i' => 'Messenger',
        '/\bInstagram\b/i' => 'Instagram',
        '/FBAN|FBAV|FB_IAB|FBIOS/' => 'Facebook',
        '/musical_ly|BytedanceWebview|TikTok|\bBytedance\b/i' => 'TikTok',
        '/\bLine\/\d/' => 'LINE',
        '/wxwork/i' => 'WeCom',
        '/MicroMessenger/i' => 'WeChat',
        '/Snapchat/i' => 'Snapchat',
        '/Twitter for (?:iPhone|iPad|Android)|TwitterAndroid/i' => 'X (Twitter)',
        '/LinkedInApp/i' => 'LinkedIn',
        '/\[Pinterest\/|Pinterest for /i' => 'Pinterest',
        '/KAKAOTALK/i' => 'KakaoTalk',
        '/NAVER\(inapp/i' => 'Naver',
        '/\bWeibo\b|__weibo__/i' => 'Weibo',
        '/\bZalo\b/i' => 'Zalo',
        '/\bQQ\/\d/' => 'QQ',
        '/AlipayClient/i' => 'Alipay',
        '/\bViber\/\d/i' => 'Viber',
        '/\bGSA\/\d/' => 'Google app',
    ];

    /** Shown for an app browser we have no name for (Android WebView; iPhone without a Safari token). */
    public const IN_APP_OTHER = 'Other app';

    private array $memo = [];

    /** @return array{device: string, browser: string, os: string, bot: bool, bot_name: ?string, in_app: ?string} */
    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return ['device' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown', 'bot' => false, 'bot_name' => null, 'in_app' => null];
        }

        return $this->memo[$userAgent] ??= $this->detect($userAgent);
    }

    /** Names of every in-app browser we recognise, for filters and tests. */
    public static function inAppNames(): array
    {
        return array_values(array_unique([...array_values(self::IN_APP), self::IN_APP_OTHER]));
    }

    private function detect(string $userAgent): array
    {
        $bot = $this->botName($userAgent);
        if ($bot === null) {
            $dd = new DeviceDetector($userAgent);
            $dd->parse();
            if ($dd->isBot()) {
                $bot = $dd->getBot()['name'] ?? 'Bot';
            }
        }
        if ($bot !== null) {
            return ['device' => 'Bot', 'browser' => $bot, 'os' => 'Unknown', 'bot' => true, 'bot_name' => $bot, 'in_app' => null];
        }
        $device = match (true) {
            $dd->isTablet() => 'Tablet',
            $dd->isMobile() => 'Mobile',
            $dd->isDesktop() => 'Desktop',
            default => ucfirst($dd->getDeviceName() ?: 'Unknown'),
        };

        return [
            'device' => $device,
            'browser' => $dd->getClient('name') ?: 'Unknown',
            'os' => $dd->getOs('name') ?: 'Unknown',
            'bot' => false,
            'bot_name' => null,
            'in_app' => $this->inApp($userAgent),
        ];
    }

    private function botName(string $ua): ?string
    {
        foreach (self::PREVIEWERS as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        if (preg_match(self::TOOLS, $ua)) {
            return 'HTTP tool';
        }
        if (preg_match(self::GENERIC_BOT, $ua)) {
            return 'Bot';
        }

        return null;
    }

    private function inApp(string $ua): ?string
    {
        foreach (self::IN_APP as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        // Android WebView ("; wv)") and iPhone browsers without the Safari token are other apps' own views,
        // while Chrome, Firefox and the rest of the iOS browsers all carry it.
        if (preg_match('/;\s*wv\)/', $ua)) {
            return self::IN_APP_OTHER;
        }
        if (preg_match('/\((?:iPhone|iPad|iPod)[^)]*\).*\bMobile\/\w+/', $ua) && ! preg_match('/Safari\/|CriOS|FxiOS|EdgiOS/', $ua)) {
            return self::IN_APP_OTHER;
        }

        return null;
    }
}
