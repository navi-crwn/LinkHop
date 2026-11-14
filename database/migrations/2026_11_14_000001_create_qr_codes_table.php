<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Saved stand-alone QR codes (Wi-Fi, contact card, link or text) made on the dashboard's "QR codes" page. The id is a
| random ULID, so an address cannot be guessed by counting. `payload` holds what the code carries (a Wi-Fi password,
| a phone number) and is stored encrypted (the model's `encrypted` cast); so is the label, which is often taken from
| the content (the network name, a contact name). user_id points at the hub's shared users
| table, so it carries no foreign key; the account purge deletes these rows by user_id. Safe to run again.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qr_codes')) {
            return;
        }
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 16);
            $table->text('label'); // encrypted, like payload: the label is often taken from the content
            $table->text('payload');
            $table->json('style');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
