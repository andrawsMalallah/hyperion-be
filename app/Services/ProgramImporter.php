<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a validated program file (see ImportProgramRequest) into a new program
 * owned by the importer.
 *
 * The one real problem here is that a program file crosses accounts and
 * instances, where exercise ids are meaningless — so exercises travel by NAME
 * and have to be resolved against the local catalog. That resolution can only
 * ever miss for a hand-edited file: the catalog is approved-only
 * (ExerciseController@index), so a program can only ever have been built from
 * exercises every account can see. A miss is therefore treated as a bad file
 * and fails the whole import rather than being papered over.
 */
class ProgramImporter
{
    public function __construct(private ProgramDaySync $daySync) {}

    /**
     * Create a private, inactive copy of the file's program for $user.
     *
     * @param  array  $data  the validated request payload (envelope + program)
     *
     * @throws ValidationException when the file names exercises not in the catalog
     */
    public function import(User $user, array $data): Program
    {
        $days = $data['program']['days'] ?? [];
        $exerciseIdsByName = $this->resolveExercises($days);

        return DB::transaction(function () use ($user, $data, $days, $exerciseIdsByName) {
            // Imported programs never activate or publish themselves — the file
            // carries no such state and shouldn't silently unseat the user's
            // current program.
            $program = $user->programs()->create([
                'name' => $data['program']['name'],
                'is_active' => false,
                'is_public' => false,
            ]);

            $this->daySync->sync($program, $this->toDaysPayload($days, $exerciseIdsByName));

            return $program;
        });
    }

    /**
     * Map every exercise name in the file to a catalog id, case-insensitively.
     * Names are globally unique (StoreExerciseRequest), so a name identifies at
     * most one row and no tiebreak is needed.
     *
     * @return array<string, int> lowercased name => exercise id
     *
     * @throws ValidationException listing every unresolvable name at once
     */
    private function resolveExercises(array $days): array
    {
        $names = collect($days)
            ->flatMap(fn ($day) => $day['exercises'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower($name))
            ->unique();

        if ($names->isEmpty()) {
            return [];
        }

        // Compared on lower(name) rather than LIKE so that % and _ in a name are
        // literal, and so the match is exact instead of a substring.
        $found = Exercise::query()
            ->where('status', 'approved')
            ->whereIn(DB::raw('lower(name)'), $names->all())
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        $missing = $names->reject(fn ($name) => $found->has($name));

        if ($missing->isNotEmpty()) {
            // Report the names as they appear in the file, not lowercased.
            $original = collect($days)
                ->flatMap(fn ($day) => $day['exercises'] ?? [])
                ->pluck('name')
                ->filter(fn ($name) => $missing->contains(mb_strtolower($name)))
                ->unique()
                ->values();

            throw ValidationException::withMessages([
                'program' => 'This file uses exercises that are not in the catalog: '
                    .$original->implode(', ').'.',
            ]);
        }

        return $found->all();
    }

    /**
     * Rewrite the file's days into the shape ProgramDaySync consumes: exercises
     * keyed by exercise_id, prescription fields passed straight through.
     */
    private function toDaysPayload(array $days, array $exerciseIdsByName): array
    {
        return collect($days)
            ->sortBy(fn ($day, $index) => $day['display_order'] ?? $index)
            ->values()
            ->map(fn ($day, $index) => [
                'day_name' => $day['day_name'],
                'display_order' => $index,
                'exercises' => collect($day['exercises'] ?? [])
                    ->map(fn ($exercise) => [
                        ...$exercise,
                        'exercise_id' => $exerciseIdsByName[mb_strtolower($exercise['name'])],
                    ])
                    ->all(),
            ])
            ->all();
    }
}
