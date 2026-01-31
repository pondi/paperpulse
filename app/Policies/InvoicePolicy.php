<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedResourcePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * InvoicePolicy
 *
 * Invoices are extracted entities belonging to a user.
 * Access is determined by ownership only.
 */
class InvoicePolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
