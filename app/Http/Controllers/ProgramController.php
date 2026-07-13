<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\WorkoutLog;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /**
     * Eager-load spec for a user's own program days: their exercises plus the
     * most recent workout date (last_performed_at), used by the Home "Up next"
     * suggestion so the client doesn't have to pull full history for it.
     * Not used by discover() — other users' training activity stays private.
     */
    private function ownDaysWith(): array
    {
        return [
            'days' => fn ($q) => $q->withMax('workoutLogs as last_performed_at', 'date_timestamp'),
            'days.exercises',
        ];
    }

    public function discover(Request $request)
    {
        $search = $request->query('search');

        $query = Program::with(['user', 'days.exercises'])->where('is_public', true);

        $operator = \DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if ($search) {
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, '%'.$search.'%')
                    ->orWhereHas('user', function ($qUser) use ($search, $operator) {
                        $qUser->where('name', $operator, '%'.$search.'%');
                    })
                    ->orWhereHas('days', function ($qDay) use ($search, $operator) {
                        $qDay->where('day_name', $operator, '%'.$search.'%')
                            ->orWhereHas('exercises', function ($qEx) use ($search, $operator) {
                                $qEx->where('name', $operator, '%'.$search.'%');
                            });
                    });
            });
        }

        $programs = $query->latest()->paginate(30);

        // Flag which of these the current user has already saved (cloned) so the
        // Discover UI shows a "Saved" state instead of the save button.
        $savedSourceIds = $request->user()->programs()
            ->whereNotNull('source_program_id')
            ->pluck('source_program_id')
            ->flip();

        $programs->getCollection()->each(function ($program) use ($savedSourceIds) {
            $program->already_saved = $savedSourceIds->has($program->id);
        });

        return ProgramResource::collection($programs);
    }

    public function index(Request $request)
    {
        $programs = $request->user()->programs()
            ->with($this->ownDaysWith())
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return ProgramResource::collection($programs);
    }

    public function store(StoreProgramRequest $request)
    {
        $validated = $request->validated();

        $program = \DB::transaction(function () use ($request, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->update(['is_active' => false]);
            }

            $program = $request->user()->programs()->create($validated);

            if ($request->has('days')) {
                foreach ($request->days as $dayData) {
                    $day = $program->days()->create([
                        'day_name' => $dayData['day_name'],
                        'display_order' => $dayData['display_order'] ?? 0,
                    ]);

                    if (isset($dayData['exercises'])) {
                        $exercises = [];
                        foreach ($dayData['exercises'] as $index => $exerciseData) {
                            $exercises[$exerciseData['exercise_id']] = [
                                'display_order' => $index,
                                'target_sets' => $exerciseData['target_sets'] ?? null,
                                'rep_range_min' => $exerciseData['rep_range_min'] ?? null,
                                'rep_range_max' => $exerciseData['rep_range_max'] ?? null,
                                'target_rpe' => $exerciseData['target_rpe'] ?? null,
                                'rest_seconds' => $exerciseData['rest_seconds'] ?? null,
                                'notes' => $exerciseData['notes'] ?? null,
                            ];
                        }
                        $day->exercises()->sync($exercises);
                    }
                }
            }

            return $program;
        });

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    /**
     * Deep-copy a public program (or one the user already owns) into the
     * requester's account as a private, inactive draft — the "Save to my
     * programs" action from Discover. Days and their full prescription pivots
     * are copied; the source program is never modified.
     */
    public function clone(Request $request, Program $program)
    {
        // Cloning is for saving *other people's* public programs. You already
        // have your own — the button is hidden client-side, and this guards a
        // direct API call.
        if ($request->user()->id === $program->user_id) {
            abort(403, 'You already have this program.');
        }

        // Someone else's private program: don't even confirm it exists.
        if (! $program->is_public) {
            abort(403);
        }

        $copy = \DB::transaction(function () use ($request, $program) {
            $copy = $request->user()->programs()->create([
                'name' => $program->name,
                'is_public' => false,
                'is_active' => false,
                'source_program_id' => $program->id,
            ]);

            $program->load('days.exercises');

            foreach ($program->days as $day) {
                $newDay = $copy->days()->create([
                    'day_name' => $day->day_name,
                    'display_order' => $day->display_order,
                ]);

                $exercises = [];
                foreach ($day->exercises as $exercise) {
                    $exercises[$exercise->id] = [
                        'display_order' => $exercise->pivot->display_order,
                        'target_sets' => $exercise->pivot->target_sets,
                        'rep_range_min' => $exercise->pivot->rep_range_min,
                        'rep_range_max' => $exercise->pivot->rep_range_max,
                        'target_rpe' => $exercise->pivot->target_rpe,
                        'rest_seconds' => $exercise->pivot->rest_seconds,
                        'notes' => $exercise->pivot->notes,
                    ];
                }

                if (! empty($exercises)) {
                    $newDay->exercises()->sync($exercises);
                }
            }

            return $copy;
        });

        return new ProgramResource($copy->load($this->ownDaysWith()));
    }

    public function show(Request $request, Program $program)
    {
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    public function update(UpdateProgramRequest $request, Program $program)
    {
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        $validated = $request->validated();

        \DB::transaction(function () use ($request, $program, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->where('id', '!=', $program->id)->update(['is_active' => false]);
            }

            $program->update($validated);

            if ($request->has('days')) {
                // Get existing day IDs that are being kept
                $keptDayIds = collect($request->days)->pluck('id')->filter()->toArray();

                // Delete days that are no longer in the payload
                $program->days()->whereNotIn('id', $keptDayIds)->delete();

                foreach ($request->days as $dayData) {
                    if (isset($dayData['id']) && $program->days()->where('id', $dayData['id'])->exists()) {
                        $day = $program->days()->find($dayData['id']);
                        $day->update([
                            'day_name' => $dayData['day_name'],
                            'display_order' => $dayData['display_order'] ?? 0,
                        ]);
                    } else {
                        $day = $program->days()->create([
                            'day_name' => $dayData['day_name'],
                            'display_order' => $dayData['display_order'] ?? 0,
                        ]);
                    }

                    if (isset($dayData['exercises'])) {
                        $exercises = [];
                        foreach ($dayData['exercises'] as $index => $exerciseData) {
                            $exercises[$exerciseData['exercise_id']] = [
                                'display_order' => $index,
                                'target_sets' => $exerciseData['target_sets'] ?? null,
                                'rep_range_min' => $exerciseData['rep_range_min'] ?? null,
                                'rep_range_max' => $exerciseData['rep_range_max'] ?? null,
                                'target_rpe' => $exerciseData['target_rpe'] ?? null,
                                'rest_seconds' => $exerciseData['rest_seconds'] ?? null,
                                'notes' => $exerciseData['notes'] ?? null,
                            ];
                        }
                        $day->exercises()->sync($exercises);
                    } else {
                        $day->exercises()->sync([]);
                    }
                }
            }
        });

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    public function getByDay(Request $request, $dayId)
    {
        $day = ProgramDay::findOrFail($dayId);
        $program = $day->program;
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    public function destroy(Request $request, Program $program)
    {
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        \DB::transaction(function () use ($program) {
            $dayIds = $program->days()->pluck('id')->toArray();
            if (! empty($dayIds)) {
                WorkoutLog::whereIn('program_day_id', $dayIds)->delete();
            }
            $program->delete();
        });

        return response()->noContent();
    }
}
