// admin_promotion.js - 販促チャット管理画面

let currentRoomId = null;
let lastMessageId = 0;
let autoRefreshInterval = null;
let selectedFile = null;

// 初期化
document.addEventListener('DOMContentLoaded', function() {
  loadPromotionRooms();
  
  // リフレッシュボタン
  document.getElementById('refresh-rooms').addEventListener('click', function() {
    loadPromotionRooms();
    if (currentRoomId) {
      loadMessages(currentRoomId);
    }
  });
  
  // 送信ボタン
  document.getElementById('send-btn').addEventListener('click', sendMessage);
  
  // Enterキーで送信（Shift+Enterで改行）
  document.getElementById('message-text').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  
  // リンク追加ボタン
  document.getElementById('add-link-btn').addEventListener('click', function() {
    const linkArea = document.getElementById('link-input-area');
    linkArea.style.display = linkArea.style.display === 'none' ? 'block' : 'none';
  });
  
  // ファイル選択
  document.getElementById('file-input').addEventListener('change', function(e) {
    selectedFile = e.target.files[0];
    if (selectedFile) {
      document.getElementById('file-name').textContent = selectedFile.name;
    }
  });
  
  // 自動更新を開始（30秒ごと）
  startAutoRefresh();
});

// 販促チャットルーム一覧を読み込み
function loadPromotionRooms() {
  fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_promotion_rooms')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayRooms(data.rooms);
        updateUnreadCount(data.rooms);
      } else {
        console.error('ルーム取得エラー:', data.error);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// ルーム一覧を表示
function displayRooms(rooms) {
  const roomList = document.getElementById('room-list');
  
  if (rooms.length === 0) {
    roomList.innerHTML = `
      <div class="text-center p-4 text-muted">
        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
        <p>ユーザーがいません</p>
      </div>
    `;
    return;
  }
  
  let html = '';
  rooms.forEach(room => {
    const unreadBadge = room.unread_count > 0 
      ? `<span class="room-unread">${room.unread_count}</span>` 
      : '';
    
    const lastMessage = room.last_message 
      ? truncate(room.last_message, 30) 
      : '(メッセージなし)';
    
    html += `
      <div class="room-item" data-room-id="${room.id}" data-user-tel="${escapeHtml(room.user_tel)}">
        <div class="room-item-header">
          <div class="room-user-name">${escapeHtml(room.user_name || room.user_tel || room.tel)}</div>
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
      const userTel = this.dataset.userTel;
      currentRoomId = roomId;
      lastMessageId = 0;
      loadMessages(roomId, userTel);
    });
  });
}

// メッセージを読み込み
function loadMessages(roomId, userTel) {
  fetch(`/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_promotion_messages&room_id=${roomId}&last_id=${lastMessageId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (lastMessageId === 0) {
          // 初回読み込み
          displayMessages(data.messages, data.room_info || { user_name: userTel });
        } else if (data.has_new) {
          // 新着メッセージのみ追加
          data.messages.forEach(msg => {
            appendMessage(msg);
            lastMessageId = Math.max(lastMessageId, msg.id);
          });
          scrollToBottom();
        }
        
        // 既読にする
        markAsRead(roomId);
      } else {
        console.error('メッセージ取得エラー:', data.error);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// メッセージを表示
function displayMessages(messages, roomInfo) {
  const messageList = document.getElementById('message-list');
  const messageInput = document.getElementById('message-input-area');
  const roomTitle = document.getElementById('current-room-title');
  
  // ヘッダー更新
  roomTitle.textContent = roomInfo.user_name || '不明なユーザー';
  
  // メッセージ表示
  if (messages.length === 0) {
    messageList.innerHTML = `
      <div class="text-center p-5 text-muted">
        <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
        <p>まだメッセージがありません</p>
      </div>
    `;
  } else {
    messageList.innerHTML = '';
    messages.forEach(msg => {
      appendMessage(msg);
      lastMessageId = Math.max(lastMessageId, msg.id);
    });
    scrollToBottom();
  }
  
  // 入力フォームを表示
  messageInput.style.display = 'block';
}

// メッセージを追加
function appendMessage(msg) {
  const messageList = document.getElementById('message-list');
  
  // 既に表示されているかチェック
  if (document.querySelector(`[data-message-id="${msg.id}"]`)) {
    return;
  }
  
  const messageDiv = document.createElement('div');
  messageDiv.className = `message-item ${msg.sender_type}`;
  messageDiv.dataset.messageId = msg.id;
  
  const senderName = msg.sender_type === 'user' ? 'ユーザー' : '店舗';
  const time = formatDateTime(msg.created_at);
  
  let contentHtml = '';
  
  if (msg.message_type === 'text') {
    contentHtml = `<div class="message-bubble">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>`;
  } else if (msg.message_type === 'image') {
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content) + '<br>' : ''}
        <div class="message-media">
          <img src="/HENNESSY/${msg.file_path}" alt="画像" onclick="window.open(this.src, '_blank')">
        </div>
      </div>
    `;
  } else if (msg.message_type === 'video') {
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content) + '<br>' : ''}
        <div class="message-media">
          <video controls>
            <source src="/HENNESSY/${msg.file_path}" type="video/mp4">
          </video>
        </div>
      </div>
    `;
  }
  
  // リンクがある場合
  let linkHtml = '';
  if (msg.link_url) {
    linkHtml = `
      <div class="message-link">
        <a href="${escapeHtml(msg.link_url)}" target="_blank">
          ${escapeHtml(msg.link_text || 'リンクを開く')}
        </a>
      </div>
    `;
  }
  
  messageDiv.innerHTML = `
    <div class="message-content">
      <div class="message-sender">${senderName}</div>
      ${contentHtml}
      ${linkHtml}
      <div class="message-time">${time}</div>
    </div>
  `;
  
  messageList.appendChild(messageDiv);
}

// メッセージを送信
function sendMessage() {
  const content = document.getElementById('message-text').value.trim();
  const linkUrl = document.getElementById('link-url').value.trim();
  const linkText = document.getElementById('link-text').value.trim();
  
  if (!content && !selectedFile) {
    alert('メッセージを入力するか、ファイルを選択してください');
    return;
  }
  
  if (!currentRoomId) {
    alert('ユーザーを選択してください');
    return;
  }
  
  const formData = new FormData();
  formData.append('room_id', currentRoomId);
  formData.append('content', content);
  formData.append('link_url', linkUrl);
  formData.append('link_text', linkText);
  
  if (selectedFile) {
    formData.append('file', selectedFile);
    formData.append('message_type', selectedFile.type.startsWith('image/') ? 'image' : 'video');
  } else {
    formData.append('message_type', 'text');
  }
  
  fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_send_promotion_message', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // 入力をクリア
      document.getElementById('message-text').value = '';
      document.getElementById('link-url').value = '';
      document.getElementById('link-text').value = '';
      document.getElementById('link-input-area').style.display = 'none';
      document.getElementById('file-input').value = '';
      document.getElementById('file-name').textContent = '';
      selectedFile = null;
      
      // メッセージを追加
      appendMessage(data.message);
      lastMessageId = Math.max(lastMessageId, data.message.id);
      scrollToBottom();
      
      // ルーム一覧を更新
      loadPromotionRooms();
    } else {
      alert('送信エラー: ' + data.error);
    }
  })
  .catch(error => {
    console.error('通信エラー:', error);
    alert('送信に失敗しました');
  });
}

// 既読にする
function markAsRead(roomId) {
  const formData = new FormData();
  formData.append('room_id', roomId);
  
  fetch('/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_mark_promotion_read', {
    method: 'POST',
    body: formData
  })
  .catch(error => {
    console.error('既読更新エラー:', error);
  });
}

// 未読数を更新
function updateUnreadCount(rooms) {
  const totalUnread = rooms.reduce((sum, room) => sum + (room.unread_count || 0), 0);
  const totalUnreadEl = document.getElementById('total-unread');
  if (totalUnreadEl) {
    totalUnreadEl.textContent = totalUnread;
  }
}

// スクロールを最下部へ
function scrollToBottom() {
  const messageList = document.getElementById('message-list');
  messageList.scrollTop = messageList.scrollHeight;
}

// 自動更新を開始
function startAutoRefresh() {
  autoRefreshInterval = setInterval(() => {
    loadPromotionRooms();
    if (currentRoomId) {
      loadMessages(currentRoomId);
    }
  }, 30000); // 30秒ごと
}

// ユーティリティ関数
function escapeHtml(text) {
  if (!text) return '';
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

function truncate(str, length) {
  if (!str) return '';
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