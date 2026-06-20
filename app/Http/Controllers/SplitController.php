<?php

namespace App\Http\Controllers;

use App\Models\Split;
use App\Http\Requests\StoreSplitRequest;
use App\Http\Requests\UpdateSplitRequest;
use App\Http\Resources\SplitResource;
use Illuminate\Http\Request;

class SplitController extends Controller
{
    public function discover(Request $request)
    {
        $search = $request->query('search');

        $query = Split::with(['user', 'days.exercises']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('split_name', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($qUser) use ($search) {
                      $qUser->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('days', function ($qDay) use ($search) {
                      $qDay->where('day_name', 'like', '%' . $search . '%')
                           ->orWhereHas('exercises', function ($qEx) use ($search) {
                               $qEx->where('name', 'like', '%' . $search . '%');
                           });
                  });
            });
        }

        $splits = $query->latest()->paginate(30);

        return SplitResource::collection($splits);
    }

    public function index(Request $request)
    {
        $splits = $request->user()->splits()
            ->with('days.exercises')
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        return SplitResource::collection($splits);
    }

    public function store(StoreSplitRequest $request)
    {
        $validated = $request->validated();
        
        $split = \DB::transaction(function () use ($request, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->splits()->update(['is_active' => false]);
            }
            
            $split = $request->user()->splits()->create($validated);

            if ($request->has('days')) {
                foreach ($request->days as $dayData) {
                    $day = $split->days()->create([
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
            return $split;
        });

        return new SplitResource($split->load('days.exercises'));
    }

    public function show(Request $request, Split $split)
    {
        if ($request->user()->id !== $split->user_id) {
            abort(403);
        }
        return new SplitResource($split->load('days.exercises'));
    }

    public function update(UpdateSplitRequest $request, Split $split)
    {
        if ($request->user()->id !== $split->user_id) {
            abort(403);
        }
        
        $validated = $request->validated();

        \DB::transaction(function () use ($request, $split, $validated) {
            if (isset($validated['is_active']) && $validated['is_active'] === true) {
                $request->user()->splits()->where('id', '!=', $split->id)->update(['is_active' => false]);
            }

            $split->update($validated);

            if ($request->has('days')) {
                // Get existing day IDs that are being kept
                $keptDayIds = collect($request->days)->pluck('id')->filter()->toArray();
                
                // Delete days that are no longer in the payload
                $split->days()->whereNotIn('id', $keptDayIds)->delete();

                foreach ($request->days as $dayData) {
                    if (isset($dayData['id']) && $split->days()->where('id', $dayData['id'])->exists()) {
                        $day = $split->days()->find($dayData['id']);
                        $day->update([
                            'day_name' => $dayData['day_name'],
                            'display_order' => $dayData['display_order'] ?? 0,
                        ]);
                    } else {
                        $day = $split->days()->create([
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

        return new SplitResource($split->load('days.exercises'));
    }

    public function getByDay(Request $request, $dayId)
    {
        $day = \App\Models\SplitDay::findOrFail($dayId);
        $split = $day->split;
        if ($request->user()->id !== $split->user_id) {
            abort(403);
        }
        return new SplitResource($split->load('days.exercises'));
    }

    public function destroy(Request $request, Split $split)
    {
        if ($request->user()->id !== $split->user_id) {
            abort(403);
        }
        
        \DB::transaction(function () use ($split) {
            $dayIds = $split->days()->pluck('id')->toArray();
            if (!empty($dayIds)) {
                \App\Models\WorkoutLog::whereIn('split_day_id', $dayIds)->delete();
            }
            $split->delete();
        });

        return response()->noContent();
    }
}
