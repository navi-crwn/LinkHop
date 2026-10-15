<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// btn_icon_invert was added with default(true), so every block that existed then got a forced
// white icon, invisible on light buttons. The editor only offers "White icon" next to custom
// button colours, so a block with no custom colour never chose it: put those back on auto.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bio_links', function (Blueprint $table) {
            $table->boolean('btn_icon_invert')->default(false)->change();
        });

        $query = DB::table('bio_links')->where('btn_icon_invert', true);
        foreach (['btn_bg_color', 'btn_text_color', 'btn_border_color'] as $column) {
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));
        }
        $query->update(['btn_icon_invert' => false]);
    }

    public function down(): void
    {
        Schema::table('bio_links', function (Blueprint $table) {
            $table->boolean('btn_icon_invert')->default(true)->change();
        });
    }
};
