<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed on the API, keyed by user id (falling back to
    | IP for guests). Defaults to 60. Production must never override it.
    |
    | It's configurable only so the E2E suite can raise it, for the same reason
    | as auth.rate_limit: the whole Playwright run drives the API through ONE
    | minted account, so every request shares a single 60/min budget and the run
    | trips the limit as specs are added — failing tests for a reason that has
    | nothing to do with the code under test. Lives here rather than as an env()
    | call in the RateLimiter closure because env() returns null once config is
    | cached.
    |
    */

    'rate_limit' => (int) env('API_RATE_LIMIT', 60),

];
