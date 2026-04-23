<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Order</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <main class="mx-auto flex min-h-screen w-full max-w-xl items-center px-6 py-10">
        <section class="w-full rounded-lg bg-white p-6 shadow">
            <h1 class="text-2xl font-semibold">Create Order</h1>

            @if (session('success'))
                <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('orders.store') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="customer_name" class="block text-sm font-medium">Customer Name</label>
                    <input
                        id="customer_name"
                        name="customer_name"
                        type="text"
                        value="{{ old('customer_name') }}"
                        required
                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    @error('customer_name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="total_amount" class="block text-sm font-medium">Total Amount</label>
                    <input
                        id="total_amount"
                        name="total_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        value="{{ old('total_amount') }}"
                        required
                        class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    @error('total_amount')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Submit Order
                </button>
            </form>
        </section>
    </main>
</body>
</html>
