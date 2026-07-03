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
    public function discover(Request $request)
    {
        $search = $request->query('search');

        $query = Program::with(['user', 'days.exercises']);

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

        return ProgramResource::collection($programs);
    }

    public function index(Request $request)
    {
        $programs = $request->user()->programs()
            ->with('days.exercises')
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

        return new ProgramResource($program->load('days.exercises'));
    }

    public function show(Request $request, Program $program)
    {
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        return new ProgramResource($program->load('days.exercises'));
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

        return new ProgramResource($program->load('days.exercises'));
    }

    public function getByDay(Request $request, $dayId)
    {
        $day = ProgramDay::findOrFail($dayId);
        $program = $day->program;
        if ($request->user()->id !== $program->user_id) {
            abort(403);
        }

        return new ProgramResource($program->load('days.exercises'));
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
