<?php

namespace App\Console\Commands;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Mints a ready-to-use account for the Playwright suite and prints it as JSON.
 *
 * Why this exists: email verification is a hard requirement (Session 18), so a
 * freshly registered account lands on /verify-email and can't reach the app.
 * The E2E fixture therefore can't register through the UI — it needs an account
 * that is already verified, plus a token to inject into localStorage.
 *
 * The output shape mirrors the /login response on purpose (`user` is rendered
 * through UserResource) so the fixture can write it straight into localStorage
 * and the SPA cannot tell the difference from a real sign-in.
 */
class E2eUserCommand extends Command
{
    protected $signature = 'hyperion:e2e-user
                            {--admin : Make the account an admin}
                            {--reset-token : Also mint a password-reset token for this account}';

    protected $description = 'Create a verified user + Passport token for the E2E suite (local/testing only)';

    public function handle(): int
    {
        // Hard stop outside local/testing. This command creates a verified
        // account and hands out a token, which must never be possible against
        // production — the standing rule is that prod is never written to.
        if (! app()->environment(['local', 'testing'])) {
            $this->error('hyperion:e2e-user is refused outside local/testing (env: '.app()->environment().').');

            return self::FAILURE;
        }

        // Satisfies the app's password policy (min 8, mixed case, number,
        // symbol) so the same value works anywhere a test needs to re-enter it.
        $password = 'E2e-Password-123!';

        $user = new User([
            'name' => 'E2E User',
            'email' => 'e2e-'.Str::lower(Str::random(12)).'@example.test',
            'password' => $password,
            'is_admin' => (bool) $this->option('admin'),
        ]);

        // email_verified_at isn't mass-assignable, so set it directly. This is
        // the whole point of the command: skip the verification round-trip.
        $user->email_verified_at = now();
        $user->save();

        $payload = [
            'token' => $user->createToken('e2e')->accessToken,
            'user' => (new UserResource($user))->toArray(request()),
            'email' => $user->email,
            'password' => $password,
        ];

        // Reset tokens are stored hashed, so a test can never read one back out
        // of password_reset_tokens. Minting it through the same broker the app
        // uses is the only way to drive the reset screen for real.
        if ($this->option('reset-token')) {
            $payload['reset_token'] = Password::broker()->createToken($user);
        }

        $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
