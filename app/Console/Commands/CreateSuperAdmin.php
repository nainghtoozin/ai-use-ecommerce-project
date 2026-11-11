<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class CreateSuperAdmin extends Command
{
    protected $signature = 'superadmin:create
        {--name= : SuperAdmin name}
        {--email= : SuperAdmin email}
        {--password= : SuperAdmin password (avoid in shared shells)}
        {--reset-password : Reset the password of an existing SuperAdmin}';

    protected $description = 'Create the initial platform SuperAdmin account (interactive if options omitted)';

    public function handle(): int
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $name = $this->option('name') ?: env('SUPERADMIN_NAME');
        $email = $this->option('email') ?: env('SUPERADMIN_EMAIL');
        $password = $this->option('password') ?: env('SUPERADMIN_PASSWORD');

        if (!$name && $this->input->isInteractive()) {
            $name = $this->ask('SuperAdmin name', 'Super Admin');
        }
        if (!$email && $this->input->isInteractive()) {
            $email = $this->ask('SuperAdmin email');
        }
        if (!$password && $this->input->isInteractive()) {
            $password = $this->secret('SuperAdmin password');
            $confirm = $this->secret('Confirm password');
            if ($password !== $confirm) {
                $this->error('Passwords do not match.');
                return self::FAILURE;
            }
        }

        $name = $name ?: 'Super Admin';

        if (!$email || !$password) {
            $this->error('SuperAdmin email and password are required (use options or SUPERADMIN_* env vars).');
            return self::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        // Ensure roles/permissions exist (non-interactive to avoid prompting).
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder', '--no-interaction' => true]);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder', '--no-interaction' => true]);

        $superadminRole = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
            ['tenant_id' => null]
        );

        $existingAccount = Account::where('email', $email)->first();
        $resetPassword = (bool) $this->option('reset-password');

        if ($existingAccount && !$resetPassword) {
            $account = $existingAccount;
            $account->name = $name;
            $account->status = Account::STATUS_ACTIVE;
            if (!$account->email_verified_at) {
                $account->email_verified_at = now();
            }
            $account->save();
            $this->info("SuperAdmin already exists; password NOT changed (use --reset-password to change it).");
        } else {
            $account = Account::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'status' => Account::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
        }
        if (!$account->hasRole('superadmin')) {
            $account->assignRole($superadminRole);
        }

        // Legacy User record (backward compatibility)
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && !$resetPassword) {
            $user = $existingUser;
            $user->name = $name;
            $user->status = User::STATUS_ACTIVE;
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();
        } else {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
        }
        if (!$user->hasRole('superadmin')) {
            $user->assignRole($superadminRole);
        }

        $this->info("SuperAdmin created/updated: {$email}");

        return self::SUCCESS;
    }
}