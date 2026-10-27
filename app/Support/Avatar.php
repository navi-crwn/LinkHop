<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile photos: re-encoded as a 400x400 JPEG (so nothing but pixels is
 * kept, no EXIF, no odd formats) on the public disk at avatars/{id}-{rand}.jpg.
 */
class Avatar
{
    public const SIZE = 400;

    public static function store(User $user, UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($src === false) {
            throw new \RuntimeException('That file is not an image we can read.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        $path = 'avatars/'.$user->id.'-'.Str::lower(Str::random(10)).'.jpg';
        Storage::disk('public')->put($path, $bytes, 'public');
        self::delete($user);

        return $path;
    }

    public static function delete(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }
}
