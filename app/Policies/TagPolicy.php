<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Concerns\OwnedResourcePolicy;

/**
 * TagPolicy
 *
 * Uses OwnedResourcePolicy to apply owner-only access rules to user tags:
 * - viewAny/create: allowed
 * - view/update/delete/restore/forceDelete/share: owner-only (user_id matches)
 *
 * See: App\Policies\Concerns\OwnedResourcePolicy
 */
class TagPolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
