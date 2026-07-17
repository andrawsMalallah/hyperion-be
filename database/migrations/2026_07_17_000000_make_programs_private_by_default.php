<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New programs default to PRIVATE (Session 26, ROADMAP 7.4). The frontend
 * always sends is_public explicitly, so this default only decides what a
 * payload that omits the flag gets — and "omitted" must never mean
 * "published to Discover". Existing rows keep whatever flag they carry;
 * a column default applies to inserts only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->change();
        });
    }
};
