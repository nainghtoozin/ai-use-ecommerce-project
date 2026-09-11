<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCodRuleRequest;
use App\Http\Requests\UpdateCodRuleRequest;
use App\Models\CodRule;
use App\Services\ActivityLogger;
use App\Services\CodRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AdminCodRuleController extends Controller
{
    public function __construct(
        private CodRuleService $codRuleService
    ) {}

    public function index(): \Inertia\Response
    {
        if (!auth()->user()->can('cod-rules.view')) {
            abort(403, 'Unauthorized');
        }

        $codRules = $this->codRuleService->list(10);

        return Inertia::render('Admin/CodRules/Index', [
            'codRules' => $codRules,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('cod-rules.create')) {
            abort(403, 'Unauthorized');
        }

        $cities = $this->codRuleService->getActiveCities();

        return Inertia::render('Admin/CodRules/Create', [
            'cities' => $cities,
        ]);
    }

    public function store(StoreCodRuleRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('cod-rules.create')) {
            abort(403, 'Unauthorized');
        }

        $codRule = $this->codRuleService->create($request->validated());

        ActivityLogger::log("COD rule '{$codRule->name}' created", 'cod_rule_created', $codRule);

        return admin_redirect('admin.cod-rules.index')
            ->with('success', 'COD rule created successfully.');
    }

    public function edit(CodRule $codRule): \Inertia\Response
    {
        if (!auth()->user()->can('cod-rules.update')) {
            abort(403, 'Unauthorized');
        }

        $codRule->load('allowedCities', 'excludedCities');
        $cities = $this->codRuleService->getActiveCities();

        return Inertia::render('Admin/CodRules/Edit', [
            'codRule' => $codRule,
            'cities' => $cities,
        ]);
    }

    public function update(UpdateCodRuleRequest $request, CodRule $codRule): RedirectResponse
    {
        if (!auth()->user()->can('cod-rules.update')) {
            abort(403, 'Unauthorized');
        }

        $this->codRuleService->update($codRule, $request->validated());

        ActivityLogger::log("COD rule '{$codRule->name}' updated", 'cod_rule_updated', $codRule);

        return admin_redirect('admin.cod-rules.index')
            ->with('success', 'COD rule updated successfully.');
    }

    public function destroy(CodRule $codRule): RedirectResponse
    {
        if (!auth()->user()->can('cod-rules.delete')) {
            abort(403, 'Unauthorized');
        }

        $name = $codRule->name;
        $this->codRuleService->delete($codRule);

        ActivityLogger::log("COD rule '{$name}' deleted", 'cod_rule_deleted', $codRule);

        return admin_redirect('admin.cod-rules.index')
            ->with('success', 'COD rule deleted successfully.');
    }

    public function toggle(CodRule $codRule): JsonResponse
    {
        if (!auth()->user()->can('cod-rules.update')) {
            abort(403, 'Unauthorized');
        }

        $codRule = $this->codRuleService->toggleActive($codRule);

        return response()->json([
            'success' => true,
            'is_active' => $codRule->is_active,
            'message' => $codRule->is_active ? 'COD rule activated.' : 'COD rule deactivated.',
        ]);
    }
}
