<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Production created links.safety_status long ago, with a migration that is no longer in the tree, as
| enum('unchecked','clean','suspicious','flagged','blocked') NOT NULL DEFAULT 'unchecked'. 2026_11_09_000001 only adds
| the column when it is missing, so production kept the enum, and the guest policy's 'pending', 'safe' and NULL do not
| fit it: creating a guest link failed with "Data truncated for column 'safety_status'".
|
| This turns the column into what the code expects (VARCHAR(20) NULL DEFAULT NULL, the index stays), then moves the
| legacy values to the ones the code reads. No code reads the legacy values any more. Safe to run again.
*/
return new class extends Migration
{
    /**
     * Legacy value => current value. Nothing that was suspicious or blocked becomes safe.
     *
     * unchecked:  the old default on every row; the code reads NULL as "nothing to say".
     * clean:      cleared by the old scan; 'safe' is what abuse:scan writes when it clears a link.
     * suspicious: not cleared; 'pending' puts the warning page in front of it and abuse:scan looks at it again.
     * blocked:    the old scan stopped it; 'flagged' is what abuse:scan and links:sweep-phishing write when they do.
     */
    public const LEGACY = [
        'unchecked' => null,
        'clean' => 'safe',
        'suspicious' => 'pending',
        'blocked' => 'flagged',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('links') || ! Schema::hasColumn('links', 'safety_status')) {
            return;
        }

        // First widen, so the legacy values survive and the new ones fit. SQLite and a fresh install already have a
        // nullable string column (2026_11_09_000001), so only MySQL / MariaDB can need it.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && ! $this->isNullableVarchar()) {
            // MODIFY keeps the column's place and its index (links_safety_status_index).
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'links` MODIFY `safety_status` VARCHAR(20) NULL DEFAULT NULL');
        }

        foreach (self::LEGACY as $legacy => $current) {
            DB::table('links')->where('safety_status', $legacy)->update(['safety_status' => $current]);
        }
    }

    /**
     * Deliberately nothing: going back to the enum would truncate 'pending' and 'safe' to '' (or fail in strict
     * mode), and every release since the guest policy writes them. The varchar column also takes the legacy values.
     */
    public function down(): void {}

    private function isNullableVarchar(): bool
    {
        $column = DB::selectOne(
            'SELECT DATA_TYPE AS type, IS_NULLABLE AS nullable, CHARACTER_MAXIMUM_LENGTH AS length, COLUMN_DEFAULT AS dflt
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getTablePrefix().'links', 'safety_status'],
        );

        return $column !== null
            && strtolower((string) $column->type) === 'varchar'
            && $column->nullable === 'YES'
            && (int) $column->length >= 20
            // MariaDB reports a NULL default as the string 'NULL', MySQL as NULL.
            && ($column->dflt === null || strtoupper((string) $column->dflt) === 'NULL');
    }
};
