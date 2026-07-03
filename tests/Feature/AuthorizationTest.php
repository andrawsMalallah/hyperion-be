<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_users_program(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $program = $owner->programs()->create(['name' => 'Private Program', 'is_active' => true]);

        Passport::actingAs($intruder);

        $this->getJson("/api/programs/{$program->id}")->assertStatus(403);
        $this->putJson("/api/programs/{$program->id}", ['name' => 'Hijacked'])->assertStatus(403);
        $this->deleteJson("/api/programs/{$program->id}")->assertStatus(403);
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name' => 'Private Program']);
    }

    public function test_user_cannot_start_workout_from_another_users_day(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $program = $owner->programs()->create(['name' => 'Private Program', 'is_active' => true]);
        $day = $program->days()->create(['day_name' => 'Push', 'display_order' => 0]);

        Passport::actingAs($intruder);

        $this->getJson("/api/programs/by-day/{$day->id}")->assertStatus(403);
    }

    public function test_user_cannot_access_another_users_workout_log(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $log = $owner->workoutLogs()->create(['date_timestamp' => now()]);

        Passport::actingAs($intruder);

        $this->getJson("/api/workout-logs/{$log->id}")->assertStatus(403);
        $this->deleteJson("/api/workout-logs/{$log->id}")->assertStatus(403);
        $this->assertDatabaseHas('workout_logs', ['id' => $log->id]);
    }

    public function test_program_update_rejects_day_ids_from_other_programs(): void
    {
        $user = User::factory()->create();
        $program = $user->programs()->create(['name' => 'Mine', 'is_active' => true]);
        $otherProgram = $user->programs()->create(['name' => 'Other', 'is_active' => false]);
        $foreignDay = $otherProgram->days()->create(['day_name' => 'Pull', 'display_order' => 0]);

        Passport::actingAs($user);

        $this->putJson("/api/programs/{$program->id}", [
            'days' => [
                ['id' => $foreignDay->id, 'day_name' => 'Stolen Day', 'display_order' => 0],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['days.0.id']);
    }
}
