<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    header('Location: contact_chat.php');
    exit;
}

// ルーム情報を取得
try {
    $pdo = dbConnect();
    $sql = "SELECT r.*, u.user_name, u.tel 
            FROM chat_rooms r 
            LEFT JOIN users u ON r.user_tel COLLATE utf8mb4_general_ci = u.tel 
            WHERE r.id = :id AND r.g_login_id = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        header('Location: contact_chat.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: contact_chat.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>お問い合わせチャット - <?= htmlspecialchars($room['user_name'] ?? 'ユーザー') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
            background-color: #f5f5f5;
            overflow: hidden;
        }
        
        #chat-container {
            max-width: 1000px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: white;
        }
        
        #chat-header {
            padding: 15px 20px;
            background-color: #00753aff;
            color: white;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #back-button {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            margin-right: 15px;
            padding: 0;
        }
        
        #chat-title {
            font-weight: bold;
            font-size: 18px;
        }
        
        #chat-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 2px;
        }
        
        #chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fafafa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            max-width: 70%;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-girl {
            align-self: flex-end;
            flex-direction: row-reverse;
            margin-left: auto;
        }
        
        .message-user {
            align-self: flex-start;
        }
        
        .message-content {
            display: flex;
            flex-direction: column;
        }
        
        .message-content p {
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.4;
            white-space: pre-wrap;
            margin: 0;
            background-color: #f0f0f0;
            color: #000000;
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
            padding: 0 5px;
        }
        
        .message-girl .message-time {
            text-align: right;
        }
        
        .chat-image, .chat-video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            display: block;
            margin-bottom: 5px;
        }
        
        #chat-input-area {
            padding: 15px 20px;
            background-color: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        #message-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            outline: none;
            resize: none;
            max-height: 100px;
            overflow-y: auto;
        }
        
        #message-input:focus {
            border-color: #4a90e2;
        }
        
        #char-counter {
            position: absolute;
            bottom: -20px;
            right: 10px;
            font-size: 11px;
            color: #999;
        }
        
        .attach-button {
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .attach-button:hover {
            background-color: #5a6268;
        }
        
        .send-button {
            padding: 10px 20px;
            background-color: #008d46ff;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .send-button:hover {
            background-color: #00743aff;
        }
        
        .send-button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        #file-input {
            display: none;
        }
        
        .file-preview {
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-preview img, .file-preview video {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-remove {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        #chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        #chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        #chat-messages::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div id="chat-container" data-room-id="<?= htmlspecialchars($room_id) ?>">
        <div id="chat-header">
            <button id="back-button" onclick="location.href='contact_chat.php'">←</button>
            <div>
                <div id="chat-title"><?= htmlspecialchars($room['user_name'] ?? 'ユーザー') ?></div>
                <div id="chat-subtitle"><?= htmlspecialchars($room['tel'] ?? '') ?></div>
            </div>
        </div>
        
        <div id="chat-messages">
            <div class="loading">読み込み中...</div>
        </div>
        
        <div id="chat-input-area">
            <form id="message-form" style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                <div id="file-preview-container"></div>
                
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <input type="file" id="file-input" accept="image/*,video/*" multiple>
                    
                    <button type="button" class="attach-button" onclick="document.getElementById('file-input').click()">
                        <i class="bi bi-paperclip"></i>
                        <span>添付</span>
                    </button>
                    
                    <div style="flex: 1; position: relative;">
                        <textarea 
                            id="message-input" 
                            placeholder="メッセージを入力..."
                            rows="1"
                            autocomplete="off"
                        ></textarea>
                        <span id="char-counter">0/500</span>
                    </div>
                    
                    <button type="submit" class="send-button">送信</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const roomId = document.getElementById('chat-container').dataset.roomId;
        const messageInput = document.getElementById('message-input');
        const charCounter = document.getElementById('char-counter');
        const fileInput = document.getElementById('file-input');
        const filePreviewContainer = document.getElementById('file-preview-container');
        const messageForm = document.getElementById('message-form');
        let selectedFiles = [];
        
        // 文字数カウンター
        messageInput.addEventListener('input', function() {
            const length = this.value.length;
            charCounter.textContent = `${length}/500`;
            
            if (length > 500) {
                charCounter.style.color = '#dc3545';
            } else {
                charCounter.style.color = '#999';
            }
            
            // 自動リサイズ
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        
        // ファイル選択時の処理
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            files.forEach(file => {
                // ファイルサイズチェック（10MB）
                if (file.size > 10 * 1024 * 1024) {
                    alert('ファイルサイズは10MB以下にしてください');
                    return;
                }
                
                selectedFiles.push(file);
                displayFilePreview(file);
            });
            
            fileInput.value = '';
        });
        
        // ファイルプレビュー表示
        function displayFilePreview(file) {
            const preview = document.createElement('div');
            preview.className = 'file-preview';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                let mediaElement;
                if (file.type.startsWith('image/')) {
                    mediaElement = document.createElement('img');
                    mediaElement.src = e.target.result;
                } else if (file.type.startsWith('video/')) {
                    mediaElement = document.createElement('video');
                    mediaElement.src = e.target.result;
                    mediaElement.controls = false;
                }
                
                const info = document.createElement('div');
                info.className = 'file-preview-info';
                info.innerHTML = `
                    <div style="font-size: 14px; font-weight: bold;">${file.name}</div>
                    <div style="font-size: 12px; color: #666;">${(file.size / 1024).toFixed(1)} KB</div>
                `;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'file-preview-remove';
                removeBtn.innerHTML = '<i class="bi bi-x"></i>';
                removeBtn.type = 'button';
                removeBtn.onclick = function() {
                    selectedFiles = selectedFiles.filter(f => f !== file);
                    preview.remove();
                };
                
                preview.appendChild(mediaElement);
                preview.appendChild(info);
                preview.appendChild(removeBtn);
                filePreviewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
        
        // メッセージ送信
        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            
            if (!message && selectedFiles.length === 0) {
                return;
            }
            
            if (message.length > 500) {
                alert('メッセージは500文字以内で入力してください');
                return;
            }
            
            const formData = new FormData();
            formData.append('room_id', roomId);
            formData.append('message', message);
            formData.append('sender_type', 'girl');
            
            selectedFiles.forEach((file, index) => {
                formData.append(`files[]`, file);
            });
            
            try {
                const response = await fetch('api/send_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageInput.value = '';
                    charCounter.textContent = '0/500';
                    selectedFiles = [];
                    filePreviewContainer.innerHTML = '';
                    messageInput.style.height = 'auto';
                    
                    // メッセージ再読み込み
                    loadMessages();
                } else {
                    alert('送信に失敗しました: ' + (result.error || '不明なエラー'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('送信中にエラーが発生しました');
            }
        });
        
        // メッセージ読み込み
        async function loadMessages() {
            try {
                const response = await fetch(`api/get_messages.php?room_id=${roomId}`);
                const result = await response.json();
                
                if (result.success) {
                    displayMessages(result.messages);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }
        
        // メッセージ表示
        function displayMessages(messages) {
            const messagesContainer = document.getElementById('chat-messages');
            messagesContainer.innerHTML = '';
            
            messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message message-${msg.sender_type}`;
                
                let content = '';
                
                // メディアファイル
                if (msg.message_type === 'image' && msg.file_path) {
                    content += `<img src="${msg.file_path}" class="chat-image" onclick="window.open('${msg.file_path}', '_blank')">`;
                } else if (msg.message_type === 'video' && msg.file_path) {
                    content += `<video src="${msg.file_path}" class="chat-video" controls></video>`;
                }
                
                // テキスト
                if (msg.content) {
                    content += `<p>${escapeHtml(msg.content)}</p>`;
                }
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        ${content}
                        <div class="message-time">${formatTime(msg.created_at)}</div>
                    </div>
                `;
                
                messagesContainer.appendChild(messageDiv);
            });
            
            // スクロールを最下部へ
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleString('ja-JP', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // 初回読み込み
        loadMessages();
        
        // 定期的に更新（5秒ごと）
        setInterval(loadMessages, 5000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>