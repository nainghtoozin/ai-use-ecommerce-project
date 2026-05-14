@extends('admin.layouts.admin')

@section('title', 'Promotions')
@section('page-title', 'Promotions & Discounts')

@section('content')
<div x-data="promotionsManager()" x-cloak>
    <div class="max-w-7xl mx-auto">

        {{-- Success Message --}}
        @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 px-4 py-3 rounded-r-lg shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-emerald-600 hover:text-emerald-800">&times;</button>
        </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Promotions</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1">{{ $promotions->total() }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-tags text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            @php
                $activeCount = $promotions->filter(fn($p) => $p->is_active && (!$p->ends_at || now()->lte($p->ends_at)))->count();
                $expiredCount = $promotions->filter(fn($p) => $p->ends_at && now()->gt($p->ends_at))->count();
                $autoCount = $promotions->filter(fn($p) => $p->is_automatic)->count();
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Active</p>
                        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $activeCount }}</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-play text-emerald-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Auto-Apply</p>
                        <p class="text-2xl font-bold text-purple-600 mt-1">{{ $autoCount }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-wand-magic-sparkles text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Expired</p>
                        <p class="text-2xl font-bold text-red-600 mt-1">{{ $expiredCount }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-calendar-xmark text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search & Header --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex-1 w-full sm:max-w-md">
                    <form action="{{ route('admin.promotions.search') }}" method="GET" class="flex gap-2">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" name="query" value="{{ request('query') }}"
                                   class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Search by name, code, or type...">
                        </div>
                        <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                            Search
                        </button>
                        @if(request('query'))
                            <a href="{{ route('admin.promotions.index') }}" class="px-3 py-2.5 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
                <a href="{{ route('admin.promotions.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                    <i class="fa-solid fa-plus"></i>
                    Create Promotion
                </a>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Promotion</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Value</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Usage</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Schedule</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($promotions as $promotion)
                            @php
                                $isExpired = $promotion->ends_at && now()->gt($promotion->ends_at);
                                $usagePercent = $promotion->usage_limit ? min(100, round(($promotion->usage_count / $promotion->usage_limit) * 100)) : 0;
                            @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0 mt-0.5">
                                        {{ substr($promotion->name, 0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate max-w-[200px]">{{ $promotion->name }}</p>
                                        @if($promotion->description)
                                            <p class="text-xs text-gray-500 truncate max-w-[200px] mt-0.5">{{ $promotion->description }}</p>
                                        @endif
                                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                                            @if($promotion->is_automatic)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                                    <i class="fa-solid fa-bolt text-[10px]"></i> Auto
                                                </span>
                                            @endif
                                            @if($promotion->stackable)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                    <i class="fa-solid fa-layer-group text-[10px]"></i> Stackable
                                                </span>
                                            @endif
                                            @if($promotion->priority > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                    P{{ $promotion->priority }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @if($promotion->code)
                                    <div class="flex items-center gap-1.5">
                                        <code class="px-2 py-1 bg-gray-100 rounded text-xs font-mono font-semibold text-gray-800">{{ $promotion->code }}</code>
                                        <button onclick="navigator.clipboard.writeText('{{ $promotion->code }}')"
                                                class="text-gray-400 hover:text-blue-600 transition-colors" title="Copy code">
                                            <i class="fa-regular fa-copy text-xs"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">No code</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    @if($promotion->type === 'percentage')
                                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">%</span>
                                        <span class="text-sm font-semibold text-gray-800">{{ $promotion->value }}%</span>
                                        @if($promotion->max_discount_amount)
                                            <span class="text-xs text-gray-400">(cap: {{ number_format($promotion->max_discount_amount, 2) }})</span>
                                        @endif
                                    @elseif($promotion->type === 'fixed')
                                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">$</span>
                                        <span class="text-sm font-semibold text-gray-800">{{ number_format($promotion->value, 2) }}</span>
                                    @else
                                        <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold">
                                            <i class="fa-solid fa-truck text-[10px]"></i>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">Free Shipping</span>
                                    @endif
                                </div>
                                @if($promotion->minimum_order_amount)
                                    <p class="text-xs text-gray-400 mt-1">Min: {{ number_format($promotion->minimum_order_amount, 2) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-medium text-gray-800">{{ $promotion->usage_count }}</span>
                                    @if($promotion->usage_limit)
                                        <span class="text-sm text-gray-400">/ {{ $promotion->usage_limit }}</span>
                                    @endif
                                </div>
                                @if($promotion->usage_limit)
                                    <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300
                                            @if($usagePercent >= 90) bg-red-500
                                            @elseif($usagePercent >= 70) bg-amber-500
                                            @else bg-emerald-500 @endif"
                                             style="width: {{ $usagePercent }}%"></div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs text-gray-600">
                                    @if($promotion->starts_at)
                                        <div class="flex items-center gap-1">
                                            <i class="fa-regular fa-calendar text-gray-400"></i>
                                            <span>{{ $promotion->starts_at->format('M d, Y') }}</span>
                                        </div>
                                    @endif
                                    @if($promotion->ends_at)
                                        <div class="flex items-center gap-1 mt-0.5">
                                            <i class="fa-regular fa-calendar-check text-gray-400"></i>
                                            <span class="{{ $isExpired ? 'text-red-500' : '' }}">{{ $promotion->ends_at->format('M d, Y') }}</span>
                                        </div>
                                    @endif
                                    @if(!$promotion->starts_at && !$promotion->ends_at)
                                        <span class="text-gray-400 italic">No schedule</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-col gap-1.5">
                                    @if($isExpired)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 w-fit">
                                            <i class="fa-solid fa-circle text-[6px]"></i> Expired
                                        </span>
                                    @elseif($promotion->is_active)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 w-fit">
                                            <i class="fa-solid fa-circle text-[6px]"></i> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 w-fit">
                                            <i class="fa-solid fa-circle text-[6px]"></i> Inactive
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1" x-data="{ open: false }">
                                    {{-- Toggle --}}
                                    <form action="{{ route('admin.promotions.toggle', $promotion->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 text-gray-400 hover:text-{{ $promotion->is_active ? 'red' : 'emerald' }}-600 rounded-lg hover:bg-gray-100 transition-colors"
                                                title="{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fa-solid fa-{{ $promotion->is_active ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                    {{-- Edit --}}
                                    <a href="{{ route('admin.promotions.edit', $promotion->id) }}"
                                       class="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-gray-100 transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    {{-- Duplicate --}}
                                    <form action="{{ route('admin.promotions.duplicate', $promotion->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 text-gray-400 hover:text-purple-600 rounded-lg hover:bg-gray-100 transition-colors" title="Duplicate">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </form>
                                    {{-- Delete --}}
                                    <button @click="openDelete({{ $promotion->id }}, '{{ addslashes($promotion->name) }}')"
                                            class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center">
                                        <i class="fa-solid fa-ticket text-gray-300 text-2xl"></i>
                                    </div>
                                    <p class="text-gray-500 font-medium">No promotions found</p>
                                    @if(request('query'))
                                        <p class="text-sm text-gray-400">Try a different search term</p>
                                    @else
                                        <a href="{{ route('admin.promotions.create') }}" class="mt-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                                            Create your first promotion
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($promotions->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/50">
                {{ $promotions->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Delete Modal --}}
    <div x-show="deleteModal" x-transition.opacity
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
         style="display: none;">
        <div @click.away="deleteModal = false" class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md">
            <div class="text-center">
                <div class="w-14 h-14 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Promotion</h3>
                <p class="text-sm text-gray-500 mb-1">Are you sure you want to delete</p>
                <p class="text-sm font-semibold text-gray-800 mb-6" x-text="deleteName"></p>
                <div class="flex gap-3 justify-center">
                    <button @click="deleteModal = false"
                            class="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <form :action="`/admin/promotions/${deleteId}`" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-5 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors shadow-sm">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function promotionsManager() {
    return {
        deleteModal: false,
        deleteId: null,
        deleteName: '',
        openDelete(id, name) {
            this.deleteId = id;
            this.deleteName = name;
            this.deleteModal = true;
        }
    }
}
</script>
@endsection
