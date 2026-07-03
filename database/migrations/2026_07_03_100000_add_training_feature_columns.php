<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Prescriptions: what a program day tells the athlete to do.
        Schema::table('day_exercise', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_sets')->nullable()->after('display_order');
            $table->unsignedSmallInteger('rep_range_min')->nullable()->after('target_sets');
            $table->unsignedSmallInteger('rep_range_max')->nullable()->after('rep_range_min');
            $table->unsignedTinyInteger('target_rpe')->nullable()->after('rep_range_max');
            $table->unsignedSmallInteger('rest_seconds')->nullable()->after('target_rpe');
            $table->string('notes', 500)->nullable()->after('rest_seconds');
        });

        Schema::table('set_logs', function (Blueprint $table) {
            $table->string('set_type', 20)->default('working')->after('rpe');
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('date_timestamp');
            $table->text('notes')->nullable()->after('ended_at');
        });

        // Catalog moderation: seeded exercises stay approved; user
        // contributions start pending and are only visible to their author.
        Schema::table('exercises', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('mechanics_type')
                ->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('approved')->after('created_by');
        });

        // Normalize stray muscle-group labels that the old Contribute form
        // offered but the seeded taxonomy never used.
        $remap = [
            'Quadriceps' => 'Legs',
            'Hamstrings' => 'Legs',
            'Glutes' => 'Legs',
            'Calves' => 'Legs',
            'Abdominals' => 'Core',
            'Traps' => 'Back',
            'Lats' => 'Back',
        ];
        foreach ($remap as $from => $to) {
            DB::table('exercises')->where('target_muscle_group', $from)->update(['target_muscle_group' => $to]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('status');
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'notes']);
        });

        Schema::table('set_logs', function (Blueprint $table) {
            $table->dropColumn('set_type');
        });

        Schema::table('day_exercise', function (Blueprint $table) {
            $table->dropColumn(['target_sets', 'rep_range_min', 'rep_range_max', 'target_rpe', 'rest_seconds', 'notes']);
        });
    }
};
