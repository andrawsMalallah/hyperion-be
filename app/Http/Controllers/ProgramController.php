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

        $Program = \DB::transaction(function () use ($request, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->update(['is_active' => false]);
            }

            $Program = $request->user()->programs()->create($validated);

            if ($request->has('days')) {
                foreach ($request->days as $dayData) {
                    $day = $Program->days()->create([
                        'day_name' => $dayData['day_name'],
                        'display_order' => $dayData['display_order'] ?? 0,
                    ]);

                    if (isset($dayData['exercises'])) {
                        $exercises = [];
                        foreach ($dayData['exercises'] as $index => $exerciseData) {
                            $exercises[$exerciseData['exercise_id']] = ['display_order' => $index];
                        }
                        $day->exercises()->sync($exercises);
                    }
                }
            }

            return $Program;
        });

        return new ProgramResource($Program->load('days.exercises'));
    }

    public function show(Request $request, Program $Program)
    {
        if ($request->user()->id !== $Program->user_id) {
            abort(403);
        }

        return new ProgramResource($Program->load('days.exercises'));
    }

    public function update(UpdateProgramRequest $request, Program $Program)
    {
        if ($request->user()->id !== $Program->user_id) {
            abort(403);
        }

        $validated = $request->validated();

        \DB::transaction(function () use ($request, $Program, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->where('id', '!=', $Program->id)->update(['is_active' => false]);
            }

            $Program->update($validated);

            if ($request->has('days')) {
                // Get existing day IDs that are being kept
                $keptDayIds = collect($request->days)->pluck('id')->filter()->toArray();

                // Delete days that are no longer in the payload
                $Program->days()->whereNotIn('id', $keptDayIds)->delete();

                foreach ($request->days as $dayData) {
                    if (isset($dayData['id']) && $Program->days()->where('id', $dayData['id'])->exists()) {
                        $day = $Program->days()->find($dayData['id']);
                        $day->update([
                            'day_name' => $dayData['day_name'],
                            'display_order' => $dayData['display_order'] ?? 0,
                        ]);
                    } else {
                        $day = $Program->days()->create([
                            'day_name' => $dayData['day_name'],
                            'display_order' => $dayData['display_order'] ?? 0,
                        ]);
                    }

                    if (isset($dayData['exercises'])) {
                        $exercises = [];
                        foreach ($dayData['exercises'] as $index => $exerciseData) {
                            $exercises[$exerciseData['exercise_id']] = ['display_order' => $index];
                        }
                        $day->exercises()->sync($exercises);
                    } else {
                        $day->exercises()->sync([]);
                    }
                }
            }
        });

        return new ProgramResource($Program->load('days.exercises'));
    }

    public function getByDay(Request $request, $dayId)
    {
        $day = ProgramDay::findOrFail($dayId);
        $Program = $day->Program;
        if ($request->user()->id !== $Program->user_id) {
            abort(403);
        }

        return new ProgramResource($Program->load('days.exercises'));
    }

    public function destroy(Request $request, Program $Program)
    {
        if ($request->user()->id !== $Program->user_id) {
            abort(403);
        }

        \DB::transaction(function () use ($Program) {
            $dayIds = $Program->days()->pluck('id')->toArray();
            if (! empty($dayIds)) {
                WorkoutLog::whereIn('program_day_id', $dayIds)->delete();
            }
            $Program->delete();
        });

        return response()->noContent();
    }
}
