<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: ../admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

try {
  $pdo = dbConnect();
  
  // 販促チャットの未読数を取得
  $stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM chat_messages m
    INNER JOIN chat_rooms r ON m.room_id = r.id
    WHERE r.room_type = 'promotion'
    AND m.sender_type = 'user' 
    AND m.is_read = 0
  ");
  $unread_chats = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  unset($pdo);
} catch (Exception $e) {
  $unread_chats = 0;
}
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>販促チャット管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../css/admin_home.css" rel="stylesheet">
  <link href="./admin_promotion.css" rel="stylesheet">
</head>

<body>
  <!-- サイドバー -->
  <div class="sidebar">
    <div class="logo-section">
      <img src="../../../img/logo/adminlogo.png" alt="Logo">
      <h4>HENNESSY</h4>
      <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
    </div>
    
    <nav class="nav-menu">
      <a href="../admin_home.php" class="nav-item">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="../admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="../admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="../admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="../admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
      </a>
      <a href="admin_promotion_chat.php" class="nav-item active">
        <i class="bi bi-megaphone"></i>
        <span>販促チャット</span>
        <?php if ($unread_chats > 0): ?>
          <span class="badge"><?= $unread_chats ?></span>
        <?php endif; ?>
      </a>
      <a href="../admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="../admin_settings.php" class="nav-item">
        <i class="bi bi-gear"></i>
        <span>設定</span>
      </a>
    </nav>
    
    <div class="logout-section">
      <a href="../admin_logout.php" class="nav-item logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>ログアウト</span>
      </a>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="main-content">
    <!-- ヘッダー -->
    <header class="top-header">
      <h1><i class="bi bi-megaphone"></i> 販促チャット管理</h1>
      <div class="header-info">
        <span class="chat-stats">
          <i class="bi bi-envelope"></i> 未読: <strong id="total-unread"><?= $unread_chats ?></strong>
        </span>
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- チャットコンテナ -->
    <div class="chat-container">
      <!-- 左パネル: ユーザー一覧 -->
      <div class="room-panel">
        <div class="panel-header">
          <h5><i class="bi bi-people"></i> ユーザー一覧</h5>
          <button class="btn btn-sm btn-outline-secondary" id="refresh-rooms">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="room-list" id="room-list">
          <div class="text-center p-4 text-muted">
            <i class="bi bi-hourglass-split" style="font-size: 2rem;"></i>
            <p>読み込み中...</p>
          </div>
        </div>
      </div>

      <!-- 右パネル: メッセージ表示・送信 -->
      <div class="message-panel">
        <div class="panel-header" id="message-header">
          <div>
            <h5 id="current-room-title">ユーザーを選択してください</h5>
            <small class="text-muted" id="current-room-info"></small>
          </div>
        </div>
        <div class="message-list" id="message-list">
          <div class="text-center p-5 text-muted">
            <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
            <p>ユーザーを選択してメッセージを送信</p>
          </div>
        </div>
        
        <!-- メッセージ送信フォーム -->
        <div class="message-input" id="message-input-area" style="display: none;">
          <div class="message-options mb-2">
            <button class="btn btn-sm btn-outline-primary" id="add-link-btn" title="リンクを追加">
              <i class="bi bi-link-45deg"></i> リンク
            </button>
            <label for="file-input" class="btn btn-sm btn-outline-secondary" title="画像/動画を添付">
              <i class="bi bi-image"></i> ファイル
            </label>
            <input type="file" id="file-input" accept="image/*,video/*" style="display: none;">
            <span id="file-name" class="text-muted ms-2"></span>
          </div>
          
          <!-- リンク入力エリア（非表示） -->
          <div id="link-input-area" style="display: none;" class="mb-2">
            <div class="row g-2">
              <div class="col-md-8">
                <input type="text" class="form-control form-control-sm" id="link-url" placeholder="リンクURL（例: https://example.com）">
              </div>
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" id="link-text" placeholder="ボタンテキスト">
              </div>
            </div>
          </div>
          
          <div class="input-group">
            <textarea class="form-control" id="message-text" rows="3" placeholder="メッセージを入力..."></textarea>
            <button class="btn btn-primary" id="send-btn">
              <i class="bi bi-send"></i> 送信
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./admin_promotion.js"></script>
</body>

</html>