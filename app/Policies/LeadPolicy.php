<?php

namespace App\Policies;

/**
 * Same audience as customers - the pipeline is their job.
 */
class LeadPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent'];

    protected array $manageRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent'];
}
