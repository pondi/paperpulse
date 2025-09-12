<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Concerns\OwnedResourcePolicy;

/**
 * FilePolicy
 *
 * Applies owner-only permissions to File records via OwnedResourcePolicy:
 * - viewAny/create: allowed
 * - view/update/delete/restore/forceDelete/share: owner-only
 *
 * See: App\Policies\Concerns\OwnedResourcePolicy
 */
class FilePolicy
{
    use HandlesAuthorization;
    use OwnedResourcePolicy;
}
