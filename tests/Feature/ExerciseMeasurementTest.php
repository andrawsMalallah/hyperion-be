<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * ROADMAP 1.9 — bodyweight and timed exercises.
 *
 * The rules under test: a set's required fields follow its exercise's
 * measurement_type, and only 'weighted' exercises feed the weight-based
 * training math (tonnage volume, estimated 1RM).
 */
class ExerciseMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private function exercise(string $name, string $measurement): Exercise
    {
        return Exercise::create([
            'name' => $name,
            'target_muscle_group' => 'Core',
            'mechanics_type' => 'Compound',
            'measurement_type' => $measurement,
        ]);
    }

    /** A workout payload with one set, ready to POST. */
    private function workoutPayload(array $set): array
    {
        return [
            'date_timestamp' => now()->toISOString(),
            'sets' => [$set + ['set_order' => 0]],
        ];
    }

    public function test_exercises_are_weighted_unless_stated_otherwise()
    {
        $exercise = Exercise::create([
            'name' => 'Barbell Row',
            'target_muscle_group' => 'Back',
            'mechanics_type' => 'Compound',
        ]);

        $this->assertSame('weighted', $exercise->fresh()->measurement_type);
    }

    /**
     * A fresh database is seeded AFTER migrations run, so the migration that
     * retypes an existing catalog can't help here — the seeder carries its own
     * copy of the lists. Without this, CI and every new dev machine would treat
     * a plank as a weighted lift.
     */
    public function test_the_seeded_catalog_carries_measurement_types()
    {
        $this->seed(ExerciseSeeder::class);

        $typeOf = fn (string $name) => Exercise::where('name', $name)->value('measurement_type');

        $this->assertSame('timed', $typeOf('Plank'));
        $this->assertSame('timed', $typeOf("Farmer's Walk"));
        $this->assertSame('bodyweight', $typeOf('Pull-Up'));
        $this->assertSame('bodyweight', $typeOf("Captain's Chair Leg Raise"));
        // Added load is the point of these, and bodyweight is the type that
        // gives them an added-weight input.
        $this->assertSame('bodyweight', $typeOf('Weighted Pull-Up'));
        // The overwhelming majority are untouched.
        $this->assertSame('weighted', $typeOf('Barbell Bench Press'));
        $this->assertSame('weighted', $typeOf('Cable Crunch'));
    }

    public function test_a_timed_set_logs_a_duration_and_no_reps()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $plank = $this->exercise('Plank', 'timed');

        $response = $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $plank->id,
            'duration_seconds' => 90,
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.sets.0.duration_seconds', 90)
            ->assertJsonPath('data.sets.0.reps', null);

        // Weight is NOT NULL in the schema; an omitted one means "no added
        // weight", which is 0 rather than unknown. Cast because the drivers
        // disagree on the shape of a decimal (SQLite 0, Postgres '0.00').
        $this->assertSame(0.0, (float) $response->json('data.sets.0.weight'));
    }

    public function test_a_bodyweight_set_logs_reps_with_optional_added_weight()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $pullUp = $this->exercise('Pull-Up', 'bodyweight');

        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $pullUp->id,
            'reps' => 12,
        ]))->assertStatus(201)->assertJsonPath('data.sets.0.reps', 12);

        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $pullUp->id,
            'reps' => 6,
            'weight' => 20,
        ]))->assertStatus(201)->assertJsonPath('data.sets.0.reps', 6);
    }

    public function test_each_measurement_type_rejects_the_wrong_fields()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $plank = $this->exercise('Plank', 'timed');
        $pullUp = $this->exercise('Pull-Up', 'bodyweight');
        $bench = $this->exercise('Bench Press', 'weighted');

        // Timed: duration required, reps rejected.
        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $plank->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('sets.0.duration_seconds');

        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $plank->id, 'duration_seconds' => 60, 'reps' => 10,
        ]))->assertStatus(422)->assertJsonValidationErrors('sets.0.reps');

        // Bodyweight: reps required, duration rejected.
        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $pullUp->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('sets.0.reps');

        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $pullUp->id, 'reps' => 10, 'duration_seconds' => 60,
        ]))->assertStatus(422)->assertJsonValidationErrors('sets.0.duration_seconds');

        // Weighted: weight is the load, so it stays required.
        $this->postJson('/api/workout-logs', $this->workoutPayload([
            'exercise_id' => $bench->id, 'reps' => 5,
        ]))->assertStatus(422)->assertJsonValidationErrors('sets.0.weight');
    }

    /**
     * The load-bearing rule of 1.9. A +20kg pull-up stores weight = 20, so
     * anything filtering on `weight > 0` alone would score it as a 20kg lift.
     */
    public function test_added_weight_on_a_bodyweight_set_never_reaches_volume_or_e1rm()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $pullUp = $this->exercise('Pull-Up', 'bodyweight');
        $plank = $this->exercise('Plank', 'timed');
        $bench = $this->exercise('Bench Press', 'weighted');

        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $bench->id, 'weight' => 100, 'reps' => 5, 'set_order' => 0]);
        $log->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 20, 'reps' => 6, 'set_order' => 1]);
        $log->sets()->create(['exercise_id' => $plank->id, 'weight' => 10, 'duration_seconds' => 60, 'set_order' => 2]);

        $response = $this->getJson('/api/progress/stats')->assertOk();

        // Only the bench counts: 100 x 5. The pull-up would add 120 and the
        // plank 0 (null reps), so a missing type filter shows up as 620.
        $this->assertSame(500.0, (float) $response->json('data.week.volume'));
        $this->assertSame(500.0, (float) $response->json('data.weekly_volume.0.volume'));

        // The e1RM trend offers weighted exercises only.
        $this->assertSame(
            [$bench->id],
            array_column($response->json('data.exercises'), 'id')
        );
        $this->assertNull($response->json("data.e1rm_by_exercise.{$pullUp->id}"));

        // Same rule on the Active Workout screen's per-exercise summary.
        $recent = $this->getJson("/api/exercises/recent-sets?ids={$bench->id},{$pullUp->id}")->assertOk();
        $this->assertGreaterThan(0, $recent->json("data.{$bench->id}.best_e1rm"));
        $this->assertSame(0.0, (float) $recent->json("data.{$pullUp->id}.best_e1rm"));
    }

    public function test_bodyweight_prs_track_reps_and_timed_prs_track_duration()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $pullUp = $this->exercise('Pull-Up', 'bodyweight');
        $plank = $this->exercise('Plank', 'timed');

        $first = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(7)]);
        $first->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 0, 'reps' => 8, 'set_order' => 0]);
        $first->sets()->create(['exercise_id' => $plank->id, 'weight' => 0, 'duration_seconds' => 60, 'set_order' => 1]);

        $second = $user->workoutLogs()->create(['date_timestamp' => now()->subDay()]);
        $second->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 0, 'reps' => 11, 'set_order' => 0]);
        $second->sets()->create(['exercise_id' => $plank->id, 'weight' => 0, 'duration_seconds' => 95, 'set_order' => 1]);

        $prs = collect($this->getJson('/api/progress/stats')->assertOk()->json('data.recent_prs'))
            ->keyBy('exercise');

        $this->assertSame('reps', $prs['Pull-Up']['kind']);
        $this->assertSame(11, $prs['Pull-Up']['reps']);

        $this->assertSame('duration', $prs['Plank']['kind']);
        $this->assertSame(95, $prs['Plank']['duration_seconds']);
    }

    /**
     * A rep PR is scored per added weight. Sharing one track would mean the
     * first belt-loaded set ends the rep PR forever (you can't beat 12 reps
     * while carrying a plate), and dropping the belt again would "PR" against
     * the loaded number.
     */
    public function test_bodyweight_pr_tracks_are_separated_by_added_weight()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $pullUp = $this->exercise('Pull-Up', 'bodyweight');

        // 12 unloaded, then 6 at +20kg: fewer reps, but a different track, and
        // the first entry on a track never counts as a PR.
        $first = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(7)]);
        $first->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 0, 'reps' => 12, 'set_order' => 0]);

        $second = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(5)]);
        $second->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 20, 'reps' => 6, 'set_order' => 0]);

        $this->assertSame([], $this->getJson('/api/progress/stats')->json('data.recent_prs'));

        // 7 at +20kg beats the 6 on its own track — even though it's nowhere
        // near the 12 unloaded reps.
        $third = $user->workoutLogs()->create(['date_timestamp' => now()->subDay()]);
        $third->sets()->create(['exercise_id' => $pullUp->id, 'weight' => 20, 'reps' => 7, 'set_order' => 0]);

        $prs = $this->getJson('/api/progress/stats')->json('data.recent_prs');

        $this->assertCount(1, $prs);
        $this->assertSame(7, $prs[0]['reps']);
        $this->assertSame(20.0, (float) $prs[0]['weight']);
    }
}
