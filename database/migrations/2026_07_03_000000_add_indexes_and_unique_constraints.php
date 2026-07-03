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
        // Merge duplicate exercise names before adding the unique index
        // (uniqueness was previously enforced only at the validation layer).
        $duplicates = DB::table('exercises')
            ->select('name', DB::raw('MIN(id) as keep_id'))
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $obsoleteIds = DB::table('exercises')
                ->where('name', $duplicate->name)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id');

            DB::table('set_logs')->whereIn('exercise_id', $obsoleteIds)->update(['exercise_id' => $duplicate->keep_id]);
            DB::table('day_exercise')->whereIn('exercise_id', $obsoleteIds)->update(['exercise_id' => $duplicate->keep_id]);
            DB::table('exercises')->whereIn('id', $obsoleteIds)->delete();
        }

        // Drop duplicate (program_day_id, exercise_id) pivot rows before the
        // unique constraint (the merge above may also have created some).
        DB::table('day_exercise')
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('MIN(id)')
                    ->from('day_exercise')
                    ->groupBy('program_day_id', 'exercise_id');
            })
            ->delete();

        Schema::table('exercises', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('day_exercise', function (Blueprint $table) {
            $table->unique(['program_day_id', 'exercise_id']);
            $table->index('exercise_id');
        });

        // Postgres does not index FK child columns automatically.
        Schema::table('programs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('program_days', function (Blueprint $table) {
            $table->index('program_id');
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('program_day_id');
            $table->index('date_timestamp');
        });

        Schema::table('set_logs', function (Blueprint $table) {
            $table->index('workout_log_id');
            $table->index('exercise_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('set_logs', function (Blueprint $table) {
            $table->dropIndex(['workout_log_id']);
            $table->dropIndex(['exercise_id']);
        });

        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['program_day_id']);
            $table->dropIndex(['date_timestamp']);
        });

        Schema::table('program_days', function (Blueprint $table) {
            $table->dropIndex(['program_id']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('day_exercise', function (Blueprint $table) {
            $table->dropUnique(['program_day_id', 'exercise_id']);
            $table->dropIndex(['exercise_id']);
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
