<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ProgressStats;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * Aggregated stats for the Progress page (weekly volume, recent PRs,
     * this-week tiles, exercise options) plus the e1RM series for the first
     * (most-logged) exercise only — computed server-side so the client fetches
     * a few KB. Other exercises' series load on demand via exerciseSeries().
     */
    public function stats(Request $request, ProgressStats $stats)
    {
        return $this->dataResponse($stats->for($request->user()));
    }

    /**
     * The e1RM-per-session series for one exercise — fetched when the user
     * picks it in the Progress dropdown (the page ships only the first
     * exercise's series upfront). The series reflects only the caller's own
     * logs (the query is user-scoped).
     */
    public function exerciseSeries(Request $request, Exercise $exercise, ProgressStats $stats)
    {
        return $this->dataResponse($stats->e1rmSeriesFor($request->user(), $exercise->id));
    }
}
