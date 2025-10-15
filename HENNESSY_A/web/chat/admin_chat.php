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
  
  // キャスト一覧を取得
  $stmt = $pdo->query("
    SELECT 
      g.g_login_id, 
      g.name
    FROM girl g
    WHERE g.name != 'サポート'
    ORDER BY g.name
  ");
  $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 承認待ち件数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM girl_pending WHERE status = 'pending'");
  $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>チャット管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../css/admin_home.css" rel="stylesheet">
  <link href="../css/admin_chat.css" rel="stylesheet">
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
      <a href="../admin_girl_approvals.php" class="nav-item">
        <i class="bi bi-check-circle"></i>
        <span>承認管理</span>
        <?php if ($pending_count > 0): ?>
          <span class="badge"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
      <a href="../admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="admin_chat.php" class="nav-item active">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
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
      <h1><i class="bi bi-chat-dots"></i> チャット管理</h1>
      <div class="header-info">
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- チャットコンテナ -->
    <div class="chat-container">
      <!-- 左パネル: キャスト選択 -->
      <div class="cast-panel">
        <div class="panel-header">
          <h5><i class="bi bi-people"></i> キャスト選択</h5>
        </div>
        <div class="cast-list">
          <div class="cast-item active" data-girl-id="all">
            <i class="bi bi-clock-history"></i>
            <span>新着順（全体）</span>
          </div>
          <?php foreach ($girls as $girl): ?>
            <div class="cast-item" data-girl-id="<?= htmlspecialchars($girl['g_login_id']) ?>">
              <i class="bi bi-person"></i>
              <span><?= htmlspecialchars($girl['name']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 中央パネル: チャットルーム一覧 -->
      <div class="room-panel">
        <div class="panel-header">
          <h5><i class="bi bi-chat-square-text"></i> チャットルーム</h5>
          <button class="btn btn-sm btn-outline-secondary" id="refresh-rooms">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="room-list" id="room-list">
          <div class="text-center p-4 text-muted">
            <i class="bi bi-chat-dots" style="font-size: 2rem;"></i>
            <p>キャストを選択してください</p>
          </div>
        </div>
      </div>

      <!-- 右パネル: メッセージ表示 -->
      <div class="message-panel">
        <div class="panel-header" id="message-header">
          <div>
            <h5 id="current-room-title">チャットを選択してください</h5>
            <small class="text-muted" id="current-room-info"></small>
          </div>
        </div>
        <div class="message-list" id="message-list">
          <div class="text-center p-5 text-muted">
            <i class="bi bi-chat-left-dots" style="font-size: 3rem;"></i>
            <p>チャットルームを選択してください</p>
          </div>
        </div>
        <div class="message-input" id="message-input" style="display: none;">
          <div class="input-group">
            <textarea class="form-control" id="reply-text" rows="2" placeholder="メッセージを入力..."></textarea>
            <button class="btn btn-primary" id="send-btn">
              <i class="bi bi-send"></i> 送信
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 編集モーダル -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">メッセージ編集</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <textarea class="form-control" id="edit-text" rows="4"></textarea>
          <input type="hidden" id="edit-message-id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-primary" id="save-edit-btn">保存</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/admin_chat.js"></script>
</body>

</html>