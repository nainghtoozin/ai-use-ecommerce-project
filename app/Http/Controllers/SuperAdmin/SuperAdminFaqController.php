<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlatformFaqRequest;
use App\Models\PlatformFaq;
use App\Services\FaqService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminFaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqService
    ) {}

    public function index(Request $request)
    {
        $faqs = $this->faqService->list(
            search: $request->get('search'),
            category: $request->get('category'),
            isActive: $request->has('status') ? ($request->get('status') === 'active' ? true : ($request->get('status') === 'inactive' ? false : null)) : null,
        );

        return Inertia::render('SuperAdmin/Faqs/Index', [
            'faqs' => $faqs,
            'categories' => $this->faqService->getCategories(),
            'filters' => [
                'search' => $request->get('search'),
                'category' => $request->get('category'),
                'status' => $request->get('status'),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Faqs/Create', [
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    public function store(StorePlatformFaqRequest $request)
    {
        $this->faqService->create($request->validated());

        return redirect()->route('superadmin.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(PlatformFaq $faq)
    {
        return Inertia::render('SuperAdmin/Faqs/Edit', [
            'faq' => $faq,
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    public function update(StorePlatformFaqRequest $request, PlatformFaq $faq)
    {
        $this->faqService->update($faq, $request->validated());

        return redirect()->route('superadmin.faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(PlatformFaq $faq)
    {
        $this->faqService->delete($faq);

        return redirect()->route('superadmin.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }

    public function toggle(PlatformFaq $faq)
    {
        $this->faqService->toggleActive($faq);

        return redirect()->route('superadmin.faqs.index')
            ->with('success', 'FAQ status updated.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:platform_faqs,id',
        ]);

        $this->faqService->reorder($request->input('ids'));

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:platform_faqs,id',
            'action' => 'required|in:delete,enable,disable',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        match ($action) {
            'delete' => $this->faqService->bulkDelete($ids),
            'enable' => $this->faqService->bulkSetActive($ids, true),
            'disable' => $this->faqService->bulkSetActive($ids, false),
        };

        return redirect()->route('superadmin.faqs.index')
            ->with('success', "Bulk action '{$action}' completed.");
    }
}
