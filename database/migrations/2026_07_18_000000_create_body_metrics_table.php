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
        Schema::create('body_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Canonical kilograms — the user's weight_unit setting only affects
            // display/input, exactly like set_logs.weight.
            $table->decimal('weight', 6, 2);
            $table->date('measured_on');
            $table->timestamps();

            // One entry per day: logging again for the same date upserts. Also
            // the composite the chart/list query orders on.
            $table->unique(['user_id', 'measured_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_metrics');
    }
};
