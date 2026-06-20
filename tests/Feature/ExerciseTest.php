<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use Laravel\Passport\Passport;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_exercises()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);

        $response = $this->getJson('/api/exercises');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    public function test_exercises_are_paginated_and_searchable()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        for ($i = 1; $i <= 35; $i++) {
            Exercise::create([
                'name' => 'Exercise ' . $i,
                'target_muscle_group' => $i === 1 ? 'Chest' : 'Legs',
                'mechanics_type' => 'Compound'
            ]);
        }

        // Check first page has 30
        $response = $this->getJson('/api/exercises');
        $response->assertStatus(200)
                 ->assertJsonCount(30, 'data')
                 ->assertJsonStructure([
                     'data',
                     'meta' => ['current_page', 'last_page', 'per_page', 'total']
                 ]);

        // Check page 2 has 5
        $response = $this->getJson('/api/exercises?page=2');
        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data');

        // Check searching works
        $response = $this->getJson('/api/exercises?search=Chest');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Exercise 1');
    }
}
