<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    private function canModifyUser(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($model->hasRole('superadmin')) {
            $count = User::role('superadmin')->count();
            return $count > 1;
        }

        if ($model->hasRole('admin')) {
            $count = User::role('admin')->count();
            return $count > 1;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canModifyUser($user, $model);
    }

    public function suspend(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function ban(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function activate(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->canModifyUser($user, $model);
    }
}
