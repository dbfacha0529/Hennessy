<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$admin = $_SESSION['ADMIN'];
?>

<!doctype html>
<html lang="ja">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>お問い合わせチャット - Hennessy Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="./css/admin_home.css" rel="stylesheet">
    <style>
        .chat-list-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00753aff 0%, #008d46ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
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
        
        .chat-tel {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
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
    <!-- サイドバー -->
    <div class="sidebar">
        <div class="logo-section">
            <img src="../../img/logo/adminlogo.png" alt="Logo">
            <h4>HENNESSY</h4>
            <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
        </div>
        
        <nav class="nav-menu">
            <a href="admin_home.php" class="nav-item">
                <i class="bi bi-speedometer2"></i>
                <span>ダッシュボード</span>
            </a>
            <a href="admin_reservations.php" class="nav-item">
                <i class="bi bi-calendar-check"></i>
                <span>予約管理</span>
            </a>
            <a href="admin_girls.php" class="nav-item">
                <i class="bi bi-people"></i>
                <span>キャスト管理</span>
            </a>
            <a href="admin_customers.php" class="nav-item">
                <i class="bi bi-person-badge"></i>
                <span>顧客管理</span>
            </a>
            <a href="contact_chat.php" class="nav-item active">
                <i class="bi bi-headset"></i>
                <span>お問い合わせチャット</span>
                <span class="badge" id="support-chat-badge" style="display: none;">0</span>
            </a>
            <a href="admin_sales.php" class="nav-item">
                <i class="bi bi-graph-up"></i>
                <span>売上レポート</span>
            </a>
            <a href="admin_settings.php" class="nav-item">
                <i class="bi bi-gear"></i>
                <span>設定</span>
            </a>
        </nav>
        
        <div class="logout-section">
            <a href="admin_logout.php" class="nav-item logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>ログアウト</span>
            </a>
        </div>
    </div>

    <!-- メインコンテンツ -->
    <div class="main-content">
        <!-- ヘッダー -->
        <header class="top-header">
            <h1><i class="bi bi-headset"></i> お問い合わせチャット</h1>
            <div class="header-info">
                <span class="admin-name">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($admin['admin_name']) ?>
                </span>
            </div>
        </header>

        <!-- チャット一覧 -->
        <div class="content-section">
            <div class="chat-list-container">
                <div id="chat-list-content">
                    <div class="loading">
                        読み込み中...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // お問い合わせチャット一覧を取得
        async function loadSupportChats() {
            try {
                const response = await fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_support_rooms');
                const data = await response.json();
                
                const container = document.getElementById('chat-list-content');
                
                if (data.success) {
                    if (data.rooms.length === 0) {
                        container.innerHTML = `
                            <div class="no-chats">
                                <div class="no-chats-icon">💬</div>
                                <p>お問い合わせはまだありません</p>
                            </div>
                        `;
                    } else {
                        let html = '';
                        data.rooms.forEach(room => {
                            const lastMessage = room.last_message || 'まだメッセージがありません';
                            const timeText = room.last_message_at ? formatTime(room.last_message_at) : '';
                            const unreadBadge = room.unread_count > 0 
                                ? `<span class="chat-unread-badge">${room.unread_count > 99 ? '99+' : room.unread_count}</span>` 
                                : '';
                            
                            const userName = room.user_name || '名前未設定';
                            const userTel = room.tel || '';
                            const initials = userName.substring(0, 2).toUpperCase();
                            
                            html += `
                                <a href="contact_chat_room.php?room_id=${room.id}" class="chat-item">
                                    <div class="chat-avatar">${escapeHtml(initials)}</div>
                                    <div class="chat-info">
                                        <div class="chat-name">${escapeHtml(userName)}</div>
                                        <div class="chat-tel">${escapeHtml(userTel)}</div>
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
                            <small>${escapeHtml(data.error || '不明なエラー')}</small>
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
        
        // 未読バッジ更新
        async function updateUnreadBadge() {
            try {
                const response = await fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_support_unread_count');
                const data = await response.json();
                
                if (data.success) {
                    const badge = document.getElementById('support-chat-badge');
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
                console.error('未読数取得エラー:', error);
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
            loadSupportChats();
            updateUnreadBadge();
            
            // 10秒ごとに自動更新
            setInterval(() => {
                loadSupportChats();
                updateUnreadBadge();
            }, 10000);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>