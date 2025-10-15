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
$err = [];

if (!$reservation_id) {
  header('Location: admin_reservations.php');
  exit;
}

try {
  $pdo = dbConnect();
  
  // 更新処理
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // バリデーション
    if (empty($_POST['date'])) {
      $err['date'] = '予約日を選択してください';
    }
    if (empty($_POST['in_time'])) {
      $err['in_time'] = '入り時刻を入力してください';
    }
    if (empty($_POST['out_time'])) {
      $err['out_time'] = '上がり時刻を入力してください';
    }
    if (empty($_POST['c_name'])) {
      $err['c_name'] = 'コースを選択してください';
    }
    if (empty($_POST['user_name'])) {
      $err['user_name'] = '顧客名を入力してください';
    }
    if (empty($_POST['tel'])) {
      $err['tel'] = '電話番号を入力してください';
    }
    if (empty($_POST['place'])) {
      $err['place'] = '場所を選択してください';
    }
    
    // 時間の妥当性チェック
    if (empty($err)) {
      $in_time = strtotime($_POST['date'] . ' ' . $_POST['in_time']);
      $out_time = strtotime($_POST['date'] . ' ' . $_POST['out_time']);
      
      if ($out_time <= $in_time) {
        $err['time'] = '上がり時刻は入り時刻より後にしてください';
      }
    }
    
    if (empty($err)) {
      // オプションの処理
      $options = isset($_POST['options']) ? $_POST['options'] : [];
      $options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
      
      // 料金計算
      $course_cost = intval($_POST['course_cost'] ?? 0);
      $options_cost = intval($_POST['options_cost'] ?? 0);
      $place_cost = intval($_POST['place_cost'] ?? 0);
      $coupon_discount = intval($_POST['coupon_discount'] ?? 0);
      $point_use = intval($_POST['point'] ?? 0);
      
      // 追加項目の処理
      $extra_items = [];
      if (isset($_POST['extra_items_json'])) {
        $extra_items = json_decode($_POST['extra_items_json'], true);
        if (!is_array($extra_items)) {
          $extra_items = [];
        }
      }
      
      // 追加項目の合計を計算
      $extra_cost_total = 0;
      foreach ($extra_items as $item) {
        if (isset($item['amount']) && $item['amount'] > 0) {
          $extra_cost_total += intval($item['amount']);
        }
      }
      
      $total_cost = $course_cost + $options_cost + $place_cost + $extra_cost_total - $coupon_discount - $point_use;
      
      // 料金内訳
      $cost_uchiwake = [
        ['name' => 'コース料金', 'amount' => $course_cost]
      ];
      
      if ($options_cost > 0) {
        $cost_uchiwake[] = ['name' => 'オプション料金', 'amount' => $options_cost];
      }
      
      if ($place_cost > 0) {
        $cost_uchiwake[] = ['name' => '場所料金', 'amount' => $place_cost];
      }
      
      // 追加項目を内訳に追加
      foreach ($extra_items as $item) {
        if (isset($item['name']) && isset($item['amount']) && $item['amount'] > 0) {
          $cost_uchiwake[] = [
            'name' => $item['name'],
            'amount' => intval($item['amount'])
          ];
        }
      }
      
      if ($coupon_discount > 0) {
        $cost_uchiwake[] = ['name' => 'クーポン割引', 'amount' => -$coupon_discount];
      }
      if ($point_use > 0) {
        $cost_uchiwake[] = ['name' => 'ポイント使用', 'amount' => -$point_use];
      }
      
      // 合計を最後に追加（表示用）
      $cost_uchiwake[] = ['name' => '合計', 'amount' => $total_cost];
      
      $cost_uchiwake_json = json_encode($cost_uchiwake, JSON_UNESCAPED_UNICODE);
      
      // 日時の組み立て
      $date = $_POST['date'];
      $in_time = $date . ' ' . $_POST['in_time'] . ':00';
      $out_time = $date . ' ' . $_POST['out_time'] . ':00';
      $start_time = $date . ' ' . $_POST['start_time'] . ':00';
      $end_time = $date . ' ' . $_POST['end_time'] . ':00';
      
      // 更新SQL
      $sql = "UPDATE reserve SET 
        c_name = :c_name,
        course_time = :course_time,
        date = :date,
        in_time = :in_time,
        out_time = :out_time,
        start_time = :start_time,
        end_time = :end_time,
        g_login_id = :g_login_id,
        user_name = :user_name,
        tel = :tel,
        contact_tel = :contact_tel,
        options = :options,
        place = :place,
        place_comment = :place_comment,
        area = :area,
        area_comment = :area_comment,
        hotel = :hotel,
        hotel_num = :hotel_num,
        hotel_comment = :hotel_comment,
        coupon = :coupon,
        point = :point,
        pay = :pay,
        cost = :cost,
        cost_uchiwake = :cost_uchiwake,
        comment = :comment
      WHERE id = :id";
      
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':c_name' => $_POST['c_name'],
        ':course_time' => $_POST['course_time'],
        ':date' => $date,
        ':in_time' => $in_time,
        ':out_time' => $out_time,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':g_login_id' => $_POST['g_login_id'],
        ':user_name' => $_POST['user_name'],
        ':tel' => $_POST['tel'],
        ':contact_tel' => $_POST['contact_tel'],
        ':options' => $options_json,
        ':place' => $_POST['place'],
        ':place_comment' => $_POST['place_comment'] ?? '',
        ':area' => $_POST['area'],
        ':area_comment' => $_POST['area_comment'] ?? '',
        ':hotel' => $_POST['hotel'] ?? '',
        ':hotel_num' => $_POST['hotel_num'] ?? '',
        ':hotel_comment' => $_POST['hotel_comment'] ?? '',
        ':coupon' => $_POST['coupon'] ?? '',
        ':point' => $point_use,
        ':pay' => $_POST['pay'],
        ':cost' => $total_cost,
        ':cost_uchiwake' => $cost_uchiwake_json,
        ':comment' => $_POST['comment'] ?? '',
        ':id' => $reservation_id
      ]);
      
      header('Location: admin_reservation_detail.php?id=' . $reservation_id . '&msg=updated');
      exit;
    }
  }
  
  // 予約情報取得
  $stmt = $pdo->prepare("
    SELECT r.*, g.name as girl_name
    FROM reserve r
    LEFT JOIN girl g ON r.g_login_id COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
    WHERE r.id = :id
  ");
  $stmt->execute([':id' => $reservation_id]);
  $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$reservation) {
    header('Location: admin_reservations.php');
    exit;
  }
  
  // オプションをデコード
  $selected_options = $reservation['options'] ? json_decode($reservation['options'], true) : [];
  if (!is_array($selected_options)) {
    $selected_options = [];
  }
  
  // 料金内訳をデコード
  $cost_uchiwake_array = [];
  if ($reservation['cost_uchiwake']) {
    $decoded = json_decode($reservation['cost_uchiwake'], true);
    if (is_array($decoded)) {
      $cost_uchiwake_array = $decoded;
    }
  }
  
  // コース一覧取得
  $stmt = $pdo->query("SELECT * FROM course ORDER BY time ASC");
  $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // キャスト一覧取得
  $stmt = $pdo->query("SELECT g_login_id, name, tel FROM girl ORDER BY name ASC");
  $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // オプション一覧取得
  $stmt = $pdo->query("SELECT * FROM options ORDER BY id ASC");
  $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 場所一覧取得
  $stmt = $pdo->query("SELECT * FROM place ORDER BY sorte_order ASC");
  $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // エリア一覧取得
  $stmt = $pdo->query("SELECT * FROM area ORDER BY sort_order ASC");
  $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // クーポン一覧取得
  $stmt = $pdo->query("SELECT * FROM coupon ORDER BY id ASC");
  $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // よく使う追加項目取得
  $quick_items = [];
  try {
    $stmt = $pdo->query("SELECT * FROM extra_fee_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $quick_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($quick_items)) {
      $quick_items = [
        ['item_name' => '特別派遣料金', 'default_amount' => 5000],
        ['item_name' => 'タオル代', 'default_amount' => 1000],
        ['item_name' => '延長料金', 'default_amount' => 10000],
        ['item_name' => '交通費', 'default_amount' => 2000],
        ['item_name' => '指名料', 'default_amount' => 3000],
        ['item_name' => '深夜料金', 'default_amount' => 5000]
      ];
    }
  } catch (Exception $e) {
    $quick_items = [
      ['item_name' => '特別派遣料金', 'default_amount' => 5000],
      ['item_name' => 'タオル代', 'default_amount' => 1000],
      ['item_name' => '延長料金', 'default_amount' => 10000],
      ['item_name' => '交通費', 'default_amount' => 2000],
      ['item_name' => '指名料', 'default_amount' => 3000],
      ['item_name' => '深夜料金', 'default_amount' => 5000]
    ];
  }
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// 時間をフォーマット
$in_time_formatted = date('H:i', strtotime($reservation['in_time']));
$out_time_formatted = date('H:i', strtotime($reservation['out_time']));
$start_time_formatted = date('H:i', strtotime($reservation['start_time']));
$end_time_formatted = date('H:i', strtotime($reservation['end_time']));
?>

<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>予約編集 #<?= $reservation_id ?> - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
    .edit-container {
      padding: 20px;
      max-width: 1200px;
      margin: 0 auto;
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
    
    .edit-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 30px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      color: white;
    }
    
    .edit-header h2 {
      margin: 0;
      font-weight: bold;
      font-size: 28px;
    }
    
    .edit-header .reservation-info {
      margin-top: 10px;
      font-size: 16px;
      opacity: 0.9;
    }
    
    .edit-card {
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border: 1px solid #dee2e6;
    }
    
    .form-section {
      margin-bottom: 40px;
      padding-bottom: 30px;
      border-bottom: 1px solid #e9ecef;
    }
    
    .form-section:last-of-type {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    
    .form-section-title {
      font-size: 20px;
      font-weight: bold;
      color: #212529;
      margin-bottom: 25px;
      padding-bottom: 12px;
      border-bottom: 3px solid #667eea;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-label .required {
      color: #dc3545;
      font-size: 12px;
    }
    
    .form-control, .form-select {
      border-radius: 8px;
      border: 2px solid #dee2e6;
      padding: 12px 15px;
      transition: all 0.2s;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .option-checkbox {
      display: flex;
      align-items: center;
      padding: 12px;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      margin-bottom: 10px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .option-checkbox:hover {
      border-color: #667eea;
      background: #f8f9fa;
    }
    
    .option-checkbox input[type="checkbox"] {
      width: 20px;
      height: 20px;
      margin-right: 12px;
      cursor: pointer;
    }
    
    .option-checkbox.checked {
      border-color: #667eea;
      background: #e8eaff;
    }
    
    .option-label {
      flex: 1;
      font-weight: 600;
      color: #212529;
    }
    
    .option-cost {
      color: #667eea;
      font-weight: bold;
    }
    
    .cost-summary {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      padding: 25px;
      border-radius: 12px;
      margin-top: 0;
      border: 2px solid #667eea;
    }
    
    .cost-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #dee2e6;
    }
    
    .cost-row:last-child {
      border-bottom: none;
      margin-top: 10px;
      padding-top: 20px;
      border-top: 3px solid #667eea;
    }
    
    .cost-label {
      font-weight: 600;
      color: #495057;
    }
    
    .cost-value {
      font-weight: 600;
      color: #212529;
    }
    
    .cost-total {
      font-size: 28px;
      font-weight: bold;
      color: #667eea;
    }
    
    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
      margin-top: 30px;
      padding-top: 30px;
      border-top: 2px solid #dee2e6;
    }
    
    .btn-submit {
      padding: 15px 40px;
      font-size: 16px;
      font-weight: bold;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .btn-save {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    
    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-cancel {
      background: #6c757d;
      color: white;
    }
    
    .btn-cancel:hover {
      background: #5a6268;
    }
    
    .alert-danger {
      background: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .invalid-feedback {
      display: block;
      color: #dc3545;
      font-size: 14px;
      margin-top: 5px;
    }
    
    .hotel-section {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .hotel-section.show {
      display: block;
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
    <div class="edit-container">
      <a href="admin_reservation_detail.php?id=<?= $reservation_id ?>" class="back-button">
        <i class="bi bi-arrow-left"></i> 詳細に戻る
      </a>

      <div class="edit-header">
        <h2><i class="bi bi-pencil-square"></i> 予約編集 #<?= str_pad($reservation_id, 4, '0', STR_PAD_LEFT) ?></h2>
        <div class="reservation-info">
          <?= htmlspecialchars($reservation['user_name']) ?> 様 | <?= date('Y年m月d日', strtotime($reservation['date'])) ?>
        </div>
      </div>

      <?php if (!empty($err)): ?>
        <div class="alert-danger">
          <strong><i class="bi bi-exclamation-triangle"></i> 入力エラー</strong>
          <ul style="margin: 10px 0 0 20px;">
            <?php foreach ($err as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="edit-card">
        <form method="post" id="edit-form">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="course_cost" id="course_cost" value="0">
          <input type="hidden" name="options_cost" id="options_cost" value="0">
          <input type="hidden" name="place_cost" id="place_cost" value="0">
          <input type="hidden" name="coupon_discount" id="coupon_discount" value="0">

          <!-- 基本情報 -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-calendar-check"></i> 基本情報
            </h4>
            
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">
                  <i class="bi bi-calendar"></i> 予約日
                  <span class="required">必須</span>
                </label>
                <input type="date" class="form-control <?= isset($err['date']) ? 'is-invalid' : '' ?>" 
                       name="date" id="date" value="<?= $reservation['date'] ?>" required>
                <?php if (isset($err['date'])): ?>
                  <div class="invalid-feedback"><?= $err['date'] ?></div>
                <?php endif; ?>
              </div>
              
              <div class="col-md-4 mb-3">
                <label class="form-label">
                  <i class="bi bi-box-arrow-in-right"></i> 入り時刻
                  <span class="required">必須</span>
                </label>
                <input type="time" class="form-control <?= isset($err['in_time']) ? 'is-invalid' : '' ?>" 
                       name="in_time" id="in_time" value="<?= $in_time_formatted ?>" required>
              </div>
              
              <div class="col-md-4 mb-3">
                <label class="form-label">
                  <i class="bi bi-box-arrow-right"></i> 上がり時刻
                  <span class="required">必須</span>
                </label>
                <input type="time" class="form-control <?= isset($err['out_time']) ? 'is-invalid' : '' ?>" 
                       name="out_time" id="out_time" value="<?= $out_time_formatted ?>" required>
              </div>
            </div>

            <?php if (isset($err['time'])): ?>
              <div class="alert-danger mb-4"><?= $err['time'] ?></div>
            <?php endif; ?>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-play-circle"></i> サービス開始
                </label>
                <input type="time" class="form-control" 
                       name="start_time" id="start_time" value="<?= $start_time_formatted ?>">
              </div>
              
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-stop-circle"></i> サービス終了
                </label>
                <input type="time" class="form-control" 
                       name="end_time" id="end_time" value="<?= $end_time_formatted ?>">
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">
                <i class="bi bi-clock-history"></i> コース
                <span class="required">必須</span>
              </label>
              <select class="form-select <?= isset($err['c_name']) ? 'is-invalid' : '' ?>" 
                      name="c_name" id="c_name" required>
                <option value="">コースを選択してください</option>
                <?php foreach ($courses as $course): ?>
                  <option value="<?= htmlspecialchars($course['c_name']) ?>" 
                          data-time="<?= $course['time'] ?>"
                          data-cost="<?= $course['cost'] ?>"
                          <?= $reservation['c_name'] === $course['c_name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['c_name']) ?> (<?= $course['time'] ?>分) - ¥<?= number_format($course['cost']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="course_time" id="course_time" value="<?= $reservation['course_time'] ?>">
              <?php if (isset($err['c_name'])): ?>
                <div class="invalid-feedback"><?= $err['c_name'] ?></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- 顧客・キャスト情報 -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-people"></i> 顧客・キャスト情報
            </h4>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-person-badge"></i> 顧客名
                  <span class="required">必須</span>
                </label>
                <input type="text" class="form-control <?= isset($err['user_name']) ? 'is-invalid' : '' ?>" 
                       name="user_name" value="<?= htmlspecialchars($reservation['user_name']) ?>" required>
                <?php if (isset($err['user_name'])): ?>
                  <div class="invalid-feedback"><?= $err['user_name'] ?></div>
                <?php endif; ?>
              </div>
              
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-person-heart"></i> 担当キャスト
                </label>
                <select class="form-select" name="g_login_id" id="g_login_id">
                  <option value="">キャストを選択してください</option>
                  <?php foreach ($girls as $girl): ?>
                    <option value="<?= htmlspecialchars($girl['g_login_id']) ?>"
                            <?= $reservation['g_login_id'] == $girl['g_login_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($girl['name']) ?> (ID: <?= htmlspecialchars($girl['g_login_id']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-telephone"></i> 電話番号
                  <span class="required">必須</span>
                </label>
                <input type="tel" class="form-control <?= isset($err['tel']) ? 'is-invalid' : '' ?>" 
                       name="tel" value="<?= htmlspecialchars($reservation['tel']) ?>" required>
                <?php if (isset($err['tel'])): ?>
                  <div class="invalid-feedback"><?= $err['tel'] ?></div>
                <?php endif; ?>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-telephone-forward"></i> 連絡先電話番号
                </label>
                <input type="tel" class="form-control" 
                       name="contact_tel" value="<?= htmlspecialchars($reservation['contact_tel']) ?>">
              </div>
            </div>
          </div>

          <!-- 場所情報 -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-geo-alt"></i> 場所情報
            </h4>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-house"></i> 場所タイプ
                  <span class="required">必須</span>
                </label>
                <select class="form-select <?= isset($err['place']) ? 'is-invalid' : '' ?>" 
                        name="place" id="place" required>
                  <option value="">場所を選択してください</option>
                  <?php foreach ($places as $place): ?>
                    <option value="<?= htmlspecialchars($place['place_name']) ?>"
                            data-cost="<?= $place['cost'] ?>"
                            <?= $reservation['place'] === $place['place_name'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($place['place_name']) ?>
                      <?php if ($place['cost'] > 0): ?>
                        (+¥<?= number_format($place['cost']) ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($err['place'])): ?>
                  <div class="invalid-feedback"><?= $err['place'] ?></div>
                <?php endif; ?>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-map"></i> エリア
                </label>
                <select class="form-select" name="area" id="area">
                  <option value="">エリアを選択してください</option>
                  <?php foreach ($areas as $area): ?>
                    <option value="<?= htmlspecialchars($area['area_name']) ?>"
                            <?= $reservation['area'] === $area['area_name'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($area['area_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">
                <i class="bi bi-chat-left-dots"></i> 場所補足
              </label>
              <textarea class="form-control" name="place_comment" rows="2"><?= htmlspecialchars($reservation['place_comment']) ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">
                <i class="bi bi-chat-left-dots"></i> エリア補足
              </label>
              <textarea class="form-control" name="area_comment" rows="2"><?= htmlspecialchars($reservation['area_comment']) ?></textarea>
            </div>

            <!-- ホテル情報 -->
            <div class="hotel-section <?= $reservation['place'] === 'ホテル' ? 'show' : '' ?>" id="hotel-section">
              <h5 style="margin-bottom: 20px; color: #667eea;">
                <i class="bi bi-building"></i> ホテル情報
              </h5>
              
              <div class="row">
                <div class="col-md-8 mb-3">
                  <label class="form-label">
                    <i class="bi bi-building"></i> ホテル名
                  </label>
                  <input type="text" class="form-control" name="hotel" 
                         value="<?= htmlspecialchars($reservation['hotel']) ?>">
                </div>

                <div class="col-md-4 mb-3">
                  <label class="form-label">
                    <i class="bi bi-door-closed"></i> 部屋番号
                  </label>
                  <input type="text" class="form-control" name="hotel_num" 
                         value="<?= htmlspecialchars($reservation['hotel_num']) ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">
                  <i class="bi bi-chat-left-dots"></i> ホテル補足
                </label>
                <textarea class="form-control" name="hotel_comment" rows="2"><?= htmlspecialchars($reservation['hotel_comment']) ?></textarea>
              </div>
            </div>
          </div>

          <!-- オプション -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-plus-circle"></i> オプション選択
            </h4>
            
            <div id="options-list">
              <?php foreach ($options as $option): ?>
                <label class="option-checkbox <?= in_array($option['option_name'], $selected_options) ? 'checked' : '' ?>">
                  <input type="checkbox" 
                         name="options[]" 
                         value="<?= htmlspecialchars($option['option_name']) ?>"
                         data-cost="<?= $option['option_cost'] ?>"
                         <?= in_array($option['option_name'], $selected_options) ? 'checked' : '' ?>>
                  <span class="option-label"><?= htmlspecialchars($option['option_name']) ?></span>
                  <span class="option-cost">+¥<?= number_format($option['option_cost']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- 追加料金項目 -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-list-ul"></i> 追加料金項目
            </h4>
            
            <div id="extra-items-container"></div>
            
            <button type="button" class="btn btn-outline-primary" onclick="addExtraItem()" style="width: 100%; margin-top: 10px;">
              <i class="bi bi-plus-circle"></i> 追加項目を追加
            </button>
            
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #2196f3;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <strong style="color: #1976d2;"><i class="bi bi-info-circle"></i> よく使う項目：</strong>
                <a href="admin_extra_items.php" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size: 12px;">
                  <i class="bi bi-gear"></i> 項目を管理
                </a>
              </div>
              <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <?php foreach ($quick_items as $item): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary" 
                          onclick="addQuickItem('<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['default_amount'] ?>)">
                    <?= htmlspecialchars($item['item_name']) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- クーポン・ポイント -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-ticket-perforated"></i> クーポン・ポイント
            </h4>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-ticket"></i> クーポン
                </label>
                <select class="form-select" name="coupon" id="coupon">
                  <option value="">クーポンを選択してください</option>
                  <?php foreach ($coupons as $coupon): ?>
                    <option value="<?= htmlspecialchars($coupon['coupon_code']) ?>"
                            data-discount="<?= $coupon['nebiki'] ?>"
                            <?= $reservation['coupon'] === $coupon['coupon_code'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($coupon['coupon_name']) ?> (-¥<?= number_format($coupon['nebiki']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label">
                  <i class="bi bi-star"></i> 使用ポイント
                </label>
                <input type="number" class="form-control" name="point" id="point" 
                       value="<?= $reservation['point'] ?>" min="0" step="1"
                       placeholder="使用するポイント数を入力">
                <small class="text-muted">※1ポイント = 1円として利用できます</small>
              </div>
            </div>
          </div>

          <!-- 支払い方法 -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-credit-card"></i> 支払い方法
            </h4>
            
            <div class="mb-3">
              <label class="form-label">
                <i class="bi bi-cash"></i> 支払い方法
              </label>
              <select class="form-select" name="pay">
                <option value="1" <?= $reservation['pay'] == 1 ? 'selected' : '' ?>>現金</option>
                <option value="2" <?= $reservation['pay'] == 2 ? 'selected' : '' ?>>クレジットカード</option>
                <option value="3" <?= $reservation['pay'] == 3 ? 'selected' : '' ?>>電子マネー</option>
              </select>
            </div>
          </div>

          <!-- コメント -->
          <div class="form-section">
            <h4 class="form-section-title">
              <i class="bi bi-chat-text"></i> コメント・要望
            </h4>
            
            <div class="mb-3">
              <label class="form-label">
                <i class="bi bi-chat-left-text"></i> 備考・要望事項
              </label>
              <textarea class="form-control" name="comment" rows="4" 
                        placeholder="お客様からの要望や注意事項などを記入してください"><?= htmlspecialchars($reservation['comment']) ?></textarea>
            </div>
          </div>

          <!-- 料金サマリー -->
          <div class="cost-summary">
            <h4 style="margin-bottom: 20px; color: #495057;">
              <i class="bi bi-calculator"></i> 料金計算
            </h4>
            
            <div class="cost-row">
              <span class="cost-label">コース料金</span>
              <span class="cost-value" id="display-course-cost">¥0</span>
            </div>
            
            <div class="cost-row">
              <span class="cost-label">オプション料金</span>
              <span class="cost-value" id="display-options-cost">¥0</span>
            </div>
            
            <div class="cost-row">
              <span class="cost-label">場所料金</span>
              <span class="cost-value" id="display-place-cost">¥0</span>
            </div>
            
            <div id="extra-cost-display"></div>
            
            <div class="cost-row" id="coupon-row" style="display: none;">
              <span class="cost-label" style="color: #dc3545;">クーポン割引</span>
              <span class="cost-value" style="color: #dc3545;" id="display-coupon-discount">-¥0</span>
            </div>
            
            <div class="cost-row" id="point-row" style="display: none;">
              <span class="cost-label" style="color: #dc3545;">ポイント使用</span>
              <span class="cost-value" style="color: #dc3545;" id="display-point-use">-¥0</span>
            </div>
            
            <div class="cost-row">
              <span class="cost-label" style="font-size: 20px;">合計金額</span>
              <span class="cost-total" id="display-total-cost">¥0</span>
            </div>
          </div>

          <!-- アクションボタン -->
          <div class="action-buttons">
            <button type="button" class="btn-submit btn-cancel" onclick="confirmCancel()">
              <i class="bi bi-x-circle"></i> キャンセル
            </button>
            <button type="button" class="btn-submit btn-save" onclick="confirmSave()">
              <i class="bi bi-check-circle"></i> 保存する
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 確認モーダル -->
  <div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-exclamation-circle"></i> 変更の保存確認
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>予約情報を変更します。よろしいですか？</p>
          <div style="background: #fff8e1; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
            <strong style="color: #f57c00;">注意事項：</strong>
            <ul style="margin: 10px 0 0 20px; color: #666;">
              <li>変更内容は即座に反映されます</li>
              <li>顧客・キャストへの通知は別途行ってください</li>
            </ul>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">戻る</button>
          <button type="button" class="btn btn-primary" onclick="submitForm()">
            <i class="bi bi-check"></i> 保存する
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let extraItems = [];
    let extraItemCounter = 0;
    
    function loadExistingExtraItems() {
      const costUchiwake = <?= json_encode($cost_uchiwake_array ?? []) ?>;
      const basicItems = ['コース料金', 'オプション料金', '場所料金', 'クーポン割引', 'ポイント使用'];
      const totalItems = ['合計', '合計金額', '総額', 'Total'];
      
      costUchiwake.forEach(item => {
        if (item && item.name && 
            !basicItems.includes(item.name) && 
            !totalItems.includes(item.name) && 
            item.amount > 0) {
          addExtraItem(item.name, item.amount);
        }
      });
    }
    
    function addExtraItem(name = '', amount = 0) {
      const id = extraItemCounter++;
      extraItems.push({ id, name, amount });
      
      const container = document.getElementById('extra-items-container');
      const itemDiv = document.createElement('div');
      itemDiv.id = `extra-item-${id}`;
      itemDiv.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: center;';
      
      itemDiv.innerHTML = `
        <div style="flex: 1;">
          <input type="text" 
                 class="form-control extra-item-name" 
                 placeholder="項目名（例：特別派遣料金）"
                 value="${name}"
                 data-id="${id}"
                 onchange="updateExtraItem(${id})">
        </div>
        <div style="width: 200px;">
          <input type="number" 
                 class="form-control extra-item-amount" 
                 placeholder="金額"
                 value="${amount}"
                 min="0"
                 step="100"
                 data-id="${id}"
                 onchange="updateExtraItem(${id})">
        </div>
        <button type="button" 
                class="btn btn-danger" 
                onclick="removeExtraItem(${id})"
                style="padding: 10px 15px;">
          <i class="bi bi-trash"></i>
        </button>
      `;
      
      container.appendChild(itemDiv);
      calculateCost();
    }
    
    function addQuickItem(name, amount) {
      addExtraItem(name, amount);
    }
    
    function updateExtraItem(id) {
      const nameInput = document.querySelector(`.extra-item-name[data-id="${id}"]`);
      const amountInput = document.querySelector(`.extra-item-amount[data-id="${id}"]`);
      
      const item = extraItems.find(i => i.id === id);
      if (item) {
        item.name = nameInput.value;
        item.amount = parseInt(amountInput.value || 0);
      }
      
      calculateCost();
    }
    
    function removeExtraItem(id) {
      extraItems = extraItems.filter(item => item.id !== id);
      document.getElementById(`extra-item-${id}`).remove();
      calculateCost();
    }
    
    function calculateCost() {
      const courseSelect = document.getElementById('c_name');
      const courseCost = courseSelect.selectedOptions[0]?.dataset.cost || 0;
      document.getElementById('course_cost').value = courseCost;
      document.getElementById('display-course-cost').textContent = '¥' + parseInt(courseCost).toLocaleString();
      
      const courseTime = courseSelect.selectedOptions[0]?.dataset.time || 0;
      document.getElementById('course_time').value = courseTime;
      
      let optionsCost = 0;
      document.querySelectorAll('input[name="options[]"]:checked').forEach(checkbox => {
        optionsCost += parseInt(checkbox.dataset.cost || 0);
      });
      document.getElementById('options_cost').value = optionsCost;
      document.getElementById('display-options-cost').textContent = '¥' + optionsCost.toLocaleString();
      
      const placeSelect = document.getElementById('place');
      const placeCost = placeSelect.selectedOptions[0]?.dataset.cost || 0;
      document.getElementById('place_cost').value = placeCost;
      document.getElementById('display-place-cost').textContent = '¥' + parseInt(placeCost).toLocaleString();
      
      let extraCostTotal = 0;
      const extraCostDisplay = document.getElementById('extra-cost-display');
      extraCostDisplay.innerHTML = '';
      
      extraItems.forEach(item => {
        if (item.name && item.amount > 0) {
          extraCostTotal += item.amount;
          const rowDiv = document.createElement('div');
          rowDiv.className = 'cost-row';
          rowDiv.innerHTML = `
            <span class="cost-label">${item.name}</span>
            <span class="cost-value">¥${item.amount.toLocaleString()}</span>
          `;
          extraCostDisplay.appendChild(rowDiv);
        }
      });
      
      const couponSelect = document.getElementById('coupon');
      const couponDiscount = couponSelect.selectedOptions[0]?.dataset.discount || 0;
      document.getElementById('coupon_discount').value = couponDiscount;
      const couponRow = document.getElementById('coupon-row');
      if (parseInt(couponDiscount) > 0) {
        couponRow.style.display = 'flex';
        document.getElementById('display-coupon-discount').textContent = '-¥' + parseInt(couponDiscount).toLocaleString();
      } else {
        couponRow.style.display = 'none';
      }
      
      const pointUse = parseInt(document.getElementById('point').value || 0);
      const pointRow = document.getElementById('point-row');
      if (pointUse > 0) {
        pointRow.style.display = 'flex';
        document.getElementById('display-point-use').textContent = '-¥' + pointUse.toLocaleString();
      } else {
        pointRow.style.display = 'none';
      }
      
      const totalCost = parseInt(courseCost) + optionsCost + parseInt(placeCost) + extraCostTotal - parseInt(couponDiscount) - pointUse;
      document.getElementById('display-total-cost').textContent = '¥' + Math.max(0, totalCost).toLocaleString();
    }
    
    document.getElementById('place').addEventListener('change', function() {
      const hotelSection = document.getElementById('hotel-section');
      if (this.value === 'ホテル') {
        hotelSection.classList.add('show');
      } else {
        hotelSection.classList.remove('show');
      }
      calculateCost();
    });
    
    document.getElementById('c_name').addEventListener('change', calculateCost);
    
    document.querySelectorAll('input[name="options[]"]').forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const label = this.closest('.option-checkbox');
        if (this.checked) {
          label.classList.add('checked');
        } else {
          label.classList.remove('checked');
        }
        calculateCost();
      });
    });
    
    document.getElementById('coupon').addEventListener('change', calculateCost);
    document.getElementById('point').addEventListener('input', calculateCost);
    
    function prepareExtraItemsForSubmit() {
      const existingHidden = document.getElementById('extra-items-json');
      if (existingHidden) {
        existingHidden.remove();
      }
      
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.id = 'extra-items-json';
      hiddenInput.name = 'extra_items_json';
      hiddenInput.value = JSON.stringify(extraItems.filter(item => item.name && item.amount > 0));
      document.getElementById('edit-form').appendChild(hiddenInput);
    }
    
    function confirmSave() {
      prepareExtraItemsForSubmit();
      const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
      modal.show();
    }
    
    function confirmCancel() {
      if (confirm('編集をキャンセルして戻りますか？\n入力内容は保存されません。')) {
        window.location.href = 'admin_reservation_detail.php?id=<?= $reservation_id ?>';
      }
    }
    
    function submitForm() {
      prepareExtraItemsForSubmit();
      document.getElementById('edit-form').submit();
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      loadExistingExtraItems();
      calculateCost();
    });
  </script>
</body>
</html>