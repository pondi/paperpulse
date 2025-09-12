<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Concerns\ShareableFilePolicy;

/**
 * ReceiptPolicy
 *
 * Delegates to ShareableFilePolicy for shared-file permissions:
 * - view: owner or active share (permission >= view)
 * - update: owner or active share with edit permission
 * - delete/share/restore/forceDelete: owner-only
 * - download: allowed if view is allowed
 *
 * See: App\Policies\Concerns\ShareableFilePolicy
 */
class ReceiptPolicy
{
    use HandlesAuthorization;
    use ShareableFilePolicy;
}
