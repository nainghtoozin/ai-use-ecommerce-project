@extends('Admin.layouts.admin')

@section('title', 'Payment Methods')
@section('page-title', 'Payment Methods')

@section('content')
<div x-data="paymentMethodToggle()">

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6">
            <strong class="font-bold">Success! </strong>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Payment Methods</h3>
        <a href="{{ route('admin.payment-methods.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Add Payment Method
        </a>
    </div>

    <div class="overflow-x-auto bg-white rounded-md shadow-md">
        <table class="min-w-full text-left border-collapse">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                <tr>
                    <th class="px-4 py-3 border-b">#</th>
                    <th class="px-4 py-3 border-b">Name</th>
                    <th class="px-4 py-3 border-b">Account Name</th>
                    <th class="px-4 py-3 border-b">Account Number</th>
                    <th class="px-4 py-3 border-b">Bank Name</th>
                    <th class="px-4 py-3 border-b text-center">Status</th>
                    <th class="px-4 py-3 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($paymentMethods as $method)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 border-b">{{ $method->id }}</td>
                    <td class="px-4 py-3 border-b font-medium">{{ $method->name }}</td>
                    <td class="px-4 py-3 border-b">{{ $method->account_name }}</td>
                    <td class="px-4 py-3 border-b">{{ $method->account_number }}</td>
                    <td class="px-4 py-3 border-b">{{ $method->bank_name ?? '-' }}</td>
                    <td class="px-4 py-3 border-b text-center">
                        <button @click="toggleActive({{ $method->id }})"
                                :class="{{ $method->is_active }} ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                class="px-3 py-1 rounded-full text-xs font-semibold hover:opacity-80">
                            {{ $method->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </td>
                    <td class="px-4 py-3 border-b text-center">
                        <div class="flex justify-center gap-2">
                            <a href="{{ route('admin.payment-methods.edit', $method->id) }}" 
                               class="bg-green-500 text-white px-3 py-1 rounded-md hover:bg-green-600">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <button @click="openDeleteModal({{ $method->id }}, '{{ addslashes($method->name) }}')"
                                    class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-600">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-gray-500 py-6">No payment methods found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex justify-center">
        {{ $paymentMethods->links() }}
    </div>

    <!-- Delete Modal -->
    <div x-show="showDeleteModal" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div @click.away="showDeleteModal = false" class="bg-white rounded-md shadow-lg p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Confirm Delete</h3>
            <p class="mb-4">Are you sure you want to delete <strong x-text="methodName"></strong>?</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">Cancel</button>
                <form :action="`/admin/payment-methods/${methodId}`" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function paymentMethodToggle() {
    return {
        showDeleteModal: false,
        methodId: null,
        methodName: '',
        openDeleteModal(id, name) {
            this.methodId = id;
            this.methodName = name;
            this.showDeleteModal = true;
        },
        toggleActive(id) {
            fetch(`/admin/payment-methods/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }
}
</script>
@endsection
