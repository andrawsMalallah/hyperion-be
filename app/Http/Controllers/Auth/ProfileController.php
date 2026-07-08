<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return new UserResource($user);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();
        $user->update(['password' => Hash::make($request->password)]);

        // Sign out every other device, keeping this session alive.
        $currentTokenId = $this->currentTokenId($request);
        $query = $user->tokens();
        if ($currentTokenId !== null) {
            $query->where('id', '!=', $currentTokenId);
        }
        $query->update(['revoked' => true]);

        return $this->messageResponse('Password changed successfully.');
    }

    /**
     * Active (non-revoked, unexpired) sessions for the Profile "Devices"
     * screen. The token name holds the User-Agent captured at login; older
     * tokens named "auth_token" render as "Unknown device" on the client.
     */
    public function sessions(Request $request)
    {
        $currentTokenId = $this->currentTokenId($request);

        $sessions = $request->user()->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $currentTokenId,
            ]);

        return $this->dataResponse($sessions);
    }

    /**
     * The id of the request's own access token, or null under
     * Passport::actingAs() where the token is a TransientToken (no id). A real
     * bearer token is an AccessToken exposing oauth_access_token_id.
     */
    private function currentTokenId(Request $request): ?string
    {
        $token = $request->user()->token();

        return $token && ! $token->transient() ? $token->oauth_access_token_id : null;
    }
}
