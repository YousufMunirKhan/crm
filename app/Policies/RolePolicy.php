<?php

namespace App\Policies;

/**
 * Permission changes are the most security-relevant mutation here.
 */
class RolePolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin'];

    protected array $manageRoles = ['Admin', 'System Admin'];
}
