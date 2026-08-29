<?php

namespace App\Policies;

/**
 * Marketing may edit templates.
 */
class EmailTemplatePolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Marketing'];

    protected array $manageRoles = ['Admin', 'System Admin', 'Marketing'];
}
