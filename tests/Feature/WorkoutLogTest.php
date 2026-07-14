<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WorkoutLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_workout_log()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $exercise = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $response = $this->postJson('/api/workout-logs', [
            'date_timestamp' => now()->toISOString(),
            'sets' => [
                [
                    'exercise_id' => $exercise->id,
                    'weight' => 100,
                    'reps' => 10,
                    'rpe' => 8,
                    'set_order' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('workout_logs', ['user_id' => $user->id]);
        $this->assertDatabaseHas('set_logs', ['weight' => 100, 'reps' => 10]);
    }

    public function test_workout_log_accepts_bodyweight_warmup_sets_and_session_metadata()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $exercise = Exercise::create(['name' => 'Plank', 'target_muscle_group' => 'Core', 'mechanics_type' => 'Isolation']);

        $start = now()->subMinutes(45);
        $response = $this->postJson('/api/workout-logs', [
            'date_timestamp' => $start->toISOString(),
            'ended_at' => now()->toISOString(),
            'notes' => 'Felt strong today.',
            'sets' => [
                [
                    'exercise_id' => $exercise->id,
                    'weight' => 0,
                    'reps' => 1,
                    'set_type' => 'warmup',
                    'set_order' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.notes', 'Felt strong today.')
            ->assertJsonPath('data.sets.0.set_type', 'warmup');
        $this->assertDatabaseHas('set_logs', ['weight' => 0, 'set_type' => 'warmup']);
        $this->assertDatabaseHas('workout_logs', ['notes' => 'Felt strong today.']);
    }

    public function test_resending_the_same_client_uuid_does_not_duplicate_the_workout()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $exercise = Exercise::create(['name' => 'Deadlift', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound']);

        $payload = [
            'client_uuid' => '11111111-2222-4333-8444-555555555555',
            'date_timestamp' => now()->toISOString(),
            'sets' => [
                [
                    'exercise_id' => $exercise->id,
                    'weight' => 140,
                    'reps' => 5,
                    'set_order' => 1,
                ],
            ],
        ];

        $first = $this->postJson('/api/workout-logs', $payload);
        $first->assertStatus(201);

        // A retry of the queued upload (lost response) must be idempotent.
        $second = $this->postJson('/api/workout-logs', $payload);
        $second->assertSuccessful()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('workout_logs', 1);
        $this->assertDatabaseCount('set_logs', 1);
    }

    public function test_workout_log_rejects_out_of_range_set_values()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $exercise = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $response = $this->postJson('/api/workout-logs', [
            'date_timestamp' => now()->toISOString(),
            'sets' => [
                [
                    'exercise_id' => $exercise->id,
                    'weight' => 5000,
                    'reps' => 500,
                    'rpe' => 15,
                    'set_order' => -1,
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'sets.0.weight',
            'sets.0.reps',
            'sets.0.rpe',
            'sets.0.set_order',
        ]);
    }

    public function test_recent_sets_returns_last_session_and_best_e1rm()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);

        // Older but heavier session (holds the all-time best).
        $old = $user->workoutLogs()->create(['date_timestamp' => now()->subDays(7)]);
        $old->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);

        // Most recent session (lighter).
        $new = $user->workoutLogs()->create(['date_timestamp' => now()->subDay()]);
        $new->sets()->create(['exercise_id' => $ex->id, 'weight' => 90, 'reps' => 8, 'set_order' => 1]);

        $response = $this->getJson('/api/exercises/recent-sets?ids='.$ex->id);

        $response->assertStatus(200);
        $this->assertEquals(90, $response->json("data.{$ex->id}.last.0.weight"));
        $this->assertEquals(8, $response->json("data.{$ex->id}.last.0.reps"));
        // Best e1rm comes from 100x5: 100 * (1 + 5/30) ≈ 116.67.
        $this->assertEqualsWithDelta(116.67, $response->json("data.{$ex->id}.best_e1rm"), 0.5);
    }

    public function test_owner_can_edit_a_logged_workout_replacing_sets_and_notes()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $log = $user->workoutLogs()->create(['date_timestamp' => now(), 'notes' => 'original']);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 100, 'reps' => 5, 'set_order' => 2]);

        $response = $this->putJson("/api/workout-logs/{$log->id}", [
            'notes' => 'fixed a typo',
            'sets' => [
                ['exercise_id' => $ex->id, 'weight' => 110, 'reps' => 5, 'set_order' => 1],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'fixed a typo')
            ->assertJsonCount(1, 'data.sets')
            ->assertJsonPath('data.sets.0.weight', 110);

        // Old set rows are replaced, not appended.
        $this->assertDatabaseCount('set_logs', 1);
        $this->assertDatabaseHas('set_logs', ['workout_log_id' => $log->id, 'weight' => 110]);
    }

    public function test_notes_only_edit_keeps_the_existing_sets()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Bench', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound']);

        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 80, 'reps' => 8, 'set_order' => 1]);

        $response = $this->putJson("/api/workout-logs/{$log->id}", [
            'notes' => 'great session',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notes', 'great session')
            ->assertJsonCount(1, 'data.sets');
        $this->assertDatabaseHas('set_logs', ['workout_log_id' => $log->id, 'weight' => 80]);
    }

    public function test_user_cannot_edit_someone_elses_workout()
    {
        $owner = User::factory()->create();
        $log = $owner->workoutLogs()->create(['date_timestamp' => now(), 'notes' => 'private']);

        $intruder = User::factory()->create();
        Passport::actingAs($intruder);

        $this->putJson("/api/workout-logs/{$log->id}", ['notes' => 'hacked'])
            ->assertStatus(403);
        $this->assertDatabaseHas('workout_logs', ['id' => $log->id, 'notes' => 'private']);
    }

    public function test_editing_a_workout_rejects_out_of_range_set_values()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Row', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound']);
        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 60, 'reps' => 10, 'set_order' => 1]);

        $this->putJson("/api/workout-logs/{$log->id}", [
            'sets' => [
                ['exercise_id' => $ex->id, 'weight' => 5000, 'reps' => 500, 'rpe' => 15, 'set_order' => -1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'sets.0.weight',
            'sets.0.reps',
            'sets.0.rpe',
            'sets.0.set_order',
        ]);
    }

    public function test_exercise_logs_are_paginated_and_scoped_to_the_exercise()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $ex = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);
        $other = Exercise::create(['name' => 'Curl', 'target_muscle_group' => 'Arms', 'mechanics_type' => 'Isolation']);

        for ($i = 0; $i < 7; $i++) {
            $log = $user->workoutLogs()->create(['date_timestamp' => now()->subDays($i)]);
            $log->sets()->create(['exercise_id' => $ex->id, 'weight' => 100 + $i, 'reps' => 5, 'set_order' => 1]);
            // Another exercise in the same session must not leak into the results.
            $log->sets()->create(['exercise_id' => $other->id, 'weight' => 20, 'reps' => 10, 'set_order' => 2]);
        }

        $page1 = $this->getJson("/api/exercises/{$ex->id}/logs?page=1");
        $page1->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.last_page', 2);

        foreach ($page1->json('data') as $log) {
            $this->assertCount(1, $log['sets']);
            $this->assertEquals($ex->id, $log['sets'][0]['exercise_id']);
        }

        $this->getJson("/api/exercises/{$ex->id}/logs?page=2")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
