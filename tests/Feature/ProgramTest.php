<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_program()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', [
            'name' => 'Push Pull Legs',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('programs', ['name' => 'Push Pull Legs']);
    }

    public function test_user_can_get_programs()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $user->programs()->create(['name' => 'Bro Program', 'is_active' => false]);

        $response = $this->getJson('/api/programs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_discover_programs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Passport::actingAs($user1);

        $user1->programs()->create(['name' => 'Upper Body Program', 'is_active' => true]);
        $user2->programs()->create(['name' => 'Lower Body Program', 'is_active' => false]);

        $response = $this->getJson('/api/programs/discover');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'is_active', 'created_at', 'days',
                        'user' => ['id', 'name'],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonMissingPath('data.0.user.email');
    }

    public function test_user_can_search_discovered_programs()
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        Passport::actingAs($user1);

        $user1->programs()->create(['name' => 'Push Pull Legs', 'is_active' => true]);
        $user2->programs()->create(['name' => 'Arnold Program', 'is_active' => false]);

        // Search for "Push"
        $response = $this->getJson('/api/programs/discover?search=Push');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Push Pull Legs');

        // Search for "Arnold"
        $response = $this->getJson('/api/programs/discover?search=Arnold');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Arnold Program');

        // Search by user name "Bob"
        $response = $this->getJson('/api/programs/discover?search=Bob');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Arnold Program');

        // Search for something non-existent
        $response = $this->getJson('/api/programs/discover?search=Cardio');
        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_private_programs_are_hidden_from_discover()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Passport::actingAs($user1);

        $user2->programs()->create(['name' => 'Public Program', 'is_active' => false, 'is_public' => true]);
        $user2->programs()->create(['name' => 'Secret Program', 'is_active' => false, 'is_public' => false]);

        $response = $this->getJson('/api/programs/discover');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Public Program');
    }

    public function test_program_stores_and_returns_exercise_prescriptions()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $exercise = Exercise::create([
            'name' => 'Bench Press',
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound',
        ]);

        $response = $this->postJson('/api/programs', [
            'name' => 'PPL',
            'days' => [
                [
                    'day_name' => 'Push',
                    'display_order' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => $exercise->id,
                            'target_sets' => 3,
                            'rep_range_min' => 8,
                            'rep_range_max' => 12,
                            'target_rpe' => 8,
                            'rest_seconds' => 120,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.pivot.target_sets', 3)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rep_range_min', 8)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rep_range_max', 12)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rest_seconds', 120);
    }

    public function test_program_rejects_inverted_rep_range()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $exercise = Exercise::create([
            'name' => 'Bench Press',
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound',
        ]);

        $response = $this->postJson('/api/programs', [
            'name' => 'PPL',
            'days' => [
                [
                    'day_name' => 'Push',
                    'exercises' => [
                        ['exercise_id' => $exercise->id, 'rep_range_min' => 12, 'rep_range_max' => 8],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['days.0.exercises.0.rep_range_max']);
    }

    public function test_program_days_expose_last_performed_at()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $program = $user->programs()->create(['name' => 'Upper / Lower', 'is_active' => true]);
        $trained = $program->days()->create(['day_name' => 'Upper 1', 'display_order' => 1]);
        $program->days()->create(['day_name' => 'Lower 1', 'display_order' => 2]);

        $user->workoutLogs()->create([
            'program_day_id' => $trained->id,
            'date_timestamp' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/programs');

        $response->assertStatus(200);
        // Days come back ordered by display_order: [0] trained, [1] untrained.
        $this->assertNotNull($response->json('data.0.days.0.last_performed_at'));
        $this->assertNull($response->json('data.0.days.1.last_performed_at'));
    }

    public function test_activating_a_program_deactivates_other_programs()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $Program1 = $user->programs()->create(['name' => 'Bro Program', 'is_active' => true]);
        $Program2 = $user->programs()->create(['name' => 'Arnold Program', 'is_active' => false]);

        $response = $this->putJson("/api/programs/{$Program2->id}", [
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $Program1->fresh()->is_active);
        $this->assertEquals(1, $Program2->fresh()->is_active);
    }
}
