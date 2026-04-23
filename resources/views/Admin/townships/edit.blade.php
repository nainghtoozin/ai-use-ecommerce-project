@extends('Admin.layouts.admin')

@section('title', 'Edit Township')
@section('page-title', 'Edit Township')

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Township</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('admin.townships.update', $township->id) }}" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-gray-700 font-medium">City</label>
            <select name="city_id" class="w-full border rounded-md px-3 py-2 focus:ring focus:border-blue-400" required>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" {{ old('city_id', $township->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 font-medium">Township Name</label>
            <input type="text" name="name" value="{{ old('name', $township->name) }}" class="w-full border rounded-md px-3 py-2 focus:ring focus:border-blue-400" required>
        </div>
        <div>
            <label class="block text-gray-700 font-medium">Postal Code</label>
            <input type="text" name="postal_code" value="{{ old('postal_code', $township->postal_code) }}" class="w-full border rounded-md px-3 py-2 focus:ring focus:border-blue-400">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $township->is_active) ? 'checked' : '' }} class="w-4 h-4">
            <label for="is_active">Active</label>
        </div>
        <div class="flex gap-3 pt-4">
            <a href="{{ route('admin.townships.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">Cancel</a>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Update</button>
        </div>
    </form>
</div>
@endsection
