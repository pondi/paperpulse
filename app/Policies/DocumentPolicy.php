<?php

namespace App\Policies;

use App\Policies\Concerns\ShareableFilePolicy;

/**
 * DocumentPolicy
 *
 * Delegates to ShareableFilePolicy for shared-file semantics:
 * - view: owner or active share (permission >= view)
 * - update: owner or active share with edit permission
 * - delete/share: owner-only
 * - download: allowed if view is allowed
 *
 * See: App\Policies\Concerns\ShareableFilePolicy
 */
class DocumentPolicy
{
    use ShareableFilePolicy;
}
