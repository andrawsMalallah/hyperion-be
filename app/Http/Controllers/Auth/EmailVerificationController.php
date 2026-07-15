<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Mark an account's email as verified.
     *
     * Reached from the link in the verification email, which may open in a
     * browser with no auth token — so this route is authenticated by its
     * signature (the `signed` middleware) plus the {hash} check below, not by a
     * Bearer token. Idempotent: a second click on an already-verified account
     * still succeeds.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        // The hash in the link is sha1(email); reject a tampered/mismatched link.
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->messageResponse('Invalid verification link.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $this->messageResponse('Email verified successfully. You can now use Hyperion.');
    }

    /**
     * Resend the verification email to the currently authenticated user.
     * Rate-limited by the route's throttle. No-op (but still 200) if already
     * verified, so the UI can call it without special-casing.
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->messageResponse('Your email is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->messageResponse('Verification email sent. Please check your inbox.');
    }
}
