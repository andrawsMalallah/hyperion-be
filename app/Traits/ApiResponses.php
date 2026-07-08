<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    /**
     * A bare `{ message }` JSON response — the app's standard shape for
     * message-only endpoints (logout, password change, reset, etc.).
     */
    protected function messageResponse(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * A `{ data }`-wrapped JSON response for payloads that aren't rendered
     * through an API Resource (e.g. raw models, hand-built maps).
     */
    protected function dataResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}
