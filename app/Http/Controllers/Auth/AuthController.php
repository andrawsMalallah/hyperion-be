<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Fires the framework's SendEmailVerificationNotification listener so the
        // new (unverified) account receives its verification link immediately.
        event(new Registered($user));

        $token = $user->createToken($this->deviceName($request))->accessToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->messageResponse('Invalid login credentials.', 401);
        }

        $token = $user->createToken($this->deviceName($request))->accessToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return $this->messageResponse('Successfully logged out.');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->update(['revoked' => true]);

        return $this->messageResponse('Logged out on all devices.');
    }

    public function user(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * The token name records the client's User-Agent so the Profile "Devices"
     * screen can identify each session. Truncated to fit the oauth name column.
     */
    private function deviceName(Request $request): string
    {
        return substr($request->userAgent() ?: 'Unknown device', 0, 150);
    }
}
