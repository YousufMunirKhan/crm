<?php

namespace App\Policies;

/**
 * Invoices are financial records: never hard-deletable below owner level.
 */
class InvoicePolicy extends BasePolicy
{
    protected array $viewRoles = ['Admin', 'System Admin', 'Manager', 'Sales'];
}
