<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exercise;
use App\Http\Resources\ExerciseResource;
use App\Http\Requests\StoreExerciseRequest;

class ExerciseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Exercise::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('target_muscle_group', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy('name', 'asc');

        return ExerciseResource::collection($query->paginate(30));
    }

    /**
     * Store a newly created resource in storage.
     */
     public function store(StoreExerciseRequest $request)
     {
         $exercise = Exercise::create($request->validated());
 
         return new ExerciseResource($exercise);
     }
 }
