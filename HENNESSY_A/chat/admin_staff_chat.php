<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: ../../HENNESSY_A/admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

try {
  $pdo = dbConnect();
  
  // スタッフチャットの未読数を取得 (g_login_id=1)
  $stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM chat_messages m
    INNER JOIN chat_rooms r ON m.room_id = r.id
    WHERE r.g_login_id = '1'
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
  <title>スタッフチャット管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../../HENNESSY_A/web/css/admin_home.css" rel="stylesheet">
  <style>
    .chat-container {
      display: flex;
      height: calc(100vh - 120px);
      gap: 20px;
      margin-top: 20px;
    }

    .users-panel {
      width: 300px;
      background: #2a2a2a;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .room-panel {
      width: 300px;
      background: #2a2a2a;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .message-panel {
      flex: 1;
      background: #2a2a2a;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .panel-header {
      padding: 20px;
      background: linear-gradient(135deg, #d4af37 0%, #aa8c2c 100%);
      color: #000;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .panel-header h5 {
      margin: 0;
      font-weight: 600;
      font-size: 1rem;
    }

    .search-box {
      padding: 15px;
      border-bottom: 1px solid #444;
    }

    .search-input {
      width: 100%;
      padding: 8px 12px;
      background: #1a1a1a;
      border: 1px solid #444;
      border-radius: 6px;
      color: #fff;
      font-size: 0.9rem;
    }

    .search-input:focus {
      outline: none;
      border-color: #d4af37;
    }

    .filter-buttons {
      padding: 10px 15px;
      border-bottom: 1px solid #444;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .filter-btn {
      padding: 5px 12px;
      background: #1a1a1a;
      border: 1px solid #444;
      border-radius: 15px;
      color: #999;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s;
    }

    .filter-btn:hover {
      border-color: #d4af37;
      color: #d4af37;
    }

    .filter-btn.active {
      background: #d4af37;
      border-color: #d4af37;
      color: #000;
    }

    .user-list, .room-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px;
    }

    .user-item, .room-item {
      padding: 12px;
      background: #1a1a1a;
      border-radius: 8px;
      margin-bottom: 8px;
      cursor: pointer;
      transition: all 0.3s;
      border: 2px solid transparent;
    }

    .user-item:hover, .room-item:hover {
      background: #252525;
      border-color: #d4af37;
    }

    .user-item.active, .room-item.active {
      background: #252525;
      border-color: #d4af37;
    }

    .room-item.unread {
      border-left: 4px solid #d4af37;
    }

    .user-name, .room-user-name {
      font-weight: 600;
      color: #d4af37;
      margin-bottom: 3px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.95rem;
    }

    .user-tel {
      color: #999;
      font-size: 0.85rem;
    }

    .room-last-message {
      color: #999;
      font-size: 0.85rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .unread-badge {
      background: #d4af37;
      color: #000;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .message-list {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
      background: #1a1a1a;
    }

    .message-item {
      margin-bottom: 15px;
      display: flex;
      flex-direction: column;
    }

    .message-item.user {
      align-items: flex-start;
    }

    .message-item.staff {
      align-items: flex-end;
    }

    .message-bubble {
      max-width: 70%;
      padding: 12px 16px;
      border-radius: 12px;
      word-wrap: break-word;
    }

    .message-item.user .message-bubble {
      background: #3a3a3a;
      color: #fff;
    }

    .message-item.staff .message-bubble {
      background: linear-gradient(135deg, #d4af37 0%, #aa8c2c 100%);
      color: #000;
    }

    .message-time {
      font-size: 0.75rem;
      color: #999;
      margin-top: 5px;
    }

    .message-status {
      font-size: 0.75rem;
      color: #d4af37;
      margin-top: 5px;
    }

    .message-input {
      padding: 20px;
      background: #2a2a2a;
      border-top: 1px solid #444;
    }

    .message-input textarea {
      background: #1a1a1a;
      border: 1px solid #444;
      color: #fff;
      resize: none;
    }

    .message-input textarea:focus {
      background: #252525;
      border-color: #d4af37;
      box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
    }

    .btn-send {
      background: linear-gradient(135deg, #d4af37 0%, #aa8c2c 100%);
      color: #000;
      border: none;
      font-weight: 600;
    }

    .btn-send:hover {
      background: linear-gradient(135deg, #aa8c2c 0%, #d4af37 100%);
      color: #000;
    }

    .top-header {
      background: #2a2a2a;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .top-header h1 {
      color: #d4af37;
      margin: 0;
      font-size: 1.8rem;
    }

    .header-info {
      display: flex;
      gap: 20px;
      align-items: center;
    }

    .chat-stats {
      color: #999;
    }

    .admin-name {
      color: #d4af37;
    }

    /* スクロールバー */
    .user-list::-webkit-scrollbar,
    .room-list::-webkit-scrollbar,
    .message-list::-webkit-scrollbar {
      width: 6px;
    }

    .user-list::-webkit-scrollbar-track,
    .room-list::-webkit-scrollbar-track,
    .message-list::-webkit-scrollbar-track {
      background: #1a1a1a;
    }

    .user-list::-webkit-scrollbar-thumb,
    .room-list::-webkit-scrollbar-thumb,
    .message-list::-webkit-scrollbar-thumb {
      background: #d4af37;
      border-radius: 3px;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #999;
    }

    .empty-state i {
      font-size: 2.5rem;
      margin-bottom: 10px;
      display: block;
    }
  </style>
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
      <a href="../../HENNESSY_A/admin_home.php" class="nav-item">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="../../HENNESSY_A/admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="../../HENNESSY_A/admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="../../HENNESSY_A/admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="../../HENNESSY_A/admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
      </a>
      <a href="admin_staff_chat.php" class="nav-item active">
        <i class="bi bi-chat-text"></i>
        <span>スタッフチャット</span>
        <?php if ($unread_chats > 0): ?>
          <span class="badge"><?= $unread_chats ?></span>
        <?php endif; ?>
      </a>
      <a href="admin_promotion_chat.php" class="nav-item">
        <i class="bi bi-megaphone"></i>
        <span>販促チャット</span>
      </a>
      <a href="../../HENNESSY_A/admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="../../HENNESSY_A/admin_settings.php" class="nav-item">
        <i class="bi bi-gear"></i>
        <span>設定</span>
      </a>
    </nav>
    
    <div class="logout-section">
      <a href="../../HENNESSY_A/admin_logout.php" class="nav-item logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>ログアウト</span>
      </a>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="main-content">
    <!-- ヘッダー -->
    <header class="top-header">
      <h1><i class="bi bi-chat-text"></i> スタッフチャット管理</h1>
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
      <!-- 左パネル: ユーザー一覧（顧客一覧） -->
      <div class="users-panel">
        <div class="panel-header">
          <h5><i class="bi bi-people-fill"></i> 顧客一覧</h5>
          <button class="btn btn-sm btn-outline-dark" id="refresh-users" title="更新">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        
        <!-- 検索ボックス -->
        <div class="search-box">
          <input type="text" class="search-input" id="user-search" placeholder="名前・電話番号で検索...">
        </div>
        
        <!-- 絞り込みフィルター -->
        <div class="filter-buttons">
          <button class="filter-btn active" data-filter="all">すべて</button>
          <button class="filter-btn" data-filter="reserved">予約済</button>
          <button class="filter-btn" data-filter="favorite">お気に入り</button>
        </div>
        
        <div class="user-list" id="user-list">
          <div class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <p>読み込み中...</p>
          </div>
        </div>
      </div>

      <!-- 中央パネル: チャットルーム一覧 -->
      <div class="room-panel">
        <div class="panel-header">
          <h5><i class="bi bi-chat-dots-fill"></i> チャットルーム</h5>
          <button class="btn btn-sm btn-outline-dark" id="refresh-rooms" title="更新">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="room-list" id="room-list">
          <div class="empty-state">
            <i class="bi bi-chat-left-dots"></i>
            <p>チャットルームがありません</p>
          </div>
        </div>
      </div>

      <!-- 右パネル: メッセージ表示・送信 -->
      <div class="message-panel">
        <div class="panel-header" id="message-header">
          <div>
            <h5 id="current-room-title">ユーザーを選択してください</h5>
            <small class="text-dark" id="current-room-info"></small>
          </div>
        </div>
        <div class="message-list" id="message-list">
          <div class="empty-state">
            <i class="bi bi-chat-left-text"></i>
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
            <span id="file-name" class="text-muted ms-2" style="font-size: 0.85rem;"></span>
          </div>
          
          <!-- リンク入力エリア（非表示） -->
          <div id="link-input-area" style="display: none;" class="mb-2">
            <div class="row g-2">
              <div class="col-md-8">
                <input type="text" class="form-control form-control-sm" id="link-url" placeholder="リンクURL（例: https://example.com）" style="background: #1a1a1a; border: 1px solid #444; color: #fff;">
              </div>
              <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" id="link-text" placeholder="ボタンテキスト" style="background: #1a1a1a; border: 1px solid #444; color: #fff;">
              </div>
            </div>
          </div>
          
          <div class="input-group">
            <textarea 
              class="form-control" 
              id="message-textarea" 
              rows="3" 
              placeholder="メッセージを入力..."></textarea>
            <button class="btn btn-send" id="send-button" style="width: 100px;">
              <i class="bi bi-send"></i> 送信
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./admin_staff_chat.js"></script>
</body>

</html>