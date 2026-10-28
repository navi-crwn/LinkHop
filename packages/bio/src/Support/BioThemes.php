<?php

namespace Hop\Bio\Support;

class BioThemes
{
    /**
     * Theme cards for the editor, with the same colours the preview and public page use.
     */
    public static function picker(): array
    {
        $all = config('bio-themes.starters') + config('bio-themes.presets');

        return collect(config('bio-themes.picker'))
            ->filter(fn ($id) => isset($all[$id]))
            ->mapWithKeys(fn ($id) => [$id => $all[$id]])
            ->all();
    }
}
