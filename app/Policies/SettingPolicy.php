<?php

namespace App\Policies;

/**
 * Settings include the SMTP relay - owners only.
 */
class SettingPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin'];

    protected array $manageRoles = ['Admin', 'System Admin'];
}
