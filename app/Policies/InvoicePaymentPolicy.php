<?php

namespace App\Policies;

/**
 * Payments alter the ledger, so management only.
 */
class InvoicePaymentPolicy extends BasePolicy
{
    // Inherits the management-only defaults from BasePolicy.
}
