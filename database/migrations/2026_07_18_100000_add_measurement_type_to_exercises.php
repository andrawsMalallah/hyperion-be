<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exercises seeded before ROADMAP 1.9 that aren't loaded with a barbell.
     *
     * Everything defaults to 'weighted', so this only has to name the
     * exceptions. Matching is by exact seeded name: a user-contributed
     * exercise with a similar name is left alone rather than guessed at.
     */
    private const TIMED = [
        'Plank',
        'Side Plank',
        'Dead Hang',
        // Carries and holds are scored by how long you last, not by reps.
        "Farmer's Walk",
        'Plate Pinch',
    ];

    /**
     * Reps-based movements where any load is *added* to body weight. Includes
     * the two 'Weighted …' rows: added weight is the whole point of those, and
     * this is the type that gives them an added-weight input.
     */
    private const BODYWEIGHT = [
        'Ab Wheel Rollout',
        'Bench Dips',
        'Bicycle Crunch',
        'Burpee',
        "Captain's Chair Leg Raise",
        'Chest Dips',
        'Chin-Up',
        'Crunch',
        'Decline Crunch',
        'Diamond Push-Up',
        'Flutter Kicks',
        'Glute-Ham Raise',
        'Hanging Knee Raise',
        'Hanging Leg Raise',
        'Hyperextension (Back Extension)',
        'Lying Leg Raise',
        'Pull-Up',
        'Push-Up',
        'Russian Twist',
        'Step-Ups',
        'Triceps Dips',
        'V-Ups',
        'Weighted Pull-Up',
        'Weighted Push-Up',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // How a set of this exercise is measured. 'weighted' keeps every
            // existing row (and every future contribution that doesn't say
            // otherwise) behaving exactly as it did before 1.9.
            $table->string('measurement_type', 20)->default('weighted')->after('mechanics_type');
        });

        DB::table('exercises')->whereIn('name', self::TIMED)->update(['measurement_type' => 'timed']);
        DB::table('exercises')->whereIn('name', self::BODYWEIGHT)->update(['measurement_type' => 'bodyweight']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('measurement_type');
        });
    }
};
