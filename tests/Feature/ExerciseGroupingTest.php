<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ExerciseGrouping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The exercise "type" on the day_exercise pivot: the tag-only types (drop set,
 * pyramid set) and the grouping types (superset, giant set) whose member counts
 * are enforced across a whole day.
 */
class ExerciseGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function exercise(string $name): Exercise
    {
        return Exercise::create([
            'name' => $name,
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound',
        ]);
    }

    /**
     * Build a one-day program payload from bare exercise entries.
     */
    private function payload(array $exercises): array
    {
        return [
            'name' => 'PPL',
            'days' => [
                [
                    'day_name' => 'Push',
                    'display_order' => 0,
                    'exercises' => $exercises,
                ],
            ],
        ];
    }

    public function test_a_tag_type_is_stored_and_returned_without_a_group()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_type' => ExerciseGrouping::DROP_SET],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', 'drop_set')
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', null);
    }

    public function test_a_superset_of_two_exercises_round_trips()
    {
        $user = User::factory()->create();
        $bench = $this->exercise('Bench Press');
        $fly = $this->exercise('Cable Fly');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $bench->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $fly->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1, 'rest_seconds' => 120],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', 'superset')
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.1.pivot.group_key', 1)
            // The rest that fires after the group belongs to its last exercise.
            ->assertJsonPath('data.days.0.exercises.1.pivot.rest_seconds', 120);
    }

    public function test_a_giant_set_of_three_exercises_round_trips()
    {
        $user = User::factory()->create();
        $first = $this->exercise('Bench Press');
        $second = $this->exercise('Cable Fly');
        $third = $this->exercise('Push Up');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $first->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
            ['exercise_id' => $second->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
            ['exercise_id' => $third->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.2.pivot.group_type', 'giant_set')
            ->assertJsonPath('data.days.0.exercises.2.pivot.group_key', 1);
    }

    public function test_two_groups_in_one_day_stay_separate()
    {
        $user = User::factory()->create();
        $a1 = $this->exercise('Bench Press');
        $a2 = $this->exercise('Cable Fly');
        $b1 = $this->exercise('Squat');
        $b2 = $this->exercise('Leg Curl');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $a1->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $a2->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $b1->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 2],
            ['exercise_id' => $b2->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 2],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.1.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.2.pivot.group_key', 2);
    }

    public function test_a_superset_of_one_exercise_is_rejected()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([
            'days.0.exercises.0.group_type' => 'A superset must join exactly 2 exercises.',
        ]);
        $this->assertEquals(0, $user->programs()->count());
    }

    public function test_a_superset_of_three_exercises_is_rejected()
    {
        $user = User::factory()->create();
        $first = $this->exercise('Bench Press');
        $second = $this->exercise('Cable Fly');
        $third = $this->exercise('Push Up');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $first->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $second->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $third->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([
            'days.0.exercises.0.group_type' => 'Use a giant set for 3 or more.',
        ]);
    }

    public function test_a_giant_set_of_two_exercises_is_rejected()
    {
        $user = User::factory()->create();
        $first = $this->exercise('Bench Press');
        $second = $this->exercise('Cable Fly');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $first->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
            ['exercise_id' => $second->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([
            'days.0.exercises.0.group_type' => 'A giant set must join at least 3 exercises.',
        ]);
    }

    public function test_a_tag_type_cannot_carry_a_group_key()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_type' => ExerciseGrouping::PYRAMID_SET, 'group_key' => 1],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days.0.exercises.0.group_key']);
    }

    public function test_a_grouping_type_without_a_group_key_is_rejected()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_type' => ExerciseGrouping::SUPERSET],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days.0.exercises.0.group_key']);
    }

    public function test_an_untyped_exercise_cannot_carry_a_group_key()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_key' => 1],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['days.0.exercises.0.group_key']);
    }

    public function test_one_group_cannot_mix_types()
    {
        $user = User::factory()->create();
        $first = $this->exercise('Bench Press');
        $second = $this->exercise('Cable Fly');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $first->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $second->id, 'group_type' => ExerciseGrouping::GIANT_SET, 'group_key' => 1],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors([
            'days.0.exercises.0.group_type' => 'The exercises in one group must all have the same type.',
        ]);
    }

    public function test_an_unknown_type_is_rejected()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $exercise->id, 'group_type' => 'mega_set'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['days.0.exercises.0.group_type']);
    }

    public function test_updating_a_program_can_break_a_group_apart()
    {
        $user = User::factory()->create();
        $bench = $this->exercise('Bench Press');
        $fly = $this->exercise('Cable Fly');
        Passport::actingAs($user);

        $created = $this->postJson('/api/programs', $this->payload([
            ['exercise_id' => $bench->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
            ['exercise_id' => $fly->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
        ]))->assertStatus(201);

        $programId = $created->json('data.id');
        $dayId = $created->json('data.days.0.id');

        // Ungrouping must clear both columns, not just the type.
        $response = $this->putJson("/api/programs/{$programId}", [
            'name' => 'PPL',
            'days' => [
                [
                    'id' => $dayId,
                    'day_name' => 'Push',
                    'display_order' => 0,
                    'exercises' => [
                        ['exercise_id' => $bench->id],
                        ['exercise_id' => $fly->id],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', null)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', null);
    }

    public function test_cloning_a_public_program_copies_its_groups()
    {
        $author = User::factory()->create();
        $cloner = User::factory()->create();
        $bench = $this->exercise('Bench Press');
        $fly = $this->exercise('Cable Fly');

        Passport::actingAs($author);
        $created = $this->postJson('/api/programs', [
            'name' => 'PPL',
            'is_public' => true,
            'days' => [
                [
                    'day_name' => 'Push',
                    'display_order' => 0,
                    'exercises' => [
                        ['exercise_id' => $bench->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
                        ['exercise_id' => $fly->id, 'group_type' => ExerciseGrouping::SUPERSET, 'group_key' => 1],
                    ],
                ],
            ],
        ])->assertStatus(201);

        Passport::actingAs($cloner);
        $response = $this->postJson("/api/programs/{$created->json('data.id')}/clone");

        $response->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', 'superset')
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.1.pivot.group_key', 1);
    }
}
