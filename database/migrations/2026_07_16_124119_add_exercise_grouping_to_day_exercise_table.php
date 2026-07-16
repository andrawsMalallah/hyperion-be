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
        Schema::table('day_exercise', function (Blueprint $table) {
            // How this exercise is performed. One exclusive type per exercise:
            // drop_set / pyramid_set are tags on a single exercise, while
            // superset / giant_set join several exercises into one group.
            $table->string('group_type', 20)->nullable()->after('notes');

            // Ties the members of a superset / giant_set together: exercises in
            // the same day sharing a key are one group. Null for the tag-only
            // types and for ungrouped exercises.
            $table->unsignedTinyInteger('group_key')->nullable()->after('group_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_exercise', function (Blueprint $table) {
            $table->dropColumn(['group_type', 'group_key']);
        });
    }
};
