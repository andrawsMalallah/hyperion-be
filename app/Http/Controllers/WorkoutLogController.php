<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutLogRequest;
use App\Http\Resources\WorkoutLogResource;
use App\Models\WorkoutLog;
use Illuminate\Http\Request;

class WorkoutLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $request->user()->workoutLogs()
            ->with(['day.program', 'sets.exercise'])
            ->orderByDesc('date_timestamp')
            ->paginate(30);

        return WorkoutLogResource::collection($logs);
    }

    public function store(StoreWorkoutLogRequest $request)
    {
        $workout = $request->user()->workoutLogs()->create($request->validated());

        if ($request->has('sets')) {
            foreach ($request->sets as $setData) {
                $workout->sets()->create($setData);
            }
        }

        return new WorkoutLogResource($workout->load(['day.program', 'sets.exercise']));
    }

    public function show(Request $request, WorkoutLog $workoutLog)
    {
        if ($request->user()->id !== $workoutLog->user_id) {
            abort(403);
        }

        return new WorkoutLogResource($workoutLog->load(['day.program', 'sets.exercise']));
    }

    public function destroy(Request $request, WorkoutLog $workoutLog)
    {
        if ($request->user()->id !== $workoutLog->user_id) {
            abort(403);
        }
        $workoutLog->delete();

        return response()->noContent();
    }
}
