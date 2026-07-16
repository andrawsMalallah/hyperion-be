<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Notifications\ExerciseApproved;
use App\Notifications\ExerciseRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminExerciseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Passport's auth:api guard loads the OAuth signing keys when it
        // resolves — even under Passport::actingAs(). This class sorts before
        // AuthTest (which generates them in CI), so provision them here too when
        // the key files are missing, or these tests fail with "Invalid key
        // supplied" on a fresh CI checkout.
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
    }

    private function pendingExercise(User $contributor, string $name = 'My Special Curl'): Exercise
    {
        return Exercise::create([
            'name' => $name,
            'target_muscle_group' => 'Biceps',
            'mechanics_type' => 'Isolation',
            'created_by' => $contributor->id,
            'status' => 'pending',
        ]);
    }

    public function test_non_admin_cannot_access_admin_routes()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->getJson('/api/admin/exercises')->assertStatus(403);
        $this->getJson('/api/admin/exercises/pending')->assertStatus(403);
    }

    public function test_admin_can_list_all_exercises_with_filters()
    {
        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();

        Exercise::create(['name' => 'Barbell Squat', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound', 'status' => 'approved']);
        Exercise::create(['name' => 'Bench Press', 'target_muscle_group' => 'Chest', 'mechanics_type' => 'Compound', 'status' => 'approved']);
        $this->pendingExercise($contributor);

        Passport::actingAs($admin);

        // Unfiltered lists everything.
        $this->getJson('/api/admin/exercises')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Status filter.
        $this->getJson('/api/admin/exercises?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Special Curl')
            ->assertJsonPath('data.0.contributor.name', $contributor->name);

        // Muscle-group filter.
        $this->getJson('/api/admin/exercises?target_muscle_group=Chest')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bench Press');

        // Name search.
        $this->getJson('/api/admin/exercises?search=squat')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Barbell Squat');

        // Contributor filter.
        $this->getJson('/api/admin/exercises?created_by='.$contributor->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Special Curl');
    }

    public function test_admin_pending_queue_only_returns_pending()
    {
        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();

        $this->pendingExercise($contributor);
        Exercise::create(['name' => 'Approved One', 'target_muscle_group' => 'Back', 'mechanics_type' => 'Compound', 'status' => 'approved']);

        Passport::actingAs($admin);

        $this->getJson('/api/admin/exercises/pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_admin_can_approve_and_contributor_is_notified()
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();
        $exercise = $this->pendingExercise($contributor);

        Passport::actingAs($admin);

        $this->postJson('/api/admin/exercises/'.$exercise->id.'/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        Notification::assertSentTo($contributor, ExerciseApproved::class);
    }

    public function test_admin_can_reject_with_reason_and_contributor_is_notified()
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();
        $exercise = $this->pendingExercise($contributor);

        Passport::actingAs($admin);

        $this->postJson('/api/admin/exercises/'.$exercise->id.'/reject', [
            'reason' => 'Duplicate of Barbell Curl.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Duplicate of Barbell Curl.');

        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'status' => 'rejected',
            'rejection_reason' => 'Duplicate of Barbell Curl.',
            'reviewed_by' => $admin->id,
        ]);

        Notification::assertSentTo($contributor, ExerciseRejected::class);
    }

    public function test_reject_reason_is_optional_but_capped()
    {
        $admin = User::factory()->admin()->create();
        $contributor = User::factory()->create();
        $exercise = $this->pendingExercise($contributor);

        Passport::actingAs($admin);

        // Blank reason is allowed.
        $this->postJson('/api/admin/exercises/'.$exercise->id.'/reject')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        // Over-long reason is rejected.
        $second = $this->pendingExercise($contributor, 'Another Curl');
        $this->postJson('/api/admin/exercises/'.$second->id.'/reject', [
            'reason' => str_repeat('a', 501),
        ])->assertStatus(422);
    }

    public function test_contributor_can_list_their_own_exercises_across_statuses()
    {
        $contributor = User::factory()->create();
        $other = User::factory()->create();

        Exercise::create(['name' => 'Mine Pending', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation', 'created_by' => $contributor->id, 'status' => 'pending']);
        Exercise::create(['name' => 'Mine Rejected', 'target_muscle_group' => 'Biceps', 'mechanics_type' => 'Isolation', 'created_by' => $contributor->id, 'status' => 'rejected', 'rejection_reason' => 'Nope']);
        Exercise::create(['name' => 'Someone Elses', 'target_muscle_group' => 'Legs', 'mechanics_type' => 'Compound', 'created_by' => $other->id, 'status' => 'pending']);

        Passport::actingAs($contributor);

        $this->getJson('/api/exercises/mine')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Search narrows to one and exposes the rejection reason.
        $this->getJson('/api/exercises/mine?search=Rejected')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rejection_reason', 'Nope');

        // Status filter narrows to only the requested state.
        $this->getJson('/api/exercises/mine?status=rejected')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine Rejected');

        $this->getJson('/api/exercises/mine?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine Pending');
    }
}
