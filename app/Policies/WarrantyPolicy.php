<?php

namespace App\Policies;

use App\Policies\Concerns\ShareableFilePolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * WarrantyPolicy
 *
 * Delegates to ShareableFilePolicy for shared-file permissions:
 * - view: owner or active share (permission >= view)
 * - update: owner or active share with edit permission
 * - delete/share/restore/forceDelete: owner-only
 * - download: allowed if view is allowed
 *
 * See: App\Policies\Concerns\ShareableFilePolicy
 */
class WarrantyPolicy
{
    use HandlesAuthorization;
    use ShareableFilePolicy;
}
