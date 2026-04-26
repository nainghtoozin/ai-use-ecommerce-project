let selectedUserId = null;
const ADMIN_ID = {{ auth()->id() }};

function loadUsers() {
    fetch('/admin/chat/users')
        .then(res => res.json())
        .then(data => {
            const list = document.getElementById('usersList');
            if (!data.users || data.users.length === 0) {
                list.innerHTML = '<div class="text-center text-muted p-4">No conversations yet</div>';
                return;
            }
            let html = '';
            data.users.forEach(function(u) {
                html += '<div class="chat-user-item" onclick="selectUser(' + u.user.id + ')">' +
                    '<div class="d-flex justify-content-between">' +
                    '<strong>' + escapeHtml(u.user.name) + '</strong>';
                if (u.unread_count > 0) {
                    html += '<span class="unread-count">' + u.unread_count + '</span>';
                }
                html += '</div><small class="text-muted">' + u.user.email + '</small></div>';
            });
            list.innerHTML = html;
        });
}

function selectUser(userId) {
    selectedUserId = userId;
    document.querySelectorAll('.chat-user-item').forEach(function(el) {
        el.classList.remove('active');
    });
    event.target.closest('.chat-user-item').classList.add('active');
    
    fetch('/admin/chat/messages/' + userId)
        .then(res => res.json())
        .then(data => {
            const header = document.getElementById('selectedUserName');
            const container = document.getElementById('chatMessages');
            const input = document.getElementById('chatInput');
            const sendBtn = document.getElementById('chatSendBtn');
            
            header.textContent = 'Chat';
            
            if (!data.messages || data.messages.length === 0) {
                container.innerHTML = '<div class="text-center text-muted p-4">No messages yet</div>';
            } else {
                let html = '';
                data.messages.forEach(function(msg) {
                    var cls = msg.sender_id === ADMIN_ID ? 'message-sent' : 'message-received';
                    html += '<div class="message ' + cls + '">' + escapeHtml(msg.message) +
                        '<small class="d-block opacity-75" style="font-size: 10px;">' + 
                        new Date(msg.created_at).toLocaleString() + '</small></div>';
                });
                container.innerHTML = html;
                container.scrollTop = container.scrollHeight;
            }
            
            input.disabled = false;
            sendBtn.disabled = false;
            
            markAsRead();
        });
}

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    var input = document.getElementById('chatInput');
    var message = input.value.trim();
    if (!message || !selectedUserId) return;

    fetch('/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            receiver_id: selectedUserId,
            message: message
        })
    })
    .then(res => res.json())
    .then(function(data) {
        if (data.success) {
            input.value = '';
            selectUser(selectedUserId);
        }
    });
}

function markAsRead() {
    if (!selectedUserId) return;
    fetch('/admin/chat/read/' + selectedUserId, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).then(function() {
        loadUsers();
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

setInterval(function() {
    loadUsers();
    if (selectedUserId) {
        selectUser(selectedUserId);
    }
}, 5000);

window.onload = loadUsers;