var ADMIN_ID = 1;
var currentUserId = {{ auth()->id() }};

fetch('/admin/chat/users')
    .then(res => res.json())
    .then(function(data) {
        if (data.users && data.users.length > 0) {
            ADMIN_ID = data.users[0].user.id;
        }
    })
    .catch(function() {
        ADMIN_ID = 1;
    });

document.getElementById('chatToggle').addEventListener('click', function() {
    var box = document.getElementById('chatBox');
    box.classList.toggle('active');
    if (box.classList.contains('active')) {
        loadMessages();
        markAsRead();
    }
});

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    var input = document.getElementById('chatInput');
    var message = input.value.trim();
    if (!message) return;

    fetch('/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            receiver_id: ADMIN_ID,
            message: message
        })
    })
    .then(res => res.json())
    .then(function(data) {
        if (data.success) {
            input.value = '';
            loadMessages();
        }
    });
}

function loadMessages() {
    fetch('/chat/messages/' + ADMIN_ID)
        .then(res => res.json())
        .then(function(data) {
            var container = document.getElementById('chatMessages');
            if (!data.messages || data.messages.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-chat-square-text fs-1"></i><p class="mt-2">Start a conversation</p></div>';
                return;
            }
            var html = '';
            data.messages.forEach(function(msg) {
                var cls = msg.sender_id === currentUserId ? 'message-sent' : 'message-received';
                html += '<div class="message ' + cls + '">' + escapeHtml(msg.message) +
                    '<small class="d-block opacity-75" style="font-size: 10px;">' + 
                    new Date(msg.created_at).toLocaleTimeString() + '</small></div>';
            });
            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        });
}

function markAsRead() {
    fetch('/chat/read/' + ADMIN_ID, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).then(function() {
        document.getElementById('unreadBadge').style.display = 'none';
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

setInterval(function() {
    var box = document.getElementById('chatBox');
    if (box && box.classList.contains('active')) {
        loadMessages();
    }
    fetch('/chat/unread-count')
        .then(res => res.json())
        .then(function(data) {
            var badge = document.getElementById('unreadBadge');
            if (data.unread_count > 0) {
                badge.style.display = 'flex';
                badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            } else {
                badge.style.display = 'none';
            }
        });
}, 5000);

window.onload = function() {
    fetch('/admin/chat/users')
        .then(res => res.json())
        .then(function(data) {
            if (data.users && data.users.length > 0) {
                ADMIN_ID = data.users[0].user.id;
            }
        });
};