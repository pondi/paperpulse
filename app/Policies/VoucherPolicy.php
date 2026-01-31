<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedResourcePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * VoucherPolicy
 *
 * Vouchers are extracted entities belonging to a user.
 * Access is determined by ownership only.
 */
class VoucherPolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
