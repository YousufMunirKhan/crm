<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared role logic for model policies.
 *
 * The app previously had no policies at all - no app/Policies directory, no
 * Gate calls, and 24 of 43 controllers with no role check whatsoever. Filament
 * calls these automatically for every resource action, so defining them here
 * closes the panel and gives the API a single place to authorise against.
 */
abstract class BasePolicy
{
    /** Roles allowed to read this resource. */
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager'];

    /** Roles allowed to create and update. */
    protected array $manageRoles = ['Admin', 'System Admin', 'Manager'];

    /** Roles allowed to delete. Narrower on purpose. */
    protected array $deleteRoles = ['Admin', 'System Admin'];

    /** Roles allowed to permanently destroy. Narrowest of all. */
    protected array $forceDeleteRoles = ['Admin', 'System Admin'];

    protected function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->isRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, $this->viewRoles);
    }

    public function view(User $user, $record = null): bool
    {
        return $this->hasAnyRole($user, $this->viewRoles);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, $this->manageRoles);
    }

    public function update(User $user, $record = null): bool
    {
        return $this->hasAnyRole($user, $this->manageRoles);
    }

    public function delete(User $user, $record = null): bool
    {
        return $this->hasAnyRole($user, $this->deleteRoles);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasAnyRole($user, $this->deleteRoles);
    }

    public function restore(User $user, $record = null): bool
    {
        return $this->hasAnyRole($user, $this->deleteRoles);
    }

    public function forceDelete(User $user, $record = null): bool
    {
        return $this->hasAnyRole($user, $this->forceDeleteRoles);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasAnyRole($user, $this->forceDeleteRoles);
    }
}
