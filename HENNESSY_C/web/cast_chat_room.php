<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];
$user_tel = $_GET['user_tel'] ?? '';

if (empty($user_tel)) {
    header('Location: cast_chat_list.php');
    exit;
}

// 管理者システムアカウントの電話番号
define('ADMIN_SYSTEM_TEL', '00000000000');

// ユーザー情報取得
if ($user_tel === ADMIN_SYSTEM_TEL) {
    // システム管理者の場合
    $user_info = [
        'user_name' => 'システム管理者',
        'login_id' => 'admin_system',
        'tel' => ADMIN_SYSTEM_TEL
    ];
} else {
    // 通常ユーザーの場合
    $sql = "SELECT user_name, login_id, tel FROM users WHERE tel = :tel";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tel' => $user_tel]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        header('Location: cast_chat_list.php');
        exit;
    }
}

// キャスト情報取得
$sql = "SELECT name FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$cast_info = $stmt->fetch(PDO::FETCH_ASSOC);
$cast_name = $cast_info['name'] ?? 'キャスト';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user_info['user_name']) ?>とのチャット</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: #fff5f7;
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
    }

    /* ヘッダー */
    .chat-header {
        background: linear-gradient(135deg, #ffe0e9 0%, #ffeef3 100%);
        padding: 1rem;
        border-bottom: 0.0625rem solid #ffc0cb;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(255, 105, 180, 0.1);
        flex-shrink: 0;
    }

    .back-button {
        font-size: 1.5rem;
        color: #c71585;
        cursor: pointer;
        text-decoration: none;
        transition: color 0.2s;
    }

    .back-button:hover {
        color: #ff1493;
    }

    .user-info {
        flex: 1;
    }

    .user-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: #c71585;
    }

    .user-status {
        font-size: 0.8125rem;
        color: #ff69b4;
    }

    /* メッセージエリア */
    .messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        background-color: #fff5f7;
    }

    /* メッセージバブル */
    .message {
        display: flex;
        gap: 0.5rem;
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(0.625rem);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.sent {
        flex-direction: row-reverse;
    }

    .message-content {
        max-width: 70%;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .message-bubble {
        padding: 0.75rem 1rem;
        border-radius: 1.125rem;
        word-wrap: break-word;
        white-space: pre-wrap;
    }

    .message.sent .message-bubble {
        background-color: #ffe0e9;
        color: #333;
        border-bottom-right-radius: 0.25rem;
        box-shadow: 0 0.0625rem 0.125rem rgba(255, 105, 180, 0.15);
    }

    .message.received .message-bubble {
        background-color: #f5f5f5;
        color: #333;
        border-bottom-left-radius: 0.25rem;
        box-shadow: 0 0.0625rem 0.125rem rgba(0, 0, 0, 0.1);
    }

    .message-time {
        font-size: 0.6875rem;
        color: #999;
        padding: 0 0.5rem;
    }

    .message.sent .message-time {
        text-align: right;
        color: #ff69b4;
    }

    /* 日付区切り */
    .date-divider {
        text-align: center;
        margin: 1rem 0;
    }

    .date-divider span {
        background-color: #ffe0e9;
        color: #c71585;
        padding: 0.25rem 0.75rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
    }

    /* 入力エリア */
    .input-container {
        background-color: #fff;
        padding: 1rem;
        border-top: 0.0625rem solid #ffc0cb;
        display: flex;
        gap: 0.75rem;
        align-items: flex-end;
        flex-shrink: 0;
        box-shadow: 0 -0.125rem 0.25rem rgba(255, 105, 180, 0.05);
    }

    .attachment-button {
        width: 2.5rem;
        height: 2.5rem;
        border: none;
        border-radius: 50%;
        background-color: #ffb6c1;
        color: white;
        font-size: 1.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
        flex-shrink: 0;
        position: relative;
    }

    .attachment-button:hover {
        background-color: #ff69b4;
    }

    .attachment-button input[type="file"] {
        position: absolute;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .message-input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 0.0625rem solid #ffb6c1;
        border-radius: 1.5rem;
        font-size: 0.9375rem;
        resize: none;
        max-height: 6rem;
        font-family: inherit;
        background-color: #fff5f7;
    }

    .message-input:focus {
        outline: none;
        border-color: #ff1493;
        background-color: #fff;
    }

    .send-button {
        width: 2.5rem;
        height: 2.5rem;
        border: none;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff1493 0%, #ff69b4 100%);
        color: white;
        font-size: 1.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, box-shadow 0.2s;
        flex-shrink: 0;
        box-shadow: 0 0.125rem 0.375rem rgba(255, 20, 147, 0.3);
    }

    .send-button:hover {
        transform: scale(1.05);
        box-shadow: 0 0.25rem 0.5rem rgba(255, 20, 147, 0.4);
    }

    .send-button:active {
        transform: scale(0.95);
    }

    .send-button:disabled {
        background: #ddd;
        cursor: not-allowed;
        box-shadow: none;
    }

    /* 画像・動画プレビュー */
    .media-preview {
        max-width: 100%;
        border-radius: 0.5rem;
        margin-top: 0.5rem;
    }

    .message-bubble img,
    .message-bubble video {
        max-width: 100%;
        border-radius: 0.5rem;
        display: block;
    }

    /* アップロード中の表示 */
    .uploading-indicator {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        z-index: 1000;
    }

    .uploading-indicator.active {
        display: block;
    }

    /* ローディング */
    .loading-container {
        display: flex;
        justify-content: center;
        padding: 2rem;
    }

    .spinner {
        border: 0.1875rem solid #ffe0e9;
        border-top: 0.1875rem solid #ff1493;
        border-radius: 50%;
        width: 2rem;
        height: 2rem;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #ff69b4;
    }

    .empty-state i {
        font-size: 3rem;
        color: #ffc0cb;
        margin-bottom: 1rem;
    }

    /* スマホ対応 (480px以下) */
    @media (max-width: 30rem) {
        .chat-header {
            padding: 0.75rem;
        }

        .user-name {
            font-size: 1rem;
        }

        .user-status {
            font-size: 0.75rem;
        }

        .messages-container {
            padding: 0.75rem;
        }

        .message-content {
            max-width: 75%;
        }

        .message-bubble {
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
        }

        .message-time {
            font-size: 0.625rem;
        }

        .input-container {
            padding: 0.75rem;
            gap: 0.5rem;
        }

        .attachment-button,
        .send-button {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 1.125rem;
        }
    }

    /* タブレット対応 (768px以下) */
    @media (max-width: 48rem) {
        .message-content {
            max-width: 75%;
        }
    }
</style>

<div class="chat-header">
    <a href="cast_chat_list.php" class="back-button">
        <i class="bi bi-chevron-left"></i>
    </a>
    <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($user_info['user_name']) ?></div>
        <div class="user-status">オンライン</div>
    </div>
</div>

<div class="messages-container" id="messagesContainer">
    <div class="loading-container">
        <div class="spinner"></div>
    </div>
</div>

<div class="uploading-indicator" id="uploadingIndicator">
    アップロード中...
</div>

<div class="input-container">
    <button class="attachment-button" title="ファイルを添付">
    <i class="bi bi-paperclip"></i>
    <input type="file" id="fileInput" accept="image/*,video/*,audio/*" multiple>
</button>
    <textarea 
        class="message-input" 
        id="messageInput" 
        placeholder="メッセージを入力..." 
        rows="1"
    ></textarea>
    <button class="send-button" id="sendButton" disabled>
        <i class="bi bi-send-fill"></i>
    </button>
</div>

<script>
const userTel = <?= json_encode($user_tel) ?>;
const castName = <?= json_encode($cast_name) ?>;
const userName = <?= json_encode($user_info['user_name']) ?>;
let roomId = null;
let isLoading = false;
let oldestMessageId = null;

// 初期化
async function init() {
    await createOrGetRoom();
    await loadMessages();
    markAsRead();
    scrollToBottom();
    
    // 10秒ごとにメッセージを更新
    setInterval(async () => {
        await loadMessages(false);
        markAsRead();
    }, 10000);
}

// ルーム作成または取得
async function createOrGetRoom() {
    try {
        const formData = new FormData();
        formData.append('user_tel', userTel);
        
        const response = await fetch('./cast_chat_api.php?action=get_or_create_room', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            roomId = data.room.id;
        } else {
            showError('チャットルームの取得に失敗しました');
        }
    } catch (error) {
        console.error('ルーム取得エラー:', error);
        showError('エラーが発生しました');
    }
}

// メッセージ読み込み
async function loadMessages(showLoading = true) {
    if (!roomId || isLoading) return;
    
    isLoading = true;
    
    try {
        const response = await fetch(`./cast_chat_api.php?action=get_messages&room_id=${roomId}&limit=50`);
        const data = await response.json();
        
        if (data.success) {
            renderMessages(data.messages, showLoading);
            if (data.messages.length > 0) {
                oldestMessageId = data.messages[0].id;
            }
        }
    } catch (error) {
        console.error('メッセージ取得エラー:', error);
    } finally {
        isLoading = false;
    }
}

// メッセージをレンダリング

// メッセージをレンダリング
function renderMessages(messages, scrollDown = true) {
    const container = document.getElementById('messagesContainer');
    
    if (messages.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-chat-text"></i>
                <p>まだメッセージがありません<br>最初のメッセージを送信しましょう</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    let lastDate = '';
    
    messages.forEach(msg => {
        const msgDate = new Date(msg.created_at);
        const dateStr = formatDate(msgDate);
        
        // 日付が変わったら区切りを表示
        if (dateStr !== lastDate) {
            html += `
                <div class="date-divider">
                    <span>${dateStr}</span>
                </div>
            `;
            lastDate = dateStr;
        }
        
        const isSent = msg.sender_type === 'girl';
        const timeStr = formatTime(msgDate);
        
        let contentHtml = '';
        if (msg.message_type === 'image') {
            // ファイル名から画像パスを生成
            const imagePath = '/HENNESSY/img/chat_uploads/' + escapeHtml(msg.file_path);
            contentHtml = `<img src="${imagePath}" alt="画像" class="media-preview">`;
        } else if (msg.message_type === 'video') {
            // ファイル名から動画パスを生成
            const videoPath = '/HENNESSY/img/chat_uploads/' + escapeHtml(msg.file_path);
            contentHtml = `<video src="${videoPath}" controls class="media-preview"></video>`;
        } else {
            contentHtml = escapeHtml(msg.content);
        }
        
        html += `
            <div class="message ${isSent ? 'sent' : 'received'}">
                <div class="message-content">
                    <div class="message-bubble">${contentHtml}</div>
                    <div class="message-time">${timeStr}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    if (scrollDown) {
        scrollToBottom();
    }
}

// メッセージ送信
async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !roomId) return;
    
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('message', message);
        formData.append('message_type', 'text');
        
        const response = await fetch('./cast_chat_api.php?action=send_message', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            await loadMessages();
            scrollToBottom();
        } else {
            alert(data.error || 'メッセージの送信に失敗しました');
        }
    } catch (error) {
        console.error('送信エラー:', error);
        alert('エラーが発生しました');
    } finally {
        sendButton.disabled = false;
        input.focus();
    }
}

// ファイルアップロード処理
// ファイルアップロード処理
document.getElementById('fileInput').addEventListener('change', async (e) => {
    const files = e.target.files;
    if (!files || files.length === 0 || !roomId) return;
    
    const uploadIndicator = document.getElementById('uploadingIndicator');
    uploadIndicator.classList.add('active');
    
    for (let file of files) {
        try {
            const formData = new FormData();
            formData.append('room_id', roomId);
            formData.append('file', file);
            
            // ファイルタイプを判定（画像・動画・音声）
            let messageType = 'text';
            if (file.type.startsWith('image/')) {
                messageType = 'image';
            } else if (file.type.startsWith('video/')) {
                messageType = 'video';
            } else if (file.type.startsWith('audio/')) {
                messageType = 'voice';
            } else {
                alert('サポートされていないファイル形式です');
                uploadIndicator.classList.remove('active');
                e.target.value = '';
                continue;
            }
            
            formData.append('message_type', messageType);
            
            const response = await fetch('./cast_chat_upload.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                await loadMessages();
                scrollToBottom();
            } else {
                alert(data.error || 'ファイルのアップロードに失敗しました');
            }
        } catch (error) {
            console.error('アップロードエラー:', error);
            alert('エラーが発生しました');
        }
    }
    
    uploadIndicator.classList.remove('active');
    e.target.value = ''; // ファイル選択をリセット
});

// 既読更新
async function markAsRead() {
    if (!roomId) return;
    
    try {
        const formData = new FormData();
        formData.append('room_id', roomId);
        
        await fetch('./cast_chat_api.php?action=mark_as_read', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('既読更新エラー:', error);
    }
}

// スクロール
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

// 日付フォーマット
function formatDate(date) {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return '今日';
    } else if (date.toDateString() === yesterday.toDateString()) {
        return '昨日';
    } else {
        return `${date.getMonth() + 1}月${date.getDate()}日`;
    }
}

// 時刻フォーマット
function formatTime(date) {
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// エラー表示
function showError(message) {
    const container = document.getElementById('messagesContainer');
    container.innerHTML = `
        <div class="empty-state">
            <i class="bi bi-exclamation-triangle"></i>
            <p>${message}</p>
        </div>
    `;
}

// イベントリスナー
document.getElementById('messageInput').addEventListener('input', (e) => {
    const input = e.target;
    const sendButton = document.getElementById('sendButton');
    
    // 自動高さ調整
    input.style.height = 'auto';
    input.style.height = input.scrollHeight + 'px';
    
    // 送信ボタン有効/無効
    sendButton.disabled = !input.value.trim();
});

document.getElementById('messageInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.getElementById('sendButton').addEventListener('click', sendMessage);

// 初期化実行
init();
</script>