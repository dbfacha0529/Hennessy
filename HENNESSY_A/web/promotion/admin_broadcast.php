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
  
  // キャスト一覧取得
  $stmt = $pdo->query("SELECT g_login_id, name FROM girl ORDER BY name");
  $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // プリファレンスオプション取得
  $stmt = $pdo->query("SELECT DISTINCT options FROM preferenceoptions ORDER BY options");
  $preferences = $stmt->fetchAll(PDO::FETCH_COLUMN);
  
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
  <title>一斉配信 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../css/admin_home.css" rel="stylesheet">
  <link href="./admin_promotion.css" rel="stylesheet">
  <style>
    /* 既存のスタイルに追加・上書きする部分 */

.broadcast-container {
  max-width: 900px;
  margin: 20px auto;
  background: #2a2a2a;
  border-radius: 8px;
  padding: 30px;
}

.section-title {
  color: #d4af37;
  font-size: 18px;
  font-weight: bold;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #444;
}

.target-option {
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 15px;
  cursor: pointer;
  transition: all 0.2s;
}

.target-option:hover {
  border-color: #d4af37;
  background: #252525;
}

.target-option.active {
  border-color: #d4af37;
  background: #333;
}

/* ラベル全体の文字色を白に */
.target-option label {
  color: #ffffff;
  cursor: pointer;
  margin: 0;
  display: flex;
  align-items: center;
  font-size: 15px;
  font-weight: 500;
}

.target-option input[type="radio"] {
  margin-right: 10px;
  cursor: pointer;
}

/* アイコンの色 */
.target-option label i {
  margin-right: 8px;
  color: #d4af37;
}

.target-details {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #444;
  display: none;
}

.target-option.active .target-details {
  display: block;
}

.preference-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}

.preference-chip {
  display: inline-block;
  padding: 8px 16px;
  background: #444;
  color: #fff;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid transparent;
  font-size: 14px;
}

.preference-chip:hover {
  background: #555;
}

.preference-chip.selected {
  background: #d4af37;
  color: #1a1a1a;
  border-color: #d4af37;
}

.target-count {
  background: #d4af37;
  color: #1a1a1a;
  padding: 15px 20px;
  border-radius: 6px;
  font-size: 18px;
  font-weight: bold;
  text-align: center;
  margin: 20px 0;
}

.message-preview {
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 6px;
  padding: 20px;
  margin: 20px 0;
  min-height: 150px;
}

.form-label {
  color: #d4af37;
  font-weight: bold;
  margin-bottom: 10px;
  display: block;
}

.form-control, .form-select {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #ffffff;
}

.form-control:focus, .form-select:focus {
  background: #252525;
  border-color: #d4af37;
  color: #ffffff;
  box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

/* プレースホルダーの色 */
.form-control::placeholder {
  color: #888;
}

/* セレクトボックスのオプション */
.form-select option {
  background: #1a1a1a;
  color: #ffffff;
}

/* 小さいテキスト（説明文） */
.text-muted {
  color: #999 !important;
}

/* チェックボックス関連 */
.form-check-label {
  color: #ffffff !important;
}

.form-check-input {
  background-color: #1a1a1a;
  border-color: #444;
}

.form-check-input:checked {
  background-color: #d4af37;
  border-color: #d4af37;
}

.form-check-input:focus {
  border-color: #d4af37;
  box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

.btn-broadcast {
  background: #d4af37;
  color: #1a1a1a;
  font-weight: bold;
  padding: 12px 30px;
  border: none;
  border-radius: 6px;
  font-size: 16px;
}

.btn-broadcast:hover {
  background: #c9a332;
  color: #1a1a1a;
}

.btn-preview {
  background: #666;
  color: #fff;
  padding: 12px 30px;
  border: none;
  border-radius: 6px;
  margin-right: 10px;
}

.btn-preview:hover {
  background: #777;
  color: #fff;
}

/* その他のボタン */
.btn-outline-secondary {
  color: #d4af37;
  border-color: #d4af37;
  background: transparent;
}

.btn-outline-secondary:hover {
  color: #1a1a1a;
  background: #d4af37;
  border-color: #d4af37;
}

/* ファイル名表示 */
#file-name {
  color: #aaa;
}

/* 対象者確認ボタン */
#preview-users-btn {
  background: transparent;
  color: #1a1a1a;
  border: 1px solid #1a1a1a;
}

#preview-users-btn:hover {
  background: rgba(26, 26, 26, 0.1);
}

/* datetime-local入力 */
input[type="datetime-local"] {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #ffffff;
}

input[type="datetime-local"]:focus {
  background: #252525;
  border-color: #d4af37;
  box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
}

/* 数値入力 */
input[type="number"] {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #ffffff;
}

input[type="number"]:focus {
  background: #252525;
  border-color: #d4af37;
}

/* リンク入力エリア */
#link-input-area input {
  background: #1a1a1a;
  border: 1px solid #444;
  color: #ffffff;
}

#link-input-area input:focus {
  background: #252525;
  border-color: #d4af37;
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
      <a href="admin_promotion_chat.php" class="nav-item">
        <i class="bi bi-megaphone"></i>
        <span>販促チャット</span>
      </a>
      <a href="admin_broadcast.php" class="nav-item active">
        <i class="bi bi-send"></i>
        <span>一斉配信</span>
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
      <h1><i class="bi bi-send"></i> 一斉配信</h1>
      <div class="header-info">
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- 配信フォーム -->
    <div class="broadcast-container">
      <form id="broadcast-form">
        <!-- 配信対象選択 -->
        <div class="section-title">
          <i class="bi bi-people"></i> 配信対象を選択
        </div>
        
        <!-- 全員 -->
        <div class="target-option" data-target="all">
          <label>
            <input type="radio" name="target_type" value="all" checked>
            <i class="bi bi-globe"></i> 全員に配信
          </label>
        </div>
        
        <!-- ランク別 -->
        <div class="target-option" data-target="rank">
          <label>
            <input type="radio" name="target_type" value="rank">
            <i class="bi bi-star"></i> ランク別
          </label>
          <div class="target-details">
            <select class="form-select" id="rank-select">
              <option value="VIP">VIP (50回以上)</option>
              <option value="Gold">Gold (20回以上)</option>
              <option value="Silver">Silver (10回以上)</option>
              <option value="Bronze">Bronze (1回以上)</option>
              <option value="New">New (未利用)</option>
            </select>
          </div>
        </div>
        
        <!-- 利用回数別 -->
        <div class="target-option" data-target="usage_count">
          <label>
            <input type="radio" name="target_type" value="usage_count">
            <i class="bi bi-graph-up"></i> 利用回数別
          </label>
          <div class="target-details">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">最小回数</label>
                <input type="number" class="form-control" id="min-count" min="0" placeholder="例: 10">
              </div>
              <div class="col-md-6">
                <label class="form-label">最大回数</label>
                <input type="number" class="form-control" id="max-count" min="0" placeholder="例: 50">
              </div>
            </div>
          </div>
        </div>
        
        <!-- 最終来店日別 -->
        <div class="target-option" data-target="days_since_visit">
          <label>
            <input type="radio" name="target_type" value="days_since_visit">
            <i class="bi bi-calendar-x"></i> 最終来店日別
          </label>
          <div class="target-details">
            <select class="form-select" id="days-select">
              <option value='{"min_days":0,"max_days":7}'>7日以内</option>
              <option value='{"min_days":30}'>30日以上前</option>
              <option value='{"min_days":60}'>60日以上前</option>
              <option value='{"min_days":90}'>90日以上前</option>
            </select>
          </div>
        </div>
        
        <!-- お気に入りキャスト別 -->
        <div class="target-option" data-target="favorite_girl">
          <label>
            <input type="radio" name="target_type" value="favorite_girl">
            <i class="bi bi-heart"></i> お気に入りキャスト別
          </label>
          <div class="target-details">
            <select class="form-select" id="girl-select">
              <option value="">キャストを選択...</option>
              <?php foreach ($girls as $girl): ?>
                <option value="<?= htmlspecialchars($girl['g_login_id']) ?>">
                  <?= htmlspecialchars($girl['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        
        <!-- プリファレンス別 -->
        <div class="target-option" data-target="preference">
          <label>
            <input type="radio" name="target_type" value="preference">
            <i class="bi bi-funnel"></i> プリファレンス別（好み）
          </label>
          <div class="target-details">
            <label class="form-label">好みを選択（複数可）</label>
            <div class="preference-chips" id="preference-chips">
              <?php foreach ($preferences as $pref): ?>
                <span class="preference-chip" data-value="<?= htmlspecialchars($pref) ?>">
                  <?= htmlspecialchars($pref) ?>
                </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        
        <!-- キャスト属性マッチ -->
        <div class="target-option" data-target="girl_attribute_match">
          <label>
            <input type="radio" name="target_type" value="girl_attribute_match">
            <i class="bi bi-lightning"></i> キャスト属性マッチ配信 ⭐
          </label>
          <div class="target-details">
            <label class="form-label">キャストを選択</label>
            <select class="form-select" id="match-girl-select">
              <option value="">キャストを選択...</option>
              <?php foreach ($girls as $girl): ?>
                <option value="<?= htmlspecialchars($girl['g_login_id']) ?>">
                  <?= htmlspecialchars($girl['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted d-block mt-2">
              選択したキャストの属性とマッチするユーザーに自動配信されます
            </small>
          </div>
        </div>
        
        <!-- 対象ユーザー数表示 -->
        <div class="target-count" id="target-count">
          <i class="bi bi-people-fill"></i> 対象ユーザー数: <span id="count-number">-</span>人
          <button type="button" class="btn btn-sm btn-outline-secondary ms-3" id="preview-users-btn" style="display: none;">
            対象者を確認
          </button>
        </div>
        
        <!-- メッセージ作成 -->
        <div class="section-title mt-4">
          <i class="bi bi-envelope"></i> メッセージ作成
        </div>
        
        <div class="mb-3">
          <label class="form-label">配信タイトル（管理用）</label>
          <input type="text" class="form-control" id="broadcast-title" placeholder="例: 新人さくらちゃん入店のお知らせ" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">メッセージ本文</label>
          <textarea class="form-control" id="broadcast-content" rows="5" placeholder="メッセージを入力..." required></textarea>
        </div>
        
        <!-- オプション -->
        <div class="mb-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="add-link-btn">
            <i class="bi bi-link-45deg"></i> リンクを追加
          </button>
          <label for="file-input" class="btn btn-sm btn-outline-secondary ms-2">
            <i class="bi bi-image"></i> 画像/動画を添付
          </label>
          <input type="file" id="file-input" accept="image/*,video/*" style="display: none;">
          <span id="file-name" class="text-muted ms-2"></span>
        </div>
        
        <!-- リンク入力エリア -->
        <div id="link-input-area" style="display: none;" class="mb-3">
          <div class="row g-2">
            <div class="col-md-8">
              <input type="text" class="form-control" id="link-url" placeholder="リンクURL（例: https://example.com）">
            </div>
            <div class="col-md-4">
              <input type="text" class="form-control" id="link-text" placeholder="ボタンテキスト">
            </div>
          </div>
        </div>
        
        <!-- 配信予約 -->
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="schedule-check">
            <label class="form-check-label text-white" for="schedule-check">
              配信を予約する
            </label>
          </div>
          <input type="datetime-local" class="form-control mt-2" id="scheduled-at" style="display: none;">
        </div>
        
        <!-- 送信ボタン -->
        <div class="text-center mt-4">
          <button type="button" class="btn btn-preview" id="preview-btn">
            <i class="bi bi-eye"></i> プレビュー
          </button>
          <button type="submit" class="btn btn-broadcast">
            <i class="bi bi-send"></i> 今すぐ配信
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./admin_broadcast.js"></script>
</body>

</html>