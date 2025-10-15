

// admin_broadcast.js - 一斉配信管理画面

let selectedPreferences = [];
let selectedFile = null;
let currentTargetType = 'all';
let currentConditions = null;

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    
  // ターゲットオプションのクリックイベント
  document.querySelectorAll('.target-option').forEach(option => {
    option.addEventListener('click', function(e) {
      // ラジオボタン自体をクリックした場合はイベントバブリングで処理される
      if (e.target.type !== 'radio') {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
      }
      
      // 全てのactiveクラスを削除
      document.querySelectorAll('.target-option').forEach(opt => {
        opt.classList.remove('active');
      });
      
      // クリックされたものにactiveクラスを追加
      this.classList.add('active');
      
      // 対象ユーザー数を更新
      updateTargetCount();
    });
  });
  
  // ラジオボタンの変更イベント
  document.querySelectorAll('input[name="target_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
      currentTargetType = this.value;
      updateTargetCount();
    });
  });
  
  // プリファレンスチップのクリック
  document.querySelectorAll('.preference-chip').forEach(chip => {
    chip.addEventListener('click', function() {
      const value = this.dataset.value;
      
      if (this.classList.contains('selected')) {
        this.classList.remove('selected');
        selectedPreferences = selectedPreferences.filter(p => p !== value);
      } else {
        this.classList.add('selected');
        selectedPreferences.push(value);
      }
      
      updateTargetCount();
    });
  });
  
  // セレクトボックスの変更イベント
  document.getElementById('rank-select').addEventListener('change', updateTargetCount);
  document.getElementById('min-count').addEventListener('input', debounce(updateTargetCount, 500));
  document.getElementById('max-count').addEventListener('input', debounce(updateTargetCount, 500));
  document.getElementById('days-select').addEventListener('change', updateTargetCount);
  document.getElementById('girl-select').addEventListener('change', updateTargetCount);
  document.getElementById('match-girl-select').addEventListener('change', updateTargetCount);
  
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
  
  // 配信予約チェック
  document.getElementById('schedule-check').addEventListener('change', function() {
    const scheduledInput = document.getElementById('scheduled-at');
    scheduledInput.style.display = this.checked ? 'block' : 'none';
    
    // 現在時刻+1時間を初期値として設定
    if (this.checked) {
      const now = new Date();
      now.setHours(now.getHours() + 1);
      const formatted = now.toISOString().slice(0, 16);
      scheduledInput.value = formatted;
    }
  });
  
  // プレビューボタン
  document.getElementById('preview-btn').addEventListener('click', showPreview);
  
  // 送信フォーム
  document.getElementById('broadcast-form').addEventListener('submit', submitBroadcast);
  
  // 初回の対象ユーザー数取得
  updateTargetCount();
});

// 対象ユーザー数を更新
function updateTargetCount() {
  const targetType = document.querySelector('input[name="target_type"]:checked').value;
  let conditions = null;
  
  // 条件を取得
  switch (targetType) {
    case 'all':
      conditions = null;
      break;
      
    case 'rank':
      conditions = document.getElementById('rank-select').value;
      break;
      
    case 'usage_count':
      const minCount = document.getElementById('min-count').value;
      const maxCount = document.getElementById('max-count').value;
      if (minCount || maxCount) {
        conditions = JSON.stringify({
          min_count: minCount ? parseInt(minCount) : null,
          max_count: maxCount ? parseInt(maxCount) : null
        });
      }
      break;
      
    case 'days_since_visit':
      conditions = document.getElementById('days-select').value;
      break;
      
    case 'favorite_girl':
      conditions = document.getElementById('girl-select').value;
      if (!conditions) {
        document.getElementById('count-number').textContent = '-';
        return;
      }
      break;
      
    case 'preference':
      if (selectedPreferences.length === 0) {
        document.getElementById('count-number').textContent = '-';
        return;
      }
      conditions = JSON.stringify(selectedPreferences);
      break;
      
    case 'girl_attribute_match':
      conditions = document.getElementById('match-girl-select').value;
      if (!conditions) {
        document.getElementById('count-number').textContent = '-';
        return;
      }
      break;
  }
  
  currentConditions = conditions;
  
  // APIリクエスト
  let url = `/HENNESSY/HENNESSY_A/web/promotion/promotion_api.php?action=admin_get_target_users&target_type=${targetType}`;
  if (conditions) {
    url += `&conditions=${encodeURIComponent(conditions)}`;
  }
  
  fetch(url)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('count-number').textContent = data.count;
        
        // 対象者確認ボタンの表示
        const previewBtn = document.getElementById('preview-users-btn');
        if (data.count > 0) {
          previewBtn.style.display = 'inline-block';
          previewBtn.onclick = () => showTargetUsers(data.users);
        } else {
          previewBtn.style.display = 'none';
        }
      } else {
        console.error('対象ユーザー取得エラー:', data.error);
        document.getElementById('count-number').textContent = 'エラー';
      }
    })
    .catch(error => {
      console.error('通信エラー:', error);
      document.getElementById('count-number').textContent = 'エラー';
    });
}

// 対象ユーザー一覧を表示
function showTargetUsers(users) {
  let html = '<div style="max-height: 400px; overflow-y: auto;">';
  html += '<table class="table table-dark table-striped">';
  html += '<thead><tr><th>ユーザー名</th><th>電話番号</th></tr></thead>';
  html += '<tbody>';
  
  users.forEach(user => {
    html += `<tr>
      <td>${escapeHtml(user.user_name)}</td>
      <td>${escapeHtml(user.tel)}</td>
    </tr>`;
  });
  
  html += '</tbody></table></div>';
  
  // モーダル表示（簡易版）
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  `;
  
  const content = document.createElement('div');
  content.style.cssText = `
    background: #2a2a2a;
    padding: 30px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
  `;
  
  content.innerHTML = `
    <h4 style="color: #d4af37; margin-bottom: 20px;">配信対象ユーザー（${users.length}人）</h4>
    ${html}
    <button class="btn btn-secondary mt-3" onclick="this.closest('.modal-backdrop').remove()">閉じる</button>
  `;
  
  modal.className = 'modal-backdrop';
  modal.appendChild(content);
  document.body.appendChild(modal);
  
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

// プレビュー表示
function showPreview() {
  const title = document.getElementById('broadcast-title').value;
  const content = document.getElementById('broadcast-content').value;
  const linkUrl = document.getElementById('link-url').value;
  const linkText = document.getElementById('link-text').value;
  
  if (!content) {
    alert('メッセージ本文を入力してください');
    return;
  }
  
  let previewHtml = `
    <div style="background: #f7daeeff; color: #000; padding: 15px; border-radius: 18px; max-width: 70%;">
      <div style="font-size: 12px; color: #666; margin-bottom: 5px;">店舗</div>
      <div style="white-space: pre-wrap;">${escapeHtml(content)}</div>
  `;
  
  if (selectedFile && selectedFile.type.startsWith('image/')) {
    const fileUrl = URL.createObjectURL(selectedFile);
    previewHtml += `<img src="${fileUrl}" style="max-width: 100%; border-radius: 8px; margin-top: 10px;">`;
  }
  
  if (linkUrl) {
    previewHtml += `
      <div style="margin-top: 10px;">
        <a href="${escapeHtml(linkUrl)}" target="_blank" 
           style="display: inline-block; padding: 8px 16px; background: #d4af37; color: #1a1a1a; 
                  text-decoration: none; border-radius: 6px; font-weight: bold;">
          ${escapeHtml(linkText || 'リンクを開く')}
        </a>
      </div>
    `;
  }
  
  previewHtml += `
      <div style="font-size: 11px; color: #999; margin-top: 5px;">
        ${new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' })}
      </div>
    </div>
  `;
  
  // モーダル表示
  const modal = document.createElement('div');
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  `;
  
  const contentDiv = document.createElement('div');
  contentDiv.style.cssText = `
    background: #2a2a2a;
    padding: 30px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
  `;
  
  contentDiv.innerHTML = `
    <h4 style="color: #d4af37; margin-bottom: 20px;">メッセージプレビュー</h4>
    <div style="background: #fafafa; padding: 20px; border-radius: 8px;">
      ${previewHtml}
    </div>
    <button class="btn btn-secondary mt-3" onclick="this.closest('.modal-backdrop').remove()">閉じる</button>
  `;
  
  modal.className = 'modal-backdrop';
  modal.appendChild(contentDiv);
  document.body.appendChild(modal);
  
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

// 配信実行
// 配信実行
function submitBroadcast(e) {
  e.preventDefault();
  
  const title = document.getElementById('broadcast-title').value;
  const content = document.getElementById('broadcast-content').value;
  const linkUrl = document.getElementById('link-url').value;
  const linkText = document.getElementById('link-text').value;
  const targetType = document.querySelector('input[name="target_type"]:checked').value;
  const scheduleCheck = document.getElementById('schedule-check').checked;
  const scheduledAt = scheduleCheck ? document.getElementById('scheduled-at').value : null;
  
  if (!title || (!content && !selectedFile)) {
    alert('タイトルとメッセージ本文または画像を入力してください');
    return;
  }
  
  const countNumber = document.getElementById('count-number').textContent;
  if (countNumber === '-' || countNumber === 'エラー' || countNumber === '0') {
    alert('配信対象ユーザーが選択されていません');
    return;
  }
  
  if (!confirm(`${countNumber}人のユーザーに配信します。よろしいですか？`)) {
    return;
  }
  
  // 送信ボタンを無効化
  const submitBtn = e.target.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 配信中...';
  
  // テキストメッセージと画像メッセージを分けて送信する
  sendBroadcastMessages(title, content, linkUrl, linkText, targetType, scheduledAt, selectedFile, submitBtn, countNumber);
}

// メッセージ送信処理（テキストと画像を分けて送信）
async function sendBroadcastMessages(title, content, linkUrl, linkText, targetType, scheduledAt, file, submitBtn, countNumber) {
  try {
    let totalSent = 0;
    
    // 1. テキストメッセージを送信（内容がある場合）
    if (content) {
      const textFormData = new FormData();
      textFormData.append('title', title + ' (テキスト)');
      textFormData.append('content', content);
      textFormData.append('link_url', linkUrl);
      textFormData.append('link_text', linkText);
      textFormData.append('target_type', targetType);
      textFormData.append('target_conditions', currentConditions || '');
      textFormData.append('scheduled_at', scheduledAt || '');
      textFormData.append('message_type', 'text');
      
      const textResponse = await fetch('/HENNESSY/HENNESSY_A/web/promotion/promotion_api.php?action=admin_create_broadcast', {
        method: 'POST',
        body: textFormData
      });
      
      const textData = await JSON.parse(await textResponse.text());
      
      if (!textData.success) {
        throw new Error('テキストメッセージの送信に失敗: ' + textData.error);
      }
      
      totalSent = textData.total_sent;
    }
    
    // 2. 画像/動画メッセージを送信（ファイルがある場合）
    if (file) {
      const fileFormData = new FormData();
      fileFormData.append('title', title + ' (画像)');
      fileFormData.append('content', ''); // 画像には空のコンテンツ
      fileFormData.append('link_url', '');
      fileFormData.append('link_text', '');
      fileFormData.append('target_type', targetType);
      fileFormData.append('target_conditions', currentConditions || '');
      fileFormData.append('scheduled_at', scheduledAt || '');
      fileFormData.append('file', file);
      fileFormData.append('message_type', file.type.startsWith('image/') ? 'image' : 'video');
      
      const fileResponse = await fetch('/HENNESSY/HENNESSY_A/web/promotion/promotion_api.php?action=admin_create_broadcast', {
        method: 'POST',
        body: fileFormData
      });
      
      const fileData = await JSON.parse(await fileResponse.text());
      
      if (!fileData.success) {
        throw new Error('画像メッセージの送信に失敗: ' + fileData.error);
      }
      
      totalSent = fileData.total_sent;
    }
    
    // 成功メッセージ
    let successMsg = `配信完了！\n対象: ${countNumber}人\n送信成功: ${totalSent}人`;
    if (content && file) {
      successMsg += '\n\n※テキストと画像を2件のメッセージとして配信しました';
    }
    
    alert(successMsg);
    location.reload();
    
  } catch (error) {
    console.error('配信エラー:', error);
    alert('配信に失敗しました: ' + error.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="bi bi-send"></i> 今すぐ配信';
  }
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

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}