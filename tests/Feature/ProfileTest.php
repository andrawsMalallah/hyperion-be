<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function setUpPassportClient(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        Client::factory()->asPersonalAccessTokenClient()->create();
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/user/profile', [
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new-email@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new-email@example.com',
        ]);
    }

    public function test_profile_update_rejects_anothers_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/user/profile', ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_accepts_own_unchanged_email(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->putJson('/api/user/profile', [
            'name' => 'Renamed Only',
            'email' => $user->email,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);
        Passport::actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'not-the-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_rejects_unconfirmed_or_weak_password(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);
        Passport::actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'super-secret-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'something-else',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->putJson('/api/user/password', [
            'current_password' => 'super-secret-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_succeeds_and_old_password_stops_working(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);
        Passport::actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'super-secret-password',
            'password' => 'Brand-New-Pass1!',
            'password_confirmation' => 'Brand-New-Pass1!',
        ])->assertStatus(200);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->assertStatus(401);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Brand-New-Pass1!',
        ])->assertStatus(200);
    }

    public function test_login_records_the_user_agent_as_the_token_name(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'super-secret-password',
            ])->assertStatus(200);

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'name' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120',
        ]);
    }

    public function test_sessions_lists_active_tokens_with_one_marked_current(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        // Two separate logins → two active sessions.
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'super-secret-password']);
        $currentToken = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$currentToken)
            ->getJson('/api/user/sessions')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'created_at', 'is_current']]]);

        $sessions = $response->json('data');
        $this->assertCount(2, $sessions);
        $this->assertCount(1, array_filter($sessions, fn ($s) => $s['is_current'] === true));
    }

    public function test_sessions_excludes_revoked_tokens(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        $firstToken = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'super-secret-password'])->json('access_token');
        $secondToken = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'super-secret-password'])->json('access_token');

        // Revoke the first session.
        $this->withHeader('Authorization', 'Bearer '.$firstToken)->postJson('/api/logout');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/user/sessions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_password_change_revokes_other_tokens_but_keeps_current(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        $firstToken = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $secondToken = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->putJson('/api/user/password', [
                'current_password' => 'super-secret-password',
                'password' => 'Brand-New-Pass1!',
                'password_confirmation' => 'Brand-New-Pass1!',
            ])
            ->assertStatus(200);

        // The other device's token must be revoked; the current one stays
        // valid. Flush the cached guard so each request re-validates.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/user')
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/user')
            ->assertStatus(200);
    }
}
