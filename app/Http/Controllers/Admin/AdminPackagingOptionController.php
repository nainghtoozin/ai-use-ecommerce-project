<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackagingOptionRequest;
use App\Http\Requests\UpdatePackagingOptionRequest;
use App\Models\PackagingOption;
use App\Services\ActivityLogger;
use App\Services\PackagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AdminPackagingOptionController extends Controller
{
    public function __construct(
        private PackagingService $packagingService
    ) {}

    public function index(): \Inertia\Response
    {
        if (!auth()->user()->can('packaging-options.view')) {
            abort(403, 'Unauthorized');
        }

        $packagingOptions = $this->packagingService->list(10);

        return Inertia::render('Admin/PackagingOptions/Index', [
            'packagingOptions' => $packagingOptions,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('packaging-options.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PackagingOptions/Create');
    }

    public function store(StorePackagingOptionRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('packaging-options.create')) {
            abort(403, 'Unauthorized');
        }

        $packagingOption = $this->packagingService->create($request->validated());

        ActivityLogger::log("Packaging option '{$packagingOption->name}' created", 'packaging_option_created', $packagingOption);

        return admin_redirect('admin.packaging-options.index')
            ->with('success', 'Packaging option created successfully.');
    }

    public function edit(PackagingOption $packagingOption): \Inertia\Response
    {
        if (!auth()->user()->can('packaging-options.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PackagingOptions/Edit', [
            'packagingOption' => $packagingOption,
        ]);
    }

    public function update(UpdatePackagingOptionRequest $request, PackagingOption $packagingOption): RedirectResponse
    {
        if (!auth()->user()->can('packaging-options.update')) {
            abort(403, 'Unauthorized');
        }

        $this->packagingService->update($packagingOption, $request->validated());

        ActivityLogger::log("Packaging option '{$packagingOption->name}' updated", 'packaging_option_updated', $packagingOption);

        return admin_redirect('admin.packaging-options.index')
            ->with('success', 'Packaging option updated successfully.');
    }

    public function destroy(PackagingOption $packagingOption): RedirectResponse
    {
        if (!auth()->user()->can('packaging-options.delete')) {
            abort(403, 'Unauthorized');
        }

        $result = $this->packagingService->delete($packagingOption);

        if ($result === null) {
            return admin_redirect('admin.packaging-options.index')
                ->with('warning', 'Packaging option is in use by orders. It has been deactivated instead of deleted.');
        }

        ActivityLogger::log("Packaging option '{$packagingOption->name}' deleted", 'packaging_option_deleted', $packagingOption);

        return admin_redirect('admin.packaging-options.index')
            ->with('success', 'Packaging option deleted successfully.');
    }

    public function toggle(PackagingOption $packagingOption): JsonResponse
    {
        if (!auth()->user()->can('packaging-options.update')) {
            abort(403, 'Unauthorized');
        }

        $packagingOption = $this->packagingService->toggleActive($packagingOption);

        return response()->json([
            'success' => true,
            'is_active' => $packagingOption->is_active,
            'message' => $packagingOption->is_active ? 'Packaging option activated.' : 'Packaging option deactivated.',
        ]);
    }
}
