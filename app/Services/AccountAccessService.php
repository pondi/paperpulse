<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\DatabaseManager;

class AccountAccessService
{
    public function __construct(private DatabaseManager $database) {}

    public function revoke(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            $this->database->connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }
    }
}
