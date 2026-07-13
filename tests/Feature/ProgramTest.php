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

    public function test_program_validation_errors_are_human_readable()
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
                        ['exercise_id' => $exercise->id, 'target_rpe' => 11],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422);
        // The error key is a literal dotted string, so fetch the bag and index it.
        $errors = $response->json('errors');
        $message = $errors['days.0.exercises.0.target_rpe'][0];
        $this->assertStringContainsStringIgnoringCase('RPE', $message);
        // The raw field path must not leak into the user-facing message.
        $this->assertStringNotContainsString('days.0.exercises.0.target_rpe', $message);
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

    public function test_user_can_clone_a_public_program()
    {
        $owner = User::factory()->create();
        $cloner = User::factory()->create();

        $exercise = Exercise::create([
            'name' => 'Bench Press',
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound',
        ]);

        $program = $owner->programs()->create(['name' => 'Public PPL', 'is_active' => true, 'is_public' => true]);
        $day = $program->days()->create(['day_name' => 'Push', 'display_order' => 1]);
        $day->exercises()->attach($exercise->id, [
            'display_order' => 0,
            'target_sets' => 3,
            'rep_range_min' => 8,
            'rep_range_max' => 12,
            'target_rpe' => 8,
            'rest_seconds' => 120,
        ]);

        Passport::actingAs($cloner);
        $response = $this->postJson("/api/programs/{$program->id}/clone");

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Public PPL')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.days.0.day_name', 'Push')
            ->assertJsonPath('data.days.0.exercises.0.pivot.target_sets', 3)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rest_seconds', 120);

        // A new, separate program row owned by the cloner, linked to its source.
        $newId = $response->json('data.id');
        $this->assertNotEquals($program->id, $newId);
        $this->assertEquals(1, $cloner->programs()->count());
        $this->assertDatabaseHas('programs', [
            'id' => $newId,
            'user_id' => $cloner->id,
            'is_public' => false,
            'source_program_id' => $program->id,
        ]);
    }

    public function test_discover_flags_programs_the_user_has_already_saved()
    {
        $owner = User::factory()->create();
        $cloner = User::factory()->create();

        $saved = $owner->programs()->create(['name' => 'Saved One', 'is_active' => false, 'is_public' => true]);
        $notSaved = $owner->programs()->create(['name' => 'Not Saved', 'is_active' => false, 'is_public' => true]);

        Passport::actingAs($cloner);
        $this->postJson("/api/programs/{$saved->id}/clone")->assertStatus(201);

        $data = collect($this->getJson('/api/programs/discover')->assertStatus(200)->json('data'))->keyBy('id');

        $this->assertTrue($data[$saved->id]['already_saved']);
        $this->assertFalse($data[$notSaved->id]['already_saved']);
    }

    public function test_cloning_does_not_modify_the_source_program()
    {
        $owner = User::factory()->create();
        $cloner = User::factory()->create();

        $program = $owner->programs()->create(['name' => 'Original', 'is_active' => true, 'is_public' => true]);
        $program->days()->create(['day_name' => 'Day 1', 'display_order' => 1]);

        Passport::actingAs($cloner);
        $this->postJson("/api/programs/{$program->id}/clone")->assertStatus(201);

        // Source untouched: still owned by owner, still public/active, one day.
        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'user_id' => $owner->id,
            'is_public' => true,
            'is_active' => true,
        ]);
        $this->assertEquals(1, $program->days()->count());
    }

    public function test_cannot_clone_your_own_program()
    {
        // Even a public program you own can't be self-cloned — cloning is for
        // saving other people's programs.
        $user = User::factory()->create();
        $program = $user->programs()->create(['name' => 'Mine', 'is_active' => false, 'is_public' => true]);

        Passport::actingAs($user);
        $this->postJson("/api/programs/{$program->id}/clone")->assertStatus(403);

        // No duplicate created — still just the original.
        $this->assertEquals(1, $user->programs()->count());
    }

    public function test_cannot_clone_a_private_program_you_do_not_own()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $program = $owner->programs()->create(['name' => 'Secret', 'is_active' => false, 'is_public' => false]);

        Passport::actingAs($intruder);
        $this->postJson("/api/programs/{$program->id}/clone")->assertStatus(403);

        $this->assertEquals(0, $intruder->programs()->count());
    }
}
