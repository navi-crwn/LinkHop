<?php

return [
    // Off: the package registers no routes, views, migrations or policy; the host app hides its bio menus
    // and never queries the bio tables, so they need not exist.
    'enabled' => (bool) env('BIO_ENABLED', true),

    // Bio slugs (hel.ink/b/{slug}) for members; admin accounts may go down to 3.
    'slug_min_length' => (int) env('BIO_SLUG_MIN_LENGTH', 5),

    // Pages per account.
    'max_pages' => (int) env('BIO_MAX_PAGES', 3),
    'upload_min_free_mb' => (int) env('BIO_UPLOAD_MIN_FREE_MB', 1024),
    'upload_min_free_percent' => (int) env('BIO_UPLOAD_MIN_FREE_PERCENT', 5),
];
