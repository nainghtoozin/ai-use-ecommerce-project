<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0084ff;
            --primary-hover: #0074e4;
            --sent-bg: #0084ff;
            --sent-text: #ffffff;
            --received-bg: #e4e6eb;
            --received-text: #050505;
            --border: #e5e5e5;
            --bg: #f0f2f5;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .chat-app { display: flex; height: 100vh; background: #fff; }

        .chat-sidebar {
            width: 320px;
            min-width: 320px;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header { padding: 18px 20px; border-bottom: 1px solid var(--border); background: #fafafa; }
        .sidebar-header h4 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        .users-list { flex: 1; overflow-y: auto; }

        .user-item {
            display: flex; align-items: center; padding: 14px 20px; cursor: pointer;
            border-bottom: 1px solid #f5f5f5; transition: background 0.15s;
        }
        .user-item:hover { background: #f7f8fa; }
        .user-item.active { background: #e7f3ff; border-left: 3px solid var(--primary); }

        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: var(--primary);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 16px; margin-right: 12px; flex-shrink: 0;
        }

        .user-info { flex: 1; min-width: 0; }
        .user-name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
        .user-preview { font-size: 12px; color: #8c8c8c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .unread-badge {
            background: var(--primary); color: #fff; border-radius: 10px; padding: 2px 7px;
            font-size: 11px; font-weight: 600; min-width: 20px; text-align: center;
        }

        .chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        .chat-header {
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; background: #fafafa;
        }
        .chat-header-left { display: flex; align-items: center; gap: 12px; }
        .chat-header h5 { font-size: 16px; font-weight: 600; margin: 0; }
        .back-btn { display: none; margin: 0; padding: 4px 8px; font-size: 18px; }

        .connection-status { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
        .connection-status.connected { background: #d4edda; color: #155724; }
        .connection-status.disconnected { background: #f8d7da; color: #721c24; }

        .messages-area { flex: 1; display: flex; flex-direction: column; min-height: 0; position: relative; }

        .messages-container { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; }

        .loading-more { text-align: center; padding: 8px; font-size: 12px; color: #8c8c8c; display: none; }
        .loading-more.active { display: block; }

        .empty-chat { flex: 1; display: flex; align-items: center; justify-content: center; color: #8c8c8c; font-size: 14px; }

        .message-row { display: flex; margin-bottom: 4px; }
        .message-row.sent { justify-content: flex-end; }
        .message-row.received { justify-content: flex-start; }

        .message-row.sent + .message-row.sent { margin-top: -2px; }
        .message-row.received + .message-row.received { margin-top: -2px; }
        .message-row.sent + .message-row.received { margin-top: 12px; }
        .message-row.received + .message-row.sent { margin-top: 12px; }

        .message-bubble {
            max-width: 60%; padding: 8px 14px; border-radius: 16px; font-size: 14px;
            line-height: 1.4; word-break: break-word; position: relative;
        }

        .message-row.sent .message-bubble { background: var(--sent-bg); color: var(--sent-text); border-bottom-right-radius: 4px; }
        .message-row.received .message-bubble { background: var(--received-bg); color: var(--received-text); border-bottom-left-radius: 4px; }

        .message-bubble:hover .msg-actions { opacity: 1; visibility: visible; }

        .message-time { display: block; font-size: 10px; margin-top: 2px; opacity: 0.6; text-align: right; }

        .msg-actions {
            position: absolute; top: -28px; background: #fff; border-radius: 16px;
            padding: 4px 6px; box-shadow: 0 1px 6px rgba(0,0,0,0.15);
            opacity: 0; visibility: hidden; transition: opacity 0.15s; z-index: 10;
        }
        .message-row.sent .msg-actions { right: 0; }
        .message-row.received .msg-actions { left: 0; }

        .msg-action-btn { background: none; border: none; padding: 4px 6px; cursor: pointer; color: #666; font-size: 14px; }
        .msg-action-btn:hover { color: var(--primary); }

        .typing-indicator { padding: 4px 16px; font-size: 12px; color: #8c8c8c; font-style: italic; min-height: 20px; }

        .reply-bar {
            display: none; padding: 8px 16px; background: #f7f8fa; border-top: 1px solid var(--border); cursor: pointer;
        }
        .reply-bar.active { display: flex; align-items: center; gap: 8px; }
        .reply-bar-label { font-size: 11px; font-weight: 600; color: var(--primary); }
        .reply-bar-text { font-size: 12px; color: #666; flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .reply-bar-close { cursor: pointer; color: #999; font-size: 16px; }

        .input-area {
            padding: 12px 16px; border-top: 1px solid var(--border); background: #fff;
            display: flex; align-items: flex-end; gap: 10px; position: relative;
        }

        .input-box {
            flex: 1; background: var(--received-bg); border-radius: 20px;
            padding: 8px 12px; display: flex; align-items: center;
        }

        .input-box textarea {
            flex: 1; border: none; outline: none; background: transparent; font-size: 14px;
            font-family: inherit; resize: none; max-height: 100px; line-height: 1.4; padding: 0 6px;
        }

        .emoji-trigger {
            background: none; border: none; padding: 4px; cursor: pointer;
            font-size: 20px; color: #8c8c8c; flex-shrink: 0;
        }
        .emoji-trigger:hover { color: var(--primary); }

        .send-btn {
            background: var(--primary); color: #fff; border: none; width: 40px; height: 40px;
            border-radius: 50%; cursor: pointer; font-size: 18px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.15s;
        }
        .send-btn:hover { background: var(--primary-hover); }
        .send-btn:disabled { background: #bcc0c4; cursor: not-allowed; }

        .emoji-picker {
            position: absolute; bottom: 60px; left: 20px; background: #fff; border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            padding: 10px; display: none; z-index: 100; width: 280px;
        }
        .emoji-picker.active { display: block; }

        .emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 2px; max-height: 180px; overflow-y: auto; }
        .emoji-btn { background: none; border: none; font-size: 18px; padding: 6px 4px; cursor: pointer; border-radius: 6px; }
        .emoji-btn:hover { background: #f0f2f5; }

        /* Chat Footer */
        .chat-footer {
            padding: 16px;
            background: #fafafa;
            border-top: 1px solid var(--border);
        }

        .footer-notice {
            font-size: 12px;
            color: #8c8c8c;
            line-height: 1.6;
            margin-bottom: 12px;
            padding: 10px;
            background: #fff8e6;
            border-radius: 8px;
            border-left: 3px solid #ffc107;
        }

        .footer-notice strong { color: #664d03; }

        .footer-social {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .footer-social a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            color: #555;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.15s;
        }

        .footer-social a:hover { background: #f0f2f5; color: var(--primary); border-color: var(--primary); }
        .footer-social a i { font-size: 16px; }

        @media (max-width: 768px) {
            .chat-sidebar { width: 100%; min-width: auto; position: absolute; z-index: 50; }
            .chat-main { display: none; }
            .chat-main.active { display: flex; z-index: 60; position: relative; }
            .back-btn { display: inline-flex; }
            .message-bubble { max-width: 80%; }
            .footer-social { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="chat-app">
        <div class="chat-sidebar" id="chatSidebar">
            <div class="sidebar-header">
                <h4><i class="bi bi-chat-dots-fill"></i> Messages</h4>
            </div>
            <div class="users-list" id="usersList">
                @forelse($conversations as $conv)
                <div class="user-item" onclick="selectUser({{ $conv['user']->id }})" data-user-id="{{ $conv['user']->id }}">
                    <div class="user-avatar">{{ substr($conv['user']->name, 0, 1) }}</div>
                    <div class="user-info">
                        <div class="user-name">{{ $conv['user']->name }}</div>
                        <div class="user-preview">
                            @if($conv['last_message'])
                            {{ \Illuminate\Support\Str::limit($conv['last_message']->message, 30) }}
                            @else
                            No messages yet
                            @endif
                        </div>
                    </div>
                    @if($conv['unread_count'] > 0)
                    <span class="unread-badge">{{ $conv['unread_count'] }}</span>
                    @endif
                </div>
                @empty
                <div class="text-muted text-center p-4">No conversations yet</div>
                @endforelse
            </div>
        </div>

        <div class="chat-main" id="chatMain">
            <div class="chat-header">
                <div class="chat-header-left">
                    <button class="btn btn-sm btn-light back-btn" onclick="showSidebar()"><i class="bi bi-arrow-left"></i></button>
                    <div class="user-avatar" style="width: 34px; height: 34px; font-size: 14px;" id="selectedUserAvatar">?</div>
                    <h5 id="selectedUserName">Select a conversation</h5>
                </div>
                <span id="connectionStatus" class="connection-status disconnected">Connecting...</span>
            </div>

            <div class="messages-area">
                <div class="messages-container" id="chatMessages">
                    <div class="loading-more" id="loadingMore"><i class="bi bi-arrow-up"></i> Loading...</div>
                </div>

                <div class="typing-indicator" id="typingIndicator"></div>

                <div class="emoji-picker" id="emojiPicker">
                    <div class="emoji-grid">
                        <button class="emoji-btn" onclick="insertEmoji('😀')">😀</button>
                        <button class="emoji-btn" onclick="insertEmoji('😃')">😃</button>
                        <button class="emoji-btn" onclick="insertEmoji('😄')">😄</button>
                        <button class="emoji-btn" onclick="insertEmoji('😁')">😁</button>
                        <button class="emoji-btn" onclick="insertEmoji('😆')">😆</button>
                        <button class="emoji-btn" onclick="insertEmoji('😅')">😅</button>
                        <button class="emoji-btn" onclick="insertEmoji('😂')">😂</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤣')">🤣</button>
                        <button class="emoji-btn" onclick="insertEmoji('😊')">😊</button>
                        <button class="emoji-btn" onclick="insertEmoji('😇')">😇</button>
                        <button class="emoji-btn" onclick="insertEmoji('🙂')">🙂</button>
                        <button class="emoji-btn" onclick="insertEmoji('😉')">😉</button>
                        <button class="emoji-btn" onclick="insertEmoji('😌')">😌</button>
                        <button class="emoji-btn" onclick="insertEmoji('😍')">😍</button>
                        <button class="emoji-btn" onclick="insertEmoji('🥰')">🥰</button>
                        <button class="emoji-btn" onclick="insertEmoji('😘')">😘</button>
                        <button class="emoji-btn" onclick="insertEmoji('😋')">😋</button>
                        <button class="emoji-btn" onclick="insertEmoji('😛')">😛</button>
                        <button class="emoji-btn" onclick="insertEmoji('😜')">😜</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤪')">🤪</button>
                        <button class="emoji-btn" onclick="insertEmoji('😝')">😝</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤑')">🤑</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤗')">🤗</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤭')">🤭</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤫')">🤫</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤔')">🤔</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤐')">🤐</button>
                        <button class="emoji-btn" onclick="insertEmoji('😏')">😏</button>
                        <button class="emoji-btn" onclick="insertEmoji('😒')">😒</button>
                        <button class="emoji-btn" onclick="insertEmoji('🙄')">🙄</button>
                        <button class="emoji-btn" onclick="insertEmoji('😬')">😬</button>
                        <button class="emoji-btn" onclick="insertEmoji('😔')">😔</button>
                        <button class="emoji-btn" onclick="insertEmoji('😪')">😪</button>
                        <button class="emoji-btn" onclick="insertEmoji('😷')">😷</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤒')">🤒</button>
                        <button class="emoji-btn" onclick="insertEmoji('🥵')">🥵</button>
                        <button class="emoji-btn" onclick="insertEmoji('🥶')">🥶</button>
                        <button class="emoji-btn" onclick="insertEmoji('🥴')">🥴</button>
                        <button class="emoji-btn" onclick="insertEmoji('😵')">😵</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤯')">🤯</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤠')">🤠</button>
                        <button class="emoji-btn" onclick="insertEmoji('🥳')">🥳</button>
                        <button class="emoji-btn" onclick="insertEmoji('😎')">😎</button>
                        <button class="emoji-btn" onclick="insertEmoji('🤓')">🤓</button>
                        <button class="emoji-btn" onclick="insertEmoji('😕')">😕</button>
                        <button class="emoji-btn" onclick="insertEmoji('😟')">😟</button>
                        <button class="emoji-btn" onclick="insertEmoji('😮')">😮</button>
                        <button class="emoji-btn" onclick="insertEmoji('😯')">😯</button>
                        <button class="emoji-btn" onclick="insertEmoji('😢')">😢</button>
                        <button class="emoji-btn" onclick="insertEmoji('😭')">😭</button>
                        <button class="emoji-btn" onclick="insertEmoji('😤')">😤</button>
                        <button class="emoji-btn" onclick="insertEmoji('😡')">😡</button>
                        <button class="emoji-btn" onclick="insertEmoji('😈')">😈</button>
                        <button class="emoji-btn" onclick="insertEmoji('👍')">👍</button>
                        <button class="emoji-btn" onclick="insertEmoji('👎')">👎</button>
                        <button class="emoji-btn" onclick="insertEmoji('❤️')">❤️</button>
                        <button class="emoji-btn" onclick="insertEmoji('🔥')">🔥</button>
                        <button class="emoji-btn" onclick="insertEmoji('💯')">💯</button>
                        <button class="emoji-btn" onclick="insertEmoji('👏')">👏</button>
                        <button class="emoji-btn" onclick="insertEmoji('🙏')">🙏</button>
                        <button class="emoji-btn" onclick="insertEmoji('✅')">✅</button>
                        <button class="emoji-btn" onclick="insertEmoji('❌')">❌</button>
                        <button class="emoji-btn" onclick="insertEmoji('⭐')">⭐</button>
                        <button class="emoji-btn" onclick="insertEmoji('🎉')">🎉</button>
                    </div>
                </div>

                <div class="reply-bar" id="replyBar" onclick="cancelReply()">
                    <span class="reply-bar-label">Replying to</span>
                    <span class="reply-bar-text" id="replyBarText"></span>
                    <span class="reply-bar-close"><i class="bi bi-x"></i></span>
                </div>

                <div class="input-area">
                    <div class="input-box">
                        <button class="emoji-trigger" id="emojiBtn"><i class="bi bi-emoji-smile"></i></button>
                        <textarea id="chatInput" rows="1" placeholder="Type a message..."></textarea>
                    </div>
                    <button class="send-btn" id="chatSendBtn"><i class="bi bi-send"></i></button>
                </div>

                <div class="chat-footer">
                    <div class="footer-notice">
                        <strong>Notice:</strong> This chat history is stored for only 7 days. Older messages will be automatically deleted. Please take screenshots if the information is important. Or contact us via the social accounts below.
                    </div>
                    <div class="footer-social">
                        @if(setting('telegram_link'))
                        <a href="{{ setting('telegram_link') }}" target="_blank" rel="noopener">
                            <i class="bi bi-telegram"></i> Telegram
                        </a>
                        @endif
                        @if(setting('viber_link'))
                        <a href="{{ setting('viber_link') }}" target="_blank" rel="noopener">
                            <i class="bi bi-chat-dots"></i> Viber
                        </a>
                        @endif
                        @if(setting('facebook_link'))
                        <a href="{{ setting('facebook_link') }}" target="_blank" rel="noopener">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                        @endif
                        @if(setting('whatsapp_link'))
                        <a href="{{ setting('whatsapp_link') }}" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.1/dist/echo.iife.js"></script>
    <script>
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: '{{ config('broadcasting.connections.pusher.key') }}',
        cluster: '{{ config('broadcasting.connections.pusher.cluster') }}',
        forceTLS: true,
        encrypted: true,
    });

    var selectedUserId = null;
    var currentUserId = {{ auth()->id() }};
    var messagesContainer = document.getElementById('chatMessages');
    var chatInput = document.getElementById('chatInput');
    var chatSendBtn = document.getElementById('chatSendBtn');
    var connectionStatus = document.getElementById('connectionStatus');
    var replyBar = document.getElementById('replyBar');
    var replyBarText = document.getElementById('replyBarText');
    var emojiPicker = document.getElementById('emojiPicker');
    var emojiBtn = document.getElementById('emojiBtn');
    var loadingMore = document.getElementById('loadingMore');
    var chatMain = document.getElementById('chatMain');
    var chatSidebar = document.getElementById('chatSidebar');
    var typingIndicator = document.getElementById('typingIndicator');

    var replyingTo = null;
    var hasMoreMessages = true;
    var isLoadingMore = false;
    var prevSender = null;
    var typingTimeout = null;
    var isTyping = false;

    // Connection
    window.Echo.connector.pusher.connection.bind('connected', function() {
        connectionStatus.textContent = 'Live';
        connectionStatus.className = 'connection-status connected';
    });

    window.Echo.connector.pusher.connection.bind('disconnected', function() {
        connectionStatus.textContent = 'Offline';
        connectionStatus.className = 'connection-status disconnected';
    });

    window.Echo.connector.pusher.connection.bind('error', function() {
        connectionStatus.textContent = 'Error';
        connectionStatus.className = 'connection-status disconnected';
    });

    // Subscribe
    window.Echo.private('chat.' + currentUserId)
        .listen('.message.sent', function(data) {
            if (data.sender_id === currentUserId) return;
            if (isDuplicate(data.id)) return;
            if (data.receiver_id === selectedUserId) {
                appendBubble(data);
                scrollToBottom();
            }
        })
        .listen('.typing', function(data) {
            if (data.sender_id === currentUserId) return;
            if (data.is_typing) {
                typingIndicator.textContent = data.sender_name + ' is typing...';
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(function() { typingIndicator.textContent = ''; }, 3000);
            } else {
                typingIndicator.textContent = '';
            }
        });

    // Typing detection
    chatInput.addEventListener('blur', function() { isTyping = false; sendTyping(false); });

    function sendTyping(status) {
        if (!selectedUserId) return;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/chat/typing', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.send(JSON.stringify({ receiver_id: selectedUserId, is_typing: status }));
    }

    // Select user
    function selectUser(userId) {
        selectedUserId = userId;
        document.querySelectorAll('.user-item').forEach(function(el) { el.classList.remove('active'); });
        var el = document.querySelector('.user-item[data-user-id="' + userId + '"]');
        if (el) {
            el.classList.add('active');
            var nameEl = el.querySelector('.user-name');
            var name = nameEl ? nameEl.textContent : 'Chat';
            document.getElementById('selectedUserName').textContent = name;
            document.getElementById('selectedUserAvatar').textContent = name.charAt(0);
        }

        chatSidebar.style.display = window.innerWidth <= 768 ? 'none' : 'flex';
        chatMain.classList.add('active');

        hasMoreMessages = true;
        prevSender = null;
        typingIndicator.textContent = '';
        messagesContainer.innerHTML = '<div class="loading-more" id="loadingMore"><i class="bi bi-arrow-up"></i> Loading...</div>';

        loadMessages();
    }

    function showSidebar() {
        chatSidebar.style.display = 'flex';
        chatMain.classList.remove('active');
        selectedUserId = null;
    }

    // Load messages
    function loadMessages(beforeId) {
        if (!selectedUserId || (isLoadingMore && !beforeId)) return;
        isLoadingMore = !!beforeId;

        var url = '/chat/messages/' + selectedUserId;
        if (beforeId) url += '/' + beforeId;

        if (beforeId) { var lm = document.getElementById('loadingMore'); if (lm) lm.classList.add('active'); }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                var res = JSON.parse(xhr.responseText);
                hasMoreMessages = res.has_more;

                if (beforeId) {
                    prependBubbles(res.messages || []);
                    var lm = document.getElementById('loadingMore');
                    if (lm) lm.classList.remove('active');
                } else {
                    renderInitial(res.messages || []);
                    markAsRead();
                }
            }
        };
        xhr.send();
    }

    // Scroll to load more
    messagesContainer.addEventListener('scroll', function() {
        if (messagesContainer.scrollTop < 50 && hasMoreMessages && !isLoadingMore && selectedUserId) {
            var firstBubble = messagesContainer.querySelector('.message-bubble');
            if (firstBubble) {
                var msgId = firstBubble.dataset.messageId;
                if (msgId && !msgId.startsWith('temp_')) loadMessages(msgId);
            }
        }
    });

    // Render
    function renderInitial(messages) {
        messagesContainer.innerHTML = '<div class="loading-more" id="loadingMore"><i class="bi bi-arrow-up"></i> Loading...</div>';

        if (messages.length === 0) {
            messagesContainer.innerHTML = '<div class="empty-chat">No messages yet. Say hello!</div>';
            return;
        }

        prevSender = null;
        for (var i = messages.length - 1; i >= 0; i--) {
            var msg = messages[i];
            msg.sender_id = msg.sender_id || msg.user_id;
            appendBubble(msg);
        }

        scrollToBottom();
    }

    function prependBubbles(messages) {
        var sh = messagesContainer.scrollHeight;
        var st = messagesContainer.scrollTop;

        for (var i = 0; i < messages.length; i++) {
            var msg = messages[i];
            msg.sender_id = msg.sender_id || msg.user_id;
            prependBubble(msg);
        }

        messagesContainer.scrollTop = messagesContainer.scrollHeight - sh + st;
    }

    function appendBubble(data) {
        var row = createRow(data);
        messagesContainer.appendChild(row);
        prevSender = data.sender_id;
        return row;
    }

    function prependBubble(data) {
        var row = createRow(data);
        var lm = document.getElementById('loadingMore');
        if (lm) { messagesContainer.insertBefore(row, lm); }
        else { messagesContainer.insertBefore(row, messagesContainer.firstChild); }
    }

    function createRow(data) {
        var isSent = data.sender_id === currentUserId;
        var direction = isSent ? 'sent' : 'received';

        var row = document.createElement('div');
        row.className = 'message-row ' + direction;

        var bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.dataset.messageId = data.id || data.temp_id || '';

        var time = data.created_at ? new Date(data.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

        var html = '';

        if (data.reply_to) {
            html += '<div style="font-size:11px;opacity:0.7;margin-bottom:3px;">Replying to: ' + escapeHtml(data.reply_to.message.substring(0, 40)) + '</div>';
        }

        html += escapeHtml(data.message);
        html += '<span class="message-time">' + time + '</span>';
        html += '<div class="msg-actions"><button class="msg-action-btn" onclick="replyTo(' + (data.id || data.temp_id || 0) + ')" title="Reply"><i class="bi bi-reply"></i></button></div>';

        bubble.innerHTML = html;
        row.appendChild(bubble);

        prevSender = data.sender_id;

        return row;
    }

    function scrollToBottom() { messagesContainer.scrollTop = messagesContainer.scrollHeight; }

    function isDuplicate(id) { return !!messagesContainer.querySelector('[data-message-id="' + id + '"]'); }

    // Reply
    function replyTo(messageId) {
        var bubble = messagesContainer.querySelector('[data-message-id="' + messageId + '"]');
        if (!bubble) return;

        replyingTo = { id: messageId, message: bubble.textContent.trim().substring(0, 50) };
        replyBarText.textContent = replyingTo.message;
        replyBar.classList.add('active');
        chatInput.focus();
    }

    function cancelReply() { replyingTo = null; replyBar.classList.remove('active'); }

    // Emoji
    emojiBtn.addEventListener('click', function(e) { e.stopPropagation(); emojiPicker.classList.toggle('active'); });

    function insertEmoji(emoji) { chatInput.value += emoji; chatInput.focus(); emojiPicker.classList.remove('active'); }

    document.addEventListener('click', function(e) {
        if (!emojiPicker.contains(e.target) && !emojiBtn.contains(e.target)) emojiPicker.classList.remove('active');
    });

    // Mark as read
    function markAsRead() {
        if (!selectedUserId) return;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/chat/read/' + selectedUserId, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.send();
    }

    // Send
    chatSendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';

        if (!selectedUserId) return;
        if (!isTyping) { isTyping = true; sendTyping(true); }
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(function() { isTyping = false; sendTyping(false); }, 2000);
    });

    function sendMessage() {
        var message = chatInput.value.trim();
        if (!message) return;

        if (!selectedUserId) {
            var firstUser = document.querySelector('.user-item');
            if (firstUser) {
                selectedUserId = parseInt(firstUser.getAttribute('data-user-id'));
                var nameEl = firstUser.querySelector('.user-name');
                var name = nameEl ? nameEl.textContent : 'Chat';
                document.getElementById('selectedUserName').textContent = name;
                document.getElementById('selectedUserAvatar').textContent = name.charAt(0);
            }
        }

        if (!selectedUserId) return;

        var tempId = 'temp_' + Date.now();
        var optimistic = { id: tempId, temp_id: tempId, sender_id: currentUserId, receiver_id: selectedUserId, message: message, created_at: new Date().toISOString() };

        if (replyingTo) { optimistic.reply_to_id = replyingTo.id; optimistic.reply_to = { id: replyingTo.id, message: replyingTo.message }; }

        appendBubble(optimistic);
        scrollToBottom();

        chatInput.value = '';
        chatInput.style.height = 'auto';
        cancelReply();
        chatInput.disabled = true;
        chatSendBtn.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/chat/send', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                chatInput.disabled = false;
                chatSendBtn.disabled = false;
                chatInput.focus();

                if (xhr.status === 200) {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && res.message) {
                        var el = messagesContainer.querySelector('[data-message-id="' + tempId + '"]');
                        if (el) el.dataset.messageId = res.message.id;
                    }
                } else {
                    var el = messagesContainer.querySelector('[data-message-id="' + tempId + '"]');
                    if (el) { el.remove(); chatInput.value = message; }
                }
            }
        };

        var payload = { receiver_id: selectedUserId, message: message };
        if (replyingTo) payload.reply_to_id = replyingTo.id;
        xhr.send(JSON.stringify(payload));
    }

    function escapeHtml(text) { var div = document.createElement('div'); div.textContent = text || ''; return div.innerHTML; }

    // Init
    var firstUser = document.querySelector('.user-item');
    if (firstUser) {
        firstUser.classList.add('active');
        var nameEl = firstUser.querySelector('.user-name');
        var name = nameEl ? nameEl.textContent : 'Chat';
        document.getElementById('selectedUserName').textContent = name;
        document.getElementById('selectedUserAvatar').textContent = name.charAt(0);
    }
    </script>
</body>
</html>