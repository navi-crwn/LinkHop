<?php

namespace Hop\Bio\Support;

use Illuminate\Support\Facades\Storage;

class UploadStorage
{
    public function available(int $incoming = 0): bool
    {
        $paths = [storage_path(), sys_get_temp_dir()];
        if (config('filesystems.disks.public.driver') === 'local') {
            $paths[] = Storage::disk('public')->path('');
        }
        foreach ($paths as $path) {
            // A new upload directory may not exist yet; measure its filesystem.
            while (! is_dir($path) && dirname($path) !== $path) {
                $path = dirname($path);
            }
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $mb = (int) config('bio.upload_min_free_mb', 1024);
            $percent = (int) config('bio.upload_min_free_percent', 5);
            $reserve = max(($mb > 0 ? $mb : 1024) * 1048576,
                $total * ($percent > 0 ? $percent : 5) / 100);
            if ($free === false || $total === false || $total <= 0 || $free <= $reserve || $incoming > ($free - $reserve) / 3) {
                return false;
            }
        }

        return true;
    }

    public function status(): array
    {
        $available = $this->available();

        return ['available' => $available, 'message' => $available ? '' : __('Uploads are temporarily paused because server storage is low or unavailable. Please try again later.')];
    }
}
