@extends('Admin.layouts.admin')

@section('title', 'Cities')
@section('page-title', 'Cities')

@section('content')
<div x-data="cityToggle()">
    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Cities</h3>
        <a href="{{ route('admin.cities.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
            <i class="fa-solid fa-plus mr-1"></i> Add City
        </a>
    </div>

    <div class="overflow-x-auto bg-white rounded-md shadow-md">
        <table class="min-w-full text-left">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                <tr>
                    <th class="px-4 py-3 border-b">#</th>
                    <th class="px-4 py-3 border-b">Name</th>
                    <th class="px-4 py-3 border-b">Delivery Fee</th>
                    <th class="px-4 py-3 border-b">Townships</th>
                    <th class="px-4 py-3 border-b text-center">Status</th>
                    <th class="px-4 py-3 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($cities as $city)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 border-b">{{ $city->id }}</td>
                    <td class="px-4 py-3 border-b font-medium">{{ $city->name }}</td>
                    <td class="px-4 py-3 border-b">{{ number_format($city->delivery_fee, 2) }} {{ $websiteInfo->currency ?? 'MMK' }}</td>
                    <td class="px-4 py-3 border-b">{{ $city->townships_count }}</td>
                    <td class="px-4 py-3 border-b text-center">
                        <button @click="toggleActive({{ $city->id }})"
                                class="px-3 py-1 rounded-full text-xs font-semibold {{ $city->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $city->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </td>
                    <td class="px-4 py-3 border-b text-center">
                        <a href="{{ route('admin.cities.edit', $city->id) }}" class="bg-green-500 text-white px-3 py-1 rounded-md hover:bg-green-600 inline-block">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <button @click="confirmDelete({{ $city->id }}, '{{ addslashes($city->name) }}')"
                                class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-600 ml-1">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-gray-500 py-6">No cities found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cities->links() }}</div>

    <!-- Delete Modal -->
    <div x-show="showModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-md p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Delete City</h3>
            <p class="mb-4">Delete <strong x-text="cityName"></strong>? All townships will also be deleted.</p>
            <div class="flex justify-end gap-3">
                <button @click="showModal = false" class="bg-gray-500 text-white px-4 py-2 rounded-md">Cancel</button>
                <form :action="`/admin/cities/${cityId}`" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-md">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function cityToggle() {
    return {
        showModal: false, cityId: null, cityName: '',
        confirmDelete(id, name) { this.cityId = id; this.cityName = name; this.showModal = true; },
        toggleActive(id) {
            fetch(`/admin/cities/${id}/toggle`, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }})
            .then(res => res.json()).then(data => { if (data.success) location.reload(); });
        }
    }
}
</script>
@endsection
