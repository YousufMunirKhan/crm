<?php

namespace App\Policies;

/**
 * Products drive quoting and reporting; sales staff read them.
 */
class ProductPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Sales', 'CallAgent'];
}
