<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutLogRequest;
use App\Http\Requests\UpdateWorkoutLogRequest;
use App\Http\Resources\WorkoutLogResource;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\WorkoutLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            // A real program id, or the literal 'unknown' for sessions whose
            // program was deleted (program_day_id nulled out — "Unknown Day").
            'program_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = $request->user()->workoutLogs()
            ->with(['day.program', 'sets.exercise'])
            ->when(isset($filters['program_id']) && $filters['program_id'] !== '', function ($query) use ($filters) {
                if ($filters['program_id'] === 'unknown') {
                    $query->whereNull('program_day_id');
                } else {
                    // Scope to the program via the day. The base query is already
                    // limited to this user's logs, so another user's program id
                    // simply matches nothing — no cross-user leak.
                    $query->whereHas('day', fn ($day) => $day->where('program_id', (int) $filters['program_id']));
                }
            })
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('date_timestamp', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('date_timestamp', '<=', $filters['to']))
            ->orderByDesc('date_timestamp')
            ->paginate(30)
            ->withQueryString();

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
        // Cap the id count so a caller can't force a huge whereIn plus one
        // per-exercise SetLog query below (each id adds a query in the loop).
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter()
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return $this->dataResponse((object) []);
        }

        $userId = $request->user()->id;

        // Most recent workout_log per requested exercise, via a window function
        // instead of scanning the user's entire set history: rank each exercise's
        // sets by session date and keep the top one. Postgres + SQLite (≥3.25)
        // both support ROW_NUMBER() OVER. Returns at most one row per exercise.
        $ranked = DB::table('set_logs')
            ->join('workout_logs', 'set_logs.workout_log_id', '=', 'workout_logs.id')
            ->where('workout_logs.user_id', $userId)
            ->whereIn('set_logs.exercise_id', $ids)
            ->select('set_logs.exercise_id', 'set_logs.workout_log_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY set_logs.exercise_id ORDER BY workout_logs.date_timestamp DESC, workout_logs.id DESC) as rn');

        $latestLog = [];
        DB::query()->fromSub($ranked, 't')->where('rn', 1)
            ->get(['exercise_id', 'workout_log_id'])
            ->each(function ($row) use (&$latestLog) {
                $latestLog[(int) $row->exercise_id] = (int) $row->workout_log_id;
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

        // Fetch the sets for every latest log in ONE query (was one query per
        // exercise). A shared log may contain other exercises whose latest
        // session is elsewhere, so keep only sets from each exercise's own
        // latest log.
        $setsByExercise = [];
        if (! empty($latestLog)) {
            SetLog::whereIn('workout_log_id', array_values($latestLog))
                ->whereIn('exercise_id', $ids)
                ->orderBy('set_order')
                ->get()
                ->each(function ($s) use (&$setsByExercise, $latestLog) {
                    $exId = (int) $s->exercise_id;
                    if (($latestLog[$exId] ?? null) !== (int) $s->workout_log_id) {
                        return;
                    }
                    $setsByExercise[$exId][] = [
                        'id' => $s->id,
                        'weight' => (float) $s->weight,
                        'reps' => (int) $s->reps,
                        'rpe' => $s->rpe,
                        'set_type' => $s->set_type,
                        'set_order' => $s->set_order,
                    ];
                });
        }

        $data = [];
        foreach ($ids as $exId) {
            $data[$exId] = [
                'last' => $setsByExercise[(int) $exId] ?? [],
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
        if ($existing = $this->existingByClientUuid($request, $data)) {
            return new WorkoutLogResource($existing->load(['day.program', 'sets.exercise']));
        }

        // One transaction for the log and its sets: a failure partway through
        // must roll back the log row too. A half-saved workout would otherwise
        // burn its client_uuid — the offline retry would hit the replay check
        // above, get the partial log back, and dequeue the payload as synced,
        // silently losing the remaining sets.
        try {
            $workout = DB::transaction(function () use ($request, $data) {
                $workout = $request->user()->workoutLogs()->create($data);

                if (! empty($data['sets'])) {
                    $workout->sets()->createMany($data['sets']);
                }

                return $workout;
            });
        } catch (QueryException $e) {
            // A concurrent replay can pass the check above before the winner
            // commits; the loser then trips the (user_id, client_uuid) unique
            // index. Answer with the winner's row — same as the replay check.
            if ($existing = $this->existingByClientUuid($request, $data)) {
                return new WorkoutLogResource($existing->load(['day.program', 'sets.exercise']));
            }

            throw $e;
        }

        return new WorkoutLogResource($workout->load(['day.program', 'sets.exercise']));
    }

    /**
     * The workout this user already stored under the payload's client_uuid,
     * if any — the server-side half of the offline outbox's dedup.
     */
    private function existingByClientUuid(Request $request, array $data): ?WorkoutLog
    {
        if (empty($data['client_uuid'])) {
            return null;
        }

        return $request->user()->workoutLogs()
            ->where('client_uuid', $data['client_uuid'])
            ->first();
    }

    public function show(Request $request, WorkoutLog $workoutLog)
    {
        $this->authorize('view', $workoutLog);

        return new WorkoutLogResource($workoutLog->load(['day.program', 'sets.exercise']));
    }

    public function update(UpdateWorkoutLogRequest $request, WorkoutLog $workoutLog)
    {
        $this->authorize('update', $workoutLog);

        $data = $request->validated();

        DB::transaction(function () use ($workoutLog, $data) {
            // Only touch notes if the caller sent them (a notes-only patch from
            // the post-save summary modal must not wipe the sets, and a sets
            // edit must not clear existing notes).
            if (array_key_exists('notes', $data)) {
                $workoutLog->notes = $data['notes'];
                $workoutLog->save();
            }

            // Full replace: the edit modal always sends the complete set list.
            if (array_key_exists('sets', $data)) {
                $workoutLog->sets()->delete();
                $workoutLog->sets()->createMany($data['sets']);
            }
        });

        return new WorkoutLogResource($workoutLog->load(['day.program', 'sets.exercise']));
    }

    public function destroy(Request $request, WorkoutLog $workoutLog)
    {
        $this->authorize('delete', $workoutLog);
        $workoutLog->delete();

        return response()->noContent();
    }
}
