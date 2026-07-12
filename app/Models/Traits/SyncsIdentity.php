<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait SyncsIdentity
{
    protected static function bootSyncsIdentity(): void
    {
        static::saved(function (self $model) {
            if (!config('identity.use_accounts')) {
                return;
            }

            if (!$model->email) {
                return;
            }

            $model->syncCounterpartRecord();
        });
    }

    protected function syncCounterpartRecord(): void
    {
        $class = $this->getCounterpartClass();
        $counterpart = $class::where('email', $this->email)->first();

        if ($counterpart) {
            $this->applyCounterpartSync($counterpart);
        } elseif ($this->wasRecentlyCreated) {
            $this->createCounterpartRecord($class);
        }
    }

    protected function applyCounterpartSync(Model $counterpart): void
    {
        $dirty = $this->getDirty();
        $syncable = $this->getSyncableAttributes();
        $fillable = $counterpart->getFillable();
        $updates = [];

        foreach ($dirty as $key => $value) {
            if (in_array($key, $syncable, true) && in_array($key, $fillable, true)) {
                $updates[$key] = $value;
            }
        }

        if (!empty($updates)) {
            $counterpart->updateQuietly($updates);
        }
    }

    protected function createCounterpartRecord(string $class): void
    {
        $counterpart = new $class();
        $syncable = $this->getSyncableAttributes();
        $fillable = $counterpart->getFillable();

        foreach ($syncable as $key) {
            if (in_array($key, $fillable, true) && !is_null($this->$key)) {
                $counterpart->$key = $this->$key;
            }
        }

        $counterpart->saveQuietly();
    }

    abstract protected function getCounterpartClass(): string;

    protected function getSyncableAttributes(): array
    {
        return [
            'name',
            'email',
            'password',
            'email_verified_at',
            'status',
            'remember_token',
            'profile_image',
            'notification_preferences',
        ];
    }
}
