<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProgressStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_stats_requires_auth()
    {
        $this->getJson('/api/progress/stats')->assertUnauthorized();
    }

    public function test_progress_stats_returns_empty_shape_with_no_history()
    {
        Passport::actingAs(User::factory()->create());

        $response = $this->getJson('/api/progress/stats');

        $response->assertOk()
            ->assertJsonPath('data.week.sessions', 0)
            ->assertJsonPath('data.week.volume', 0)
            ->assertJsonPath('data.exercises', [])
            ->assertJsonPath('data.weekly_volume', [])
            ->assertJsonPath('data.recent_prs', []);
    }

    public function test_progress_stats_builds_e1rm_series_and_recent_prs()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);

        // Two sessions, the newer one heavier → an all-time best = a PR.
        $first = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(7)]);
        $first->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);
        // A warmup must be ignored by the e1RM math.
        $first->sets()->create(['exercise_id' => $ex->id, 'weight' => 200, 'reps' => 1, 'set_type' => 'warmup', 'set_order' => 0]);

        $second = $user->workoutLogs()->create(['date_timestamp' => now()->subDay()]);
        $second->sets()->create(['exercise_id' => $ex->id, 'weight' => 110, 'reps' => 5, 'set_order' => 1]);

        $response = $this->getJson('/api/progress/stats');

        $response->assertOk();
        // Series: two points, oldest first, warmup excluded (200 would dominate).
        $series = $response->json("data.e1rm_by_exercise.{$ex->id}");
        $this->assertCount(2, $series);
        $this->assertEqualsWithDelta(116.67, $series[0]['e1rm'], 0.5); // 100 x5
        $this->assertEqualsWithDelta(128.33, $series[1]['e1rm'], 0.5); // 110 x5
        // The second session is a PR over the first.
        $this->assertCount(1, $response->json('data.recent_prs'));
        $this->assertSame($ex->id, $response->json('data.recent_prs.0.exercise_id'));
        $this->assertSame('Bench', $response->json('data.recent_prs.0.exercise'));
        // Dropdown option present with its set count (3 = 2 in the first session
        // incl. warmup + 1 in the second).
        $this->assertSame($ex->id, $response->json('data.exercises.0.id'));
        $this->assertSame(3, $response->json('data.exercises.0.count'));
    }

    public function test_progress_stats_is_scoped_to_the_user()
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $ex = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $log = $other->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 150, 'reps' => 3, 'set_order' => 1]);

        Passport::actingAs($me);
        $response = $this->getJson('/api/progress/stats');

        $response->assertOk()
            ->assertJsonPath('data.exercises', [])
            ->assertJsonPath('data.week.sessions', 0);
    }

    public function test_progress_stats_weekly_volume_sums_working_sets()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Deadlift', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound']);

        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]); // 500
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 2]); // 500
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 60, 'reps' => 10, 'set_type' => 'warmup', 'set_order' => 0]); // ignored

        $response = $this->getJson('/api/progress/stats');

        $response->assertOk()
            ->assertJsonPath('data.week.sessions', 1)
            ->assertJsonPath('data.week.volume', 1000);
        $weekly = $response->json('data.weekly_volume');
        $this->assertCount(1, $weekly);
        $this->assertSame(1000, (int) $weekly[0]['volume']);
    }

    public function test_progress_stats_ships_only_the_first_exercises_series()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $a = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);
        $b = Exercise::create(['name' => 'Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound']);

        // A is logged more (2 sets) → it's the most-logged, so it's "first".
        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $a->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);
        $log->sets()->create(['exercise_id' => $a->id, 'weight' => 100, 'reps' => 5, 'set_order' => 2]);
        $log->sets()->create(['exercise_id' => $b->id, 'weight' => 50, 'reps' => 8, 'set_order' => 3]);

        $response = $this->getJson('/api/progress/stats');

        $response->assertOk();
        // Dropdown lists both exercises...
        $this->assertCount(2, $response->json('data.exercises'));
        // ...but only the first (A) has its series shipped; B loads on demand.
        $this->assertArrayHasKey((string) $a->id, $response->json('data.e1rm_by_exercise'));
        $this->assertArrayNotHasKey((string) $b->id, $response->json('data.e1rm_by_exercise'));
    }

    public function test_exercise_series_endpoint_returns_one_exercises_series()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);

        $first = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(3)]);
        $first->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);
        $first->sets()->create(['exercise_id' => $ex->id, 'weight' => 200, 'reps' => 1, 'set_type' => 'warmup', 'set_order' => 0]);
        $second = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $second->sets()->create(['exercise_id' => $ex->id, 'weight' => 110, 'reps' => 5, 'set_order' => 1]);

        $response = $this->getJson("/api/progress/exercises/{$ex->id}/e1rm");

        $response->assertOk();
        $series = $response->json('data');
        $this->assertCount(2, $series); // oldest first, warmup excluded
        $this->assertEqualsWithDelta(116.67, $series[0]['e1rm'], 0.5);
        $this->assertEqualsWithDelta(128.33, $series[1]['e1rm'], 0.5);
    }

    public function test_exercise_series_requires_auth()
    {
        $ex = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);
        $this->getJson("/api/progress/exercises/{$ex->id}/e1rm")->assertUnauthorized();
    }

    public function test_exercise_series_is_scoped_to_the_user()
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $ex = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $log = $other->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 150, 'reps' => 3, 'set_order' => 1]);

        Passport::actingAs($me);
        $this->getJson("/api/progress/exercises/{$ex->id}/e1rm")
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
