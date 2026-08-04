<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteFaqRequest;
use App\Models\WebsiteFaq;
use App\Services\WebsiteFaqService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WebsiteFaqController extends Controller
{
    public function __construct(
        private readonly WebsiteFaqService $faqService
    ) {}

    public function index(Request $request)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $faqs = $this->faqService->list(
            search: $request->get('search'),
            category: $request->get('category'),
            isActive: $request->has('status')
                ? ($request->get('status') === 'active' ? true : ($request->get('status') === 'inactive' ? false : null))
                : null,
        );

        return Inertia::render('Admin/Faqs/Index', [
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
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Faqs/Create', [
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    public function store(StoreWebsiteFaqRequest $request)
    {
        $this->faqService->create($request->validated());

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(WebsiteFaq $faq)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Faqs/Edit', [
            'faq' => $faq,
            'categories' => $this->faqService->getCategories(),
        ]);
    }

    public function update(StoreWebsiteFaqRequest $request, WebsiteFaq $faq)
    {
        $this->faqService->update($faq, $request->validated());

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(WebsiteFaq $faq)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $this->faqService->delete($faq);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }

    public function toggle(WebsiteFaq $faq)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $this->faqService->toggleActive($faq);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ status updated.');
    }

    public function duplicate(WebsiteFaq $faq)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $this->faqService->duplicate($faq);

        return redirect()->route('admin.faqs.index')
            ->with('success', 'FAQ duplicated successfully.');
    }

    public function reorder(Request $request)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:website_faqs,id',
        ]);

        $this->faqService->reorder($request->input('ids'));

        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request)
    {
        if (!auth()->user()->can('settings.website')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:website_faqs,id',
            'action' => 'required|in:delete,enable,disable',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        match ($action) {
            'delete' => $this->faqService->bulkDelete($ids),
            'enable' => $this->faqService->bulkSetActive($ids, true),
            'disable' => $this->faqService->bulkSetActive($ids, false),
        };

        return redirect()->route('admin.faqs.index')
            ->with('success', "Bulk action '{$action}' completed.");
    }
}
