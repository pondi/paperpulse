<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedResourcePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ContractPolicy
 *
 * Contracts are extracted entities belonging to a user.
 * Access is determined by ownership only.
 */
class ContractPolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
