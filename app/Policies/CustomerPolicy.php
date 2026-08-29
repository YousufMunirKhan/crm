<?php

namespace App\Policies;

/**
 * Field staff work customers daily; only owners may delete.
 */
class CustomerPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent', 'Support'];

    protected array $manageRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent'];
}
