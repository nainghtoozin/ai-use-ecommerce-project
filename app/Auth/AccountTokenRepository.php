<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Carbon;

class AccountTokenRepository extends DatabaseTokenRepository
{
    /**
     * Create a new token record using account_id instead of email.
     */
    public function create(CanResetPassword $user): string
    {
        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert([
            'account_id' => $user->getAuthIdentifier(),
            'token' => $this->hasher->make($token),
            'created_at' => new Carbon,
        ]);

        return $token;
    }

    /**
     * Delete all existing reset tokens for the user.
     */
    protected function deleteExisting(CanResetPassword $user): int
    {
        return $this->getTable()
            ->where('account_id', $user->getAuthIdentifier())
            ->delete();
    }

    /**
     * Determine if a token record exists and is valid.
     */
    public function exists(CanResetPassword $user, $token): bool
    {
        $record = (array) $this->getTable()
            ->where('account_id', $user->getAuthIdentifier())
            ->first();

        return $record &&
               !$this->tokenExpired($record['created_at']) &&
                $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the given user recently created a password reset token.
     */
    public function recentlyCreatedToken(CanResetPassword $user): bool
    {
        $record = (array) $this->getTable()
            ->where('account_id', $user->getAuthIdentifier())
            ->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * Delete expired tokens.
     */
    public function deleteExpired(): int
    {
        return $this->getTable()
            ->where('created_at', '<', Carbon::now()->subSeconds($this->expires))
            ->delete();
    }
}
