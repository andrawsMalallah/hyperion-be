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
}
