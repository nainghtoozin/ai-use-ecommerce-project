@extends('Client.layouts.client')

@section('title', 'Live Chat Support')

@section('content')
<style>
.chat-widget { position: fixed; bottom: 20px; right: 20px; z-index: 1000; }
.chat-toggle { width: 60px; height: 60px; border-radius: 50%; background: #0d6efd; color: white; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 24px; position: relative; }
.chat-box { position: absolute; bottom: 70px; right: 0; width: 350px; height: 450px; background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.2); display: none; flex-direction: column; }
.chat-box.active { display: flex; }
.chat-header { background: #0d6efd; color: white; padding: 15px; border-radius: 12px 12px 0 0; font-weight: 600; }
.chat-messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
.message { max-width: 80%; padding: 10px 14px; border-radius: 18px; font-size: 14px; word-wrap: break-word; }
.message-sent { background: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.message-received { background: #e9ecef; color: #333; align-self: flex-start; border-bottom-left-radius: 4px; }
.chat-input-area { padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; }
.chat-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; outline: none; }
.chat-input:focus { border-color: #0d6efd; }
.chat-send-btn { background: #0d6efd; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; }
.chat-send-btn:hover { background: #0b5ed7; }
.unread-badge { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 12px; display: none; align-items: center; justify-content: center; }
</style>

<div class="chat-widget">
    <div class="chat-box" id="chatBox">
        <div class="chat-header"><i class="bi bi-chat-dots-fill"></i> Live Chat Support</div>
        <div class="chat-messages" id="chatMessages">
            <div class="text-center text-muted py-4"><i class="bi bi-chat-square-text fs-1"></i><p class="mt-2">Start a conversation</p></div>
        </div>
        <div class="chat-input-area">
            <input type="text" class="chat-input" id="chatInput" placeholder="Type your message..." maxlength="5000">
            <button class="chat-send-btn" id="chatSendBtn">Send</button>
        </div>
    </div>
    <button class="chat-toggle" id="chatToggle"><i class="bi bi-chat-dots-fill"></i><span class="unread-badge" id="unreadBadge">0</span></button>
</div>

<script src="{{ asset('js/chat-client.js') }}"></script>
@endsection