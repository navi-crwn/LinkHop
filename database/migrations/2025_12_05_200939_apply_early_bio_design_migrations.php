<?php

use Illuminate\Database\Migrations\Migration;

/**
 * The 2024_12_05 bio migrations sort before the bio tables are created, so a
 * fresh install skips them, and later migrations that refer to their columns
 * (for example ->after('background_gradient')) fail on MySQL/MariaDB. Apply them
 * just before the first migration that needs them. Both are idempotent
 * (hasColumn guards), so existing databases are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/2024_12_05_000001_add_design_fields_to_bio_pages_table.php')->up();
        (require __DIR__.'/2024_12_05_000002_add_brand_field_to_bio_links_table.php')->up();
    }

    public function down(): void
    {
        // Columns are owned by the original migrations.
    }
};
