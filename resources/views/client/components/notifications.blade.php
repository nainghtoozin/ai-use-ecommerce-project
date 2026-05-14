@php
    $clientNotifications = Auth::user()->notifications()->latest()->take(8)->get();
    $clientUnreadCount = Auth::user()->unreadNotifications()->count();
@endphp

<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle position-relative text-dark" href="#" id="clientNotificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell-fill me-1"></i>
        @if($clientUnreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {{ $clientUnreadCount > 99 ? '99+' : $clientUnreadCount }}
            </span>
        @endif
    </a>

    <ul class="dropdown-menu dropdown-menu-end p-0 shadow" aria-labelledby="clientNotificationDropdown" style="min-width: 330px; max-width: 360px;">
        <li class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
            <span class="fw-semibold">Notifications</span>
            @if($clientUnreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Mark all as read</button>
                </form>
            @endif
        </li>

        @forelse($clientNotifications as $notification)
            @php
                $data = $notification->data;
                $isUnread = is_null($notification->read_at);
            @endphp
            <li class="{{ $isUnread ? 'bg-primary bg-opacity-10' : '' }}">
                <a class="dropdown-item py-3 text-wrap" href="{{ $data['action_url'] ?? '#' }}">
                    <div class="fw-semibold">{{ $data['title'] ?? 'Notification' }}</div>
                    <div class="small text-muted" style="white-space: pre-line;">{{ $data['message'] ?? '' }}</div>
                    <div class="small text-secondary mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                </a>

                @if($isUnread)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="px-3 pb-2">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Mark as Read</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="px-3 py-4 text-center text-muted small">No notifications yet.</li>
        @endforelse
    </ul>
</li>
