<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;
use App\Support\Permissions;

class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permissions::VORGANG_VIEW_ANY,
            Permissions::VORGANG_VIEW_ASSIGNED,
        ]);
    }

    /** Visibility follows the Vorgang the inspection belongs to. */
    public function view(User $user, Inspection $inspection): bool
    {
        return $inspection->vorgang !== null
            && $user->can('view', $inspection->vorgang);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::INSPECTION_CREATE);
    }

    public function update(User $user, Inspection $inspection): bool
    {
        if (! $user->can(Permissions::INSPECTION_UPDATE) || ! $this->view($user, $inspection)) {
            return false;
        }

        // A submitted result is the record of what was found on site; only the
        // office may correct it afterwards.
        if ($inspection->isCompleted()) {
            return $user->can(Permissions::VORGANG_UPDATE);
        }

        return true;
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        return $user->can(Permissions::VORGANG_DELETE);
    }
}
