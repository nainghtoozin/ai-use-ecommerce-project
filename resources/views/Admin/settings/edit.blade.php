@extends('Admin.layouts.admin')

@section('title', 'Customer Support Settings')
@section('page-title', 'Customer Support Settings')

@section('content')
<div class="max-w-4xl mx-auto bg-white rounded-lg shadow-sm border border-gray-200">
    {{-- Card Header --}}
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                <i class="fas fa-headset text-blue-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Customer Support Settings</h3>
                <p class="text-sm text-gray-500">Configure social links for the customer support chat footer</p>
            </div>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="p-6">
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-start">
            <i class="fas fa-check-circle mt-0.5 mr-3 text-green-500"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Telegram --}}
            <div>
                <label for="telegram_link" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fab fa-telegram text-blue-500 mr-2"></i>Telegram
                </label>
                <input type="text" 
                       id="telegram_link" 
                       name="telegram_link" 
                       value="{{ old('telegram_link', $settings['telegram_link'] ?? '') }}" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                       placeholder="@username OR https://t.me/username">
                <p class="mt-1.5 text-xs text-gray-500">Enter username (@john) or full Telegram link</p>
            </div>

            {{-- Viber --}}
            <div>
                <label for="viber_link" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fab fa-viber text-purple-600 mr-2"></i>Viber
                </label>
                <input type="text" 
                       id="viber_link" 
                       name="viber_link" 
                       value="{{ old('viber_link', $settings['viber_link'] ?? '') }}" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                       placeholder="Phone number (+1234567890) OR viber://chat?number=+1234567890">
                <p class="mt-1.5 text-xs text-gray-500">Enter phone number with country code or Viber link</p>
            </div>

            {{-- Facebook --}}
            <div>
                <label for="facebook_link" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fab fa-facebook text-blue-600 mr-2"></i>Facebook
                </label>
                <input type="text" 
                       id="facebook_link" 
                       name="facebook_link" 
                       value="{{ old('facebook_link', $settings['facebook_link'] ?? '') }}" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                       placeholder="Page name OR https://facebook.com/yourpage">
                <p class="mt-1.5 text-xs text-gray-500">Enter page name or full Facebook URL</p>
            </div>

            {{-- WhatsApp --}}
            <div>
                <label for="whatsapp_link" class="block text-sm font-semibold text-gray-700 mb-2">
                    <i class="fab fa-whatsapp text-green-500 mr-2"></i>WhatsApp
                </label>
                <input type="text" 
                       id="whatsapp_link" 
                       name="whatsapp_link" 
                       value="{{ old('whatsapp_link', $settings['whatsapp_link'] ?? '') }}" 
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                       placeholder="Number (+1234567890) OR https://wa.me/1234567890">
                <p class="mt-1.5 text-xs text-gray-500">Enter phone number with country code or wa.me link</p>
            </div>

            {{-- Submit Buttons --}}
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.dashboard') }}" 
                   class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm hover:shadow transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
