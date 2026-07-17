<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProgramRequest;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\User;
use App\Services\ProgramDaySync;
use App\Services\ProgramImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('name', '%'.$search.'%')
                    ->orWhereHas('user', function ($qUser) use ($search) {
                        $qUser->whereLike('name', '%'.$search.'%');
                    })
                    ->orWhereHas('days', function ($qDay) use ($search) {
                        $qDay->whereLike('day_name', '%'.$search.'%')
                            ->orWhereHas('exercises', function ($qEx) use ($search) {
                                $qEx->whereLike('name', '%'.$search.'%');
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

    public function store(StoreProgramRequest $request, ProgramDaySync $daySync)
    {
        $validated = $request->validated();

        $program = DB::transaction(function () use ($request, $validated, $daySync) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->update(['is_active' => false]);
            }

            $program = $request->user()->programs()->create($validated);

            if ($request->has('days')) {
                $daySync->sync($program, $request->days);
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
    public function clone(Request $request, Program $program, ProgramDaySync $daySync)
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

        $copy = $this->deepCopy($program, $request->user(), $program->name, $program->id, $daySync);

        return new ProgramResource($copy->load($this->ownDaysWith()));
    }

    /**
     * Duplicate one of the user's OWN programs — a starting point for a
     * variation without rebuilding it by hand. Shares clone()'s copy body but
     * deliberately not its authorization: clone is public-only and rejects your
     * own programs, which is the exact opposite rule.
     *
     * source_program_id stays null. Its only consumer is discover()'s
     * "already saved" flag, which would then mark the user's own public program
     * as saved-from-Discover — a duplicate is not that.
     */
    public function duplicate(Request $request, Program $program, ProgramDaySync $daySync)
    {
        $this->authorize('view', $program);

        $copy = $this->deepCopy($program, $request->user(), $this->copyName($program->name), null, $daySync);

        return (new ProgramResource($copy->load($this->ownDaysWith())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Deep-copy a program into an account as a private, inactive draft: the
     * shared body behind clone() and duplicate(). Days and their full
     * prescription pivots come along (via ProgramDaySync, so a new prescription
     * column can't silently skip copies); the source is never modified.
     * Callers own the authorization — the two paths have different rules.
     */
    private function deepCopy(Program $source, User $owner, string $name, ?int $sourceProgramId, ProgramDaySync $daySync): Program
    {
        return DB::transaction(function () use ($source, $owner, $name, $sourceProgramId, $daySync) {
            $copy = $owner->programs()->create([
                'name' => $name,
                'is_public' => false,
                'is_active' => false,
                'source_program_id' => $sourceProgramId,
            ]);

            $source->load('days.exercises');

            $daySync->sync($copy, $daySync->toDaysPayload($source));

            return $copy;
        });
    }

    /**
     * Name for a duplicate: "Full Body" → "Full Body (Copy)". The base is
     * trimmed so the suffix always fits the column's 255 characters, rather than
     * an already-long name failing the copy outright.
     */
    private function copyName(string $name): string
    {
        $suffix = ' (Copy)';

        return mb_substr($name, 0, 255 - mb_strlen($suffix)).$suffix;
    }

    /**
     * Create a program from an uploaded program file. Export is generated
     * client-side, so this is the only half of the round-trip that needs an
     * endpoint. The copy lands private + inactive, like clone().
     */
    public function import(ImportProgramRequest $request, ProgramImporter $importer)
    {
        $program = $importer->import($request->user(), $request->validated());

        return (new ProgramResource($program->load($this->ownDaysWith())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Program $program)
    {
        $this->authorize('view', $program);

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    public function update(UpdateProgramRequest $request, Program $program, ProgramDaySync $daySync)
    {
        $this->authorize('update', $program);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $program, $validated, $daySync) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->programs()->where('id', '!=', $program->id)->update(['is_active' => false]);
            }

            $program->update($validated);

            if ($request->has('days')) {
                $daySync->sync($program, $request->days);
            }
        });

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    public function getByDay(Request $request, $dayId)
    {
        $day = ProgramDay::findOrFail($dayId);
        $program = $day->program;
        $this->authorize('view', $program);

        return new ProgramResource($program->load($this->ownDaysWith()));
    }

    /**
     * Delete a program. Logged workouts are deliberately KEPT: the days cascade
     * away and workout_logs.program_day_id nulls out (nullOnDelete), so the
     * sessions survive as history without a day — History renders them as
     * "Unknown Day". Removing a day in the builder already behaves this way
     * (ProgramDaySync deletes days without touching logs); deleting the whole
     * program must not be the one path that erases training history.
     */
    public function destroy(Request $request, Program $program)
    {
        $this->authorize('delete', $program);

        $program->delete();

        return response()->noContent();
    }
}
