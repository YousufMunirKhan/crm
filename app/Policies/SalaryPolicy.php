<?php

namespace App\Policies;

/**
 * Payroll. Deliberately narrow.
 */
class SalaryPolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager'];
}
