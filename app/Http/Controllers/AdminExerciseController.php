<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Notifications\ExerciseApproved;
use App\Notifications\ExerciseRejected;
use Illuminate\Http\Request;

class AdminExerciseController extends Controller
{
    /**
     * All exercises for the admin dashboard, newest first, with optional
     * filters: status, target_muscle_group, name search, and contributor.
     */
    public function index(Request $request)
    {
        $query = Exercise::query()->with('contributor:id,name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($muscleGroup = $request->query('target_muscle_group')) {
            $query->where('target_muscle_group', $muscleGroup);
        }

        if ($search = $request->query('search')) {
            $query->whereLike('name', '%'.$search.'%');
        }

        if ($createdBy = $request->query('created_by')) {
            $query->where('created_by', $createdBy);
        }

        // id is a deterministic tiebreaker: many seeded rows share the same
        // created_at, and without it LIMIT/OFFSET pagination is unstable and can
        // repeat/skip rows across pages.
        $query->orderByDesc('created_at')->orderByDesc('id');

        return ExerciseResource::collection($query->paginate(30)->withQueryString());
    }

    /**
     * The pending-review queue: contributions awaiting a decision, oldest first
     * so the longest-waiting submission is reviewed first.
     */
    public function pending(Request $request)
    {
        $query = Exercise::query()
            ->with('contributor:id,name')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        return ExerciseResource::collection($query->paginate(30));
    }

    /**
     * Approve a pending contribution and notify its contributor.
     */
    public function approve(Request $request, Exercise $exercise)
    {
        $exercise->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        // De-identified/seeded rows have no contributor to notify.
        $exercise->contributor?->notify(new ExerciseApproved($exercise));

        return new ExerciseResource($exercise->load('contributor:id,name'));
    }

    /**
     * Reject a pending contribution (with an optional reason) and notify its
     * contributor.
     */
    public function reject(RejectExerciseRequest $request, Exercise $exercise)
    {
        $exercise->update([
            'status' => 'rejected',
            'rejection_reason' => $request->validated('reason'),
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        $exercise->contributor?->notify(new ExerciseRejected($exercise));

        return new ExerciseResource($exercise->load('contributor:id,name'));
    }
}
