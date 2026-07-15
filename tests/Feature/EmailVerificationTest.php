<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function setUpPassportClient(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        Client::factory()->asPersonalAccessTokenClient()->create();
    }

    public function test_registration_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->setUpPassportClient();

        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'new@example.com',
            'password' => 'Super-Secret-Pass1!',
            'password_confirmation' => 'Super-Secret-Pass1!',
        ])->assertStatus(201)
            ->assertJsonPath('user.email_verified', false);

        $user = User::where('email', 'new@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_is_blocked_from_data_routes_with_409(): void
    {
        $user = User::factory()->unverified()->create();

        Passport::actingAs($user);

        $this->getJson('/api/programs')
            ->assertStatus(409)
            ->assertJsonPath('code', 'email_unverified');
    }

    public function test_verified_user_can_reach_data_routes(): void
    {
        $user = User::factory()->create(); // verified by default

        Passport::actingAs($user);

        $this->getJson('/api/programs')->assertStatus(200);
    }

    public function test_unverified_user_can_still_read_their_own_account(): void
    {
        $user = User::factory()->unverified()->create();

        Passport::actingAs($user);

        // /user stays outside the gate so the SPA can detect the unverified state.
        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->getJson($url)->assertStatus(200);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_with_a_bad_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-elses-email')]
        );

        $this->getJson($url)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson('/api/email/verify/'.$user->id.'/'.sha1($user->getEmailForVerification()))
            ->assertStatus(403);
    }

    public function test_authenticated_user_can_resend_the_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Passport::actingAs($user);

        $this->postJson('/api/email/verification-notification')->assertStatus(200);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_is_a_noop_for_an_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // verified
        Passport::actingAs($user);

        $this->postJson('/api/email/verification-notification')->assertStatus(200);

        Notification::assertNothingSent();
    }

    public function test_verification_link_points_at_the_frontend_page(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $url = call_user_func(VerifyEmail::$createUrlCallback, $user);

            return str_starts_with($url, config('app.frontend_url').'/verify-email/'.$user->id)
                && str_contains($url, 'signature=');
        });
    }
}
