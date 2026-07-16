<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * Permanently delete the authenticated user's account and every trace of
     * their data. Re-confirmed with the current password (DeleteAccountRequest).
     *
     * What the database FKs already cascade on user delete: programs (→ days →
     * day_exercise), workout_logs (→ set_logs), and user_settings. What has NO
     * cascade and must be cleaned by hand is done here inside one transaction:
     *   - The user's own UNAPPROVED (pending) contributed exercises, but ONLY
     *     those nothing references — see removeUnreferencedPendingExercises().
     *     Approved exercises are shared catalog rows other users' programs
     *     reference, so those are left in place and only de-identified
     *     (created_by → null via the exercises.created_by nullOnDelete FK) —
     *     deleting them would cascade-break other members.
     *   - Passport tokens (oauth_access_tokens / oauth_refresh_tokens) and the
     *     transient oauth auth/device codes — none have a cascade FK to users.
     *   - This email's password-reset token and any legacy web session rows.
     */
    public function destroy(DeleteAccountRequest $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            // Capture these BEFORE deleting the user: that delete nulls
            // created_by (nullOnDelete), after which their contributions can no
            // longer be identified.
            $ownPendingIds = Exercise::where('created_by', $user->id)
                ->where('status', '!=', 'approved')
                ->pluck('id');

            // Passport tokens have no cascade FK to users — remove refresh
            // tokens first (they reference the access token), then the access
            // tokens, then the transient authorization/device-flow codes.
            $accessTokenIds = $user->tokens()->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $accessTokenIds)->delete();
            $user->tokens()->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->id)->delete();

            // Auth-adjacent leftovers keyed by email / user_id with no cascade.
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Cascades programs (→ days → day_exercise), workout_logs (→
            // set_logs), user_settings, and nulls created_by on kept exercises.
            // Runs BEFORE the exercise cleanup so this user's own programs and
            // logs are already gone and no longer count as references.
            $user->delete();

            $this->removeUnreferencedPendingExercises($ownPendingIds);
        });

        return $this->messageResponse('Your account has been permanently deleted.');
    }

    /**
     * Delete the departing user's pending contributions, but only the ones
     * nothing points at.
     *
     * A pending exercise is NOT necessarily private to its contributor: if they
     * published a program built on it, anyone who cloned that program now
     * references it, and `day_exercise.exercise_id` / `set_logs.exercise_id` both
     * cascade — so deleting the row would silently strip an exercise out of
     * someone else's program, or delete sets they actually logged. Anything still
     * referenced is kept and merely de-identified (the user delete already nulled
     * created_by), exactly like an approved row.
     *
     * @param  Collection  $ownPendingIds  captured before the user was deleted
     */
    private function removeUnreferencedPendingExercises($ownPendingIds): void
    {
        if ($ownPendingIds->isEmpty()) {
            return;
        }

        Exercise::whereIn('id', $ownPendingIds)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('day_exercise')
                ->whereColumn('day_exercise.exercise_id', 'exercises.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('set_logs')
                ->whereColumn('set_logs.exercise_id', 'exercises.id'))
            ->delete();
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
