// グローバル変数
let currentGirlId = 'all';
let currentRoomId = null;
let autoRefreshInterval = null;
let editModal = null;

// 初期化
document.addEventListener('DOMContentLoaded', function() {
  // Bootstrapモーダルの初期化
  editModal = new bootstrap.Modal(document.getElementById('editModal'));
  
  // キャスト選択イベント
  document.querySelectorAll('.cast-item').forEach(item => {
    item.addEventListener('click', function() {
      // アクティブ状態の切り替え
      document.querySelectorAll('.cast-item').forEach(i => i.classList.remove('active'));
      this.classList.add('active');
      
      // キャストIDを取得
      currentGirlId = this.dataset.girlId;
      currentRoomId = null;
      
      // チャットルームを読み込み
      loadChatRooms();
      
      // メッセージパネルをリセット
      resetMessagePanel();
    });
  });
  
  // リフレッシュボタン
  document.getElementById('refresh-rooms').addEventListener('click', function() {
    loadChatRooms();
    if (currentRoomId) {
      loadMessages(currentRoomId);
    }
  });
  
  // 送信ボタン
  document.getElementById('send-btn').addEventListener('click', sendMessage);
  
  // Enterキーで送信（Shift+Enterで改行）
  document.getElementById('reply-text').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  
  // 編集保存ボタン
  document.getElementById('save-edit-btn').addEventListener('click', saveEditMessage);
  
  // 初期表示：新着順で全体を表示
  loadChatRooms();
  
  // 自動更新を開始（30秒ごと）
  startAutoRefresh();
});

// チャットルーム一覧を読み込み
function loadChatRooms() {
  fetch(`../api/get_chat_rooms.php?girl_id=${currentGirlId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayChatRooms(data.rooms);
        if (data.unread_counts) {
          updateUnreadCounts(data.unread_counts);
        }
      } else {
        console.error('ルーム取得エラー:', data.message);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// チャットルームを表示
function displayChatRooms(rooms) {
  const roomList = document.getElementById('room-list');
  
  if (rooms.length === 0) {
    roomList.innerHTML = `
      <div class="text-center p-4 text-muted">
        <i class="bi bi-chat-dots" style="font-size: 2rem;"></i>
        <p>チャットルームがありません</p>
      </div>
    `;
    return;
  }
  
  let html = '';
  rooms.forEach(room => {
  const unreadBadge = room.unread_count > 0 
    ? `<span class="room-unread">${room.unread_count}</span>` 
    : '';
  
  let lastMessage = '(メッセージなし)';
  if (room.last_message) {
    lastMessage = truncate(room.last_message, 30);
  }
  
  html += `
    <div class="room-item" data-room-id="${room.id}">
      <div class="room-item-header">
        <div class="room-user-name">${escapeHtml(room.user_name || '')} / ${escapeHtml(room.girl_name || '')}</div>
        <div class="room-time">${formatTime(room.last_message_at)}</div>
      </div>
      <div class="room-item-body">
        <div class="room-last-message">${escapeHtml(lastMessage)}</div>
        ${unreadBadge}
      </div>
    </div>
  `;
});
  
  roomList.innerHTML = html;
  
  // ルームクリックイベント
  document.querySelectorAll('.room-item').forEach(item => {
    item.addEventListener('click', function() {
      document.querySelectorAll('.room-item').forEach(i => i.classList.remove('active'));
      this.classList.add('active');
      
      const roomId = this.dataset.roomId;
      currentRoomId = roomId;
      loadMessages(roomId);
    });
  });
}

// メッセージを読み込み
function loadMessages(roomId) {
  fetch(`../api/get_messages.php?room_id=${roomId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayMessages(data.messages, data.room_info);
      } else {
        console.error('メッセージ取得エラー:', data.message);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// メッセージを表示
function displayMessages(messages, roomInfo) {
  const messageList = document.getElementById('message-list');
  const messageInput = document.getElementById('message-input');
  const roomTitle = document.getElementById('current-room-title');
  const roomInfoEl = document.getElementById('current-room-info');
  
  // ヘッダー更新
  roomTitle.textContent = `${roomInfo.user_name} ⇔ ${roomInfo.girl_name}`;
  roomInfoEl.textContent = '';
  
  // メッセージ表示
  if (messages.length === 0) {
    messageList.innerHTML = `
      <div class="text-center p-5 text-muted">
        <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
        <p>まだメッセージがありません</p>
      </div>
    `;
  } else {
    let html = '';
    messages.forEach(msg => {
      html += createMessageHtml(msg);
    });
    messageList.innerHTML = html;
    
    // 最下部にスクロール
    messageList.scrollTop = messageList.scrollHeight;
  }
  
  // 入力フォームを表示
  messageInput.style.display = 'block';
  
  // メッセージアクションのイベント設定
  attachMessageActions();
}

// メッセージHTMLを作成
function createMessageHtml(msg) {
  const senderClass = msg.sender_type;
  const senderName = msg.sender_type === 'user' ? 'ユーザー' : 'キャスト';
  const isReadBadge = msg.is_read ? '<i class="bi bi-check-all text-primary"></i>' : '<i class="bi bi-check"></i>';
  
  let contentHtml = '';
  let actionButtons = '';
  
  if (msg.message_type === 'text') {
    contentHtml = `<div class="message-bubble">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>`;
    
    // テキストメッセージの場合は編集・削除ボタン
    if (msg.sender_type === 'girl') {
      actionButtons = `
        <div class="message-actions">
          <button class="btn btn-sm btn-outline-primary edit-btn" data-message-id="${msg.id}">
            <i class="bi bi-pencil"></i> 編集
          </button>
          <button class="btn btn-sm btn-outline-danger delete-btn" data-message-id="${msg.id}">
            <i class="bi bi-trash"></i> 削除
          </button>
        </div>
      `;
    }
  } else if (msg.message_type === 'image') {
    // 画像パスの処理
    const imagePath = msg.file_path.startsWith('/') ? msg.file_path : `../../../img/chat_uploads/${msg.file_path}`;
    
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content) + '<br>' : ''}
        <div class="message-media">
          <img src="${imagePath}" alt="画像" onclick="window.open(this.src, '_blank')" style="max-width: 300px; border-radius: 8px; cursor: pointer;">
        </div>
      </div>
    `;
    
    // 画像メッセージの場合は削除ボタンのみ（キャストのメッセージのみ）
    if (msg.sender_type === 'girl') {
      actionButtons = `
        <div class="message-actions">
          <button class="btn btn-sm btn-outline-danger delete-btn" data-message-id="${msg.id}">
            <i class="bi bi-trash"></i> 削除
          </button>
        </div>
      `;
    }
  } else if (msg.message_type === 'video') {
    // 動画パスの処理
    const videoPath = msg.file_path.startsWith('/') ? msg.file_path : `../../../img/chat_uploads/${msg.file_path}`;
    
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content) + '<br>' : ''}
        <div class="message-media">
          <video controls style="max-width: 300px; border-radius: 8px;">
            <source src="${videoPath}" type="video/mp4">
          </video>
        </div>
      </div>
    `;
    
    // 動画メッセージの場合は削除ボタンのみ（キャストのメッセージのみ）
    if (msg.sender_type === 'girl') {
      actionButtons = `
        <div class="message-actions">
          <button class="btn btn-sm btn-outline-danger delete-btn" data-message-id="${msg.id}">
            <i class="bi bi-trash"></i> 削除
          </button>
        </div>
      `;
    }
  }
  
  return `
    <div class="message-item ${senderClass}" data-message-id="${msg.id}">
      <div class="message-content">
        <div class="message-sender">${senderName} ${isReadBadge}</div>
        ${contentHtml}
        <div class="message-time">${formatDateTime(msg.created_at)}</div>
        ${actionButtons}
      </div>
    </div>
  `;
}

// メッセージアクションのイベント設定
function attachMessageActions() {
  // 編集ボタン
  document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const messageId = this.dataset.messageId;
      openEditModal(messageId);
    });
  });
  
  // 削除ボタン
  document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const messageId = this.dataset.messageId;
      deleteMessage(messageId);
    });
  });
}

// メッセージを送信
function sendMessage() {
  const textarea = document.getElementById('reply-text');
  const content = textarea.value.trim();
  
  if (!content || !currentRoomId) return;
  
  const formData = new FormData();
  formData.append('room_id', currentRoomId);
  formData.append('content', content);
  
  fetch('../api/send_message.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      textarea.value = '';
      loadMessages(currentRoomId);
      loadChatRooms(); // ルーム一覧も更新
    } else {
      alert('送信エラー: ' + data.message);
    }
  })
  .catch(error => {
    console.error('通信エラー:', error);
    alert('送信に失敗しました');
  });
}

// 編集モーダルを開く
function openEditModal(messageId) {
  // メッセージ内容を取得
  const messageElement = document.querySelector(`[data-message-id="${messageId}"] .message-bubble`);
  const content = messageElement.textContent.trim();
  
  document.getElementById('edit-text').value = content;
  document.getElementById('edit-message-id').value = messageId;
  
  editModal.show();
}

// メッセージを編集保存
function saveEditMessage() {
  const messageId = document.getElementById('edit-message-id').value;
  const content = document.getElementById('edit-text').value.trim();
  
  if (!content) {
    alert('メッセージを入力してください');
    return;
  }
  
  const formData = new FormData();
  formData.append('message_id', messageId);
  formData.append('content', content);
  
  fetch('../api/edit_message.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      editModal.hide();
      loadMessages(currentRoomId);
    } else {
      alert('編集エラー: ' + data.message);
    }
  })
  .catch(error => {
    console.error('通信エラー:', error);
    alert('編集に失敗しました');
  });
}

// メッセージを削除
function deleteMessage(messageId) {
  if (!confirm('このメッセージを削除してもよろしいですか？\n※画像・動画ファイルも削除されます')) {
    return;
  }
  
  const formData = new FormData();
  formData.append('message_id', messageId);
  
  fetch('../api/delete_message.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      loadMessages(currentRoomId);
      loadChatRooms(); // ルーム一覧も更新
    } else {
      alert('削除エラー: ' + data.message);
    }
  })
  .catch(error => {
    console.error('通信エラー:', error);
    alert('削除に失敗しました');
  });
}

// 未読数を更新
function updateUnreadCounts(unreadCounts) {
  console.log('updateUnreadCounts called with:', unreadCounts);
  
  if (!unreadCounts) {
    console.warn('unreadCounts is null or undefined');
    return;
  }
  
  // 全体の未読数
  const totalUnreadEl = document.getElementById('total-unread');
  console.log('total-unread element:', totalUnreadEl);
  if (totalUnreadEl) {
    totalUnreadEl.textContent = unreadCounts.total || 0;
  } else {
    console.warn('total-unread element not found');
  }
  
  const allUnreadEl = document.getElementById('all-unread');
  console.log('all-unread element:', allUnreadEl);
  if (allUnreadEl) {
    const total = unreadCounts.total || 0;
    allUnreadEl.textContent = total;
    allUnreadEl.style.display = total > 0 ? 'inline-block' : 'none';
  } else {
    console.warn('all-unread element not found');
  }
  
  // 各キャストの未読数
  if (unreadCounts.girls) {
    Object.keys(unreadCounts.girls).forEach(girlId => {
      const badge = document.querySelector(`[data-girl-unread="${girlId}"]`);
      if (badge) {
        const count = unreadCounts.girls[girlId] || 0;
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
      }
    });
  } else {
    console.warn('unreadCounts.girls is null or undefined');
  }
}

// メッセージパネルをリセット
function resetMessagePanel() {
  document.getElementById('current-room-title').textContent = 'チャットを選択してください';
  document.getElementById('current-room-info').textContent = '';
  document.getElementById('message-list').innerHTML = `
    <div class="text-center p-5 text-muted">
      <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
      <p>チャットルームを選択してください</p>
    </div>
  `;
  document.getElementById('message-input').style.display = 'none';
}

// 自動更新を開始
function startAutoRefresh() {
  autoRefreshInterval = setInterval(() => {
    loadChatRooms();
    if (currentRoomId) {
      loadMessages(currentRoomId);
    }
  }, 30000); // 30秒ごと
}

// ユーティリティ関数
function escapeHtml(text) {
  if (text == null) return '';
  
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

function truncate(str, length) {
  if (str.length <= length) return str;
  return str.substring(0, length) + '...';
}

function formatTime(datetime) {
  if (!datetime) return '';
  const date = new Date(datetime);
  const now = new Date();
  const diff = now - date;
  
  if (diff < 60000) { // 1分以内
    return 'たった今';
  } else if (diff < 3600000) { // 1時間以内
    return Math.floor(diff / 60000) + '分前';
  } else if (diff < 86400000) { // 24時間以内
    return Math.floor(diff / 3600000) + '時間前';
  } else {
    return `${date.getMonth() + 1}/${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
  }
}

function formatDateTime(datetime) {
  if (!datetime) return '';
  const date = new Date(datetime);
  return `${date.getMonth() + 1}/${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}