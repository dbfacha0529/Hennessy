<?php
include '../web/header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チャット一覧</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }
        
        .chat-list-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .chat-list-header {
            padding: 20px;
            background-color: #4a90e2;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .chat-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            color: inherit;
        }
        
        .chat-item:hover {
            background-color: #f8f9fa;
        }
        
        .chat-item:last-child {
            border-bottom: none;
        }
        
        .chat-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #666;
    flex-shrink: 0;
    margin-right: 15px;
}
        
        .chat-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .chat-last-message {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 10px;
        }
        
        .chat-time {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .chat-unread-badge {
            background-color: #ff3b30;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }
        
        .no-chats {
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }
        
        .no-chats-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .loading {
            padding: 40px;
            text-align: center;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="chat-list-container">
        <div class="chat-list-header">
            チャット
        </div>
        <div id="chat-list-content">
            <div class="loading">
                読み込み中...
            </div>
        </div>
    </div>

    <script>
        // チャット一覧を取得して表示
        async function loadChatList() {
            try {
                const response = await fetch('chat_api.php?action=get_chat_list');
                const data = await response.json();
                
                const container = document.getElementById('chat-list-content');
                
                if (data.success) {
                    if (data.chat_list.length === 0) {
                        container.innerHTML = `
                            <div class="no-chats">
                                <div class="no-chats-icon">💬</div>
                                <p>チャットがまだありません</p>
                                <small>予約完了した女の子とチャットができます</small>
                            </div>
                        `;
                    } else {
                        let html = '';
                        data.chat_list.forEach(chat => {
                            const lastMessage = chat.last_message || 'まだメッセージがありません';
                            const timeText = chat.last_message_at ? formatTime(chat.last_message_at) : '';
                            const unreadBadge = chat.unread_count > 0 
                                ? `<span class="chat-unread-badge">${chat.unread_count > 99 ? '99+' : chat.unread_count}</span>` 
                                : '';
                            
                            const avatarImg = chat.img 
    ? `<img src="../img/${escapeHtml(chat.img)}" alt="${escapeHtml(chat.name)}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">` 
    : `<div class="chat-avatar" style="width: 60px; height: 60px;">👤</div>`;

html += `
    <a href="chat_room.php?room_id=${chat.room_id || ''}&g_login_id=${chat.g_login_id}" class="chat-item">
        ${avatarImg}
                                    <div class="chat-info">
                                        <div class="chat-name">${escapeHtml(chat.name)}</div>
                                        <div class="chat-last-message">${escapeHtml(lastMessage)}</div>
                                    </div>
                                    <div class="chat-meta">
                                        <div class="chat-time">${timeText}</div>
                                        ${unreadBadge}
                                    </div>
                                </a>
                            `;
                        });
                        container.innerHTML = html;
                    }
                } else {
                    container.innerHTML = `
                        <div class="no-chats">
                            <p>エラーが発生しました</p>
                            <small>${data.error || '不明なエラー'}</small>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('チャット一覧取得エラー:', error);
                document.getElementById('chat-list-content').innerHTML = `
                    <div class="no-chats">
                        <p>読み込みに失敗しました</p>
                    </div>
                `;
            }
        }
        
        // 時刻フォーマット
        function formatTime(datetime) {
            const date = new Date(datetime);
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (days === 0) {
                return date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
            } else if (days === 1) {
                return '昨日';
            } else if (days < 7) {
                return `${days}日前`;
            } else {
                return date.toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' });
            }
        }
        
        // HTMLエスケープ
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // ページ読み込み時に実行
        document.addEventListener('DOMContentLoaded', function() {
            loadChatList();
        });
    </script>
    <script src="../web/script.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../web/footer.php'; ?>