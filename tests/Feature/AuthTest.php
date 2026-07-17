<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Client;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function setUpPassportClient(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        Client::factory()->asPersonalAccessTokenClient()->create();
    }

    public function test_user_can_register(): void
    {
        $this->setUpPassportClient();

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'new@example.com',
            'password' => 'Super-Secret-Pass1!',
            'password_confirmation' => 'Super-Secret-Pass1!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'access_token']);
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_registration_is_rejected_when_the_honeypot_is_filled(): void
    {
        $this->setUpPassportClient();

        $response = $this->postJson('/api/register', [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'password' => 'Super-Secret-Pass1!',
            'password_confirmation' => 'Super-Secret-Pass1!',
            'website' => 'http://spam.example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_the_honeypot_rejection_does_not_name_the_field(): void
    {
        // The whole point of a honeypot is that a bot can't learn which input to
        // leave empty. Naming it in `errors` would hand that straight over.
        //
        // The trap short-circuits before token creation, so Passport is never
        // reached — but set the client up anyway, so a regression fails on the
        // assertion below rather than on a confusing Passport 500.
        $this->setUpPassportClient();

        $response = $this->postJson('/api/register', [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'password' => 'Super-Secret-Pass1!',
            'password_confirmation' => 'Super-Secret-Pass1!',
            'website' => 'http://spam.example.com',
        ]);

        $response->assertStatus(422);
        $this->assertArrayNotHasKey('errors', $response->json());
        $this->assertStringNotContainsString('website', $response->getContent());
    }

    public function test_an_empty_honeypot_does_not_block_registration(): void
    {
        // A real browser posts the field present-but-empty, not absent. If that
        // tripped the trap, nobody could ever sign up.
        $this->setUpPassportClient();

        $response = $this->postJson('/api/register', [
            'name' => 'Real Person',
            'email' => 'real@example.com',
            'password' => 'Super-Secret-Pass1!',
            'password_confirmation' => 'Super-Secret-Pass1!',
            'website' => '',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'real@example.com']);
    }

    public function test_registration_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'new@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_and_logout(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'access_token']);

        $token = $response->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // The revoked token must no longer authenticate. Flush the cached
        // guard so the next in-test request re-validates the token.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_forgot_password_response_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $knownResponse = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $unknownResponse = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        $knownResponse->assertStatus(200);
        $unknownResponse->assertStatus(200);
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
    }

    public function test_reset_link_points_at_the_frontend_reset_page(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = call_user_func($notification::$createUrlCallback, $user, $notification->token);

            return str_starts_with($url, config('app.frontend_url').'/reset-password/'.$notification->token)
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_password_reset_revokes_all_existing_sessions(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);

        // A live session that must be killed by the reset.
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertStatus(200);

        $resetToken = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'Brand-New-Pass1!',
            'password_confirmation' => 'Brand-New-Pass1!',
        ])->assertStatus(200);

        // The pre-reset token is now revoked. Flush the cached guard so the
        // next request re-validates against the database.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    public function test_logout_all_revokes_every_token(): void
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
            ->postJson('/api/logout-all')
            ->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/user')
            ->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/user')
            ->assertStatus(401);
    }
}
