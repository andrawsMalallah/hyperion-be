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
        Schema::table('set_logs', function (Blueprint $table) {
            // How long the set was held, for exercises measured in time
            // (planks, hangs, carries). Null for every rep-based set.
            $table->unsignedInteger('duration_seconds')->nullable()->after('reps');

            // A timed set has no reps.
            $table->integer('reps')->nullable()->change();

            // `weight` deliberately stays NOT NULL: for a bodyweight movement 0
            // means "no added weight", which is true rather than unknown — and
            // it keeps the existing `weight > 0` filters in ProgressStats
            // correct without a rewrite. The default is what lets a bodyweight
            // or timed payload omit the key entirely (a model mutator can't
            // help there — it only runs for attributes that are actually set).
            $table->decimal('weight', 8, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('set_logs', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
            $table->integer('reps')->nullable(false)->change();
            $table->decimal('weight', 8, 2)->default(null)->change();
        });
    }
};
