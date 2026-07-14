<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProgramPolicy
{
    /**
     * Only the owner may view a program's full detail.
     */
    public function view(User $user, Program $program): Response
    {
        return $this->ownedBy($user, $program);
    }

    /**
     * Only the owner may edit a program.
     */
    public function update(User $user, Program $program): Response
    {
        return $this->ownedBy($user, $program);
    }

    /**
     * Only the owner may delete a program.
     */
    public function delete(User $user, Program $program): Response
    {
        return $this->ownedBy($user, $program);
    }

    /**
     * Shared ownership gate. A program the user doesn't own is hidden as a 404
     * (denyAsNotFound) rather than a 403 so a non-owner can't tell whether the
     * id exists. The message matches the framework's NotFoundHttpException
     * handler in bootstrap/app.php so the API response is identical either way.
     */
    private function ownedBy(User $user, Program $program): Response
    {
        return $user->id === $program->user_id
            ? Response::allow()
            : Response::denyAsNotFound('The requested item was not found.');
    }
}
