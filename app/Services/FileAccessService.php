<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FileAccessService
{
    public function allows(User $user, Model $model, string $permission = 'view'): bool
    {
        $fileId = $model instanceof File ? $model->getKey() : $model->getAttribute('file_id');

        if (! $fileId) {
            return $model->getAttribute('user_id') === $user->id;
        }

        return File::query()->accessibleBy($user, $permission)->whereKey($fileId)->exists();
    }
}
