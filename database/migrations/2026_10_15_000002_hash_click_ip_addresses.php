<?php

use App\Support\VisitorHash;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The privacy policy promises hashed IPs, but click logs stored them raw.
 * Replace every stored raw address with its keyed hash. Not reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['link_clicks', 'bio_clicks'] as $table) {
            DB::table($table)
                ->whereNotNull('ip_address')
                ->select('id', 'ip_address')
                ->chunkById(1000, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $hash = VisitorHash::make($row->ip_address);
                        if ($hash !== $row->ip_address) {
                            DB::table($table)->where('id', $row->id)->update(['ip_address' => $hash]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Hashes cannot be turned back into addresses.
    }
};
