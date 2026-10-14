<?php

namespace App\Services;

use DeviceDetector\DeviceDetector;

/**
 * User-agent parsing via matomo/device-detector (browsers, OS, device types, bots).
 */
class UserAgentService
{
    private array $memo = [];

    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return ['device' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown', 'bot' => false];
        }

        return $this->memo[$userAgent] ??= $this->detect($userAgent);
    }

    private function detect(string $userAgent): array
    {
        $dd = new DeviceDetector($userAgent);
        $dd->parse();
        if ($dd->isBot()) {
            return ['device' => 'Bot', 'browser' => $dd->getBot()['name'] ?? 'Bot', 'os' => 'Unknown', 'bot' => true];
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
        ];
    }
}
