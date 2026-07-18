<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BodyMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_the_users_own_metrics_oldest_first(): void
    {
        $user = User::factory()->create();
        $user->bodyMetrics()->create(['weight' => 82.5, 'measured_on' => '2026-07-10']);
        $user->bodyMetrics()->create(['weight' => 81.4, 'measured_on' => '2026-07-05']);

        // Another user's entry must never appear.
        User::factory()->create()->bodyMetrics()->create(['weight' => 99, 'measured_on' => '2026-07-08']);

        Passport::actingAs($user);

        $this->getJson('/api/body-metrics')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.measured_on', '2026-07-05')
            ->assertJsonPath('data.0.weight', 81.4)
            ->assertJsonPath('data.1.measured_on', '2026-07-10');
    }

    public function test_a_weight_can_be_logged(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson('/api/body-metrics', ['weight' => 80.25, 'measured_on' => '2026-07-15'])
            ->assertStatus(201)
            ->assertJsonPath('data.weight', 80.25)
            ->assertJsonPath('data.measured_on', '2026-07-15');

        $this->assertDatabaseHas('body_metrics', [
            'user_id' => $user->id,
            'measured_on' => '2026-07-15',
            'weight' => 80.25,
        ]);
    }

    public function test_logging_the_same_date_upserts_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // First submission creates (201); the correction updates the same row
        // (200 — the resource returns 201 only when the model was just created).
        $this->postJson('/api/body-metrics', ['weight' => 80, 'measured_on' => '2026-07-15'])->assertStatus(201);
        $this->postJson('/api/body-metrics', ['weight' => 79.5, 'measured_on' => '2026-07-15'])
            ->assertStatus(200)
            ->assertJsonPath('data.weight', 79.5);

        // One row for the day, carrying the corrected weight.
        $this->assertDatabaseCount('body_metrics', 1);
        $this->assertDatabaseHas('body_metrics', [
            'user_id' => $user->id,
            'measured_on' => '2026-07-15',
            'weight' => 79.5,
        ]);
    }

    public function test_store_rejects_invalid_input(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Missing weight.
        $this->postJson('/api/body-metrics', ['measured_on' => '2026-07-15'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);

        // Future date.
        $this->postJson('/api/body-metrics', ['weight' => 80, 'measured_on' => now()->addDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['measured_on']);

        // Out-of-range weight (an lbs value typed as kg by mistake).
        $this->postJson('/api/body-metrics', ['weight' => 1200, 'measured_on' => '2026-07-15'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_a_user_can_delete_their_own_entry(): void
    {
        $user = User::factory()->create();
        $metric = $user->bodyMetrics()->create(['weight' => 80, 'measured_on' => '2026-07-15']);

        Passport::actingAs($user);

        $this->deleteJson("/api/body-metrics/{$metric->id}")->assertStatus(204);
        $this->assertDatabaseMissing('body_metrics', ['id' => $metric->id]);
    }

    public function test_a_user_cannot_delete_another_users_entry(): void
    {
        $owner = User::factory()->create();
        $metric = $owner->bodyMetrics()->create(['weight' => 80, 'measured_on' => '2026-07-15']);

        Passport::actingAs(User::factory()->create());

        $this->deleteJson("/api/body-metrics/{$metric->id}")->assertStatus(404);
        $this->assertDatabaseHas('body_metrics', ['id' => $metric->id]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/body-metrics')->assertStatus(401);
        $this->postJson('/api/body-metrics', ['weight' => 80, 'measured_on' => '2026-07-15'])->assertStatus(401);
    }
}
