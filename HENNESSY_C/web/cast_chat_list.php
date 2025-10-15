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
    <title>チャット一覧 - <?= htmlspecialchars($cast_name) ?></title>
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
        padding-bottom: 11vh;
    }

    /* ヘッダー */
    .chat-header {
        background: linear-gradient(135deg, #ffe0e9 0%, #ffeef3 100%);
        padding: 1rem;
        border-bottom: 0.0625rem solid #ffc0cb;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 0.125rem 0.25rem rgba(255, 105, 180, 0.1);
    }

    .chat-header h1 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #c71585;
    }

    /* 検索バー */
    .search-bar {
        padding: 1rem;
        background-color: #fff;
        border-bottom: 0.0625rem solid #ffc0cb;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 0.0625rem solid #ffb6c1;
        border-radius: 1.5rem;
        font-size: 0.9375rem;
        background-color: #fff5f7;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%23ff69b4" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>');
        background-repeat: no-repeat;
        background-position: 0.75rem center;
        background-size: 1rem;
    }

    .search-input:focus {
        outline: none;
        border-color: #ff1493;
        background-color: #fff;
    }

    /* 新規ユーザーセクション */
    .new-users-section {
        background-color: #fff0f5;
        padding: 0.5rem 0;
        border-bottom: 0.0625rem solid #ffc0cb;
    }

    .section-header {
        padding: 0 1rem 0.25rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #ff1493;
    }

    .section-count {
        font-size: 0.625rem;
        color: #ff69b4;
        background-color: #ffe0e9;
        padding: 0.0625rem 0.375rem;
        border-radius: 0.625rem;
    }

    .users-scroll-container {
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 0.25rem 0;
    }

    .users-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .users-list {
        display: flex;
        gap: 0.75rem;
        padding: 0 1rem;
        min-width: min-content;
    }

    .user-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        min-width: 3.5rem;
        transition: opacity 0.2s;
    }

    .user-item:active {
        opacity: 0.7;
    }

    .user-avatar {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        background: linear-gradient(135deg, #ff1493 0%, #ff69b4 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        font-weight: 600;
        flex-shrink: 0;
        box-shadow: 0 0.125rem 0.375rem rgba(255, 20, 147, 0.3);
    }

    .user-name {
        font-size: 0.625rem;
        color: #333;
        text-align: center;
        max-width: 3.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* システム管理者用の背景色 */
    .chat-item.system-admin {
        background-color: #f5f5f5;
    }

    .chat-item.system-admin:hover {
        background-color: #eeeeee;
    }

    .chat-item.system-admin:active {
        background-color: #e0e0e0;
    }

    /* 空状態 */
    .empty-new-users {
        text-align: center;
        padding: 0.75rem;
        color: #999;
        font-size: 0.75rem;
    }

    /* チャットリスト */
    .chat-list {
        background-color: #fff;
    }

    .chat-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 0.0625rem solid #ffe0e9;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        transition: background-color 0.2s;
        position: relative;
    }

    .chat-item:hover {
        background-color: #fff5f7;
    }

    .chat-item:active {
        background-color: #ffe0e9;
    }

    /* チャット情報 */
    .chat-info {
        flex: 1;
        min-width: 0;
        margin-right: 0.5rem;
    }

    .chat-info-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
    }

    .chat-user-name-wrapper {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        flex: 1;
        min-width: 0;
    }

    .chat-user-name {
        font-size: 1rem;
        font-weight: 600;
        color: #c71585;
    }

    .reserve-history-btn {
        background: none;
        border: none;
        color: #ff69b4;
        font-size: 1.125rem;
        cursor: pointer;
        padding: 0.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s, color 0.2s;
        flex-shrink: 0;
    }

    .reserve-history-btn:hover {
        color: #ff1493;
        transform: scale(1.1);
    }

    .reserve-history-btn:active {
        transform: scale(0.95);
    }

    .chat-time {
        font-size: 0.75rem;
        color: #ff69b4;
        flex-shrink: 0;
        margin-left: 0.5rem;
    }

    .last-message {
        font-size: 0.875rem;
        color: #666;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* 未読バッジ */
    .unread-badge {
        background: linear-gradient(135deg, #ff1493 0%, #ff69b4 100%);
        color: white;
        border-radius: 50%;
        min-width: 1.25rem;
        height: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        padding: 0 0.375rem;
        margin-left: 0.5rem;
        box-shadow: 0 0.125rem 0.375rem rgba(255, 20, 147, 0.3);
    }

    /* ソースバッジ */
    .source-badge {
        display: inline-block;
        padding: 0.125rem 0.5rem;
        border-radius: 0.75rem;
        font-size: 0.6875rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .source-favorite {
        background-color: #ffe0e9;
        color: #c71585;
    }

    .source-reserve {
        background-color: #ffeef3;
        color: #ff1493;
    }

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #ff69b4;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ffc0cb;
        margin-bottom: 1rem;
    }

    .empty-state p {
        font-size: 0.9375rem;
        line-height: 1.6;
    }

    /* ローディング */
    .loading {
        text-align: center;
        padding: 2rem;
        color: #ff69b4;
    }

    .spinner {
        border: 0.25rem solid #ffe0e9;
        border-top: 0.25rem solid #ff1493;
        border-radius: 50%;
        width: 2.5rem;
        height: 2.5rem;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* スマホ対応 (480px以下) */
    @media (max-width: 30rem) {
        .chat-header h1 {
            font-size: 1.125rem;
        }

        .chat-user-name {
            font-size: 0.9375rem;
        }

        .last-message {
            font-size: 0.8125rem;
        }

        .chat-time {
            font-size: 0.6875rem;
        }

        .unread-badge {
            min-width: 1.125rem;
            height: 1.125rem;
            font-size: 0.6875rem;
        }

        .source-badge {
            font-size: 0.625rem;
            padding: 0.0625rem 0.375rem;
        }

        .user-avatar {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 0.8125rem;
        }

        .user-name {
            font-size: 0.5625rem;
        }

        .reserve-history-btn {
            font-size: 1rem;
        }
    }

    /* タブレット対応 (768px以下) */
    @media (max-width: 48rem) {
        .chat-header h1 {
            font-size: 1.1875rem;
        }
    }
</style>

<div class="chat-header">
    <h1><i class="bi bi-chat-dots"></i> チャット</h1>
</div>

<div class="search-bar">
    <input type="text" class="search-input" id="searchInput" placeholder="ユーザー名で検索">
</div>

<!-- ご利用済みユーザー一覧 -->
<div class="new-users-section" id="reserveUsersSection">
    <div class="section-header">
        <span class="section-title">ご利用済みユーザー</span>
        <span class="section-count" id="reserveUsersCount">0</span>
    </div>
    <div class="users-scroll-container">
        <div class="users-list" id="reserveUsersList">
            <div class="loading">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- お気に入りしてくれたユーザー一覧 -->
<div class="new-users-section" id="favoriteUsersSection">
    <div class="section-header">
        <span class="section-title">お気に入りしてくれたユーザー</span>
        <span class="section-count" id="favoriteUsersCount">0</span>
    </div>
    <div class="users-scroll-container">
        <div class="users-list" id="favoriteUsersList">
            <div class="loading">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- 既存チャットルーム一覧 -->
<div id="chatListContainer" class="chat-list">
    <div class="loading">
        <div class="spinner"></div>
        <p>読み込み中...</p>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
let allChatUsers = [];
let reserveUsers = [];
let favoriteUsers = [];

// 初期データ取得
async function init() {
    await Promise.all([
        loadNewUsers(),
        loadChatUsers()
    ]);
}

// 新規ユーザー（チャットルーム未作成）取得
async function loadNewUsers() {
    try {
        const response = await fetch('./cast_chat_api.php?action=get_new_users');
        const text = await response.text();
        
        console.log('新規ユーザーAPI レスポンス:', text.substring(0, 200));
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON パースエラー:', text);
            renderNewUsers('reserve', []);
            renderNewUsers('favorite', []);
            return;
        }
        
        if (data.success) {
            reserveUsers = data.reserve_users || [];
            favoriteUsers = data.favorite_users || [];
            
            renderNewUsers('reserve', reserveUsers);
            renderNewUsers('favorite', favoriteUsers);
        } else {
            console.error('API エラー:', data.error);
        }
    } catch (error) {
        console.error('新規ユーザー取得エラー:', error);
        renderNewUsers('reserve', []);
        renderNewUsers('favorite', []);
    }
}

// 新規ユーザーをレンダリング
function renderNewUsers(type, users) {
    const listId = type === 'reserve' ? 'reserveUsersList' : 'favoriteUsersList';
    const countId = type === 'reserve' ? 'reserveUsersCount' : 'favoriteUsersCount';
    const container = document.getElementById(listId);
    const countElement = document.getElementById(countId);
    
    countElement.textContent = users.length;
    
    if (users.length === 0) {
        container.innerHTML = '<div class="empty-new-users">該当するユーザーはいません</div>';
        return;
    }
    
    container.innerHTML = users.map(user => {
        const initial = user.user_name.charAt(0);
        return `
            <a href="cast_chat_room.php?user_tel=${encodeURIComponent(user.tel)}" class="user-item">
                <div class="user-avatar">${escapeHtml(initial)}</div>
                <div class="user-name">${escapeHtml(user.user_name)}</div>
            </a>
        `;
    }).join('');
}

// 既存チャットユーザー取得
async function loadChatUsers() {
    try {
        const response = await fetch('./cast_chat_api.php?action=get_chat_users');
        const text = await response.text();
        
        console.log('チャットユーザーAPI レスポンス:', text.substring(0, 200));
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON パースエラー:', text);
            showError('データの取得に失敗しました');
            return;
        }
        
        if (data.success) {
            allChatUsers = data.users;
            renderChatList(allChatUsers);
        } else {
            showError('データの取得に失敗しました: ' + (data.error || ''));
        }
    } catch (error) {
        console.error('チャットユーザー取得エラー:', error);
        showError('エラーが発生しました');
    }
}

// チャットリストをレンダリング
function renderChatList(users) {
    const container = document.getElementById('chatListContainer');
    
    if (users.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-chat-text"></i>
                <p>チャット可能なユーザーがいません<br>お気に入り登録または予約完了したユーザーとチャットできます</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = users.map(user => {
        const lastMessage = user.last_message || 'まだメッセージがありません';
        const timeStr = user.last_message_at ? formatTime(user.last_message_at) : '';
        const unreadBadge = user.unread_count > 0 ? 
            `<span class="unread-badge">${user.unread_count > 99 ? '99+' : user.unread_count}</span>` : '';
        
        // システム管理者かどうかを判定
        const isSystemAdmin = user.source === 'system';
        const systemAdminClass = isSystemAdmin ? ' system-admin' : '';
        
        // バッジを配列で管理(システム管理者以外)
        let badges = [];
        if (!isSystemAdmin) {
            if (user.is_favorite == 1 || user.is_favorite === true) {
                badges.push('<span class="source-badge source-favorite">♥お気に入り</span>');
            }
            if (user.is_reserve == 1 || user.is_reserve === true || user.is_reserve > 0) {
                badges.push('<span class="source-badge source-reserve">★ご利用済み</span>');
            }
        }
        const sourceBadges = badges.join('');
        
        return `
            <div class="chat-item${systemAdminClass}">
                <div class="chat-info" onclick="goToChat('${escapeHtml(user.tel)}')">
                    <div class="chat-info-top">
                        <div class="chat-user-name-wrapper">
                            <span class="chat-user-name">${escapeHtml(user.user_name)}</span>
                            ${!isSystemAdmin ? `<button class="reserve-history-btn" onclick="event.stopPropagation(); goToReserveHistory('${escapeHtml(user.user_name)}');" title="予約履歴を見る">
                                <i class="bi bi-calendar-check"></i>
                            </button>` : ''}
                            ${sourceBadges}
                        </div>
                        <span class="chat-time">${timeStr}</span>
                    </div>
                    <div class="last-message">
                        ${escapeHtml(lastMessage)}
                        ${unreadBadge}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// チャットルームへ遷移
function goToChat(tel) {
    window.location.href = `cast_chat_room.php?user_tel=${encodeURIComponent(tel)}`;
}

// 予約履歴ページへ遷移
function goToReserveHistory(userName) {
    window.location.href = `cast_reserve_list.php?search_user=${encodeURIComponent(userName)}`;
}

// 検索機能
document.getElementById('searchInput').addEventListener('input', (e) => {
    const keyword = e.target.value.toLowerCase();
    const filtered = allChatUsers.filter(user => 
        user.user_name.toLowerCase().includes(keyword) ||
        user.login_id.toLowerCase().includes(keyword)
    );
    renderChatList(filtered);
});

// 時刻フォーマット
function formatTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'たった今';
    if (diffMins < 60) return `${diffMins}分前`;
    if (diffHours < 24) return `${diffHours}時間前`;
    if (diffDays < 7) return `${diffDays}日前`;
    
    const month = date.getMonth() + 1;
    const day = date.getDate();
    return `${month}/${day}`;
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// エラー表示
function showError(message) {
    const container = document.getElementById('chatListContainer');
    container.innerHTML = `
        <div class="empty-state">
            <i class="bi bi-exclamation-triangle"></i>
            <p>${message}</p>
        </div>
    `;
}

// 初期ロード
init();

// 10秒ごとに自動更新
setInterval(() => {
    loadNewUsers();
    loadChatUsers();
}, 10000);
</script>

</body>
</html>