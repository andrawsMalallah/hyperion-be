<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Auth\Access\Response;

class WorkoutLogPolicy
{
    /**
     * Only the owner may view a workout log.
     */
    public function view(User $user, WorkoutLog $workoutLog): Response
    {
        return $this->ownedBy($user, $workoutLog);
    }

    /**
     * Only the owner may edit a workout log.
     */
    public function update(User $user, WorkoutLog $workoutLog): Response
    {
        return $this->ownedBy($user, $workoutLog);
    }

    /**
     * Only the owner may delete a workout log.
     */
    public function delete(User $user, WorkoutLog $workoutLog): Response
    {
        return $this->ownedBy($user, $workoutLog);
    }

    /**
     * Shared ownership gate. A log the user doesn't own is hidden as a 404
     * (denyAsNotFound) rather than a 403 so a non-owner can't tell whether the
     * id exists. The message matches the framework's NotFoundHttpException
     * handler in bootstrap/app.php so the API response is identical either way.
     */
    private function ownedBy(User $user, WorkoutLog $workoutLog): Response
    {
        return $user->id === $workoutLog->user_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested item was not found.');
    }
}
