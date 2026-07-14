<?php

namespace App\Http\Controllers;

use App\Services\ProgressStats;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * Aggregated stats for the Progress page (e1RM trends, weekly volume,
     * recent PRs, this-week tiles) — computed server-side so the client fetches
     * a few KB instead of paging the full workout history.
     */
    public function __invoke(Request $request, ProgressStats $stats)
    {
        return $this->dataResponse($stats->for($request->user()));
    }
}
