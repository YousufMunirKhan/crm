<?php

namespace App\Policies;

/**
 * Consent records are evidence; read widely, change rarely.
 */
class ContactConsentPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Marketing'];

    protected array $manageRoles = ['Admin', 'System Admin'];
}
