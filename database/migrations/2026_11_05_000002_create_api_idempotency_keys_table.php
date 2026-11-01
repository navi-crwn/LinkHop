<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Idempotency-Key on the token API: the answer to a create request, kept for 24 hours so a retry gets the same answer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            // sha256 of method, path and the sorted body: the same key with other content is refused.
            $table->char('fingerprint', 64);
            // null while the first request is still running.
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
