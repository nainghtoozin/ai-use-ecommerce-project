<?php

namespace App\Auth;

use App\Models\Account;
use App\Models\User;

class CompatibilityBridge
{
    public function userToAccount(User $user): Account
    {
        $account = new Account;
        $account->id = $user->id;
        $account->email = $user->email;
        $account->password = $user->password;
        $account->email_verified_at = $user->email_verified_at;
        $account->remember_token = $user->remember_token;
        $account->profile_image = $user->profile_image;
        $account->status = $user->status ?? 'active';
        $account->notification_preferences = $user->notification_preferences;
        $account->created_at = $user->created_at;
        $account->updated_at = $user->updated_at;

        return $account;
    }

    public function accountToUser(Account $account): User
    {
        $user = new User;
        $user->id = $account->id;
        $user->email = $account->email;
        $user->password = $account->password;
        $user->email_verified_at = $account->email_verified_at;
        $user->remember_token = $account->remember_token;
        $user->profile_image = $account->profile_image;
        $user->status = $account->status;
        $user->notification_preferences = $account->notification_preferences;
        $user->created_at = $account->created_at;
        $user->updated_at = $account->updated_at;

        return $user;
    }

    public function isCompatible(User $user, Account $account): bool
    {
        return $user->id === $account->id
            && $user->email === $account->email;
    }

    public function mapUserToAccountAttrs(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'password' => $user->password,
            'email_verified_at' => $user->email_verified_at,
            'remember_token' => $user->remember_token,
            'profile_image' => $user->profile_image,
            'status' => $user->status ?? 'active',
            'notification_preferences' => $user->notification_preferences,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
