<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBodyMetricRequest;
use App\Http\Resources\BodyMetricResource;
use App\Models\BodyMetric;
use Illuminate\Http\Request;

class BodyMetricController extends Controller
{
    /**
     * The user's body-weight entries, oldest first — the order the Progress
     * chart plots, and the list reverses for a newest-first view.
     */
    public function index(Request $request)
    {
        $metrics = $request->user()->bodyMetrics()
            ->orderBy('measured_on')
            ->get();

        return BodyMetricResource::collection($metrics);
    }

    /**
     * Log (or correct) the weight for a date. One entry per day, so a repeat
     * submission for the same date updates it instead of creating a duplicate —
     * which is also why the unique index exists rather than a second row.
     */
    public function store(StoreBodyMetricRequest $request)
    {
        $data = $request->validated();

        $metric = $request->user()->bodyMetrics()->updateOrCreate(
            ['measured_on' => $data['measured_on']],
            ['weight' => $data['weight']],
        );

        return new BodyMetricResource($metric);
    }

    /**
     * Remove one entry. A non-owner is answered with 404 (the app's convention
     * for hiding a resource's existence), so no policy is needed for this one
     * owner-only action.
     */
    public function destroy(Request $request, BodyMetric $bodyMetric)
    {
        abort_unless($bodyMetric->user_id === $request->user()->id, 404);

        $bodyMetric->delete();

        return response()->noContent();
    }
}
