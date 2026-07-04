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
        $data = $request->validated();

        // Idempotent replay: an offline-queued workout may be uploaded more
        // than once (e.g. the first response never made it back). If we
        // already stored this client_uuid for this user, return the existing
        // record instead of creating a duplicate.
        if (! empty($data['client_uuid'])) {
            $existing = $request->user()->workoutLogs()
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing) {
                return new WorkoutLogResource($existing->load(['day.program', 'sets.exercise']));
            }
        }

        $workout = $request->user()->workoutLogs()->create($data);

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
