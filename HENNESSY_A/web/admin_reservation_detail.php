<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];
$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id) {
  header('Location: admin_reservations.php');
  exit;
}

// ステータス更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    $pdo = dbConnect();
    
    if ($_POST['action'] === 'update_status') {
      $new_status = $_POST['new_status'];
      $stmt = $pdo->prepare("UPDATE reserve SET done = :status WHERE id = :id");
      $stmt->execute([':status' => $new_status, ':id' => $reservation_id]);
      
      header('Location: admin_reservation_detail.php?id=' . $reservation_id . '&msg=status_updated');
      exit;
    }
    
    unset($pdo);
  } catch (Exception $e) {
    $error_message = "更新に失敗しました: " . $e->getMessage();
  }
}

try {
  $pdo = dbConnect();
  
  // 予約詳細を取得
  $stmt = $pdo->prepare("
    SELECT r.*, g.name as girl_name, g.tel as girl_tel, g.img as girl_img,
           u.login_id as user_login_id
    FROM reserve r
    LEFT JOIN girl g ON r.g_login_id COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON r.tel COLLATE utf8mb4_unicode_ci = u.tel COLLATE utf8mb4_unicode_ci
    WHERE r.id = :id
  ");
  $stmt->execute([':id' => $reservation_id]);
  $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$reservation) {
    header('Location: admin_reservations.php');
    exit;
  }
  
  // オプションをJSON配列から変換
  $options_array = $reservation['options'] ? json_decode($reservation['options'], true) : [];
  if (!is_array($options_array)) {
    $options_array = [];
  }
  
  // 料金内訳をJSON配列から変換
  $cost_uchiwake_array = $reservation['cost_uchiwake'] ? json_decode($reservation['cost_uchiwake'], true) : [];
  if (!is_array($cost_uchiwake_array)) {
    $cost_uchiwake_array = [];
  }
  
  // コース情報を取得
  $stmt = $pdo->prepare("SELECT * FROM course WHERE c_name = :c_name");
  $stmt->execute([':c_name' => $reservation['c_name']]);
  $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// ステータス情報を取得
function getStatusInfo($done) {
  switch ($done) {
    case 1:
      return ['class' => 'status-waiting', 'text' => '情報待ち', 'icon' => 'hourglass-split', 'color' => '#ff9800'];
    case 2:
      return ['class' => 'status-confirmed', 'text' => '予約確定', 'icon' => 'check-circle-fill', 'color' => '#4caf50'];
    case 3:
      return ['class' => 'status-completed', 'text' => '完了', 'icon' => 'check-all', 'color' => '#2196f3'];
    case 4:
      return ['class' => 'status-processing', 'text' => '対応中', 'icon' => 'clock-history', 'color' => '#ffd54f'];
    case 5:
      return ['class' => 'status-cancelled', 'text' => 'キャンセル', 'icon' => 'x-circle', 'color' => '#bdbdbd'];
    default:
      return ['class' => 'status-unknown', 'text' => '不明', 'icon' => 'question-circle', 'color' => '#6c757d'];
  }
}

$status_info = getStatusInfo($reservation['done']);
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>予約詳細 #<?= $reservation_id ?> - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
    .detail-container {
      padding: 20px;
      max-width: 1400px;
    }
    
    .back-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: #6c757d;
      color: white;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      margin-bottom: 20px;
      transition: background 0.2s;
    }
    
    .back-button:hover {
      background: #5a6268;
      color: white;
    }
    
    .detail-header {
      background: linear-gradient(135deg, #d4af37 0%, #f0c94a 100%);
      padding: 30px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .detail-header-left h2 {
      margin: 0;
      color: #1a1a1a;
      font-weight: bold;
      font-size: 28px;
    }
    
    .detail-header-left .reservation-date {
      color: #1a1a1a;
      font-size: 18px;
      margin-top: 8px;
      font-weight: 600;
    }
    
    .status-badge-large {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 15px 30px;
      border-radius: 30px;
      font-weight: 700;
      font-size: 18px;
      border: 3px solid;
      background: white;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .status-waiting { border-color: #ff9800; color: #ff9800; }
    .status-confirmed { border-color: #4caf50; color: #4caf50; }
    .status-completed { border-color: #2196f3; color: #2196f3; }
    .status-processing { border-color: #ffd54f; color: #f57c00; }
    .status-cancelled { border-color: #bdbdbd; color: #757575; }
    
    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .detail-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border: 1px solid #dee2e6;
      margin-bottom: 20px;
    }
    
    .card-title {
      font-size: 20px;
      font-weight: bold;
      color: #1a1a1a;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 3px solid #d4af37;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .info-row {
      display: flex;
      padding: 15px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .info-row:last-child {
      border-bottom: none;
    }
    
    .info-label {
      width: 150px;
      font-weight: 600;
      color: #6c757d;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .info-value {
      flex: 1;
      color: #212529;
      font-size: 15px;
      font-weight: 500;
    }
    
    .info-value.large {
      font-size: 24px;
      font-weight: bold;
      color: #d4af37;
    }
    
    .time-display {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
      text-align: center;
      margin: 15px 0;
    }
    
    .time-label {
      font-size: 12px;
      color: #6c757d;
      margin-bottom: 5px;
    }
    
    .time-value {
      font-size: 28px;
      font-weight: bold;
      color: #212529;
    }
    
    .time-separator {
      margin: 0 15px;
      color: #6c757d;
    }
    
    .cast-info {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 20px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 12px;
      margin-bottom: 20px;
    }
    
    .cast-image {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #d4af37;
    }
    
    .cast-details h4 {
      margin: 0;
      font-size: 20px;
      font-weight: bold;
      color: #212529;
    }
    
    .cast-details p {
      margin: 5px 0 0 0;
      color: #6c757d;
      font-size: 14px;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }
    
    .action-btn {
      flex: 1;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 15px;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-chat {
      background: #d4af37;
      color: #1a1a1a;
    }
    
    .btn-chat:hover {
      background: #f0c94a;
    }
    
    .btn-edit {
      background: #2196f3;
      color: white;
    }
    
    .btn-edit:hover {
      background: #1976d2;
    }
    
    .status-change-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      margin-top: 20px;
    }
    
    .status-change-section h4 {
      margin-bottom: 15px;
      font-size: 16px;
      font-weight: bold;
      color: #495057;
    }
    
    .status-buttons {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    
    .status-btn {
      padding: 12px;
      border: 2px solid;
      border-radius: 8px;
      background: white;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 14px;
    }
    
    .status-btn:hover:not(.active) {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .status-btn.active {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    .status-btn-1 {
      border-color: #ff9800;
      color: #ff9800;
    }
    
    .status-btn-1:hover:not(.active) {
      background: #ff9800;
      color: white;
    }
    
    .status-btn-2 {
      border-color: #4caf50;
      color: #4caf50;
    }
    
    .status-btn-2:hover:not(.active) {
      background: #4caf50;
      color: white;
    }
    
    .status-btn-3 {
      border-color: #2196f3;
      color: #2196f3;
    }
    
    .status-btn-3:hover:not(.active) {
      background: #2196f3;
      color: white;
    }
    
    .status-btn-4 {
      border-color: #ffc107;
      color: #f57c00;
    }
    
    .status-btn-4:hover:not(.active) {
      background: #ffc107;
      color: #1a1a1a;
    }
    
    .status-btn-5 {
      border-color: #9e9e9e;
      color: #757575;
      grid-column: 1 / -1;
    }
    
    .status-btn-5:hover:not(.active) {
      background: #9e9e9e;
      color: white;
    }
    
    .comment-box {
      background: #fff8e1;
      border-left: 4px solid #ffc107;
      padding: 15px;
      border-radius: 4px;
      margin-top: 15px;
    }
    
    .comment-box strong {
      color: #f57c00;
      display: block;
      margin-bottom: 8px;
    }
    
    .alert-success {
      background: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .options-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 5px;
    }
    
    .option-badge {
      background: #e3f2fd;
      color: #1976d2;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
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
    <div class="detail-container">
      <a href="admin_reservations.php?date=<?= $reservation['date'] ?>" class="back-button">
        <i class="bi bi-arrow-left"></i> 予約一覧に戻る
      </a><br>
<a href="admin_schedule.php?date=<?= $reservation['date'] ?>" class="back-button">
        <i class="bi bi-arrow-left"></i> スケジュールに戻る
      </a>
      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
        <div class="alert-success">
          <i class="bi bi-check-circle-fill" style="font-size: 24px;"></i>
          <span>ステータスを更新しました</span>
        </div>
      <?php endif; ?>

      <!-- ヘッダー -->
      <div class="detail-header">
        <div class="detail-header-left">
          <h2><i class="bi bi-calendar-event"></i> 予約 #<?= str_pad($reservation['id'], 4, '0', STR_PAD_LEFT) ?></h2>
          <div class="reservation-date">
            <?= date('Y年m月d日 (D)', strtotime($reservation['date'])) ?>
          </div>
        </div>
        <div>
          <span class="status-badge-large <?= $status_info['class'] ?>">
            <i class="bi bi-<?= $status_info['icon'] ?>"></i>
            <?= $status_info['text'] ?>
          </span>
        </div>
      </div>

      <div class="content-grid">
        <!-- 左カラム：詳細情報 -->
        <div>
          <!-- 予約時間 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-clock"></i> 予約時間
            </h3>
            
            <div style="display: flex; align-items: center; justify-content: center;">
              <div class="time-display" style="flex: 1;">
                <div class="time-label">入り時刻</div>
                <div class="time-value"><?= date('H:i', strtotime($reservation['in_time'])) ?></div>
              </div>
              <span class="time-separator" style="font-size: 32px; font-weight: bold;">～</span>
              <div class="time-display" style="flex: 1;">
                <div class="time-label">上がり時刻</div>
                <div class="time-value"><?= date('H:i', strtotime($reservation['out_time'])) ?></div>
              </div>
            </div>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-play-circle"></i> サービス開始
              </div>
              <div class="info-value">
                <?= date('Y/m/d H:i', strtotime($reservation['start_time'])) ?>
              </div>
            </div>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-stop-circle"></i> サービス終了
              </div>
              <div class="info-value">
                <?= date('Y/m/d H:i', strtotime($reservation['end_time'])) ?>
              </div>
            </div>
          </div>

          <!-- 顧客情報 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-person"></i> 顧客情報
            </h3>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-person-badge"></i> 名前
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['user_name']) ?>
              </div>
            </div>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-telephone"></i> 電話番号
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['tel']) ?>
              </div>
            </div>
            
            <?php if ($reservation['contact_tel'] && $reservation['contact_tel'] !== $reservation['tel']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-telephone-forward"></i> 連絡先
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['contact_tel']) ?>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-key"></i> ログインID
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['login_id']) ?>
              </div>
            </div>
          </div>

          <!-- サービス内容 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-list-check"></i> サービス内容
            </h3>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-clock-history"></i> コース
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['c_name']) ?> (<?= $reservation['course_time'] ?>分)
              </div>
            </div>
            
            <?php if (!empty($options_array)): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-plus-circle"></i> オプション
              </div>
              <div class="info-value">
                <div class="options-list">
                  <?php foreach ($options_array as $option): ?>
                    <span class="option-badge"><?= htmlspecialchars(trim($option)) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-geo-alt"></i> 場所
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['place']) ?>
              </div>
            </div>

            <?php if ($reservation['place_comment']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-chat-left-dots"></i> 場所補足
              </div>
              <div class="info-value">
                <?= nl2br(htmlspecialchars($reservation['place_comment'])) ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-map"></i> エリア
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['area']) ?>
                <?php if (isset($reservation['area_outside'])): ?>
                  <span style="margin-left: 10px; font-weight: 700; <?= $reservation['area_outside'] == 1 ? 'color: #dc3545;' : 'color: #28a745;' ?>">
                    <?= $reservation['area_outside'] == 1 ? '【市外】' : '【市内】' ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($reservation['area_comment']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-chat-left-dots"></i> エリア補足
              </div>
              <div class="info-value">
                <?= nl2br(htmlspecialchars($reservation['area_comment'])) ?>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if ($reservation['place'] === 'ホテル' && $reservation['hotel']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-building"></i> ホテル名
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['hotel']) ?>
              </div>
            </div>
            
            <?php if ($reservation['hotel_num']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-door-closed"></i> 部屋番号
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['hotel_num']) ?>
              </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- 料金情報 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-currency-yen"></i> 料金情報
            </h3>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-receipt"></i> 合計金額
              </div>
              <div class="info-value large">
                ¥<?= number_format($reservation['cost']) ?>
              </div>
            </div>
            
            <?php if ($reservation['cost_uchiwake']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-list-ul"></i> 内訳
              </div>
              <div class="info-value">
                <?php if (!empty($cost_uchiwake_array)): ?>
                  <table style="width: 100%; border-collapse: collapse;">
                    <?php foreach ($cost_uchiwake_array as $item): ?>
                      <tr style="border-bottom: 1px solid #f0f0f0;">
                        <td style="padding: 8px 0; color: #6c757d;"><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 600;">
                          ¥<?= number_format($item['amount'] ?? 0) ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </table>
                <?php else: ?>
                  <?= nl2br(htmlspecialchars($reservation['cost_uchiwake'])) ?>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if ($reservation['coupon']): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-ticket-perforated"></i> クーポン
              </div>
              <div class="info-value">
                <?= htmlspecialchars($reservation['coupon']) ?>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if ($reservation['point'] && $reservation['point'] > 0): ?>
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-star"></i> 使用ポイント
              </div>
              <div class="info-value">
                <?= number_format($reservation['point']) ?>pt
              </div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-cash"></i> 支払い方法
              </div>
              <div class="info-value">
                <?php
                switch ($reservation['pay']) {
                  case 1: echo '現金'; break;
                  case 2: echo 'クレジットカード'; break;
                  case 3: echo '電子マネー'; break;
                  default: echo '未設定';
                }
                ?>
              </div>
            </div>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-check-square"></i> 支払い状況
              </div>
              <div class="info-value">
                <?= $reservation['pay_done'] ? '支払い済み' : '未払い' ?>
              </div>
            </div>
          </div>

          <!-- コメント -->
          <?php if ($reservation['comment']): ?>
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-chat-left-text"></i> コメント・要望
            </h3>
            <div class="comment-box">
              <?= nl2br(htmlspecialchars($reservation['comment'])) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- 右カラム：キャスト情報・アクション -->
        <div>
          <!-- キャスト情報 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-person-heart"></i> キャスト情報
            </h3>
            
            <?php if ($reservation['girl_name']): ?>
<div class="cast-info">
  <?php if ($reservation['girl_img']): ?>
    <img src="../../img/icon/<?= htmlspecialchars($reservation['girl_img']) ?>" 
         alt="<?= htmlspecialchars($reservation['girl_name']) ?>" 
         class="cast-image"
         onerror="this.src='../../img/logo/adminlogo.png'">
  <?php else: ?>
    <div class="cast-image" style="background: #e9ecef; display: flex; align-items: center; justify-content: center;">
      <i class="bi bi-person" style="font-size: 40px; color: #6c757d;"></i>
    </div>
  <?php endif; ?>
  
  <div class="cast-details">
    <h4><?= htmlspecialchars($reservation['girl_name']) ?></h4>
    <p><i class="bi bi-telephone"></i> <?= htmlspecialchars($reservation['girl_tel'] ?? '未登録') ?></p>
  </div>
</div>

<div class="info-row">
  <div class="info-label">
    <i class="bi bi-key"></i> キャストID
  </div>
  <div class="info-value">
    <?= htmlspecialchars($reservation['g_login_id']) ?>
  </div>
</div>
<?php else: ?>
<div style="text-align: center; padding: 20px; color: #6c757d;">
  <i class="bi bi-person-x" style="font-size: 48px; margin-bottom: 10px;"></i>
  <p>キャスト未定</p>
</div>
<?php endif; ?>
          </div>

          <!-- ステータス変更 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-arrow-repeat"></i> ステータス変更
            </h3>
            
            <div class="status-change-section">
              <h4>ステータスを変更する</h4>
              <form method="post" id="status-form">
                <input type="hidden" name="action" value="update_status">
                <div class="status-buttons">
                  <button type="button" 
                          class="status-btn status-btn-1 <?= $reservation['done'] == 1 ? 'active' : '' ?>"
                          onclick="changeStatus(1)"
                          <?= $reservation['done'] == 1 ? 'disabled' : '' ?>>
                    <i class="bi bi-hourglass-split"></i> 情報待ち
                  </button>
                  
                  <button type="button" 
                          class="status-btn status-btn-2 <?= $reservation['done'] == 2 ? 'active' : '' ?>"
                          onclick="changeStatus(2)"
                          <?= $reservation['done'] == 2 ? 'disabled' : '' ?>>
                    <i class="bi bi-check-circle-fill"></i> 予約確定
                  </button>
                  
                  <button type="button" 
                          class="status-btn status-btn-4 <?= $reservation['done'] == 4 ? 'active' : '' ?>"
                          onclick="changeStatus(4)"
                          <?= $reservation['done'] == 4 ? 'disabled' : '' ?>>
                    <i class="bi bi-clock-history"></i> 対応中
                  </button>
                  
                  <button type="button" 
                          class="status-btn status-btn-3 <?= $reservation['done'] == 3 ? 'active' : '' ?>"
                          onclick="changeStatus(3)"
                          <?= $reservation['done'] == 3 ? 'disabled' : '' ?>>
                    <i class="bi bi-check-all"></i> 完了
                  </button>
                  
                  <button type="button" 
                          class="status-btn status-btn-5 <?= $reservation['done'] == 5 ? 'active' : '' ?>"
                          onclick="changeStatus(5)"
                          <?= $reservation['done'] == 5 ? 'disabled' : '' ?>>
                    <i class="bi bi-x-circle"></i> キャンセル
                  </button>
                </div>
                <input type="hidden" name="new_status" id="new_status">
              </form>
            </div>
          </div>

          <!-- アクションボタン -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-lightning"></i> クイックアクション
            </h3>
            
            <div class="action-buttons">
              <button class="action-btn btn-chat" onclick="location.href='admin_chat.php?user_tel=<?= $reservation['tel'] ?>&g_login_id=<?= $reservation['g_login_id'] ?>'">
                <i class="bi bi-chat-dots"></i> チャット
              </button>
              
              <button class="action-btn btn-edit" onclick="location.href='admin_reservation_edit.php?id=<?= $reservation['id'] ?>'">
                <i class="bi bi-pencil-square"></i> 編集
              </button>
            </div>
            
            <div class="action-buttons" style="margin-top: 10px;">
              <button class="action-btn" style="background: #17a2b8; color: white;" 
                      onclick="location.href='admin_customers.php?tel=<?= $reservation['tel'] ?>'">
                <i class="bi bi-person-lines-fill"></i> 顧客詳細
              </button>
              
              <?php if ($reservation['girl_name']): ?>
              <button class="action-btn" style="background: #e91e63; color: white;" 
                      onclick="location.href='admin_girls.php?id=<?= $reservation['g_login_id'] ?>'">
                <i class="bi bi-person-badge"></i> キャスト詳細
              </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- 予約情報 -->
          <div class="detail-card">
            <h3 class="card-title">
              <i class="bi bi-info-circle"></i> 予約情報
            </h3>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-calendar-plus"></i> 予約日時
              </div>
              <div class="info-value" style="font-size: 13px;">
                <?= date('Y/m/d H:i:s', strtotime($reservation['created_at'])) ?>
              </div>
            </div>
            
            <div class="info-row">
              <div class="info-label">
                <i class="bi bi-hash"></i> 予約ID
              </div>
              <div class="info-value">
                #<?= str_pad($reservation['id'], 4, '0', STR_PAD_LEFT) ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ステータス変更確認モーダル -->
  <div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-exclamation-triangle"></i> ステータス変更の確認
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>予約のステータスを <strong id="status-text"></strong> に変更しますか？</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-primary" onclick="submitStatusChange()">変更する</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let selectedStatus = null;
    const statusTexts = {
      1: '情報待ち',
      2: '予約確定',
      3: '完了',
      4: '対応中',
      5: 'キャンセル'
    };
    
    function changeStatus(status) {
      selectedStatus = status;
      document.getElementById('status-text').textContent = statusTexts[status];
      const modal = new bootstrap.Modal(document.getElementById('statusModal'));
      modal.show();
    }
    
    function submitStatusChange() {
      if (selectedStatus) {
        document.getElementById('new_status').value = selectedStatus;
        document.getElementById('status-form').submit();
      }
    }
    
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
      const dateTimeElement = document.getElementById('current-datetime');
      if (dateTimeElement) {
        dateTimeElement.textContent = now.toLocaleString('ja-JP', options);
      }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
              