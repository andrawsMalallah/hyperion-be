<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

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

    public function test_user_contributions_are_pending_and_hidden_from_the_catalog()
    {
        $contributor = User::factory()->create();
        $otherUser = User::factory()->create();

        Passport::actingAs($contributor);
        $response = $this->postJson('/api/exercises', [
            'name' => 'My Special Curl',
            'target_muscle_group' => 'Biceps',
            'mechanics_type' => 'Isolation',
        ]);
        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');

        // The catalog is approved-only, so a contributor cannot select their own
        // pending exercise either — that's what keeps programs referencing only
        // exercises a program file can resolve by name elsewhere.
        $this->getJson('/api/exercises?search=Special')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // They still track it on Contribute via /exercises/mine.
        $this->getJson('/api/exercises/mine?search=Special')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');

        // Other users don't see it in the catalog either.
        Passport::actingAs($otherUser);
        $this->getJson('/api/exercises?search=Special')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_rejected_exercises_are_excluded_from_the_catalog()
    {
        $contributor = User::factory()->create();
        Passport::actingAs($contributor);

        Exercise::create([
            'name' => 'Rejected Movement',
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Isolation',
            'created_by' => $contributor->id,
            'status' => 'rejected',
        ]);

        $this->getJson('/api/exercises?search=Rejected')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_exercises_are_paginated_and_searchable()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        for ($i = 1; $i <= 35; $i++) {
            Exercise::create([
                'name' => 'Exercise '.$i,
                'target_muscle_group' => $i === 1 ? 'Chest' : 'Legs',
                'mechanics_type' => 'Compound',
            ]);
        }

        // Check first page has 30
        $response = $this->getJson('/api/exercises');
        $response->assertStatus(200)
            ->assertJsonCount(30, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
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
