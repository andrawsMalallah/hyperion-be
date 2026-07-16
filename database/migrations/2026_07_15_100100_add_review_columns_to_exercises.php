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
        // Audit trail for the admin approval flow. `status` and `created_by`
        // already exist (add_training_feature_columns); this adds who/when a
        // contribution was reviewed and, on rejection, the reason the
        // contributor is told. All nullable — existing rows stay untouched.
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['rejection_reason', 'reviewed_at']);
        });
    }
};
