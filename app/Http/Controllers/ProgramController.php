<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProgramRequest;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\WorkoutLog;
use App\Services\ProgramDaySync;
use App\Services\ProgramImporter;
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

    public function store(StoreProgramRequest $request, ProgramDaySync $daySync)
    {
        $validated = $request->validated();

        $program = \DB::transaction(function () use ($request, $validated, $daySync) {
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

        $copy = \DB::transaction(function () use ($request, $program, $daySync) {
            $copy = $request->user()->programs()->create([
                'name' => $program->name,
                'is_public' => false,
                'is_active' => false,
                'source_program_id' => $program->id,
            ]);

            $program->load('days.exercises');

            $daySync->sync($copy, $daySync->toDaysPayload($program));

            return $copy;
        });

        return new ProgramResource($copy->load($this->ownDaysWith()));
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

        \DB::transaction(function () use ($request, $program, $validated, $daySync) {
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

    public function destroy(Request $request, Program $program)
    {
        $this->authorize('delete', $program);

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
