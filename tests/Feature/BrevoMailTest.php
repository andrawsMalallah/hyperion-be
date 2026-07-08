<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route mail through the Brevo HTTP transport with a fake key.
        config([
            'mail.default' => 'brevo-api',
            'mail.mailers.brevo-api.key' => 'test-key',
            'mail.from.address' => 'support.hyperion@gmail.com',
            'mail.from.name' => 'Hyperion',
        ]);
    }

    public function test_mail_is_sent_via_the_brevo_http_api(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201),
        ]);

        Mail::raw('Hello world', function ($message) {
            $message->to('user@example.com')->subject('Test Subject');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-key')
                && $request['subject'] === 'Test Subject'
                && $request['to'][0]['email'] === 'user@example.com'
                && $request['sender']['email'] === 'support.hyperion@gmail.com';
        });
    }

    public function test_password_reset_notification_goes_through_brevo(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201),
        ]);

        $user = User::factory()->create(['email' => 'reset@example.com']);

        // Send the framework's reset notification (the real production path).
        $user->notify(new ResetPassword('fake-token'));

        Http::assertSent(function ($request) {
            $body = ($request['htmlContent'] ?? '').($request['textContent'] ?? '');

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request['to'][0]['email'] === 'reset@example.com'
                // The reset link (built by createUrlUsing) carries the token.
                && str_contains($body, 'fake-token');
        });
    }
}
