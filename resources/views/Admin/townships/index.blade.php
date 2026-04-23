@extends('Admin.layouts.admin')

@section('title', 'Townships')
@section('page-title', 'Townships')

@section('content')
<div>
    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-md mb-6">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">Townships</h3>
        <a href="{{ route('admin.townships.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
            <i class="fa-solid fa-plus mr-1"></i> Add Township
        </a>
    </div>

    <div class="mb-4">
        <form method="GET" class="flex gap-2">
            <select name="city_id" class="border rounded-md px-3 py-2">
                <option value="">All Cities</option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" {{ request('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md">Filter</button>
            @if(request('city_id'))
                <a href="{{ route('admin.townships.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md">Clear</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto bg-white rounded-md shadow-md">
        <table class="min-w-full text-left">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                <tr>
                    <th class="px-4 py-3 border-b">#</th>
                    <th class="px-4 py-3 border-b">Name</th>
                    <th class="px-4 py-3 border-b">City</th>
                    <th class="px-4 py-3 border-b">Postal Code</th>
                    <th class="px-4 py-3 border-b text-center">Status</th>
                    <th class="px-4 py-3 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @forelse($townships as $township)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 border-b">{{ $township->id }}</td>
                    <td class="px-4 py-3 border-b font-medium">{{ $township->name }}</td>
                    <td class="px-4 py-3 border-b">{{ $township->city->name ?? 'N/A' }}</td>
                    <td class="px-4 py-3 border-b">{{ $township->postal_code ?? '-' }}</td>
                    <td class="px-4 py-3 border-b text-center">
                        <form action="{{ route('admin.townships.toggle', $township->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1 rounded-full text-xs font-semibold {{ $township->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $township->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 border-b text-center">
                        <a href="{{ route('admin.townships.edit', $township->id) }}" class="bg-green-500 text-white px-3 py-1 rounded-md hover:bg-green-600 inline-block">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <form action="{{ route('admin.townships.destroy', $township->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this township?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-600 ml-1">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-gray-500 py-6">No townships found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $townships->links() }}</div>
</div>
@endsection