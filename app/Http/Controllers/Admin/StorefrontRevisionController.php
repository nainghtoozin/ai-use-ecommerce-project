<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Storefront;
use App\Models\StorefrontRevision;
use App\Services\StorefrontRevisionService;
use App\Services\StorefrontRevisionComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class StorefrontRevisionController extends Controller
{
    public function __construct(
        private readonly StorefrontRevisionService $revisions,
        private readonly StorefrontRevisionComparisonService $comparison,
    ) {}

    public function publish()
    {
        try {
            $this->revisions->publish($this->storefront());
        } catch (\Throwable $exception) {
            Log::error('Storefront publish failed', [
                'tenant_id' => tenant()?->id,
                'exception' => $exception::class,
            ]);
            throw $exception;
        }
        return back()->with('success', 'Draft storefront published successfully.');
    }

    public function index()
    {
        $storefront = $this->storefront();
        return Inertia::render('Admin/Storefront/Revisions', [
            'revisions' => StorefrontRevision::where('storefront_id', $storefront->id)
                ->orderByDesc('revision_number')->paginate(20)->through(fn ($revision) => [
                    'id' => $revision->id,
                    'revision_number' => $revision->revision_number,
                    'status' => $revision->status,
                    'created_at' => $revision->created_at?->toISOString(),
                    'created_by' => $revision->created_by_id ? ($revision->created_by_type . ' #' . $revision->created_by_id) : 'System',
                    'published_at' => $revision->published_at?->toISOString(),
                    'published_by' => $revision->published_by_id ? ($revision->published_by_type . ' #' . $revision->published_by_id) : null,
                ]),
            'publishedRevisionId' => $storefront->published_revision_id,
            'draftRevisionId' => $storefront->draft_revision_id,
        ]);
    }

    public function compare(Request $request)
    {
        $storefront = $this->storefront();
        $validated = $request->validate(['from' => ['required', 'integer'], 'to' => ['required', 'integer']]);
        $from = StorefrontRevision::where('storefront_id', $storefront->id)->findOrFail($validated['from']);
        $to = StorefrontRevision::where('storefront_id', $storefront->id)->findOrFail($validated['to']);

        return Inertia::render('Admin/Storefront/Compare', [
            'comparison' => $this->comparison->compare($from, $to),
            'revisions' => StorefrontRevision::where('storefront_id', $storefront->id)->orderByDesc('revision_number')->get(['id', 'revision_number', 'status']),
        ]);
    }

    public function restore(StorefrontRevision $revision)
    {
        $storefront = $this->storefront();
        abort_unless((int) $revision->storefront_id === (int) $storefront->id, 404);
        $this->revisions->restoreAsDraft($storefront, $revision);
        return back()->with('success', 'Revision restored as a draft.');
    }

    private function storefront(): Storefront
    {
        abort_unless(tenant() && Schema::hasTable('storefront_revisions'), 404);
        $storefront = Storefront::first();
        abort_unless($storefront && (int) $storefront->tenant_id === (int) tenant()->id, 404);
        return $storefront;
    }
}
