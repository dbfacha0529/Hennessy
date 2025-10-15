// chat.js - チャット機能のフロントエンド

class ChatClient {
    constructor(roomId, gLoginId = null) {
        this.roomId = roomId;
        this.gLoginId = gLoginId;
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
        }, 10000);
        
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
            const response = await fetch(`chat_api.php?action=get_messages&room_id=${this.roomId}&last_id=${this.lastMessageId}`);
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
    
    // メッセージ送信
    async sendMessage(content, messageType = 'text', file = null) {
        const formData = new FormData();
        formData.append('room_id', this.roomId);
        formData.append('message_type', messageType);
        formData.append('content', content);
        
        if (file) {
            formData.append('file', file);
        }
        
        try {
            const response = await fetch('chat_api.php?action=send_message', {
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
        
        // 女の子のメッセージの場合はアイコンを追加
        if (message.sender_type === 'girl') {
            const container = document.getElementById('chat-container');
            const girlImg = container.dataset.girlImg;
            
            if (girlImg) {
                const avatar = document.createElement('img');
                avatar.src = `../img/${girlImg}`;
                avatar.className = 'message-avatar';
                avatar.style.cssText = 'width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-right: 10px; flex-shrink: 0;';
                messageDiv.appendChild(avatar);
            }
        }
        
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
                let imagePath = message.file_path || '';
                if (imagePath && !imagePath.startsWith('/') && !imagePath.startsWith('http') && !imagePath.startsWith('..')) {
                    imagePath = '../../img/chat_uploads/' + imagePath;
                }
                contentHtml = `<img src="${imagePath}" alt="画像" class="chat-image" onclick="window.open(this.src, '_blank')">`;
                break;
            case 'video':
                let videoPath = message.file_path || '';
                if (videoPath && !videoPath.startsWith('/') && !videoPath.startsWith('http') && !videoPath.startsWith('..')) {
                    videoPath = '../../img/chat_uploads/' + videoPath;
                }
                contentHtml = `<video src="${videoPath}" controls class="chat-video"></video>`;
                break;
            case 'voice':
                let audioPath = message.file_path || '';
                if (audioPath && !audioPath.startsWith('/') && !audioPath.startsWith('http') && !audioPath.startsWith('..')) {
                    audioPath = '../../img/chat_uploads/' + audioPath;
                }
                contentHtml = `<audio src="${audioPath}" controls class="chat-audio"></audio>`;
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
            // 販促チャットの場合は専用APIを使用
            if (this.gLoginId === 'promotion') {
                const response = await fetch('/HENNESSY/HENNESSY_A/web/promotion/promotion_api.php?action=mark_promotion_read', {
                    method: 'POST',
                    body: formData
                });
                
                // レスポンスがJSONかチェック
                const text = await response.text();
                try {
                    const data = JSON.parse(text);
                    console.log('Promotion API response:', data);
                } catch (e) {
                    console.error('既読API JSONパースエラー:', text.substring(0, 200));
                    return;
                }
            } else {
                // 通常チャット
                const response = await fetch('chat_api.php?action=mark_as_read', {
                    method: 'POST',
                    body: formData
                });
                
                // レスポンスがJSONかチェック
                const text = await response.text();
                try {
                    const data = JSON.parse(text);
                    console.log('Chat API response:', data);
                } catch (e) {
                    console.error('既読API JSONパースエラー:', text.substring(0, 200));
                    return;
                }
            }
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

// 未読バッジ更新(home.phpで使用)
async function updateUnreadBadge() {
    try {
        const response = await fetch('../chat/chat_api.php?action=get_unread_count');
        const data = await response.json();
        
        if (data.success) {
            const badge = document.getElementById('chat-badge');
            if (badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    } catch (error) {
        // エラーは静かに無視
    }
}

// チャット初期化
let chatClient;

document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chat-container');
    
    // チャット画面の場合
    if (chatContainer) {
        const roomId = chatContainer.dataset.roomId;
        const gLoginId = chatContainer.dataset.gLoginId;
        
        if (roomId) {
            chatClient = new ChatClient(roomId, gLoginId);
            chatClient.startPolling();
            
            // 送信フォーム
            const sendForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            
            sendForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const content = messageInput.value.trim();
                if (!content) return;
                
                const success = await chatClient.sendMessage(content);
                if (success) {
                    messageInput.value = '';
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
            });
            
            // ファイル送信
            const fileInput = document.getElementById('file-input');
            if (fileInput) {
                fileInput.addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    
                    let messageType = 'text';
                    if (file.type.startsWith('image/')) {
                        messageType = 'image';
                    } else if (file.type.startsWith('video/')) {
                        messageType = 'video';
                    } else if (file.type.startsWith('audio/')) {
                        messageType = 'voice';
                    } else {
                        alert('サポートされていないファイル形式です');
                        fileInput.value = '';
                        return;
                    }
                    
                    await chatClient.sendMessage('', messageType, file);
                    fileInput.value = '';
                });
            }
        }
        
        // ページを離れる時にポーリング停止
        window.addEventListener('beforeunload', function() {
            if (chatClient) {
                chatClient.stopPolling();
            }
        });
    }
    
    // home.phpの未読バッジ更新
    const chatBadge = document.getElementById('chat-badge');
    if (chatBadge) {
        updateUnreadBadge(); // 初回実行
        setInterval(updateUnreadBadge, 10000);
    }
});