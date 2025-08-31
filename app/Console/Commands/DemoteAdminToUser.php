<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DemoteAdminToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:demote-admin {email : The email of the admin to demote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demote an administrator to regular user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        if (! $user->is_admin) {
            $this->info("User {$email} is not an administrator.");

            return 0;
        }

        // Check if this is the last admin
        $adminCount = User::where('is_admin', true)->count();
        if ($adminCount <= 1) {
            $this->error('Cannot demote the last administrator. At least one admin must exist.');

            return 1;
        }

        $user->update(['is_admin' => false]);

        $this->info("User {$email} has been demoted to regular user.");

        return 0;
    }
}
