<?php

namespace App\Policies;

use App\Policies\Concerns\ShareableFilePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * FilePolicy
 *
 * Applies owner-only permissions to File records via ShareableFilePolicy:
 * - viewAny/create: allowed
 * - view/update/delete/restore/forceDelete/share: owner-only
 *
 * See: App\Policies\Concerns\ShareableFilePolicy
 */
class FilePolicy
{
    use HandlesAuthorization;
    use ShareableFilePolicy;
}
