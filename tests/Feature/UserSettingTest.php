<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_created_with_defaults_on_first_read(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->getJson('/api/user/settings')
            ->assertStatus(200)
            ->assertJsonPath('data.weight_unit', 'kg')
            ->assertJsonPath('data.default_rest_time', 90);

        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id]);
    }

    public function test_settings_can_be_updated(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/user/settings', ['weight_unit' => 'lbs', 'default_rest_time' => 120])
            ->assertStatus(200)
            ->assertJsonPath('data.weight_unit', 'lbs')
            ->assertJsonPath('data.default_rest_time', 120);
    }

    public function test_settings_reject_out_of_range_values(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/user/settings', ['default_rest_time' => 4000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_rest_time']);

        $this->putJson('/api/user/settings', ['weight_unit' => 'stone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weight_unit']);
    }
}
