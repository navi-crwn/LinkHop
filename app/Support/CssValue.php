<?php

namespace App\Support;

/**
 * Validation patterns for design fields that end up inside style attributes and
 * <style> blocks. Only plain colours, gradients made of colours and stops, and
 * uploaded or https images are accepted, so a value cannot close the declaration
 * or pull in anything else (BIO-07).
 */
class CssValue
{
    public const COLOR = '/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,%\s]+\)|transparent)$/';

    public const GRADIENT = '/^(repeating-)?(linear|radial)-gradient\((?:[#0-9a-zA-Z.,%\s-]|(?:rgb|hsl)a?\([0-9.,%\s]+\))+\)$/';

    /** An uploaded file (path or /storage URL) or an https image URL without quotes, spaces or parentheses. */
    public const IMAGE = '#^(/?storage/)?bio-images/[A-Za-z0-9._-]+$|^https://[^\s\'"()\\\\<>]+$#';

    /** Keywords such as theme, font and animation names. */
    public const KEYWORD = 'regex:/^[a-z0-9-]*$/i';

    public static function color(int $max = 20): array
    {
        return ['nullable', 'string', "max:{$max}", 'regex:'.self::COLOR];
    }

    public static function keyword(int $max): array
    {
        return ['nullable', 'string', "max:{$max}", self::KEYWORD];
    }

    /** background_value holds a colour, a gradient or an image depending on background_type. */
    public static function isBackground(?string $value): bool
    {
        return $value === null || $value === ''
            || preg_match(self::COLOR, $value) || preg_match(self::GRADIENT, $value) || preg_match(self::IMAGE, $value);
    }
}
