import { useState, useCallback } from 'react';

export default function TypingIndicator({ receiverId, currentUserId }) {
    const [isTyping, setIsTyping] = useState({});

    const handleTyping = useCallback((e) => {
        if (e.receiver_id === currentUserId) {
            setIsTyping((prev) => ({ ...prev, [e.sender_id]: e.is_typing }));
        }
    }, [currentUserId]);

    return { isTyping, handleTyping };
}
