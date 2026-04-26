@php
    $adminNotifications = Auth::user()->notifications()->latest()->take(8)->get();
    $adminUnreadCount = Auth::user()->unreadNotifications()->count();
@endphp

<div class="relative">
    <button id="adminNotificationButton" type="button" class="relative p-2 rounded-full text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i class="fas fa-bell text-lg"></i>
        @if($adminUnreadCount > 0)
            <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-xs flex items-center justify-center">
                {{ $adminUnreadCount > 99 ? '99+' : $adminUnreadCount }}
            </span>
        @endif
    </button>

    <div id="adminNotificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg border border-gray-200 z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">Notifications</p>
            @if($adminUnreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800">Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($adminNotifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = is_null($notification->read_at);
                @endphp
                <div class="px-4 py-3 border-b border-gray-100 {{ $isUnread ? 'bg-blue-50' : 'bg-white' }}">
                    <a href="{{ $data['action_url'] ?? '#' }}" class="block">
                        <p class="text-sm font-semibold text-gray-900">{{ $data['title'] ?? 'Notification' }}</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                    </a>

                    @if($isUnread)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="mt-2">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800">Mark as Read</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-gray-500 text-center">No notifications yet.</div>
            @endforelse
        </div>
    </div>
</div>
