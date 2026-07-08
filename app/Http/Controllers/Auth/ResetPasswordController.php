<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function reset(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // A reset is the recovery path for a lost or compromised
                // account, so every existing session must die — unlike the
                // change-password flow, there's no "current" session to keep
                // (the reset form is unauthenticated).
                $user->tokens()->update(['revoked' => true]);
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? $this->messageResponse(__($status))
                    : $this->messageResponse(__($status), 400);
    }
}
