<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Slugs of expired guest links stay blocked for a while, so nobody can take over
// a link that is still shared somewhere and send its old visitors elsewhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_slugs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->timestamp('retired_at');
            $table->timestamp('reusable_after')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_slugs');
    }
};
