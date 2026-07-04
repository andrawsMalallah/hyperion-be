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
}
