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
  
  // 今日の日付
  $today = date('Y-m-d');
  
  // 本日の予約件数
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reserve WHERE date = :today");
  $stmt->execute([':today' => $today]);
  $today_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // 本日の売上（完了済みのみ）
  $stmt = $pdo->prepare("SELECT SUM(CAST(cost AS UNSIGNED)) as total FROM reserve WHERE date = :today AND done >= 2");
  $stmt->execute([':today' => $today]);
  $today_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
  
  // 現在進行中の予約
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reserve WHERE date = :today AND done = 1");
  $stmt->execute([':today' => $today]);
  $ongoing_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // 本日のシフト登録数
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM shift WHERE date = :today");
  $stmt->execute([':today' => $today]);
  $today_shifts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // 未読チャット数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND sender_type = 'user'");
  $unread_chats = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // キャスト承認待ち件数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM girl_pending WHERE status = 'pending'");
  $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

  // シフト承認待ち件数（追加）
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM shift_requests WHERE status = 'pending'");
  $shift_pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

  // 最近の予約5件
  $stmt = $pdo->prepare("
    SELECT r.*, g.name as girl_name 
    FROM reserve r 
    LEFT JOIN girl g ON r.g_login_id = g.g_login_id 
    ORDER BY r.created_at DESC 
    LIMIT 5
  ");
  $stmt->execute();
  $recent_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
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
  <title>管理画面 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
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
      <a href="admin_home.php" class="nav-item active">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="admin_schedule.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>スケジュール</span>
      </a>
      <a href="admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      
      <a href="admin_girl_approvals.php" class="nav-item">
        <i class="bi bi-check-circle"></i>
        <span>キャスト承認</span>
        <?php if ($pending_count > 0): ?>
          <span class="badge"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>

      <!-- シフト承認メニュー追加 -->
      <a href="admin_shift_requests.php" class="nav-item">
        <i class="bi bi-calendar2-check"></i>
        <span>シフト承認</span>
        <?php if ($shift_pending_count > 0): ?>
          <span class="badge"><?= $shift_pending_count ?></span>
        <?php endif; ?>
      </a>

      <a href="admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="./chat/admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
        <?php if ($unread_chats > 0): ?>
          <span class="badge"><?= $unread_chats ?></span>
        <?php endif; ?>
      </a>
      <a href="contact_chat.php" class="nav-item">
        <i class="bi bi-headset"></i>
        <span>お問い合わせチャット</span>
        <span class="badge" id="support-chat-badge" style="display: none;">0</span>
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
    <!-- ヘッダー -->
    <header class="top-header">
      <h1>ダッシュボード</h1>
      <div class="header-info">
        <span class="date-time" id="current-datetime"></span>
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- 統計カード -->
    <div class="stats-container">
      <div class="stat-card card-blue">
        <div class="stat-icon">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div class="stat-info">
          <h3><?= number_format($today_reservations) ?></h3>
          <p>本日の予約</p>
        </div>
      </div>
      
      <div class="stat-card card-green">
        <div class="stat-icon">
          <i class="bi bi-currency-yen"></i>
        </div>
        <div class="stat-info">
          <h3>¥<?= number_format($today_sales) ?></h3>
          <p>本日の売上</p>
        </div>
      </div>
      
      <div class="stat-card card-orange">
        <div class="stat-icon">
          <i class="bi bi-clock-history"></i>
        </div>
        <div class="stat-info">
          <h3><?= number_format($ongoing_reservations) ?></h3>
          <p>進行中</p>
        </div>
      </div>
      
      <div class="stat-card card-purple">
        <div class="stat-icon">
          <i class="bi bi-person-check"></i>
        </div>
        <div class="stat-info">
          <h3><?= number_format($today_shifts) ?></h3>
          <p>出勤予定</p>
        </div>
      </div>

      <!-- シフト承認待ちカード追加 -->
      <?php if ($shift_pending_count > 0): ?>
      <div class="stat-card card-warning" style="cursor: pointer;" onclick="location.href='admin_shift_requests.php'">
        <div class="stat-icon">
          <i class="bi bi-calendar2-check"></i>
        </div>
        <div class="stat-info">
          <h3><?= number_format($shift_pending_count) ?></h3>
          <p>シフト承認待ち</p>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 最近の予約 -->
    <div class="content-section">
      <div class="section-header">
        <h2><i class="bi bi-clock-history"></i> 最近の予約</h2>
        <a href="admin_reservations.php" class="btn-link">すべて見る <i class="bi bi-arrow-right"></i></a>
      </div>
      
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>予約ID</th>
              <th>日時</th>
              <th>顧客名</th>
              <th>キャスト</th>
              <th>コース</th>
              <th>料金</th>
              <th>ステータス</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_reservations)): ?>
              <tr>
                <td colspan="7" class="text-center">予約データがありません</td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_reservations as $res): ?>
                <tr>
                  <td>#<?= htmlspecialchars($res['id']) ?></td>
                  <td><?= date('m/d H:i', strtotime($res['start_time'])) ?></td>
                  <td><?= htmlspecialchars($res['user_name']) ?></td>
                  <td><?= htmlspecialchars($res['girl_name'] ?? '未定') ?></td>
                  <td><?= htmlspecialchars($res['c_name']) ?></td>
                  <td>¥<?= number_format($res['cost']) ?></td>
                  <td>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    switch ($res['done']) {
                      case 0:
                        $status_class = 'status-pending';
                        $status_text = '予約済';
                        break;
                      case 1:
                        $status_class = 'status-progress';
                        $status_text = '進行中';
                        break;
                      case 2:
                        $status_class = 'status-complete';
                        $status_text = '完了';
                        break;
                      default:
                        $status_class = 'status-cancel';
                        $status_text = 'キャンセル';
                    }
                    ?>
                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- クイックアクション -->
    <div class="quick-actions">
      <h2><i class="bi bi-lightning"></i> クイックアクション</h2>
      <div class="action-buttons">
        <a href="admin_reservations.php?action=new" class="action-btn btn-primary">
          <i class="bi bi-plus-circle"></i>
          <span>新規予約</span>
        </a>
        <a href="admin_girls.php?action=shift" class="action-btn btn-secondary">
          <i class="bi bi-calendar-week"></i>
          <span>シフト登録</span>
        </a>
        <a href="admin_sales.php" class="action-btn btn-success">
          <i class="bi bi-file-earmark-text"></i>
          <span>売上確認</span>
        </a>
        <a href="admin_shift_requests.php" class="action-btn btn-warning">
          <i class="bi bi-calendar2-check"></i>
          <span>シフト承認</span>
          <?php if ($shift_pending_count > 0): ?>
            <span class="badge bg-danger ms-2"><?= $shift_pending_count ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>

  <script>
    // 現在日時を表示
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

    // 未読バッジ更新
    async function updateSupportChatBadge() {
      try {
        const response = await fetch('../../HENNESSY/chat/chat_api.php?action=admin_get_support_unread_count');
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          console.error('APIがJSONを返していません');
          return;
        }
        
        const data = await response.json();
        
        if (data.success) {
          const badge = document.getElementById('support-chat-badge');
          if (badge) {
            if (data.unread_count > 0) {
              badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
              badge.style.display = 'inline-block';
            } else {
              badge.style.display = 'none';
            }
          }
        }
      } catch (error) {
        console.error('未読数取得エラー:', error);
      }
    }

    // 初回実行と定期実行
    updateSupportChatBadge();
    setInterval(updateSupportChatBadge, 10000);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>