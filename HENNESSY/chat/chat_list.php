<?php
include '../web/header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}
?>

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
            background-color: #00753aff;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        /* タブスタイル */
        .chat-tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .chat-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            background-color: white;
            border: none;
            font-size: 16px;
            color: #666;
            transition: all 0.3s;
        }
        
        .chat-tab.active {
            color: #00753aff;
            border-bottom: 3px solid #00753aff;
            font-weight: bold;
        }
        
        .chat-tab:hover {
            background-color: #f8f9fa;
        }
        
        /* スワイプ用のラッパー */
        .chat-item-wrapper {
            position: relative;
            overflow: hidden;
        }
        
        .chat-item-content {
            position: relative;
            transition: transform 0.3s ease;
            background-color: white;
            z-index: 2;
        }
        
        .chat-item-actions {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            display: flex;
            align-items: center;
            z-index: 1;
        }
        
        .action-btn {
            height: 100%;
            padding: 0 20px;
            border: none;
            color: white;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
        }
        
        .action-pin {
            background-color: #4CAF50;
        }
        
        .action-delete {
            background-color: #ff3b30;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pin-badge {
            background-color: #4CAF50;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
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
        
        /* キャスト選択用のスタイル */
        .girl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        
        .girl-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: background-color 0.2s;
            text-decoration: none;
            color: inherit;
            position: relative;
        }
        
        .girl-card:hover {
            background-color: #f8f9fa;
        }
        
        .girl-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 8px;
            border: 2px solid #e0e0e0;
        }
        
        .girl-name {
            font-size: 14px;
            text-align: center;
            font-weight: 500;
        }
        
        .girl-badges {
            display: flex;
            gap: 4px;
            margin-top: 5px;
        }
        
        .girl-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            background-color: #f0f0f0;
            color: #666;
        }
        
        .girl-badge.favorite {
            background-color: #ff69b4;
            color: white;
        }
        
        .girl-badge.reserve {
            background-color: #4CAF50;
            color: white;
        }
        
        .girl-unread {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #ff3b30;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
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
        
        /* ピン順序選択モーダル */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            max-width: 300px;
            width: 90%;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .pin-order-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-btn-cancel {
            background-color: #e0e0e0;
            color: #333;
        }
        
        .modal-btn-confirm {
            background-color: #4CAF50;
            color: white;
        }
        .action-unpin {
    background-color: #FF9800; /* オレンジ色 */
}
    </style>
</head>
<body>
    <div class="chat-list-container">
        <div class="chat-list-header">
            チャット
        </div>
        
        <!-- タブ -->
        <div class="chat-tabs">
            <button class="chat-tab active" onclick="switchTab('active')">
                進行中
            </button>
            <button class="chat-tab" onclick="switchTab('girls')">
                キャスト選択
            </button>
        </div>
        
        <!-- タブコンテンツ -->
        <div id="active-chats-content">
            <div class="loading">読み込み中...</div>
        </div>
        
        <div id="girls-selection-content" style="display: none;">
            <div class="loading">読み込み中...</div>
        </div>
    </div>
    
    <!-- ピン順序選択モーダル -->
    <div class="modal-overlay" id="pin-modal">
        <div class="modal-content">
            <div class="modal-title">固定順序を設定</div>
            <input type="number" class="pin-order-input" id="pin-order-input" min="1" placeholder="1から順に指定">
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closePinModal()">キャンセル</button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmPin()">固定</button>
            </div>
        </div>
    </div>

    <script>
        let currentTab = 'active';
        let currentSwipedElement = null;
        let currentPinRoomId = null;
        
        // タブ切り替え
        function switchTab(tab) {
            currentTab = tab;
            
            // タブのアクティブ状態を更新
            document.querySelectorAll('.chat-tab').forEach(el => el.classList.remove('active'));
            event.target.classList.add('active');
            
            // コンテンツの表示切り替え
            document.getElementById('active-chats-content').style.display = tab === 'active' ? 'block' : 'none';
            document.getElementById('girls-selection-content').style.display = tab === 'girls' ? 'block' : 'none';
            
            // データ読み込み
            if (tab === 'active') {
                loadActiveChatList();
            } else {
                loadAvailableGirls();
            }
        }
        
        // スワイプ初期化
        function initSwipe(element, roomId) {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;
            const content = element.querySelector('.chat-item-content');
            const threshold = 100; // スワイプと判定する閾値
            
            content.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                isDragging = true;
                content.style.transition = 'none';
            });
            
            content.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
                const diff = startX - currentX;
                
                if (diff > 0 && diff < 160) {
                    content.style.transform = `translateX(-${diff}px)`;
                }
            });
            
            content.addEventListener('touchend', () => {
                if (!isDragging) return;
                isDragging = false;
                
                const diff = startX - currentX;
                content.style.transition = 'transform 0.3s ease';
                
                if (diff > threshold) {
                    // スワイプ完了
                    content.style.transform = 'translateX(-160px)';
                    if (currentSwipedElement && currentSwipedElement !== content) {
                        currentSwipedElement.style.transform = 'translateX(0)';
                    }
                    currentSwipedElement = content;
                } else {
                    // スワイプ戻す
                    content.style.transform = 'translateX(0)';
                }
            });
            
            // マウスイベント (PC用)
            content.addEventListener('mousedown', (e) => {
                startX = e.clientX;
                isDragging = true;
                content.style.transition = 'none';
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                currentX = e.clientX;
                const diff = startX - currentX;
                
                if (diff > 0 && diff < 160) {
                    content.style.transform = `translateX(-${diff}px)`;
                }
            });
            
            document.addEventListener('mouseup', () => {
                if (!isDragging) return;
                isDragging = false;
                
                const diff = startX - currentX;
                content.style.transition = 'transform 0.3s ease';
                
                if (diff > threshold) {
                    content.style.transform = 'translateX(-160px)';
                    if (currentSwipedElement && currentSwipedElement !== content) {
                        currentSwipedElement.style.transform = 'translateX(0)';
                    }
                    currentSwipedElement = content;
                } else {
                    content.style.transform = 'translateX(0)';
                }
            });
        }
        
        // ピン留めモーダルを開く
        function openPinModal(roomId) {
            currentPinRoomId = roomId;
            document.getElementById('pin-modal').classList.add('active');
            document.getElementById('pin-order-input').value = '';
            document.getElementById('pin-order-input').focus();
        }
        
        // ピン留めモーダルを閉じる
        function closePinModal() {
            document.getElementById('pin-modal').classList.remove('active');
            currentPinRoomId = null;
        }
        
        // ピン留め確定
        async function confirmPin() {
            const pinOrder = document.getElementById('pin-order-input').value;
            
            if (!pinOrder || pinOrder < 1) {
                alert('1以上の数値を入力してください');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('room_id', currentPinRoomId);
                formData.append('pin_order', pinOrder);
                
                const response = await fetch('chat_api.php?action=pin_chat', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    closePinModal();
                    loadActiveChatList(); // リロード
                } else {
                    alert('固定に失敗しました: ' + (data.error || '不明なエラー'));
                }
            } catch (error) {
                console.error('固定エラー:', error);
                alert('固定中にエラーが発生しました');
            }
        }
        
        // チャットを非表示
        async function hideChat(roomId) {
            if (!confirm('このチャットを非表示にしますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('room_id', roomId);
                
                const response = await fetch('chat_api.php?action=hide_chat', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    loadActiveChatList(); // リロード
                } else {
                    alert('削除に失敗しました: ' + (data.error || '不明なエラー'));
                }
            } catch (error) {
                console.error('削除エラー:', error);
                alert('削除中にエラーが発生しました');
            }
        }
        
        // 進行中のチャット一覧を取得
        async function loadActiveChatList() {
            try {
                const response = await fetch('chat_api.php?action=get_chat_list');
                const data = await response.json();
                
                const container = document.getElementById('active-chats-content');
                
                if (data.success) {
                    if (data.chat_list.length === 0) {
                        container.innerHTML = `
                            <div class="no-chats">
                                <div class="no-chats-icon">💬</div>
                                <p>進行中のチャットがありません</p>
                                <small>「キャスト選択」タブからチャットを開始できます</small>
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
                            
                            const pinBadge = chat.user_pin_order 
                                ? `<span class="pin-badge">📌 ${chat.user_pin_order}</span>` 
                                : '';
                            
                            const avatarImg = chat.img 
                                ? `<img src="../../img/icon/${escapeHtml(chat.img)}" alt="${escapeHtml(chat.name)}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">` 
                                : `<div class="chat-avatar" style="width: 60px; height: 60px;">👤</div>`;

                           // ピン留めボタンの表示内容を決定
const pinButton = chat.user_pin_order 
    ? `<button class="action-btn action-unpin" onclick="event.stopPropagation(); unpinChat(${chat.room_id})">
        🔓 解除
       </button>`
    : `<button class="action-btn action-pin" onclick="event.stopPropagation(); openPinModal(${chat.room_id})">
        📌 固定
       </button>`;

html += `
    <div class="chat-item-wrapper" data-room-id="${chat.room_id}">
        <div class="chat-item-content">
            <a href="chat_room.php?room_id=${chat.room_id || ''}&g_login_id=${chat.g_login_id}" class="chat-item">
                ${avatarImg}
                <div class="chat-info">
                    <div class="chat-name">
                        ${escapeHtml(chat.name)}
                        ${pinBadge}
                    </div>
                    <div class="chat-last-message">${escapeHtml(lastMessage)}</div>
                </div>
                <div class="chat-meta">
                    <div class="chat-time">${timeText}</div>
                    ${unreadBadge}
                </div>
            </a>
        </div>
        <div class="chat-item-actions">
            ${pinButton}
            <button class="action-btn action-delete" onclick="event.stopPropagation(); hideChat(${chat.room_id})">
                🗑️ 削除
            </button>
        </div>
    </div>
`;
                        });
                        container.innerHTML = html;
                        
                        // スワイプ初期化
                        document.querySelectorAll('.chat-item-wrapper').forEach(wrapper => {
                            initSwipe(wrapper, wrapper.dataset.roomId);
                        });
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
                document.getElementById('active-chats-content').innerHTML = `
                    <div class="no-chats">
                        <p>読み込みに失敗しました</p>
                    </div>
                `;
            }
        }
        
        // チャット可能なキャスト一覧を取得
        async function loadAvailableGirls() {
            try {
                const response = await fetch('chat_api.php?action=get_available_girls');
                const data = await response.json();
                
                const container = document.getElementById('girls-selection-content');
                
                if (data.success) {
                    if (data.girls.length === 0) {
                        container.innerHTML = `
                            <div class="no-chats">
                                <div class="no-chats-icon">👭</div>
                                <p>チャット可能なキャストがいません</p>
                                <small>お気に入り登録またはご利用後にチャットができます</small>
                            </div>
                        `;
                    } else {
                        let html = '<div class="girl-grid">';
                        data.girls.forEach(girl => {
                            const avatarImg = girl.img 
                                ? `../../img/icon/${escapeHtml(girl.img)}` 
                                : '../../img/icon/default.png';
                            
                            const unreadBadge = girl.unread_count > 0 
                                ? `<div class="girl-unread">${girl.unread_count > 99 ? '99+' : girl.unread_count}</div>` 
                                : '';
                            
                            const badges = [];
                            if (girl.is_favorite > 0) badges.push('<span class="girl-badge favorite">♥ お気に入り</span>');
                            if (girl.is_reserve > 0) badges.push('<span class="girl-badge reserve">✓ ご利用済み</span>');
                            const badgesHtml = badges.length > 0 ? `<div class="girl-badges">${badges.join('')}</div>` : '';
                            
                            html += `
                                <div class="girl-card" onclick="openChat('${girl.g_login_id}', ${girl.room_id || 'null'})">
                                    ${unreadBadge}
                                    <img src="${avatarImg}" alt="${escapeHtml(girl.name)}" class="girl-avatar">
                                    <div class="girl-name">${escapeHtml(girl.name)}</div>
                                    ${badgesHtml}
                                </div>
                            `;
                        });
                        html += '</div>';
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
                console.error('キャスト一覧取得エラー:', error);
                document.getElementById('girls-selection-content').innerHTML = `
                    <div class="no-chats">
                        <p>読み込みに失敗しました</p>
                    </div>
                `;
            }
        }
        
        // チャットを開く
async function openChat(gLoginId, roomId) {
    try {
        // 常にget_or_create_room APIを呼び出す（user_view更新のため）
        const formData = new FormData();
        formData.append('g_login_id', gLoginId);
        
        const response = await fetch('chat_api.php?action=get_or_create_room', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // APIから取得したroom_idで遷移
            location.href = `chat_room.php?room_id=${data.room.id}&g_login_id=${gLoginId}`;
        } else {
            alert(data.error || 'チャットルームの取得に失敗しました');
        }
    } catch (error) {
        console.error('チャット開始エラー:', error);
        alert('エラーが発生しました');
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
        
        // モーダル外クリックで閉じる
        document.getElementById('pin-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePinModal();
            }
        });
        
        // Enterキーでピン確定
        document.getElementById('pin-order-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                confirmPin();
            }
        });
        
        // ページ読み込み時に実行
        document.addEventListener('DOMContentLoaded', function() {
            loadActiveChatList();
        });

    // ピン留め解除
async function unpinChat(roomId) {
    try {
        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('pin_order', ''); // NULLを設定
        
        const response = await fetch('chat_api.php?action=pin_chat', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadActiveChatList(); // リロード
        } else {
            alert('解除に失敗しました: ' + (data.error || '不明なエラー'));
        }
    } catch (error) {
        console.error('解除エラー:', error);
        alert('解除中にエラーが発生しました');
    }
}
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../web/footer.php'; ?>