@extends('Admin.layouts.admin')

@section('title', 'Edit Payment Method')
@section('page-title', 'Edit Payment Method')

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Payment Method</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.payment-methods.update', $paymentMethod->id) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-gray-700 font-medium">Payment Method Name</label>
            <input type="text" name="name" id="name" value="{{ old('name', $paymentMethod->name) }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="e.g., KBZ Pay, WavePay, Bank Transfer" required>
        </div>

        <div>
            <label for="account_name" class="block text-gray-700 font-medium">Account Name</label>
            <input type="text" name="account_name" id="account_name" value="{{ old('account_name', $paymentMethod->account_name) }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="Account holder name" required>
        </div>

        <div>
            <label for="account_number" class="block text-gray-700 font-medium">Account Number</label>
            <input type="text" name="account_number" id="account_number" value="{{ old('account_number', $paymentMethod->account_number) }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="Account/Phone number" required>
        </div>

        <div>
            <label for="bank_name" class="block text-gray-700 font-medium">Bank Name <span class="text-gray-400">(Optional)</span></label>
            <input type="text" name="bank_name" id="bank_name" value="{{ old('bank_name', $paymentMethod->bank_name) }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="e.g., KBZ Bank, AYA Bank">
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" 
                   class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500" 
                   {{ old('is_active', $paymentMethod->is_active) ? 'checked' : '' }}>
            <label for="is_active" class="text-gray-700">Active (visible to customers)</label>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('admin.payment-methods.index') }}"
               class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                Cancel
            </a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                <i class="fa-solid fa-save mr-1"></i> Update
            </button>
        </div>
    </form>
</div>
@endsection
