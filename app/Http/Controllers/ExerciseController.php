<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Exercise::query();

        // Approved catalog plus the requester's own pending contributions.
        $query->where(function ($q) use ($request) {
            $q->where('status', 'approved')
                ->orWhere('created_by', $request->user()->id);
        });

        $operator = \DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if ($search) {
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, '%'.$search.'%')
                    ->orWhere('target_muscle_group', $operator, '%'.$search.'%');
            });
        }

        $query->orderBy('name', 'asc');

        return ExerciseResource::collection($query->paginate(30));
    }

    /**
     * The requester's own contributed exercises across every status
     * (pending/approved/rejected), newest first, with an optional name search.
     * Powers the "My exercises" list on the Contribute page so a contributor
     * can see where each submission stands and any rejection reason.
     */
    public function mine(Request $request)
    {
        $query = Exercise::query()
            ->where('created_by', $request->user()->id);

        // Optional status filter — only the known contribution states.
        if (in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->query('status'));
        }

        if ($search = $request->query('search')) {
            $query->whereLike('name', '%'.$search.'%');
        }

        // id tiebreaker keeps pagination stable when rows share a created_at.
        $query->orderByDesc('created_at')->orderByDesc('id');

        return ExerciseResource::collection($query->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExerciseRequest $request)
    {
        $exercise = Exercise::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return new ExerciseResource($exercise);
    }
}
