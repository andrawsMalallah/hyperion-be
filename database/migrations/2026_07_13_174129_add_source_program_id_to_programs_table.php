<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // The public program a cloned copy was saved from (null for
            // originals). A soft reference — intentionally no FK constraint so
            // a deleted source simply leaves a harmless dangling id (that source
            // no longer appears in Discover, so nothing shows it as "saved").
            $table->unsignedBigInteger('source_program_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('source_program_id');
        });
    }
};
