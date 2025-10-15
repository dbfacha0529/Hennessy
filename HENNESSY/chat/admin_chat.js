// admin_chat.js - 管理者用チャット機能

class AdminChatClient {
    constructor(roomId) {
        this.roomId = roomId;
        this.lastMessageId = 0;
        this.pollingInterval = null;
        this.isPolling = false;
    }
    
    // ポーリング開始
    startPolling() {
        if (this.isPolling) return;
        
        this.isPolling = true;
        this.pollingInterval = setInterval(() => {
            this.fetchNewMessages();
        }, 5000);
        
        // 初回実行
        this.fetchNewMessages();
    }
    
    // ポーリング停止
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.isPolling = false;
    }
    
    // 新着メッセージ取得
    async fetchNewMessages() {
        try {
            const response = await fetch(`/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_support_messages&room_id=${this.roomId}&last_id=${this.lastMessageId}`);
            const data = await response.json();
            
            if (data.success) {
                // 初回の「読み込み中」を削除
                const messagesContainer = document.getElementById('chat-messages');
                const loading = messagesContainer.querySelector('.loading');
                if (loading) {
                    loading.remove();
                }
                
                if (data.has_new) {
                    data.messages.forEach(message => {
                        this.displayMessage(message);
                        this.lastMessageId = Math.max(this.lastMessageId, message.id);
                    });
                    
                    // 新着メッセージがあったら既読にする
                    this.markAsRead();
                    
                    // スクロールを最下部へ
                    this.scrollToBottom();
                }
            }
        } catch (error) {
            console.error('メッセージ取得エラー:', error);
        }
    }
    
    // メッセージ送信（管理者として）
    async sendMessage(content) {
        const formData = new FormData();
        formData.append('room_id', this.roomId);
        formData.append('message_type', 'text');
        formData.append('content', content);
        
        try {
            const response = await fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_send_support_message', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayMessage(data.message);
                this.lastMessageId = Math.max(this.lastMessageId, data.message.id);
                this.scrollToBottom();
                return true;
            }
        } catch (error) {
            console.error('送信エラー:', error);
        }
        
        return false;
    }
    
    // メッセージを画面に表示
    displayMessage(message) {
        const messagesContainer = document.getElementById('chat-messages');
        
        // 初回の「読み込み中」を削除
        const loading = messagesContainer.querySelector('.loading');
        if (loading) {
            loading.remove();
        }
        
        // 既に表示されているかチェック
        if (document.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }
            
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${message.sender_type}`;
        messageDiv.dataset.messageId = message.id;
        
        // メッセージコンテンツラッパー
        const contentWrapper = document.createElement('div');
        contentWrapper.className = 'message-content';
        
        const time = new Date(message.created_at).toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        let contentHtml = '';
        
        switch (message.message_type) {
            case 'text':
                contentHtml = `<p>${this.escapeHtml(message.content)}</p>`;
                break;
            case 'image':
                contentHtml = `<img src="/HENNESSY/HENNESSY/chat/${message.file_path}" alt="画像" class="chat-image" onclick="window.open(this.src, '_blank')">`;
                break;
            case 'video':
                contentHtml = `<video src="/HENNESSY/HENNESSY/chat/${message.file_path}" controls class="chat-video"></video>`;
                break;
            case 'voice':
                contentHtml = `<audio src="/HENNESSY/HENNESSY/chat/${message.file_path}" controls class="chat-audio"></audio>`;
                break;
        }
        
        contentWrapper.innerHTML = `
            ${contentHtml}
            <span class="message-time">${time}</span>
        `;
        
        messageDiv.appendChild(contentWrapper);
        messagesContainer.appendChild(messageDiv);
    }
    
    // 既読にする
    async markAsRead() {
        const formData = new FormData();
        formData.append('room_id', this.roomId);
        
        try {
            await fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_mark_support_read', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('既読更新エラー:', error);
        }
    }
    
    // スクロールを最下部へ
    scrollToBottom() {
        const messagesContainer = document.getElementById('chat-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // HTMLエスケープ
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// チャット初期化
let adminChatClient;

document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chat-container');
    
    // チャット画面の場合
    if (chatContainer) {
        const roomId = chatContainer.dataset.roomId;
        
        if (roomId) {
            adminChatClient = new AdminChatClient(roomId);
            adminChatClient.startPolling();
            
            // 送信フォーム
            const sendForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            
            sendForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const content = messageInput.value.trim();
                if (!content) return;
                
                const success = await adminChatClient.sendMessage(content);
                if (success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                }
            });
            
            // 文字数カウンター
            messageInput.addEventListener('input', function() {
                const counter = document.getElementById('char-counter');
                if (counter) {
                    const length = this.value.length;
                    counter.textContent = `${length}/500`;
                    if (length > 500) {
                        counter.style.color = 'red';
                    } else {
                        counter.style.color = '#999';
                    }
                }
                
                // 自動リサイズ
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
            
            // Enterキーで送信（Shift+Enterで改行）
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendForm.requestSubmit();
                }
            });
        }
        
        // ページを離れる時にポーリング停止
        window.addEventListener('beforeunload', function() {
            if (adminChatClient) {
                adminChatClient.stopPolling();
            }
        });
    }
});