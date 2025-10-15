<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

try {
  $pdo = dbConnect();
  
  // 現在時刻から基準日を計算
  $now = new DateTime();
  $current_hour = (int)$now->format('H');
  
  // 6:00未満なら前日が基準日
  if ($current_hour < 6) {
    $today_base_date = $now->modify('-1 day')->format('Y-m-d');
  } else {
    $today_base_date = $now->format('Y-m-d');
  }
  
  // 表示する日付（デフォルトは基準日での今日）
  $selected_date = $_GET['date'] ?? $today_base_date;
  
  // ステータスフィルター
  $status_filter = $_GET['status'] ?? 'all';
  
  // 検索キーワード
  $search_keyword = $_GET['search'] ?? '';
  
  // 基準日の範囲：選択日の6:00から翌日の5:59まで
  $base_date_start = $selected_date . ' 06:00:00';
  $base_date_end = date('Y-m-d H:i:s', strtotime($selected_date . ' +1 day 06:00:00') - 1);
  
  // SQL構築
  $sql = "
    SELECT r.*, g.name as girl_name, g.img as girl_img
    FROM reserve r
    LEFT JOIN girl g ON r.g_login_id COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
    WHERE r.date = :date
  ";
  
  $params = [':date' => $selected_date];
  
  // ステータスフィルター
  if ($status_filter !== 'all') {
    $sql .= " AND r.done = :status";
    $params[':status'] = $status_filter;
  }
  
  // 検索フィルター
  if ($search_keyword !== '') {
    $sql .= " AND (r.user_name LIKE :search OR r.tel LIKE :search OR r.contact_tel LIKE :search OR g.name LIKE :search)";
    $params[':search'] = '%' . $search_keyword . '%';
  }
  
  $sql .= " ORDER BY r.in_time ASC";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 各ステータスの件数を取得
  $status_counts = [
    'all' => 0,
    '1' => 0,
    '2' => 0,
    '3' => 0,
    '4' => 0,
    '5' => 0
  ];
  
  $count_sql = "SELECT done, COUNT(*) as count FROM reserve WHERE date = :date GROUP BY done";
  $count_stmt = $pdo->prepare($count_sql);
  $count_stmt->execute([':date' => $selected_date]);
  $counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($counts as $count) {
    $status_counts[$count['done']] = $count['count'];
    $status_counts['all'] += $count['count'];
  }
  
  // 現在時刻
  $current_time = new DateTime();
  $current_timestamp = $current_time->format('Y-m-d H:i:s');
  
  // 現在時刻が基準日内かチェック
  $show_current_time = false;
  $current_time_index = -1;
  
  if ($current_timestamp >= $base_date_start && $current_timestamp <= $base_date_end) {
    $show_current_time = true;
    // 現在時刻以降の最初の予約のインデックスを見つける
    foreach ($reservations as $index => $res) {
      if ($res['in_time'] >= $current_timestamp) {
        $current_time_index = $index;
        break;
      }
    }
    // 現在時刻以降の予約がない場合は最後
    if ($current_time_index === -1) {
      $current_time_index = count($reservations);
    }
  }
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// ステータスに応じたクラス名とテキストを返す
function getStatusInfo($done) {
  switch ($done) {
    case 1:
      return ['class' => 'status-waiting', 'text' => '情報待ち', 'icon' => 'hourglass-split'];
    case 2:
      return ['class' => 'status-confirmed', 'text' => '予約確定', 'icon' => 'check-circle-fill'];
    case 3:
      return ['class' => 'status-completed', 'text' => '完了', 'icon' => 'check-all'];
    case 4:
      return ['class' => 'status-processing', 'text' => '対応中', 'icon' => 'clock-history'];
    case 5:
      return ['class' => 'status-cancelled', 'text' => 'キャンセル', 'icon' => 'x-circle'];
    default:
      return ['class' => 'status-unknown', 'text' => '不明', 'icon' => 'question-circle'];
  }
}
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>予約管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
    .reservations-container {
      padding: 20px;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .date-selector {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #dee2e6;
    }
    
    .date-selector input[type="date"] {
      background: #ffffff;
      border: 1px solid #ced4da;
      color: #212529;
      padding: 8px 12px;
      border-radius: 4px;
    }
    
    .date-nav-btn {
      background: #d4af37;
      border: none;
      color: #1a1a1a;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: bold;
      transition: background 0.2s;
    }
    
    .date-nav-btn:hover {
      background: #f0c94a;
    }
    
    .search-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    
    .search-bar input {
      flex: 1;
      padding: 10px 15px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .search-btn {
      background: #d4af37;
      border: none;
      color: #1a1a1a;
      padding: 10px 20px;
      border-radius: 4px;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.2s;
    }
    
    .search-btn:hover {
      background: #f0c94a;
    }
    
    .status-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .status-tab {
      padding: 10px 20px;
      border: 2px solid #dee2e6;
      background: #ffffff;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s;
      text-decoration: none;
      color: #495057;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .status-tab:hover {
      background: #f8f9fa;
      border-color: #d4af37;
    }
    
    .status-tab.active {
      background: #d4af37;
      border-color: #d4af37;
      color: #1a1a1a;
    }
    
    .status-tab .badge {
      background: #495057;
      color: white;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 12px;
    }
    
    .status-tab.active .badge {
      background: #1a1a1a;
    }
    
    .reservations-list {
      max-height: calc(100vh - 450px);
      overflow-y: auto;
      padding-right: 10px;
    }
    
    .reservation-card {
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 20px;
      margin-bottom: 12px;
      background: #ffffff;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .reservation-card:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      border-color: #d4af37;
    }
    
    .reservation-id {
      font-size: 18px;
      font-weight: bold;
      color: #495057;
      min-width: 80px;
    }
    
    .reservation-time {
      min-width: 200px;
      font-size: 16px;
      font-weight: 600;
      color: #212529;
    }
    
    .reservation-time .time-range {
      display: block;
      font-size: 14px;
      color: #6c757d;
      margin-top: 2px;
    }
    
    .reservation-info {
      display: flex;
      gap: 30px;
      flex: 1;
      align-items: center;
    }
    
    .info-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    
    .info-label {
      font-size: 11px;
      color: #6c757d;
      font-weight: 500;
    }
    
    .info-value {
      font-size: 14px;
      font-weight: 600;
      color: #212529;
    }
    
    .reservation-status {
      min-width: 120px;
      text-align: center;
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 13px;
      border: 2px solid;
    }
    
    .status-waiting {
      background: #ff9800;
      border-color: #f57c00;
      color: #ffffff;
    }
    
    .status-confirmed {
      background: #4caf50;
      border-color: #388e3c;
      color: #ffffff;
    }
    
    .status-completed {
      background: #2196f3;
      border-color: #1976d2;
      color: #ffffff;
    }
    
    .status-processing {
      background: #ffd54f;
      border-color: #fbc02d;
      color: #1a1a1a;
    }
    
    .status-cancelled {
      background: #bdbdbd;
      border-color: #9e9e9e;
      color: #ffffff;
      opacity: 0.7;
    }
    
    .current-time-divider {
      display: flex;
      align-items: center;
      gap: 15px;
      margin: 20px 0;
      scroll-margin-top: 20px;
    }
    
    .current-time-divider::before,
    .current-time-divider::after {
      content: '';
      flex: 1;
      height: 3px;
      background: linear-gradient(to right, #ff0000, #ff5555);
      border-radius: 2px;
    }
    
    .current-time-label {
      background: #ff0000;
      color: white;
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 14px;
      white-space: nowrap;
      box-shadow: 0 2px 8px rgba(255, 0, 0, 0.3);
    }
    
    .no-reservations {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }
    
    .no-reservations i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.5;
    }
    
    .date-selector span {
      color: #212529 !important;
    }
  </style>
</head>

<body>
  <!-- サイドバー -->
  <div class="sidebar">
    <div class="logo-section">
      <img src="../../img/logo/adminlogo.png" alt="Logo">
      <h4>HENNESSY</h4>
      <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
    </div>
    
    <nav class="nav-menu">
      <a href="admin_home.php" class="nav-item">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="admin_schedule.php" class="nav-item">
        <i class="bi bi-calendar3"></i>
        <span>スケジュール</span>
      </a>
      <a href="admin_reservations.php" class="nav-item active">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
      </a>
      <a href="admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="admin_settings.php" class="nav-item">
        <i class="bi bi-gear"></i>
        <span>設定</span>
      </a>
    </nav>
    
    <div class="logout-section">
      <a href="admin_logout.php" class="nav-item logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>ログアウト</span>
      </a>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="main-content">
    <header class="top-header">
      <h1><i class="bi bi-calendar-check"></i> 予約管理</h1>
      <div class="header-info">
        <span class="date-time" id="current-datetime"></span>
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <div class="reservations-container">
      <!-- 日付選択 -->
      <div class="date-selector">
        <button class="date-nav-btn" onclick="changeDate(-1)">
          <i class="bi bi-chevron-left"></i> 前日
        </button>
        
        <input type="date" id="date-picker" value="<?= $selected_date ?>" 
               onchange="updateURL('date', this.value)">
        
        <button class="date-nav-btn" onclick="changeDate(1)">
          次日 <i class="bi bi-chevron-right"></i>
        </button>
        
        <button class="date-nav-btn" onclick="location.href='admin_reservations.php'">
          <i class="bi bi-calendar-today"></i> 今日
        </button>
        
        <span style="margin-left: auto; color: #212529; font-weight: bold; font-size: 16px;">
          <?= date('Y年m月d日 (D)', strtotime($selected_date)) ?>
        </span>
      </div>

      <!-- 検索バー -->
      <form method="get" class="search-bar" id="search-form">
        <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <input type="text" name="search" placeholder="🔍 キャスト名・顧客名・電話番号で検索..." 
               value="<?= htmlspecialchars($search_keyword) ?>">
        <button type="submit" class="search-btn">
          <i class="bi bi-search"></i> 検索
        </button>
        <?php if ($search_keyword !== ''): ?>
          <button type="button" class="search-btn" onclick="clearSearch()" style="background: #6c757d;">
            <i class="bi bi-x"></i> クリア
          </button>
        <?php endif; ?>
      </form>

      <!-- ステータスタブ -->
      <div class="status-tabs">
        <a href="?date=<?= $selected_date ?>&status=all<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
          全て
          <span class="badge"><?= $status_counts['all'] ?></span>
        </a>
        <a href="?date=<?= $selected_date ?>&status=1<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === '1' ? 'active' : '' ?>">
          <i class="bi bi-hourglass-split"></i> 情報待ち
          <span class="badge"><?= $status_counts['1'] ?></span>
        </a>
        <a href="?date=<?= $selected_date ?>&status=2<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === '2' ? 'active' : '' ?>">
          <i class="bi bi-check-circle-fill"></i> 予約確定
          <span class="badge"><?= $status_counts['2'] ?></span>
        </a>
        <a href="?date=<?= $selected_date ?>&status=4<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === '4' ? 'active' : '' ?>">
          <i class="bi bi-clock-history"></i> 対応中
          <span class="badge"><?= $status_counts['4'] ?></span>
        </a>
        <a href="?date=<?= $selected_date ?>&status=3<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === '3' ? 'active' : '' ?>">
          <i class="bi bi-check-all"></i> 完了
          <span class="badge"><?= $status_counts['3'] ?></span>
        </a>
        <a href="?date=<?= $selected_date ?>&status=5<?= $search_keyword ? '&search=' . urlencode($search_keyword) : '' ?>" 
           class="status-tab <?= $status_filter === '5' ? 'active' : '' ?>">
          <i class="bi bi-x-circle"></i> キャンセル
          <span class="badge"><?= $status_counts['5'] ?></span>
        </a>
      </div>

      <!-- 予約一覧 -->
      <div class="reservations-list" id="reservations-list">
        <?php if (empty($reservations)): ?>
          <div class="no-reservations">
            <i class="bi bi-calendar-x"></i>
            <p>予約がありません</p>
          </div>
        <?php else: ?>
          <?php 
          $current_time_shown = false;
          foreach ($reservations as $index => $res): 
            $status_info = getStatusInfo($res['done']);
            
            // 現在時刻ラインを表示
            if ($show_current_time && !$current_time_shown && $index === $current_time_index):
              $current_time_shown = true;
          ?>
              <div class="current-time-divider" id="current-time-marker">
                <div class="current-time-label">
                  <i class="bi bi-clock"></i> 現在時刻 <?= $current_time->format('H:i') ?>
                </div>
              </div>
          <?php endif; ?>
          
          <div class="reservation-card" onclick="location.href='admin_reservation_detail.php?id=<?= $res['id'] ?>'">
            <div class="reservation-id">
              #<?= str_pad($res['id'], 4, '0', STR_PAD_LEFT) ?>
            </div>
            
            <div class="reservation-time">
              <?= date('H:i', strtotime($res['in_time'])) ?> ～ <?= date('H:i', strtotime($res['out_time'])) ?>
              <span class="time-range"><?= date('m/d', strtotime($res['in_time'])) ?></span>
            </div>
            
            <div class="reservation-info">
              <div class="info-item">
                <span class="info-label">顧客</span>
                <span class="info-value">
                  <i class="bi bi-person"></i> <?= htmlspecialchars($res['user_name']) ?>
                </span>
              </div>
              
              <div class="info-item">
                <span class="info-label">キャスト</span>
                <span class="info-value">
                  <i class="bi bi-person-heart"></i> <?= htmlspecialchars($res['girl_name'] ?? '未定') ?>
                </span>
              </div>
              
              <div class="info-item">
                <span class="info-label">コース</span>
                <span class="info-value">
                  <i class="bi bi-clock"></i> <?= htmlspecialchars($res['c_name']) ?>
                </span>
              </div>
              
              <div class="info-item">
                <span class="info-label">場所</span>
                <span class="info-value">
                  <i class="bi bi-geo-alt"></i> 
                  <?php
                  if ($res['place'] === 'ホテル') {
                    echo htmlspecialchars($res['area'] . ' ' . $res['hotel']);
                  } else {
                    echo htmlspecialchars($res['area'] . ' ' . $res['place']);
                  }
                  ?>
                </span>
              </div>
            </div>
            
            <div class="reservation-status">
              <span class="status-badge <?= $status_info['class'] ?>">
                <i class="bi bi-<?= $status_info['icon'] ?>"></i>
                <?= $status_info['text'] ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php 
          // 現在時刻が全ての予約より後の場合
          if ($show_current_time && !$current_time_shown): 
          ?>
            <div class="current-time-divider" id="current-time-marker">
              <div class="current-time-label">
                <i class="bi bi-clock"></i> 現在時刻 <?= $current_time->format('H:i') ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function changeDate(days) {
      const currentDate = new Date('<?= $selected_date ?>');
      currentDate.setDate(currentDate.getDate() + days);
      const newDate = currentDate.toISOString().split('T')[0];
      updateURL('date', newDate);
    }
    
    function updateURL(param, value) {
      const url = new URL(window.location.href);
      url.searchParams.set(param, value);
      window.location.href = url.toString();
    }
    
    function clearSearch() {
      const url = new URL(window.location.href);
      url.searchParams.delete('search');
      window.location.href = url.toString();
    }
    
    // 現在時刻表示の更新
    function updateDateTime() {
      const now = new Date();
      const options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
      };
      document.getElementById('current-datetime').textContent = 
        now.toLocaleString('ja-JP', options);
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // 現在時刻マーカーへ自動スクロール
    window.addEventListener('load', function() {
      const marker = document.getElementById('current-time-marker');
      if (marker) {
        setTimeout(() => {
          marker.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>