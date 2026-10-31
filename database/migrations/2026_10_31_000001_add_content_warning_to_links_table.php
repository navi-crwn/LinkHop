<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('links')) {
            return;
        }

        Schema::table('links', function (Blueprint $table) {
            if (! Schema::hasColumn('links', 'content_warning')) {
                $table->string('content_warning', 32)->default('none')->after('status');
            }
            if (! Schema::hasColumn('links', 'warning_reason')) {
                $table->string('warning_reason', 255)->nullable()->after('content_warning');
            }
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            if (Schema::hasColumn('links', 'warning_reason')) {
                $table->dropColumn('warning_reason');
            }
            if (Schema::hasColumn('links', 'content_warning')) {
                $table->dropColumn('content_warning');
            }
        });
    }
};

