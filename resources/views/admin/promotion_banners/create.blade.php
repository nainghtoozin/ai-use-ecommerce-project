@extends('admin.layouts.admin')

@section('title', 'Add Banner')
@section('page-title', 'Add New Banner')

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded-md shadow-md">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Create New Banner</h3>

    @if ($errors->any())
        <div class="bg-red-100 text-red-600 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label for="title" class="block text-gray-700 font-medium">Banner Title</label>
            <input type="text" name="title" id="title" value="{{ old('title') }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="Enter banner title" required>
        </div>

        <div>
            <label for="description" class="block text-gray-700 font-medium">Description</label>
            <textarea name="description" id="description" rows="3"
                      class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                      placeholder="Optional">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="link" class="block text-gray-700 font-medium">Banner Link</label>
            <input type="url" name="link" id="link" value="{{ old('link') }}"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400"
                   placeholder="https://example.com" required>
        </div>

        <div>
            <label for="image" class="block text-gray-700 font-medium">Banner Image</label>
            <input type="file" name="image" id="image" accept="image/*"
                   class="w-full border border-gray-300 rounded-md px-3 py-2 mt-1 focus:outline-none focus:ring focus:border-blue-400" required>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400"
                   {{ old('is_active', true) ? 'checked' : '' }}>
            <label for="is_active" class="text-gray-700 font-medium">Active</label>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('admin.banners.index') }}"
               class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                Cancel
            </a>
            <button type="submit"
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                <i class="fa-solid fa-save mr-1"></i> Save
            </button>
        </div>
    </form>
</div>
@endsection
