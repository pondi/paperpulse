<?php

namespace App\Policies;

use App\Policies\Concerns\ShareableFilePolicy;
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
    use ShareableFilePolicy;
}
