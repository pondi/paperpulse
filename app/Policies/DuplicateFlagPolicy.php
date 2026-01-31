<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedResourcePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * DuplicateFlagPolicy
 *
 * Applies owner-only permissions to DuplicateFlag records via OwnedResourcePolicy:
 * - viewAny/create: allowed
 * - view/update/delete/restore/forceDelete/share: owner-only
 *
 * See: App\Policies\Concerns\OwnedResourcePolicy
 */
class DuplicateFlagPolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
