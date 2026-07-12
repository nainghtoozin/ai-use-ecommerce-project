<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait HasUser
{
    public static function bootHasUser(): void
    {
        static::creating(function (Model $model) {
            if ($model->isDirty('user_id') && $model->user_id && !$model->user_type) {
                $auth = auth()->user();
                $model->user_type = $auth?->getMorphClass() ?? (new User)->getMorphClass();
            }
        });
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForUser($query, $userOrId, ?string $userType = null)
    {
        if ($userOrId instanceof Model) {
            return $query->where('user_id', $userOrId->getKey())
                ->where('user_type', $userOrId->getMorphClass());
        }
        $query->where('user_id', $userOrId);
        if ($userType) {
            $query->where('user_type', $userType);
        }
        return $query;
    }
}
