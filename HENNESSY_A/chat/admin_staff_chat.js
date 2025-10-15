// admin_staff_chat.js - スタッフチャット管理

let currentRoomId = null;
let currentUserTel = null;
let currentUserName = null;
let lastMessageId = 0;
let pollingInterval = null;
let allUsers = [];
let currentFilter = 'all';
let selectedFile = null;

// 初期化
document.addEventListener('DOMContentLoaded', function() {
  loadUserList();
  loadRoomList();
  
  // 更新ボタン
  document.getElementById('refresh-users').addEventListener('click', function() {
    loadUserList();
  });
  
  document.getElementById('refresh-rooms').addEventListener('click', function() {
    loadRoomList();
  });
  
  // 送信ボタン
  document.getElementById('send-button').addEventListener('click', function() {
    sendMessage();
  });
  
  // Enterキーで送信（Shift+Enterで改行）
  document.getElementById('message-textarea').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
  
  // 検索機能
  document.getElementById('user-search').addEventListener('input', function(e) {
    filterUsers(e.target.value);
  });
  
  // フィルターボタン
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.dataset.filter;
      loadUserList();
    });
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
});

// ユーザー一覧を読み込み（全顧客）
function loadUserList() {
  fetch(`staff_chat_api.php?action=get_all_users&filter=${currentFilter}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        allUsers = data.users;
        displayUserList(data.users);
      } else {
        console.error('ユーザー一覧取得エラー:', data.error);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// ユーザー一覧を表示
function displayUserList(users) {
  const userList = document.getElementById('user-list');
  
  if (users.length === 0) {
    userList.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p>ユーザーがいません</p>
      </div>
    `;
    return;
  }
  
  userList.innerHTML = '';
  
  users.forEach(user => {
    const userDiv = document.createElement('div');
    userDiv.className = 'user-item';
    userDiv.dataset.tel = user.tel;
    userDiv.dataset.name = user.user_name;
    
    userDiv.innerHTML = `
      <div class="user-name">${escapeHtml(user.user_name)}</div>
      <div class="user-tel">${escapeHtml(user.tel)}</div>
    `;
    
    userDiv.addEventListener('click', () => {
      startChatWithUser(user.tel, user.user_name);
    });
    
    userList.appendChild(userDiv);
  });
}

// 検索フィルター
function filterUsers(searchText) {
  const filtered = allUsers.filter(user => {
    return user.user_name.includes(searchText) || user.tel.includes(searchText);
  });
  displayUserList(filtered);
}

// ユーザーとのチャット開始
function startChatWithUser(userTel, userName) {
  const formData = new FormData();
  formData.append('user_tel', userTel);
  
  fetch('staff_chat_api.php?action=get_or_create_room', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      selectRoom(data.room.id, userTel, userName);
      loadRoomList(); // チャットルーム一覧を更新
    } else {
      alert('エラー: ' + data.error);
    }
  })
  .catch(error => {
    console.error('チャット開始エラー:', error);
    alert('チャットの開始に失敗しました');
  });
}

// チャットルーム一覧を読み込み
function loadRoomList() {
  fetch('staff_chat_api.php?action=get_room_list')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayRoomList(data.rooms);
        updateTotalUnread(data.rooms);
      } else {
        console.error('チャットルーム一覧取得エラー:', data.error);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// チャットルーム一覧を表示
function displayRoomList(rooms) {
  const roomList = document.getElementById('room-list');
  
  if (rooms.length === 0) {
    roomList.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-chat-left-dots"></i>
        <p>チャットルームがありません</p>
      </div>
    `;
    return;
  }
  
  roomList.innerHTML = '';
  
  rooms.forEach(room => {
    const roomDiv = document.createElement('div');
    roomDiv.className = 'room-item';
    roomDiv.dataset.roomId = room.id;
    roomDiv.dataset.userTel = room.user_tel;
    roomDiv.dataset.userName = room.user_name;
    
    if (room.unread_count > 0) {
      roomDiv.classList.add('unread');
    }
    if (room.id === currentRoomId) {
      roomDiv.classList.add('active');
    }
    
    roomDiv.innerHTML = `
      <div class="room-user-name">
        <span>${escapeHtml(room.user_name)}</span>
        ${room.unread_count > 0 ? `<span class="unread-badge">${room.unread_count}</span>` : ''}
      </div>
      <div class="room-last-message">
        ${room.last_message ? escapeHtml(room.last_message) : 'メッセージがありません'}
      </div>
      ${room.last_message_at ? `<small class="text-muted" style="font-size:0.75rem;">${formatDateTime(room.last_message_at)}</small>` : ''}
    `;
    
    roomDiv.addEventListener('click', function() {
      selectRoom(room.id, room.user_tel, room.user_name);
    });
    
    roomList.appendChild(roomDiv);
  });
}

// 総未読数を更新
function updateTotalUnread(rooms) {
  const totalUnread = rooms.reduce((sum, room) => sum + (room.unread_count || 0), 0);
  document.getElementById('total-unread').textContent = totalUnread;
}

// ルームを選択
function selectRoom(roomId, userTel, userName) {
  currentRoomId = roomId;
  currentUserTel = userTel;
  currentUserName = userName;
  lastMessageId = 0;
  
  // アクティブ状態を更新（すべてのroom-itemから削除して、該当するものだけ追加）
  document.querySelectorAll('.room-item').forEach(item => {
    item.classList.remove('active');
    if (item.dataset.roomId == roomId) {
      item.classList.add('active');
    }
  });
  
  // ヘッダーを更新
  document.getElementById('current-room-title').textContent = userName;
  document.getElementById('current-room-info').textContent = `電話番号: ${userTel}`;
  
  // 入力フォームを表示
  document.getElementById('message-input-area').style.display = 'flex';
  
  // メッセージを読み込み
  loadMessages();
  
  // ポーリング開始
  startPolling();
}

// メッセージを読み込み
function loadMessages() {
  if (!currentRoomId) return;
  
  fetch(`staff_chat_api.php?action=get_messages&room_id=${currentRoomId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayMessages(data.messages);
        
        // 既読にする
        markAsRead();
      } else {
        console.error('メッセージ取得エラー:', data.error);
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
    });
}

// メッセージを表示
function displayMessages(messages) {
  const messageList = document.getElementById('message-list');
  
  if (messages.length === 0) {
    messageList.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-chat-left-dots"></i>
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
}

// メッセージを追加
function appendMessage(msg) {
  const messageList = document.getElementById('message-list');
  
  // 既に表示されているかチェック
  if (document.querySelector(`[data-message-id="${msg.id}"]`)) {
    return;
  }
  
  const messageDiv = document.createElement('div');
  messageDiv.className = `message-item ${msg.sender_type === 'user' ? 'user' : 'staff'}`;
  messageDiv.dataset.messageId = msg.id;
  
  const time = formatDateTime(msg.created_at);
  
  let contentHtml = '';
  
  // メッセージタイプ別の表示
  if (msg.message_type === 'text') {
    contentHtml = `<div class="message-bubble">${escapeHtml(msg.content).replace(/\n/g, '<br>')}</div>`;
  } else if (msg.message_type === 'image') {
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content).replace(/\n/g, '<br>') + '<br>' : ''}
        <div class="message-media">
          <img src="/HENNESSY/${msg.file_path}" alt="画像" onclick="window.open(this.src, '_blank')" 
               style="max-width: 300px; border-radius: 8px; margin-top: 8px; cursor: pointer;">
        </div>
      </div>
    `;
  } else if (msg.message_type === 'video') {
    contentHtml = `
      <div class="message-bubble">
        ${msg.content ? escapeHtml(msg.content).replace(/\n/g, '<br>') + '<br>' : ''}
        <div class="message-media">
          <video controls style="max-width: 300px; border-radius: 8px; margin-top: 8px;">
            <source src="/HENNESSY/${msg.file_path}" type="video/mp4">
          </video>
        </div>
      </div>
    `;
  }
  
  // リンクボタン
  let linkHtml = '';
  if (msg.link_url) {
    linkHtml = `
      <div style="margin-top: 10px;">
        <a href="${escapeHtml(msg.link_url)}" target="_blank" 
           style="display: inline-block; padding: 8px 16px; background: #d4af37; color: #1a1a1a; 
                  text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;">
          ${escapeHtml(msg.link_text || 'リンクを開く')}
        </a>
      </div>
    `;
  }
  
  let statusHtml = '';
  if (msg.sender_type === 'staff') {
    statusHtml = msg.is_read ? 
      '<div class="message-status"><i class="bi bi-check-all"></i> 既読</div>' : 
      '<div class="message-status"><i class="bi bi-check"></i> 未読</div>';
  }
  
  messageDiv.innerHTML = `
    ${contentHtml}
    ${linkHtml}
    <div class="message-time">${time}</div>
    ${statusHtml}
  `;
  
  messageList.appendChild(messageDiv);
}

// メッセージを送信
function sendMessage() {
  const textarea = document.getElementById('message-textarea');
  const content = textarea.value.trim();
  const linkUrl = document.getElementById('link-url').value.trim();
  const linkText = document.getElementById('link-text').value.trim();
  
  if (!content && !selectedFile) {
    alert('メッセージを入力するか、ファイルを選択してください');
    return;
  }
  
  if (!currentRoomId) return;
  
  const formData = new FormData();
  formData.append('room_id', currentRoomId);
  formData.append('user_tel', currentUserTel);
  formData.append('content', content);
  formData.append('link_url', linkUrl);
  formData.append('link_text', linkText);
  
  // ファイルがある場合
  if (selectedFile) {
    formData.append('file', selectedFile);
    formData.append('message_type', selectedFile.type.startsWith('image/') ? 'image' : 'video');
  } else {
    formData.append('message_type', 'text');
  }
  
  fetch('staff_chat_api.php?action=send_message', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // 入力をクリア
      textarea.value = '';
      document.getElementById('link-url').value = '';
      document.getElementById('link-text').value = '';
      document.getElementById('link-input-area').style.display = 'none';
      document.getElementById('file-input').value = '';
      document.getElementById('file-name').textContent = '';
      selectedFile = null;
      
      appendMessage(data.message);
      lastMessageId = Math.max(lastMessageId, data.message.id);
      scrollToBottom();
      
      // ルーム一覧を更新
      loadRoomList();
    } else {
      alert('送信エラー: ' + data.error);
    }
  })
  .catch(error => {
    console.error('送信エラー:', error);
    alert('送信に失敗しました');
  });
}

// 既読にする
function markAsRead() {
  if (!currentRoomId) return;
  
  const formData = new FormData();
  formData.append('room_id', currentRoomId);
  
  fetch('staff_chat_api.php?action=mark_as_read', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // ルーム一覧を更新
      loadRoomList();
    }
  })
  .catch(error => {
    console.error('既読更新エラー:', error);
  });
}

// ポーリング開始
function startPolling() {
  stopPolling(); // 既存のポーリングを停止
  
  pollingInterval = setInterval(() => {
    checkNewMessages();
  }, 5000); // 5秒ごと
}

// ポーリング停止
function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}

// 新着メッセージをチェック
function checkNewMessages() {
  if (!currentRoomId) return;
  
  fetch(`staff_chat_api.php?action=get_new_messages&room_id=${currentRoomId}&last_id=${lastMessageId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.has_new) {
        data.messages.forEach(msg => {
          appendMessage(msg);
          lastMessageId = Math.max(lastMessageId, msg.id);
        });
        scrollToBottom();
        
        // 既読にする
        markAsRead();
        
        // ルーム一覧を更新
        loadRoomList();
      }
    })
    .catch(error => {
      console.error('新着メッセージチェックエラー:', error);
    });
}

// 最下部にスクロール
function scrollToBottom() {
  const messageList = document.getElementById('message-list');
  messageList.scrollTop = messageList.scrollHeight;
}

// 日時フォーマット
function formatDateTime(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diff = now - date;
  
  // 1分未満
  if (diff < 60000) {
    return 'たった今';
  }
  
  // 1時間未満
  if (diff < 3600000) {
    const minutes = Math.floor(diff / 60000);
    return `${minutes}分前`;
  }
  
  // 今日
  if (date.toDateString() === now.toDateString()) {
    return date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
  }
  
  // 昨日
  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  if (date.toDateString() === yesterday.toDateString()) {
    return '昨日 ' + date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
  }
  
  // それ以前
  return date.toLocaleDateString('ja-JP', { month: '2-digit', day: '2-digit' }) + ' ' +
         date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
}

// HTMLエスケープ
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

// ページを離れる時にポーリングを停止
window.addEventListener('beforeunload', stopPolling);