<?php

namespace App\Models\Scopes;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private array $exemptModels = [
        ActivityLog::class,
    ];

    public function apply(Builder $builder, Model $model): void
    {
        if (in_array(get_class($model), $this->exemptModels)) {
            return;
        }

        if (app()->runningInConsole() && $this->isMigrationOrSeed()) {
            return;
        }

        $tenant = Tenant::getCurrent();
        if ($tenant) {
            $builder->where($model->getTable() . '.tenant_id', $tenant->id);

            if ($this->modelAllowsNullTenantFallback($model)) {
                $builder->orWhereNull($model->getTable() . '.tenant_id');
            }
        }
    }

    protected function modelAllowsNullTenantFallback(Model $model): bool
    {
        $uses = class_uses_recursive($model);

        if (! in_array(TenantAware::class, $uses)) {
            return false;
        }

        return $model::allowsNullTenantFallback();
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }

    protected function isMigrationOrSeed(): bool
    {
        if (! isset($_SERVER['argv'])) {
            return false;
        }

        $command = $_SERVER['argv'][1] ?? 'artisan';

        $exempt = [
            'db:seed',
            'db:wipe',
            'migrate',
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'migrate:rollback',
            'migrate:status',
            'migrate:install',
        ];

        foreach ($exempt as $pattern) {
            if (str_starts_with($command, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
