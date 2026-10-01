<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SendUnverifiedUserVerification extends Command
{
    protected $signature = 'users:send-verification {--user= : Existing account ID to send a fresh verification link}';

    protected $description = 'Send a verification link to an existing unverified account';

    public function handle(): int
    {
        $user = User::query()->find($this->option('user'));
        if (! $user) {
            $this->error('Select an existing account with --user.');

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info('The account is already verified.');

            return self::SUCCESS;
        }

        $user->sendEmailVerificationNotification();
        $this->info('Verification link sent.');

        return self::SUCCESS;
    }
}
