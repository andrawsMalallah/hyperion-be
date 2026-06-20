<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Split;
use Laravel\Passport\Passport;

class SplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_split()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->postJson('/api/splits', [
            'split_name' => 'Push Pull Legs',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('splits', ['split_name' => 'Push Pull Legs']);
    }

    public function test_user_can_get_splits()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        Split::create(['user_id' => $user->id, 'split_name' => 'Bro Split', 'is_active' => false]);

        $response = $this->getJson('/api/splits');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    public function test_user_can_discover_splits()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Passport::actingAs($user1);

        Split::create(['user_id' => $user1->id, 'split_name' => 'Upper Body Split', 'is_active' => true]);
        Split::create(['user_id' => $user2->id, 'split_name' => 'Lower Body Split', 'is_active' => false]);

        $response = $this->getJson('/api/splits/discover');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id', 'split_name', 'is_active', 'created_at', 'days',
                             'user' => ['id', 'name', 'email']
                         ]
                     ],
                     'meta' => ['current_page', 'last_page', 'per_page', 'total']
                 ]);
    }

    public function test_user_can_search_discovered_splits()
    {
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);

        Passport::actingAs($user1);

        Split::create(['user_id' => $user1->id, 'split_name' => 'Push Pull Legs', 'is_active' => true]);
        Split::create(['user_id' => $user2->id, 'split_name' => 'Arnold Split', 'is_active' => false]);

        // Search for "Push"
        $response = $this->getJson('/api/splits/discover?search=Push');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.split_name', 'Push Pull Legs');

        // Search for "Arnold"
        $response = $this->getJson('/api/splits/discover?search=Arnold');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.split_name', 'Arnold Split');

        // Search by user name "Bob"
        $response = $this->getJson('/api/splits/discover?search=Bob');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.split_name', 'Arnold Split');
                 
        // Search for something non-existent
        $response = $this->getJson('/api/splits/discover?search=Cardio');
        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
     }

     public function test_activating_a_split_deactivates_other_splits()
     {
         $user = User::factory()->create();
         Passport::actingAs($user);

         $split1 = Split::create(['user_id' => $user->id, 'split_name' => 'Bro Split', 'is_active' => true]);
         $split2 = Split::create(['user_id' => $user->id, 'split_name' => 'Arnold Split', 'is_active' => false]);

         $response = $this->putJson("/api/splits/{$split2->id}", [
             'is_active' => true,
         ]);

         $response->assertStatus(200);
         $this->assertEquals(0, $split1->fresh()->is_active);
         $this->assertEquals(1, $split2->fresh()->is_active);
     }
}
