<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f5f6fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .chat-container { max-width: 900px; margin: 30px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .chat-list { width: 280px; border-right: 1px solid #e0e0e0; height: 500px; overflow-y: auto; }
        .chat-user { padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
        .chat-user:hover { background: #f8f9fa; }
        .chat-user.active { background: #e9ecef; border-left: 3px solid #0d6efd; }
        .chat-user-name { font-weight: 600; margin: 0; }
        .chat-user-preview { font-size: 13px; color: #6c757d; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
        .unread-badge { background: #dc3545; color: white; border-radius: 50%; padding: 2px 7px; font-size: 11px; }
        .chat-main { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 15px; border-bottom: 1px solid #e0e0e0; background: #f8f9fa; }
        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; height: 350px; }
        .message { max-width: 70%; padding: 12px 16px; border-radius: 18px; font-size: 14px; word-wrap: break-word; }
        .message-sent { background: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
        .message-received { background: #e9ecef; color: #333; align-self: flex-start; border-bottom-left-radius: 4px; }
        .message-time { font-size: 10px; opacity: 0.7; display: block; margin-top: 4px; }
        .chat-input-area { padding: 15px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; }
        .chat-input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
        .chat-input:focus { border-color: #0d6efd; }
        .chat-send-btn { background: #0d6efd; color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; }
        .chat-send-btn:hover { background: #0b5ed7; }
        .empty-chat { display: flex; align-items: center; justify-content: center; height: 100%; color: #999; text-align: center; }
        .empty-state { padding: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-chat-dots-fill"></i> Live Chat Support</h4>
            <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house"></i> Home</a>
        </div>
        
        <div class="chat-container d-flex">
            <div class="chat-list">
                <div class="p-3 border-bottom bg-light">
                    <strong>Conversations</strong>
                </div>
                <div id="usersList">
                    @forelse($conversations as $conv)
                    <div class="chat-user" onclick="selectUser({{ $conv['user']->id }})" data-user-id="{{ $conv['user']->id }}">
                        <div class="d-flex justify-content-between">
                            <p class="chat-user-name">{{ $conv['user']->name }}</p>
                            @if($conv['unread_count'] > 0)
                            <span class="unread-badge">{{ $conv['unread_count'] }}</span>
                            @endif
                        </div>
                        <p class="chat-user-preview">
                            @if($conv['last_message'])
                            {{ \Illuminate\Support\Str::limit($conv['last_message']->message, 30) }}
                            @else
                            No messages yet
                            @endif
                        </p>
                    </div>
                    @empty
                    <div class="text-muted text-center p-4">No conversations yet</div>
                    @endforelse
                </div>
            </div>
            
            <div class="chat-main">
                <div class="chat-header">
                    <h5 class="mb-0" id="selectedUserName">Select a conversation</h5>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="empty-chat">
                        <div class="empty-state">
                            <i class="bi bi-chat-square-text fs-1"></i>
                            <p class="mt-3">Select a conversation to start chatting</p>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-area">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Type your message..." maxlength="5000">
                    <button class="chat-send-btn" id="chatSendBtn"><i class="bi bi-send"></i> Send</button>
                </div>
            </div>
        </div>
    </div>

<script>
var selectedUserId = null;
var currentUserId = {{ auth()->id() }};

function initChat() {
    var firstUser = document.querySelector('.chat-user');
    if (firstUser) {
        var userId = parseInt(firstUser.getAttribute('data-user-id'));
        if (userId) {
            selectUser(userId);
        }
    }
}

function selectUser(userId) {
    selectedUserId = userId;
    var items = document.querySelectorAll('.chat-user');
    for (var i = 0; i < items.length; i++) {
        items[i].classList.remove('active');
    }
    var el = document.querySelector('.chat-user[data-user-id="' + userId + '"]');
    if (el) {
        el.classList.add('active');
        var nameEl = el.querySelector('.chat-user-name');
        document.getElementById('selectedUserName').textContent = nameEl ? nameEl.textContent : 'Chat';
    }
    loadMessages();
}

function loadMessages() {
    if (!selectedUserId) return;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/chat/messages/' + selectedUserId, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            var container = document.getElementById('chatMessages');
            
            if (!data.messages || data.messages.length === 0) {
                container.innerHTML = '<div class="text-center text-muted p-4">Type a message to start the conversation!</div>';
                return;
            }
            
            var html = '';
            for (var i = 0; i < data.messages.length; i++) {
                var msg = data.messages[i];
                var cls = msg.sender_id === currentUserId ? 'message-sent' : 'message-received';
                var time = new Date(msg.created_at).toLocaleString();
                html += '<div class="message ' + cls + '">' + escapeHtml(msg.message) +
                    '<small class="message-time">' + time + '</small></div>';
            }
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
            
            markAsRead();
        }
    };
    xhr.send();
}

function markAsRead() {
    if (!selectedUserId) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/chat/read/' + selectedUserId, true);
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.send();
}

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    var input = document.getElementById('chatInput');
    var message = input.value.trim();
    if (!message) return;
    
    if (!selectedUserId) {
        var firstUser = document.querySelector('.chat-user');
        if (firstUser) {
            selectedUserId = parseInt(firstUser.getAttribute('data-user-id'));
        }
    }
    
    if (!selectedUserId) {
        alert('No chat available. Please try again later.');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/chat/send', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                input.value = '';
                loadMessages();
            }
        }
    };
    xhr.send(JSON.stringify({
        receiver_id: selectedUserId,
        message: message
    }));
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

setInterval(function() {
    if (selectedUserId) {
        loadMessages();
    }
}, 3000);

window.onload = initChat;
</script>
</body>
</html>