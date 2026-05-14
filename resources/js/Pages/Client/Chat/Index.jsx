import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import ShopLayout from '@/Layouts/ShopLayout';

export default function ChatIndex({ conversations = [] }) {
    const { auth } = usePage().props;
    const [selectedUser, setSelectedUser] = useState(null);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [hasMore, setHasMore] = useState(true);
    const [typingUsers, setTypingUsers] = useState({});
    const messagesEndRef = useRef(null);
    const messagesContainerRef = useRef(null);
    const typingTimeoutRef = useRef(null);
    const [refreshing, setRefreshing] = useState(false);

    const scrollToBottom = useCallback(() => {
        setTimeout(() => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
    }, []);

    useEffect(() => {
        if (selectedUser) {
            loadMessages(selectedUser.id);
            axios.post(`/chat/read/${selectedUser.id}`);
        }
    }, [selectedUser?.id]);

    useEffect(() => {
        if (auth?.user?.id) {
            window.Echo.private(`chat.${auth.user.id}`)
                .listen('MessageSent', (e) => {
                    const msg = e.message;
                    if (selectedUser && (msg.sender_id === selectedUser.id || msg.receiver_id === selectedUser.id)) {
                        setMessages((prev) => [...prev, msg]);
                        scrollToBottom();
                        axios.post(`/chat/read/${selectedUser.id}`);
                    }
                })
                .listen('UserTyping', (e) => {
                    if (selectedUser && e.sender_id === selectedUser.id) {
                        setTypingUsers((prev) => ({ ...prev, [e.sender_id]: true }));
                        clearTimeout(typingTimeoutRef.current);
                        typingTimeoutRef.current = setTimeout(() => {
                            setTypingUsers((prev) => ({ ...prev, [e.sender_id]: false }));
                        }, 3000);
                    }
                });
            return () => {
                window.Echo.leave(`chat.${auth.user.id}`);
                clearTimeout(typingTimeoutRef.current);
            };
        }
    }, [selectedUser, auth?.user?.id, scrollToBottom]);

    async function loadMessages(userId, beforeId) {
        setLoading(true);
        try {
            const url = beforeId ? `/chat/messages/${userId}/${beforeId}` : `/chat/messages/${userId}`;
            const res = await axios.get(url);
            const msgs = res.data.messages;
            setHasMore(res.data.has_more);
            if (beforeId) {
                setMessages((prev) => [...msgs, ...prev]);
            } else {
                setMessages(msgs);
                scrollToBottom();
            }
            await axios.post(`/chat/read/${userId}`);
        } catch (err) {
            console.error('Failed to load messages:', err);
        } finally {
            setLoading(false);
        }
    }

    async function handleSend(e) {
        e.preventDefault();
        if (!newMessage.trim() || !selectedUser) return;
        const text = newMessage.trim();
        setNewMessage('');
        try {
            const res = await axios.post('/chat/send', {
                receiver_id: selectedUser.id,
                message: text,
            });
            setMessages((prev) => [...prev, res.data.message]);
            scrollToBottom();
        } catch (err) {
            console.error('Failed to send message:', err);
        }
    }

    function handleTyping() {
        if (selectedUser) {
            axios.post('/chat/typing', { receiver_id: selectedUser.id, is_typing: true });
            clearTimeout(typingTimeoutRef.current);
            typingTimeoutRef.current = setTimeout(() => {
                axios.post('/chat/typing', { receiver_id: selectedUser.id, is_typing: false });
            }, 2000);
        }
    }

    function handleScroll() {
        const el = messagesContainerRef.current;
        if (el && el.scrollTop === 0 && hasMore && !loading && selectedUser) {
            const firstMsg = messages[0];
            if (firstMsg) {
                loadMessages(selectedUser.id, firstMsg.id);
            }
        }
    }

    return (
        <ShopLayout>
            <Head title="Chat" />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="bg-white rounded-lg border border-gray-200 overflow-hidden h-[calc(100vh-12rem)] flex">
                    {/* Conversations List */}
                    <div className="w-72 border-r border-gray-200 flex-shrink-0 overflow-y-auto">
                        <div className="p-4 border-b border-gray-200">
                            <h2 className="font-semibold text-gray-900">Conversations</h2>
                        </div>
                        {conversations.length === 0 ? (
                            <p className="p-4 text-sm text-gray-500">No conversations yet.</p>
                        ) : conversations.map((conv) => (
                            <button
                                key={conv.user.id}
                                onClick={() => setSelectedUser(conv.user)}
                                className={`w-full text-left p-4 hover:bg-gray-50 border-b border-gray-100 transition-colors ${selectedUser?.id === conv.user.id ? 'bg-blue-50' : ''}`}
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-medium text-sm text-gray-900">{conv.user.name}</span>
                                    {conv.unread_count > 0 && (
                                        <span className="bg-blue-600 text-white text-xs rounded-full px-2 py-0.5">{conv.unread_count}</span>
                                    )}
                                </div>
                                {conv.last_message && (
                                    <p className="text-xs text-gray-500 mt-1 truncate">{conv.last_message.message}</p>
                                )}
                            </button>
                        ))}
                    </div>

                    {/* Chat Area */}
                    <div className="flex-1 flex flex-col">
                        {selectedUser ? (
                            <>
                                {/* Chat Header */}
                                <div className="p-4 border-b border-gray-200 flex items-center justify-between">
                                    <h3 className="font-semibold text-gray-900">{selectedUser.name}</h3>
                                </div>

                                {/* Messages */}
                                <div ref={messagesContainerRef} onScroll={handleScroll} className="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
                                    {loading && <p className="text-center text-sm text-gray-400">Loading...</p>}
                                    {!loading && messages.length === 0 && (
                                        <p className="text-center text-sm text-gray-400">No messages yet. Say hello!</p>
                                    )}
                                    {messages.map((msg) => {
                                        const isOwn = msg.sender_id === auth?.user?.id;
                                        return (
                                            <div key={msg.id} className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}>
                                                <div className={`max-w-[70%] px-4 py-2 rounded-lg ${isOwn ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-900'}`}>
                                                    {msg.reply_to && (
                                                        <div className={`text-xs mb-1 p-1 rounded ${isOwn ? 'bg-blue-500' : 'bg-gray-100'}`}>
                                                            Replying to: {msg.reply_to.message}
                                                        </div>
                                                    )}
                                                    <p className="text-sm">{msg.message}</p>
                                                    <p className={`text-xs mt-1 ${isOwn ? 'text-blue-200' : 'text-gray-400'}`}>
                                                        {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {typingUsers[selectedUser.id] && (
                                        <div className="flex justify-start">
                                            <div className="bg-white border border-gray-200 rounded-lg px-4 py-2 text-sm text-gray-500 italic">typing...</div>
                                        </div>
                                    )}
                                    <div ref={messagesEndRef} />
                                </div>

                                {/* Input */}
                                <form onSubmit={handleSend} className="p-4 border-t border-gray-200 flex gap-2">
                                    <input
                                        type="text"
                                        value={newMessage}
                                        onChange={(e) => setNewMessage(e.target.value)}
                                        onKeyUp={handleTyping}
                                        placeholder="Type a message..."
                                        className="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button type="submit" disabled={!newMessage.trim()}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                        Send
                                    </button>
                                </form>
                            </>
                        ) : (
                            <div className="flex-1 flex items-center justify-center text-gray-400">
                                <div className="text-center">
                                    <svg className="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                    <p className="mt-2">Select a conversation to start chatting</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ShopLayout>
    );
}
