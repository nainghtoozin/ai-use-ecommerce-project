<?php

namespace App\Listeners;

use App\Models\Account;
use Illuminate\Auth\Events\Login;

class UpdateAccountLastLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof Account) {
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ]);
        }
    }
}
