<?php

namespace App\Policies;

use App\Policies\Concerns\OwnedResourcePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * CategoryPolicy
 *
 * Uses OwnedResourcePolicy to enforce simple owner-only access rules:
 * - viewAny/create: allowed
 * - view/update/delete/restore/forceDelete/share: only if the authenticated user owns the resource (user_id matches)
 *
 * See: App\Policies\Concerns\OwnedResourcePolicy
 */
class CategoryPolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
