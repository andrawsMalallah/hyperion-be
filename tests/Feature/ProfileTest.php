<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\UserSetting;
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

    public function test_account_deletion_removes_the_user_and_all_their_data(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);
        $otherUser = User::factory()->create();

        // Data owned by the user that must cascade away on delete.
        $program = $user->programs()->create(['name' => 'My Program']);
        $log = $user->workoutLogs()->create(['date_timestamp' => now()]);
        $exercise = Exercise::create(['name' => 'Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound']);
        $log->sets()->create(['exercise_id' => $exercise->id, 'weight' => 100, 'reps' => 5, 'set_order' => 1]);
        UserSetting::create(['user_id' => $user->id, 'weight_unit' => 'kg']);

        // The user's own PENDING contribution, referenced by nobody → deleted.
        $ownPending = Exercise::create([
            'name' => 'Secret Curl', 'target_muscle_group' => 'Biceps',
            'mechanics_type' => 'Isolation', 'created_by' => $user->id, 'status' => 'pending',
        ]);
        // The user's APPROVED contribution is shared catalog → must survive,
        // only de-identified (created_by nulled).
        $ownApproved = Exercise::create([
            'name' => 'Community Press', 'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound', 'created_by' => $user->id, 'status' => 'approved',
        ]);

        // A real login so there's an oauth_access_tokens row to clean up.
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/user', ['current_password' => 'super-secret-password'])
            ->assertStatus(200);

        // The account and every cascaded record is gone.
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
        $this->assertDatabaseMissing('workout_logs', ['id' => $log->id]);
        $this->assertDatabaseMissing('set_logs', ['workout_log_id' => $log->id]);
        $this->assertDatabaseMissing('user_settings', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('oauth_access_tokens', ['user_id' => $user->id]);

        // The user's own pending exercise is gone…
        $this->assertDatabaseMissing('exercises', ['id' => $ownPending->id]);
        // …but the approved (shared) one survives, de-identified.
        $this->assertDatabaseHas('exercises', ['id' => $ownApproved->id, 'created_by' => null]);

        // Other members are untouched.
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    /**
     * A pending exercise is only private until its contributor publishes a
     * program built on it and someone clones that program. Deleting the row then
     * cascades (day_exercise.exercise_id / set_logs.exercise_id), silently
     * stripping an exercise out of the cloner's program and deleting sets they
     * logged — so a referenced pending row must be kept and de-identified.
     */
    public function test_account_deletion_keeps_a_pending_exercise_another_members_program_uses(): void
    {
        $this->setUpPassportClient();

        $user = User::factory()->create(['password' => 'super-secret-password']);
        $cloner = User::factory()->create();

        $pending = Exercise::create([
            'name' => 'Cable Y-Raise', 'target_muscle_group' => 'Shoulders',
            'mechanics_type' => 'Isolation', 'created_by' => $user->id, 'status' => 'pending',
        ]);

        // The contributor publishes a program built on their pending exercise…
        $public = $user->programs()->create(['name' => 'Shared PPL', 'is_public' => true, 'is_active' => false]);
        $publicDay = $public->days()->create(['day_name' => 'Push', 'display_order' => 1]);
        $publicDay->exercises()->attach($pending->id, ['display_order' => 0, 'target_sets' => 3]);

        // …and another member clones it, so their program now references it too.
        Passport::actingAs($cloner);
        $this->postJson("/api/programs/{$public->id}/clone")->assertStatus(201);
        $clonedDayId = $cloner->programs()->first()->days()->first()->id;

        // The cloner also logs a set with it, which set_logs.exercise_id cascades.
        $clonerLog = $cloner->workoutLogs()->create(['date_timestamp' => now()]);
        $clonerSet = $clonerLog->sets()->create([
            'exercise_id' => $pending->id, 'weight' => 20, 'reps' => 12, 'set_order' => 1,
        ]);

        // Passport::actingAs leaves the api guard resolved to the cloner, which
        // would make current_password:api check the wrong user's password.
        app('auth')->forgetGuards();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'super-secret-password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/user', ['current_password' => 'super-secret-password'])
            ->assertStatus(200);

        // The contributor and their own program are gone…
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('programs', ['id' => $public->id]);

        // …but the exercise survives, de-identified, because others point at it.
        $this->assertDatabaseHas('exercises', ['id' => $pending->id, 'created_by' => null, 'status' => 'pending']);

        // The cloner's program still has it, and their logged set survives.
        $this->assertDatabaseHas('day_exercise', ['program_day_id' => $clonedDayId, 'exercise_id' => $pending->id]);
        $this->assertDatabaseHas('set_logs', ['id' => $clonerSet->id, 'exercise_id' => $pending->id]);
    }

    public function test_account_deletion_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'super-secret-password']);
        Passport::actingAs($user);

        $this->deleteJson('/api/user', ['current_password' => 'not-the-password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Nothing was deleted.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_account_deletion_requires_authentication(): void
    {
        $this->deleteJson('/api/user', ['current_password' => 'whatever'])
            ->assertStatus(401);
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
