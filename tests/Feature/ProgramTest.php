<?php

namespace Tests\Feature;

use App\Models\Program;
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

        Program::create(['user_id' => $user->id, 'name' => 'Bro Program', 'is_active' => false]);

        $response = $this->getJson('/api/programs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_discover_programs()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Passport::actingAs($user1);

        Program::create(['user_id' => $user1->id, 'name' => 'Upper Body Program', 'is_active' => true]);
        Program::create(['user_id' => $user2->id, 'name' => 'Lower Body Program', 'is_active' => false]);

        $response = $this->getJson('/api/programs/discover');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'is_active', 'created_at', 'days',
                        'user' => ['id', 'name', 'email'],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_can_search_discovered_programs()
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        Passport::actingAs($user1);

        Program::create(['user_id' => $user1->id, 'name' => 'Push Pull Legs', 'is_active' => true]);
        Program::create(['user_id' => $user2->id, 'name' => 'Arnold Program', 'is_active' => false]);

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

    public function test_activating_a_program_deactivates_other_programs()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $Program1 = Program::create(['user_id' => $user->id, 'name' => 'Bro Program', 'is_active' => true]);
        $Program2 = Program::create(['user_id' => $user->id, 'name' => 'Arnold Program', 'is_active' => false]);

        $response = $this->putJson("/api/programs/{$Program2->id}", [
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $Program1->fresh()->is_active);
        $this->assertEquals(1, $Program2->fresh()->is_active);
    }
}
