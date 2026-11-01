<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Webhook endpoints an account registers, and the log of what was sent to them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2000);
            // Encrypted at rest (it has to be readable to sign); the owner sees it once, when it is made.
            $table->text('secret');
            // null = every event; otherwise a list of event names.
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failure_streak')->default(0);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
        });
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('event', 40);
            $table->text('payload');
            $table->string('status', 10)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->string('error', 190)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
            $table->index(['webhook_endpoint_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
