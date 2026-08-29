<?php

namespace App\Policies;

/**
 * Support staff need full ticket access.
 */
class TicketPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Support', 'Sales', 'CallAgent'];

    protected array $manageRoles = ['Admin', 'System Admin', 'Manager', 'Support'];
}
