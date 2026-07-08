<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutLogRequest;
use App\Http\Resources\WorkoutLogResource;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Lightweight per-exercise summary for the Active Workout screen: the sets
     * from each exercise's most recent session (for the "Last: …" hint) plus its
     * all-time best estimated 1RM (so PR detection works without pulling the
     * user's full history). Query: ?ids=8,35,...
     */
    public function recentSets(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $this->dataResponse((object) []);
        }

        $userId = $request->user()->id;

        // Most recent workout_log per requested exercise (rows are date-desc, so
        // the first one seen per exercise is its latest session).
        $latestLog = [];
        DB::table('set_logs')
            ->join('workout_logs', 'set_logs.workout_log_id', '=', 'workout_logs.id')
            ->where('workout_logs.user_id', $userId)
            ->whereIn('set_logs.exercise_id', $ids)
            ->orderByDesc('workout_logs.date_timestamp')
            ->get(['set_logs.exercise_id', 'set_logs.workout_log_id'])
            ->each(function ($row) use (&$latestLog) {
                $latestLog[$row->exercise_id] ??= $row->workout_log_id;
            });

        // All-time best Epley 1RM per exercise over working sets.
        $bestE1rm = DB::table('set_logs')
            ->join('workout_logs', 'set_logs.workout_log_id', '=', 'workout_logs.id')
            ->where('workout_logs.user_id', $userId)
            ->whereIn('set_logs.exercise_id', $ids)
            ->where('set_logs.set_type', '!=', 'warmup')
            ->where('set_logs.weight', '>', 0)
            ->where('set_logs.reps', '>', 0)
            ->groupBy('set_logs.exercise_id')
            ->selectRaw('set_logs.exercise_id, MAX(CASE WHEN set_logs.reps = 1 THEN set_logs.weight ELSE set_logs.weight * (1 + set_logs.reps / 30.0) END) as best')
            ->pluck('best', 'set_logs.exercise_id');

        $data = [];
        foreach ($ids as $exId) {
            $sets = [];
            if (isset($latestLog[$exId])) {
                $sets = SetLog::where('workout_log_id', $latestLog[$exId])
                    ->where('exercise_id', $exId)
                    ->orderBy('set_order')
                    ->get()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'weight' => (float) $s->weight,
                        'reps' => (int) $s->reps,
                        'rpe' => $s->rpe,
                        'set_type' => $s->set_type,
                        'set_order' => $s->set_order,
                    ]);
            }
            $data[$exId] = [
                'last' => $sets,
                'best_e1rm' => (float) ($bestE1rm[$exId] ?? 0),
            ];
        }

        return $this->dataResponse($data);
    }

    /**
     * Paginated history for a single exercise (5 sessions per page, newest
     * first), each carrying only that exercise's sets. Backs the history modal's
     * load-more-on-scroll.
     */
    public function exerciseLogs(Request $request, Exercise $exercise)
    {
        $logs = $request->user()->workoutLogs()
            ->whereHas('sets', fn ($q) => $q->where('exercise_id', $exercise->id))
            ->with(['sets' => fn ($q) => $q->where('exercise_id', $exercise->id)->orderBy('set_order')])
            ->orderByDesc('date_timestamp')
            ->paginate(5);

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
