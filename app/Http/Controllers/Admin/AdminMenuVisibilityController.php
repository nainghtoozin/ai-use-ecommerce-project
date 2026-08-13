<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MenuVisibilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminMenuVisibilityController extends Controller
{
    public function edit()
    {
        $visibility = MenuVisibilityService::getVisibility();

        return Inertia::render('Admin/Settings/MenuVisibility', [
            'menuVisibility' => $visibility,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'visibility' => 'required|array',
            'visibility.*' => 'required|boolean',
        ]);

        MenuVisibilityService::saveVisibility($validated['visibility']);

        return back()->with('success', 'Menu visibility updated successfully.');
    }

    public function showAll()
    {
        MenuVisibilityService::saveVisibility(MenuVisibilityService::getAllEnabled());

        return back()->with('success', 'All menu items are now visible.');
    }

    public function resetDefaults()
    {
        MenuVisibilityService::saveVisibility(MenuVisibilityService::getDefaults());

        return back()->with('success', 'Menu visibility reset to recommended defaults.');
    }
}
