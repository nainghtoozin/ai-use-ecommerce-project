@extends('Admin.layouts.admin')

@section('title', 'Live Chat')

@section('content')
<div class="admin-chat-container">
    <div class="chat-users-list">
        <div class="p-3 border-bottom">
            <h5 class="mb-0"><i class="bi bi-chat-dots-fill"></i> Customer Chats</h5>
        </div>
        <div id="usersList">
            <div class="text-center text-muted p-4">Loading...</div>
        </div>
    </div>
    
    <div class="chat-main">
        <div class="chat-header" id="chatHeader">
            <h5 class="mb-0" id="selectedUserName">Select a customer</h5>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="empty-chat">
                <p><i class="bi bi-chat-square-text fs-1"></i><br>Select a customer to start chatting</p>
            </div>
        </div>
        
        <div class="chat-input-area">
            <input type="text" class="chat-input" id="chatInput" placeholder="Type your reply..." maxlength="5000" disabled>
            <button class="chat-send-btn" id="chatSendBtn" disabled>Send</button>
        </div>
    </div>
</div>

<style>
.admin-chat-container { display: flex; height: calc(100vh - 100px); }
.chat-users-list { width: 280px; border-right: 1px solid #ddd; overflow-y: auto; }
.chat-user-item { padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
.chat-user-item:hover { background: #f8f9fa; }
.chat-user-item.active { background: #e9ecef; }
.chat-main { flex: 1; display: flex; flex-direction: column; }
.chat-header { padding: 15px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
.chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.message { max-width: 70%; padding: 12px 16px; border-radius: 18px; font-size: 14px; }
.message-sent { background: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.message-received { background: #e9ecef; color: #333; align-self: flex-start; border-bottom-left-radius: 4px; }
.chat-input-area { padding: 15px; border-top: 1px solid #ddd; display: flex; gap: 10px; }
.chat-input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; }
.chat-input:focus { border-color: #0d6efd; }
.chat-send-btn { background: #0d6efd; color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; }
.chat-send-btn:hover { background: #0b5ed7; }
.unread-count { background: red; color: white; border-radius: 50%; padding: 2px 6px; font-size: 11px; margin-left: 8px; }
.empty-chat { display: flex; align-items: center; justify-content: center; height: 100%; color: #999; }
</style>

<script src="{{ asset('js/chat-admin.js') }}"></script>
@endsection