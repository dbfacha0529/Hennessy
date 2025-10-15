<?php
// キャスト情報取得
$cast_g_login_id = isset($_SESSION['CAST']['g_login_id']) ? $_SESSION['CAST']['g_login_id'] : null;

// プロフィール未登録項目チェック
$profile_incomplete = false;
if ($cast_g_login_id) {
    $sql = "SELECT high, age, b, w, h, head_comment, comment, ichimon, `option`, img, imgs FROM girl WHERE g_login_id = :g_login_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':g_login_id' => $cast_g_login_id]);
    $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile_data) {
        // 必須項目が未登録かチェック
        if (empty($profile_data['high']) || empty($profile_data['age']) || 
            empty($profile_data['b']) || empty($profile_data['w']) || empty($profile_data['h']) ||
            empty($profile_data['head_comment']) || empty($profile_data['comment']) ||
            empty($profile_data['ichimon']) || empty($profile_data['option']) ||
            empty($profile_data['img']) || empty($profile_data['imgs'])) {
            $profile_incomplete = true;
        }
    }
}

// チャット未読数取得
$chat_unread_count = 0;
if ($cast_g_login_id) {
    $sql = "SELECT COUNT(*) FROM chat_messages m
            INNER JOIN chat_rooms r ON m.room_id = r.id
            WHERE r.g_login_id = :g_login_id 
            AND m.sender_type = 'user' 
            AND m.is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':g_login_id' => $cast_g_login_id]);
    $chat_unread_count = $stmt->fetchColumn();
}
?>

<style>
  .cast-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #e8e8e8;
    height: 11vh;
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 1rem;
    box-shadow: 0 -0.125rem 0.5rem rgba(0, 0, 0, 0.1);
    z-index: 1000;
  }

  .cast-footer-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #666;
    transition: all 0.3s ease;
    flex: 1;
    padding: 0.5rem;
    border-radius: 0.5rem;
    position: relative;
  }

  .cast-footer-item:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: #333;
  }

  .cast-footer-item:active {
    transform: scale(0.95);
  }

  .cast-footer-icon {
    font-size: 1.75rem;
    margin-bottom: 0.25rem;
    position: relative;
  }

  .cast-footer-label {
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
  }

  /* バッジ共通スタイル */
  .cast-badge {
    position: absolute;
    top: -0.25rem;
    right: -0.5rem;
    background-color: #ff4444;
    color: white;
    border-radius: 50%;
    min-width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: bold;
    padding: 0.125rem;
  }

  /* ドットバッジ（通知あり） */
  .cast-badge-dot {
    width: 0.625rem;
    height: 0.625rem;
    min-width: 0.625rem;
    padding: 0;
  }

  /* 数字バッジ（チャット用） */
  .cast-badge-number {
    min-width: 1.25rem;
    padding: 0 0.25rem;
  }

  /* フッター分のマージン確保 */
  body {
    padding-bottom: 11vh;
  }

  

 /* スマホ対応 (480px以下) */
@media (max-width: 30rem) {
    .cast-footer {
        padding: 0 0.125rem;
        height: 11vh;
    }

    .cast-footer-item {
        padding: 0.25rem 0.125rem;
        gap: 0.125rem;
    }

    .cast-footer-icon {
        font-size: 2.25rem;  /* 大きく */
        margin-bottom: 0.125rem;
    }

    .cast-footer-label {
        font-size: 0.8125rem;  /* 大きく */
        line-height: 1;
        white-space: nowrap;  /* 1行に固定 */
        overflow: hidden;
        text-overflow: ellipsis;  /* 長い場合は省略 */
    }

    .cast-badge {
        min-width: 1.125rem;
        height: 1.125rem;
        font-size: 0.5625rem;
        top: -0.125rem;
        right: -0.25rem;
    }

    .cast-badge-dot {
        width: 0.5625rem;
        height: 0.5625rem;
        min-width: 0.5625rem;
    }

    .cast-badge-number {
        padding: 0 0.1875rem;
    }
}

/* タブレット対応 (768px以下) */
@media (max-width: 48rem) {
    .cast-footer-icon {
        font-size: 1.875rem;
    }

    .cast-footer-label {
        font-size: 0.75rem;
        white-space: nowrap;
    }
}
</style>

<footer class="cast-footer">
  <!-- ホーム -->
  <a href="<?= WEB_URL ?>home.php" class="cast-footer-item">
    <div class="cast-footer-icon">🏠</div>
    <div class="cast-footer-label">ホーム</div>
  </a>

  <!-- プロフィール編集 -->
  <a href="<?= WEB_URL ?>cast_profile.php" class="cast-footer-item">
    <div class="cast-footer-icon">
      ✏️
      <?php if ($profile_incomplete): ?>
        <span class="cast-badge cast-badge-dot"></span>
      <?php endif; ?>
    </div>
    <div class="cast-footer-label">プロフィール</div>
  </a>

  <!-- タイムライン -->
  <a href="<?= TIMELINE_URL ?>cast_timeline.php" class="cast-footer-item">
    <div class="cast-footer-icon">📋</div>
    <div class="cast-footer-label">タイムライン</div>
  </a>

  <!-- チャット -->
  <a href="<?= WEB_URL ?>cast_chat_list.php" class="cast-footer-item">
    <div class="cast-footer-icon">
      💬
      <?php if ($chat_unread_count > 0): ?>
        <span class="cast-badge cast-badge-number" id="cast-chat-badge">
          <?= $chat_unread_count > 99 ? '99+' : $chat_unread_count ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="cast-footer-label">チャット</div>
  </a>
<!-- スケジュール -->
<a href="cast_schedule_list.php" class="cast-footer-item">
    <div class="cast-footer-icon">📅</div>
    <div class="cast-footer-label">スケジュール</div>
</a>
  
</footer>

<script>
// チャット未読数を定期的に更新
async function updateCastChatBadge() {
    try {
        const response = await fetch('<?= WEB_URL ?>cast_chat_api.php?action=get_unread_count');
        const data = await response.json();
        
        if (data.success) {
            const badge = document.getElementById('cast-chat-badge');
            const iconDiv = badge ? badge.parentElement : document.querySelector('.cast-footer-item:nth-child(4) .cast-footer-icon');
            
            if (data.unread_count > 0) {
                if (!badge) {
                    // バッジが存在しない場合は作成
                    const newBadge = document.createElement('span');
                    newBadge.className = 'cast-badge cast-badge-number';
                    newBadge.id = 'cast-chat-badge';
                    newBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    iconDiv.appendChild(newBadge);
                } else {
                    // 既存バッジを更新
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.style.display = 'flex';
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        }
    } catch (error) {
        console.error('未読数取得エラー:', error);
    }
}

// 10秒ごとに未読数を更新
if (document.getElementById('cast-chat-badge') || document.querySelector('.cast-footer')) {
    setInterval(updateCastChatBadge, 10000);
}
</script>

</body>
</html>