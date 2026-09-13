<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vorgang;
use App\Support\Permissions;

/**
 * Außendienst users see only their own cases. This is enforced here as well as
 * in the panel query scope - the scope hides other cases from lists, the policy
 * stops a guessed URL.
 */
class VorgangPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permissions::VORGANG_VIEW_ANY,
            Permissions::VORGANG_VIEW_ASSIGNED,
        ]);
    }

    public function view(User $user, Vorgang $vorgang): bool
    {
        if ($user->can(Permissions::VORGANG_VIEW_ANY)) {
            return true;
        }

        return $user->can(Permissions::VORGANG_VIEW_ASSIGNED)
            && $vorgang->assigned_to_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        // Vorgänge are created by the Kobo pipeline, never by hand.
        return false;
    }

    public function update(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::VORGANG_UPDATE) && $this->view($user, $vorgang);
    }

    public function delete(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::VORGANG_DELETE);
    }

    public function restore(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::VORGANG_DELETE);
    }

    public function forceDelete(User $user, Vorgang $vorgang): bool
    {
        return false;
    }

    public function assign(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::VORGANG_ASSIGN);
    }

    public function addNote(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::VORGANG_NOTE_CREATE) && $this->view($user, $vorgang);
    }

    public function inspect(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::INSPECTION_CREATE) && $this->view($user, $vorgang);
    }

    public function uploadAttachment(User $user, Vorgang $vorgang): bool
    {
        return $user->can(Permissions::ATTACHMENT_UPLOAD) && $this->view($user, $vorgang);
    }
}
