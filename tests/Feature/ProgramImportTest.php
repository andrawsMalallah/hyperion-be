<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProgramImportTest extends TestCase
{
    use RefreshDatabase;

    private function exercise(string $name, string $status = 'approved', ?int $createdBy = null): Exercise
    {
        return Exercise::create([
            'name' => $name,
            'target_muscle_group' => 'Chest',
            'mechanics_type' => 'Compound',
            'status' => $status,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * A well-formed file, as the client's export would produce it.
     */
    private function file(array $overrides = []): array
    {
        return [
            'app' => ProgramFile::APP_MARKER,
            'schema_version' => ProgramFile::SCHEMA_VERSION,
            'exported_at' => '2026-07-16T10:00:00.000Z',
            'program' => [
                'name' => 'Imported PPL',
                'days' => [
                    [
                        'day_name' => 'Push',
                        'display_order' => 0,
                        'exercises' => [
                            [
                                'name' => 'Bench Press',
                                'target_muscle_group' => 'Chest',
                                'target_sets' => 4,
                                'rep_range_min' => 6,
                                'rep_range_max' => 8,
                                'target_rpe' => 8,
                                'rest_seconds' => 180,
                                'notes' => 'Pause on the chest.',
                            ],
                        ],
                    ],
                ],
            ],
            ...$overrides,
        ];
    }

    public function test_import_creates_a_private_inactive_program_with_its_prescriptions()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs/import', $this->file());

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Imported PPL')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.days.0.day_name', 'Push')
            ->assertJsonPath('data.days.0.exercises.0.id', $exercise->id)
            ->assertJsonPath('data.days.0.exercises.0.pivot.target_sets', 4)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rep_range_min', 6)
            ->assertJsonPath('data.days.0.exercises.0.pivot.rest_seconds', 180)
            ->assertJsonPath('data.days.0.exercises.0.pivot.notes', 'Pause on the chest.');

        $this->assertDatabaseHas('programs', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'is_active' => false,
            'is_public' => false,
        ]);
    }

    public function test_import_does_not_deactivate_the_users_current_program()
    {
        $user = User::factory()->create();
        $this->exercise('Bench Press');
        $active = $user->programs()->create(['name' => 'Current', 'is_active' => true, 'is_public' => false]);

        Passport::actingAs($user);
        $this->postJson('/api/programs/import', $this->file())->assertStatus(201);

        $this->assertTrue($active->fresh()->is_active);
    }

    public function test_import_resolves_exercise_names_case_insensitively()
    {
        $user = User::factory()->create();
        $exercise = $this->exercise('Bench Press');
        Passport::actingAs($user);

        $file = $this->file();
        $file['program']['days'][0]['exercises'][0]['name'] = 'bENCh pRESS';

        $this->postJson('/api/programs/import', $file)
            ->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.id', $exercise->id);
    }

    public function test_import_fails_and_names_exercises_missing_from_the_catalog()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $response = $this->postJson('/api/programs/import', $this->file());

        $response->assertStatus(422);
        $this->assertStringContainsString('Bench Press', $response->json('errors.program.0'));

        // Nothing partially created.
        $this->assertEquals(0, $user->programs()->count());
    }

    public function test_import_will_not_resolve_a_pending_exercise()
    {
        // The catalog is approved-only, so a file naming someone's pending
        // contribution is treated as unresolvable rather than silently creating it.
        $user = User::factory()->create();
        $this->exercise('Bench Press', 'pending', $user->id);
        Passport::actingAs($user);

        $this->postJson('/api/programs/import', $this->file())->assertStatus(422);
        $this->assertEquals(0, $user->programs()->count());
    }

    public function test_import_rejects_a_file_that_is_not_a_hyperion_program()
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson('/api/programs/import', ['some' => 'other json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['app', 'schema_version', 'program']);
    }

    public function test_import_rejects_an_unknown_schema_version()
    {
        $user = User::factory()->create();
        $this->exercise('Bench Press');
        Passport::actingAs($user);

        $this->postJson('/api/programs/import', $this->file(['schema_version' => 99]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schema_version']);
    }

    public function test_import_enforces_prescription_bounds()
    {
        $user = User::factory()->create();
        $this->exercise('Bench Press');
        Passport::actingAs($user);

        $file = $this->file();
        $file['program']['days'][0]['exercises'][0]['target_rpe'] = 50;

        $this->postJson('/api/programs/import', $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program.days.0.exercises.0.target_rpe']);
    }

    public function test_import_requires_authentication()
    {
        $this->postJson('/api/programs/import', $this->file())->assertStatus(401);
    }

    public function test_import_carries_exercise_groups_through()
    {
        $user = User::factory()->create();
        $bench = $this->exercise('Bench Press');
        $fly = $this->exercise('Cable Fly');
        Passport::actingAs($user);

        $file = $this->file();
        $file['program']['days'][0]['exercises'] = [
            ['name' => 'Bench Press', 'group_type' => 'superset', 'group_key' => 1],
            ['name' => 'Cable Fly', 'group_type' => 'superset', 'group_key' => 1],
        ];

        $this->postJson('/api/programs/import', $file)
            ->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.id', $bench->id)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', 'superset')
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.1.id', $fly->id)
            ->assertJsonPath('data.days.0.exercises.1.pivot.group_key', 1);
    }

    public function test_import_rejects_a_file_whose_group_is_the_wrong_size()
    {
        $user = User::factory()->create();
        $this->exercise('Bench Press');
        Passport::actingAs($user);

        // A hand-edited file: a superset naming only one exercise.
        $file = $this->file();
        $file['program']['days'][0]['exercises'][0]['group_type'] = 'superset';
        $file['program']['days'][0]['exercises'][0]['group_key'] = 1;

        $this->postJson('/api/programs/import', $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program.days.0.exercises.0.group_type']);

        $this->assertEquals(0, $user->programs()->count());
    }

    public function test_import_still_accepts_a_schema_version_1_file()
    {
        // Version 1 predates grouping. Its files stay importable — they simply
        // describe no groups — so nothing already exported is stranded.
        $user = User::factory()->create();
        $this->exercise('Bench Press');
        Passport::actingAs($user);

        $this->postJson('/api/programs/import', $this->file(['schema_version' => 1]))
            ->assertStatus(201)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', null)
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', null);
    }

    /**
     * A verbatim file as the client's exporter emits it (frontend
     * src/utils/programFile.js buildProgramFile). Export is generated there, not
     * here, so this fixture is what stops the two halves of the format drifting
     * apart unnoticed — update it if that builder's output changes.
     */
    public function test_import_accepts_a_file_produced_by_the_client_exporter()
    {
        $user = User::factory()->create();
        $bench = $this->exercise('Bench Press');
        $fly = $this->exercise('Cable Fly');
        $row = $this->exercise('Barbell Row');
        Passport::actingAs($user);

        $exported = <<<'JSON'
        {
          "app": "hyperion",
          "schema_version": 2,
          "exported_at": "2026-07-16T12:53:24.357Z",
          "program": {
            "name": "Push / Pull!",
            "days": [
              {
                "day_name": "Push",
                "display_order": 0,
                "exercises": [
                  {
                    "name": "Bench Press",
                    "target_muscle_group": "Chest",
                    "target_sets": 4,
                    "rep_range_min": 6,
                    "rep_range_max": 8,
                    "target_rpe": 8,
                    "rest_seconds": 180,
                    "group_type": "superset",
                    "group_key": 1
                  },
                  {
                    "name": "Cable Fly",
                    "target_muscle_group": "Chest",
                    "group_type": "superset",
                    "group_key": 1,
                    "rest_seconds": 90
                  },
                  {
                    "name": "Barbell Row",
                    "target_muscle_group": "Back",
                    "group_type": "drop_set",
                    "target_sets": 3
                  }
                ]
              },
              {
                "day_name": "Pull",
                "display_order": 1,
                "exercises": [
                  {
                    "name": "Barbell Row",
                    "target_muscle_group": "Back",
                    "target_sets": 3,
                    "rep_range_min": 8,
                    "rep_range_max": 12,
                    "notes": "Slow eccentric."
                  }
                ]
              }
            ]
          }
        }
        JSON;

        $response = $this->postJson('/api/programs/import', json_decode($exported, true));

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Push / Pull!')
            ->assertJsonPath('data.days.0.day_name', 'Push')
            ->assertJsonPath('data.days.1.day_name', 'Pull')
            // Day order and each day's exercise order survive the round-trip.
            ->assertJsonPath('data.days.0.exercises.0.id', $bench->id)
            ->assertJsonPath('data.days.0.exercises.1.id', $fly->id)
            ->assertJsonPath('data.days.0.exercises.2.id', $row->id)
            ->assertJsonPath('data.days.0.exercises.0.pivot.target_rpe', 8)
            // The superset survives, and the rest that fires after it is the
            // last member's.
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_type', 'superset')
            ->assertJsonPath('data.days.0.exercises.0.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.1.pivot.group_key', 1)
            ->assertJsonPath('data.days.0.exercises.1.pivot.rest_seconds', 90)
            // A tag type carries no group.
            ->assertJsonPath('data.days.0.exercises.2.pivot.group_type', 'drop_set')
            ->assertJsonPath('data.days.0.exercises.2.pivot.group_key', null)
            // The same exercise carries a different prescription on another day.
            ->assertJsonPath('data.days.1.exercises.0.id', $row->id)
            ->assertJsonPath('data.days.1.exercises.0.pivot.target_sets', 3)
            ->assertJsonPath('data.days.1.exercises.0.pivot.group_type', null)
            ->assertJsonPath('data.days.1.exercises.0.pivot.notes', 'Slow eccentric.');
    }
}
