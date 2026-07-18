<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-side aggregates for the Progress page: per-exercise estimated-1RM
 * series, weekly volume, a recent-PR feed, and this-week tiles. Replaces the
 * old client-side computation that downloaded up to 5 pages of full workout
 * logs and crunched them in the browser — here it's a few small aggregate
 * queries returning a few KB.
 */
class ProgressStats
{
    /** Weekly-volume history depth, in weeks (matches the frontend chart). */
    private const VOLUME_WEEKS = 8;

    /** Recent-PR feed length. */
    private const RECENT_PRS = 5;

    /** Epley estimated 1RM as a SQL expression over a set_logs alias `sl`. */
    private const E1RM_EXPR = '(CASE WHEN sl.reps = 1 THEN sl.weight ELSE sl.weight * (1 + sl.reps / 30.0) END)';

    /**
     * How a PR was earned, so the client knows what to render: an estimated 1RM
     * (weighted), a rep count (bodyweight) or a hold time (timed).
     */
    private const PR_E1RM = 'e1rm';

    private const PR_REPS = 'reps';

    private const PR_DURATION = 'duration';

    public function for(User $user): array
    {
        $userId = $user->id;

        $names = $this->exerciseNames($userId);
        $bestSets = $this->bestSetPerSession($userId);
        [$seriesByExercise, $recentPrs] = $this->buildSeriesAndPrs($bestSets, $names);
        $exercises = $this->exerciseOptions($userId);

        // Bodyweight and timed exercises can't earn an e1RM PR, so they get
        // their own tracks (most reps / longest hold) merged into the feed.
        $recentPrs = $this->mergePrs($recentPrs, $this->nonWeightedPrs($userId, $names));

        // Ship only the first (most-logged) exercise's series so the page renders
        // immediately; the rest load on demand via e1rmSeriesFor() when the user
        // selects them.
        $firstId = $exercises[0]['id'] ?? null;
        $initial = $firstId !== null ? [$firstId => $seriesByExercise[$firstId] ?? []] : [];

        return [
            'week' => $this->weekTiles($userId),
            'exercises' => $exercises,
            'weekly_volume' => $this->weeklyVolume($userId),
            'recent_prs' => $recentPrs,
            // Cast to object so it always JSON-encodes as a map keyed by exercise
            // id, never as a positional array.
            'e1rm_by_exercise' => (object) $initial,
        ];
    }

    /**
     * e1RM-per-session series for a single exercise (oldest first) — the
     * lazy-loaded payload when the user picks an exercise the page didn't ship
     * upfront. Scoped to the user's own logs via the query's user_id filter.
     */
    public function e1rmSeriesFor(User $user, int $exerciseId): array
    {
        return $this->bestSetPerSession($user->id, $exerciseId)
            ->map(fn ($row) => $this->pointFromRow($row))
            ->all();
    }

    /**
     * One row per (exercise, session): the working set with the best estimated
     * 1RM that session, ordered oldest-first within each exercise. Uses a window
     * function (Postgres + SQLite ≥3.25) instead of pulling every set.
     *
     * Weighted exercises only — on a bodyweight movement `weight` is *added*
     * load, so a +20kg pull-up would otherwise be scored as a 20kg lift.
     */
    private function bestSetPerSession(int $userId, ?int $exerciseId = null): Collection
    {
        $ranked = DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->where('e.measurement_type', ExerciseMeasurement::WEIGHTED)
            ->where('sl.set_type', '!=', 'warmup')
            ->where('sl.weight', '>', 0)
            ->where('sl.reps', '>', 0)
            ->when($exerciseId !== null, fn ($q) => $q->where('sl.exercise_id', $exerciseId))
            ->select('sl.exercise_id', 'wl.date_timestamp', 'sl.weight', 'sl.reps')
            ->selectRaw(self::E1RM_EXPR.' as e1rm')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY sl.exercise_id, sl.workout_log_id ORDER BY '.self::E1RM_EXPR.' DESC, sl.weight DESC) as rn');

        return DB::query()->fromSub($ranked, 't')
            ->where('rn', 1)
            ->orderBy('exercise_id')
            ->orderBy('date_timestamp')
            ->get();
    }

    /**
     * From the per-session best sets, build the e1RM series per exercise and the
     * recent-PR feed (a session that beat every prior session of that exercise).
     *
     * @return array{0: array<int, array>, 1: array}
     */
    private function buildSeriesAndPrs(Collection $bestSets, array $names): array
    {
        $seriesByExercise = [];
        $prs = [];
        $runningBest = [];

        foreach ($bestSets as $row) {
            $exId = (int) $row->exercise_id;
            $e1rm = (float) $row->e1rm;
            $seriesByExercise[$exId][] = $this->pointFromRow($row);

            // A new all-time best (with prior history) is a PR.
            $prev = $runningBest[$exId] ?? 0;
            if ($prev > 0 && $e1rm > $prev) {
                $prs[] = [
                    'kind' => self::PR_E1RM,
                    'exercise_id' => $exId,
                    'exercise' => $names[$exId] ?? 'Exercise',
                    'date' => $row->date_timestamp,
                    'weight' => (float) $row->weight,
                    'reps' => (int) $row->reps,
                    'e1rm' => $e1rm,
                ];
            }
            if ($e1rm > $prev) {
                $runningBest[$exId] = $e1rm;
            }
        }

        // Most recent PRs first, capped.
        usort($prs, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
        $prs = array_slice($prs, 0, self::RECENT_PRS);

        return [$seriesByExercise, $prs];
    }

    /**
     * PRs for bodyweight and timed exercises: most reps in a set, or the
     * longest single hold.
     *
     * ⚠️ A track is keyed by (exercise, added weight), not by exercise alone.
     * 12 bodyweight pull-ups and 6 at +20kg are separate achievements — scoring
     * them on one track means the first belt-loaded set ends the rep PR forever
     * (you can never beat 12 reps while carrying a plate), and the day you drop
     * the belt you'd "PR" against the loaded number. Timed uses the same key so
     * a loaded carry doesn't compete with an unloaded one.
     *
     * @param  array<int, string>  $names  exercise_id => name
     */
    private function nonWeightedPrs(int $userId, array $names): array
    {
        // Best set per (exercise, added weight, session). An exercise is either
        // rep-based or timed, never both, so COALESCE picks whichever column it
        // actually uses as that set's score.
        $scoreExpr = 'COALESCE(sl.duration_seconds, sl.reps)';

        $ranked = DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->where('e.measurement_type', '!=', ExerciseMeasurement::WEIGHTED)
            ->where('sl.set_type', '!=', 'warmup')
            ->whereRaw("{$scoreExpr} > 0")
            ->select('sl.exercise_id', 'sl.weight', 'sl.reps', 'sl.duration_seconds', 'wl.date_timestamp', 'e.measurement_type')
            ->selectRaw("{$scoreExpr} as score")
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY sl.exercise_id, sl.weight, sl.workout_log_id ORDER BY {$scoreExpr} DESC) as rn");

        $rows = DB::query()->fromSub($ranked, 't')
            ->where('rn', 1)
            ->orderBy('exercise_id')
            ->orderBy('weight')
            ->orderBy('date_timestamp')
            ->get();

        $prs = [];
        $runningBest = [];

        foreach ($rows as $row) {
            $trackKey = $row->exercise_id.':'.$row->weight;
            $score = (float) $row->score;
            $prev = $runningBest[$trackKey] ?? 0;

            if ($prev > 0 && $score > $prev) {
                $prs[] = [
                    'kind' => $row->measurement_type === ExerciseMeasurement::TIMED
                        ? self::PR_DURATION
                        : self::PR_REPS,
                    'exercise_id' => (int) $row->exercise_id,
                    'exercise' => $names[(int) $row->exercise_id] ?? 'Exercise',
                    'date' => $row->date_timestamp,
                    'weight' => (float) $row->weight,
                    'reps' => $row->reps === null ? null : (int) $row->reps,
                    'duration_seconds' => $row->duration_seconds === null ? null : (int) $row->duration_seconds,
                ];
            }

            if ($score > $prev) {
                $runningBest[$trackKey] = $score;
            }
        }

        return $prs;
    }

    /** Newest PRs first across every track, capped to the feed length. */
    private function mergePrs(array ...$lists): array
    {
        $all = array_merge(...$lists);
        usort($all, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($all, 0, self::RECENT_PRS);
    }

    /** A single e1RM series point from a best-set query row. */
    private function pointFromRow(object $row): array
    {
        return [
            'date' => $row->date_timestamp,
            'e1rm' => (float) $row->e1rm,
            'weight' => (float) $row->weight,
            'reps' => (int) $row->reps,
        ];
    }

    /** exercise_id => name, for every exercise the user has logged. */
    private function exerciseNames(int $userId): array
    {
        return DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->pluck('e.name', 'sl.exercise_id')
            ->map(fn ($name) => (string) $name)
            ->toArray();
    }

    /**
     * Exercises present in the user's history, most-logged first — the trend
     * dropdown options. Count is the number of logged sets (matches the old
     * client-side ordering).
     *
     * Weighted only: the dropdown drives the e1RM chart, and picking a plank
     * would select an exercise whose series is always empty.
     */
    private function exerciseOptions(int $userId): array
    {
        return DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->where('e.measurement_type', ExerciseMeasurement::WEIGHTED)
            ->groupBy('sl.exercise_id', 'e.name')
            ->select('sl.exercise_id as id', 'e.name')
            ->selectRaw('COUNT(*) as count')
            ->orderByDesc('count')
            ->orderBy('e.name')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'count' => (int) $r->count,
            ])
            ->all();
    }

    /** Sessions and working-set volume for the current (Monday-start) week. */
    private function weekTiles(int $userId): array
    {
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $sessions = DB::table('workout_logs')
            ->where('user_id', $userId)
            ->where('date_timestamp', '>=', $monday)
            ->count();

        $volume = (float) DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->where('e.measurement_type', ExerciseMeasurement::WEIGHTED)
            ->where('sl.set_type', '!=', 'warmup')
            ->where('wl.date_timestamp', '>=', $monday)
            ->sum(DB::raw('sl.weight * sl.reps'));

        return ['sessions' => $sessions, 'volume' => $volume];
    }

    /**
     * Working-set volume bucketed by ISO week (Monday start), most recent
     * VOLUME_WEEKS entries, oldest first. Bucketing is done in PHP (from a
     * per-session volume query) so no Postgres/SQLite-specific week function is
     * needed — and it matches the frontend's week math exactly.
     */
    private function weeklyVolume(int $userId): array
    {
        $sessions = DB::table('set_logs as sl')
            ->join('workout_logs as wl', 'sl.workout_log_id', '=', 'wl.id')
            ->join('exercises as e', 'e.id', '=', 'sl.exercise_id')
            ->where('wl.user_id', $userId)
            ->where('e.measurement_type', ExerciseMeasurement::WEIGHTED)
            ->where('sl.set_type', '!=', 'warmup')
            ->groupBy('wl.id', 'wl.date_timestamp')
            ->select('wl.date_timestamp')
            ->selectRaw('SUM(sl.weight * sl.reps) as volume')
            ->get();

        $byWeek = [];
        foreach ($sessions as $s) {
            $weekStart = Carbon::parse($s->date_timestamp)->startOfWeek(Carbon::MONDAY)->toDateString();
            $byWeek[$weekStart] = ($byWeek[$weekStart] ?? 0) + (float) $s->volume;
        }

        ksort($byWeek); // 'YYYY-MM-DD' sorts chronologically

        return collect($byWeek)
            ->map(fn ($volume, $weekStart) => ['week_start' => $weekStart, 'volume' => $volume])
            ->values()
            ->slice(-self::VOLUME_WEEKS)
            ->values()
            ->all();
    }
}
