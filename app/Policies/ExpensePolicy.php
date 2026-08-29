<?php

namespace App\Policies;

/**
 * Expenses are management-only end to end.
 */
class ExpensePolicy extends BasePolicy
{
    // Inherits the management-only defaults from BasePolicy.
}
