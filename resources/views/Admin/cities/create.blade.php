@extends('Admin.layouts.admin')

@section('title', 'Add City')
@section('page-title', 'Add City')

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Add City</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('admin.cities.store') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-gray-700 font-medium">City Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded-md px-3 py-2 focus:ring focus:border-blue-400" placeholder="e.g., Yangon" required>
        </div>
        <div>
            <label class="block text-gray-700 font-medium">Delivery Fee</label>
            <input type="number" name="delivery_fee" value="{{ old('delivery_fee', 0) }}" step="0.01" min="0" class="w-full border rounded-md px-3 py-2 focus:ring focus:border-blue-400" required>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active') ? 'checked' : 'checked' }} class="w-4 h-4">
            <label for="is_active">Active</label>
        </div>
        <div class="flex gap-3 pt-4">
            <a href="{{ route('admin.cities.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Save</button>
        </div>
    </form>
</div>
@endsection
