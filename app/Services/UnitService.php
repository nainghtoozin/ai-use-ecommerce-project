<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Validation\Rule;

class UnitService
{
    public function list()
    {
        return Unit::latest()->paginate(10);
    }

    public function search(string $query)
    {
        return Unit::where('name', 'like', "%{$query}%")
            ->orWhere('short_name', 'like', "%{$query}%")
            ->latest()
            ->paginate(10);
    }

    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }

    public function rules(?Unit $unit = null): array
    {
        $tenantId = tenant()?->id;
        return [
            'name' => [
                'required',
                'max:255',
                Rule::unique('units', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($unit?->id),
            ],
            'short_name' => 'required|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
