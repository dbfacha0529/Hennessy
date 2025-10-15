<?php
require_once(dirname(__FILE__) . '/../functions.php');

// 1セル（30分）の幅をピクセルで定義
$cell_width_px = 60; // 30分 = 60px

// 固定の時間範囲：17:00〜29:00（翌日5:00）
$fixed_start_hour = 17;
$fixed_end_hour = 29;
$total_hours = $fixed_end_hour - $fixed_start_hour; // 12時間
$total_slots = $total_hours * 2; // 30分単位なので×2
$timeline_width_px = $total_slots * $cell_width_px;

// 固定の時間軸スロットを生成
$time_slots = [];
for ($h = $fixed_start_hour; $h < $fixed_end_hour; $h++) {
  for ($m = 0; $m < 60; $m += 30) {
    $display_hour = $h >= 24 ? $h - 24 : $h;
    $time_slots[] = sprintf('%02d:%02d', $display_hour, $m);
  }
}

// 時刻を17:00基準の時間（17〜29）に変換する関数
function timeToFixedHour($time_str) {
  $time = new DateTime($time_str);
  $hour = (int)$time->format('H');
  $minute = (int)$time->format('i');
  
  // 6:00未満なら24を足す（翌日扱い）
  if ($hour < 6) {
    $hour += 24;
  }
  
  return $hour + ($minute / 60); // 小数点込みの時間を返す
}

// 時刻からピクセル位置を計算する関数
function calcPositionPx($time_str, $fixed_start_hour, $cell_width_px) {
  $fixed_hour = timeToFixedHour($time_str);
  $offset_hours = $fixed_hour - $fixed_start_hour;
  
  // 1時間 = 2セル（30分×2）
  return $offset_hours * 2 * $cell_width_px;
}

// 開始時刻と終了時刻から幅を計算する関数
function calcWidthPx($start_time_str, $end_time_str, $cell_width_px) {
  $start_fixed = timeToFixedHour($start_time_str);
  $end_fixed = timeToFixedHour($end_time_str);
  
  $duration_hours = $end_fixed - $start_fixed;
  
  // 1時間 = 2セル（30分×2）
  return $duration_hours * 2 * $cell_width_px;
}

// ===== データ取得部分 =====
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
  
  // 表示する日付
  $selected_date = $_GET['date'] ?? $today_base_date;
  
  // その日のシフト情報を取得
  $stmt = $pdo->prepare("
    SELECT s.*, g.name as girl_name, g.img
    FROM shift s
    LEFT JOIN girl g ON s.g_login_id = g.g_login_id
    WHERE s.date = :date
    ORDER BY s.in_time
  ");
  $stmt->execute([':date' => $selected_date]);
  $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // その日の予約情報を取得
  $stmt = $pdo->prepare("
    SELECT r.*, g.name as girl_name
    FROM reserve r
    LEFT JOIN girl g ON r.g_login_id = g.g_login_id
    WHERE r.date = :date AND r.done != 5
    ORDER BY r.in_time
  ");
  $stmt->execute([':date' => $selected_date]);
  $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // キャスト毎の予約を整理
  $schedule_data = [];
  foreach ($shifts as $shift) {
    $g_id = $shift['g_login_id'];
    $schedule_data[$g_id] = [
      'shift' => $shift,
      'reservations' => []
    ];
    
    foreach ($reservations as $res) {
      if ($res['g_login_id'] == $g_id) {
        $schedule_data[$g_id]['reservations'][] = $res;
      }
    }
  }
  
  // 現在時刻が選択された基準日の範囲内かチェック
  $now_timestamp = time();
  $base_date_start = strtotime($selected_date . ' 06:00:00');
  $base_date_end = strtotime($selected_date . ' +1 day 06:00:00') - 1;
  
  $show_current_time = false;
  if ($now_timestamp >= $base_date_start && $now_timestamp <= $base_date_end) {
    $show_current_time = true;
  }
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// その他の関数は既存のまま
function getStatusClass($done) {
  switch ($done) {
    case 1: return 'status-waiting';
    case 2: return 'status-confirmed';
    case 3: return 'status-completed';
    case 4: return 'status-processing';
    default: return 'status-cancelled';
  }
}

function getReservationLocationText($res) {
  $text = '';
  
  if (!empty($res['area'])) {
    if ($res['area'] === 'その他' && !empty($res['area_comment'])) {
      $text = htmlspecialchars($res['area_comment']);
    } else {
      $text = htmlspecialchars($res['area']);
    }
  }
  
  if (!empty($res['place'])) {
    if ($text) $text .= ' / ';
    
    if ($res['place'] === 'その他' && !empty($res['place_comment'])) {
      $text .= htmlspecialchars($res['place_comment']);
    } else {
      $text .= htmlspecialchars($res['place']);
    }
  }
  
  return $text;
}
?>

<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>スケジュール管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
    .schedule-container {
      padding: 20px;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      position: relative;
    }
    
    .date-selector {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 30px;
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
    }
    
    .date-nav-btn:hover {
      background: #f0c94a;
    }
    
    .schedule-wrapper {
      overflow-x: auto;
      position: relative;
      max-height: calc(100vh - 350px);
      overflow-y: auto;
    }
    
    .schedule-grid {
      min-width: <?= $timeline_width_px + 150 ?>px; /* キャスト名150px + タイムライン */
      position: relative;
      background: #ffffff;
    }
    
    .time-header {
      display: flex;
      margin-bottom: 10px;
      padding-left: 150px;
      position: sticky;
      top: 0;
      z-index: 20;
      background: #f8f9fa;
      border-radius: 4px;
      border: 1px solid #dee2e6;
    }
    
    .time-slot-header {
      width: <?= $cell_width_px ?>px;
      flex: none;
      text-align: center;
      border-left: 1px solid #dee2e6;
      padding: 8px 5px;
      font-size: 13px;
      color: #495057;
      font-weight: 600;
    }
    
    .cast-row {
      display: flex;
      margin-bottom: 8px;
      position: relative;
      min-height: 70px;
      background: #ffffff;
      border-radius: 4px;
      border: 1px solid #dee2e6;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .cast-name {
      width: 150px;
      display: flex;
      align-items: center;
      padding: 10px;
      font-weight: bold;
      color: #212529;
      background: #f8f9fa;
      border-right: 2px solid #d4af37;
      font-size: 14px;
      position: sticky;
      left: 0;
      z-index: 10;
    }
    
    .cast-timeline {
      position: relative;
      width: <?= $timeline_width_px ?>px;
      min-width: <?= $timeline_width_px ?>px;
      background: #ffffff;
    }
    
    .time-grid {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: flex;
    }
    
    .time-cell {
      width: <?= $cell_width_px ?>px;
      border-left: 1px solid #e9ecef;
    }
    
    .shift-block {
      position: absolute;
      top: 0;
      bottom: 0;
      background: rgba(212, 175, 55, 0.1);
      border: 1px dashed #d4af37;
      border-radius: 2px;
    }
    
    .reservation-block {
      position: absolute;
      top: 8px;
      bottom: 8px;
      border-radius: 6px;
      padding: 4px 8px;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      overflow: hidden;
      transition: all 0.2s;
      box-shadow: 0 2px 4px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1px;
    }
    
    .reservation-block .res-time {
      font-size: 10px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      opacity: 0.95;
    }
    
    .reservation-block .res-header {
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .reservation-block .res-location {
      font-size: 9px;
      font-weight: 500;
      opacity: 0.85;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .reservation-block:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.25);
      z-index: 10;
    }
    
    .status-waiting {
      background: #ff9800;
      border: 2px solid #f57c00;
      color: #ffffff;
    }
    
    .status-confirmed {
      background: #4caf50;
      border: 2px solid #388e3c;
      color: #ffffff;
    }
    
    .status-completed {
      background: #2196f3;
      border: 2px solid #1976d2;
      color: #ffffff;
    }
    
    .status-processing {
      background: #ffd54f;
      border: 2px solid #fbc02d;
      color: #1a1a1a;
    }
    
    .status-cancelled {
      background: #bdbdbd;
      border: 2px solid #9e9e9e;
      color: #ffffff;
      opacity: 0.6;
    }
    
    .current-time-line {
      position: absolute;
      top: 0;
      bottom: 0;
      width: 3px;
      background: #ff0000;
      z-index: 15;
      pointer-events: none;
    }
    
    .current-time-line::before {
      content: '現在';
      position: absolute;
      top: -20px;
      left: -15px;
      background: #ff0000;
      color: white;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: bold;
    }
    
    .legend {
      display: flex;
      gap: 20px;
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 4px;
      border: 1px solid #dee2e6;
      flex-wrap: wrap;
      position: sticky;
      bottom: 0;
      z-index: 20;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #495057;
      font-weight: 500;
    }
    
    .legend-color {
      width: 35px;
      height: 22px;
      border-radius: 4px;
    }
    
    .no-shift {
      text-align: center;
      padding: 40px;
      color: #6c757d;
      font-size: 14px;
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
      <a href="admin_schedule.php" class="nav-item active">
        <i class="bi bi-calendar3"></i>
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
      <h1><i class="bi bi-calendar3"></i> スケジュール管理</h1>
      <div class="header-info">
        <span class="date-time" id="current-datetime"></span>
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <div class="schedule-container">
      <!-- 日付選択 -->
      <div class="date-selector">
        <button class="date-nav-btn" onclick="changeDate(-1)">
          <i class="bi bi-chevron-left"></i> 前日
        </button>
        
        <input type="date" id="date-picker" value="<?= $selected_date ?>" 
               onchange="location.href='admin_schedule.php?date=' + this.value">
        
        <button class="date-nav-btn" onclick="changeDate(1)">
          次日 <i class="bi bi-chevron-right"></i>
        </button>
        
        <button class="date-nav-btn" onclick="location.href='admin_schedule.php'">
          <i class="bi bi-calendar-today"></i> 今日
        </button>
        
        <span style="margin-left: auto; color: #212529; font-weight: bold; font-size: 16px;">
          <?= date('Y年m月d日 (D)', strtotime($selected_date)) ?>
        </span>
      </div>

      <?php if (empty($schedule_data)): ?>
        <div class="no-shift">
          <i class="bi bi-calendar-x" style="font-size: 48px; margin-bottom: 15px;"></i>
          <p>この日はシフト登録がありません</p>
        </div>
      <?php else: ?>
        <!-- スケジュールグリッド -->
        <div class="schedule-wrapper" id="schedule-wrapper">
          <div class="schedule-grid">
            <!-- 時間軸ヘッダー -->
            <div class="time-header">
              <?php foreach ($time_slots as $slot): ?>
                <div class="time-slot-header">
                  <?= $slot ?>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- キャスト毎の行 -->
            <?php foreach ($schedule_data as $g_id => $data): ?>
              <?php
              $shift = $data['shift'];
              $shift_left_px = calcPositionPx($shift['in_time'], $fixed_start_hour, $cell_width_px);
              $shift_width_px = calcWidthPx($shift['in_time'], $shift['out_time'], $cell_width_px);
              ?>
              
              <div class="cast-row">
                <div class="cast-name">
                  <?= htmlspecialchars($shift['girl_name']) ?>
                </div>
                
                <div class="cast-timeline">
                  <!-- 時間グリッド -->
                  <div class="time-grid">
                    <?php foreach ($time_slots as $slot): ?>
                      <div class="time-cell"></div>
                    <?php endforeach; ?>
                  </div>
                  
                  <!-- シフト時間帯 -->
                  <div class="shift-block" style="left: <?= $shift_left_px ?>px; width: <?= $shift_width_px ?>px;"></div>
                  
                  <!-- 現在時刻ライン -->
                  <?php if ($show_current_time): ?>
                    <?php $current_time_px = calcPositionPx(date('Y-m-d H:i:s'), $fixed_start_hour, $cell_width_px); ?>
                    <div class="current-time-line" style="left: <?= $current_time_px ?>px;"></div>
                  <?php endif; ?>
                  
                  <!-- 予約ブロック -->
                  <?php foreach ($data['reservations'] as $res): ?>
                    <?php
                    $res_left_px = calcPositionPx($res['in_time'], $fixed_start_hour, $cell_width_px);
                    $res_width_px = calcWidthPx($res['in_time'], $res['out_time'], $cell_width_px);
                    $status_class = getStatusClass($res['done']);
                    $location_text = getReservationLocationText($res);
                    $girl_name = htmlspecialchars($res['girl_name'] ?? '未定');
                    $course_name = htmlspecialchars($res['c_name']);
                    $in_time = date('H:i', strtotime($res['in_time']));
                    $out_time = date('H:i', strtotime($res['out_time']));
                    ?>
                    
                    <div class="reservation-block <?= $status_class ?>" 
                         style="left: <?= $res_left_px ?>px; width: <?= $res_width_px ?>px;"
                         onclick="location.href='admin_reservation_detail.php?id=<?= $res['id'] ?>'">
                      <div class="res-time"><?= $in_time ?> - <?= $out_time ?></div>
                      <div class="res-header"><?= $girl_name ?> - <?= $course_name ?></div>
                      <?php if ($location_text): ?>
                        <div class="res-location"><?= $location_text ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 凡例 -->
        <div class="legend">
          <div class="legend-item">
            <div class="legend-color status-waiting"></div>
            <span>情報待ち</span>
          </div>
          <div class="legend-item">
            <div class="legend-color status-confirmed"></div>
            <span>予約確定</span>
          </div>
          <div class="legend-item">
            <div class="legend-color status-processing"></div>
            <span>対応中</span>
          </div>
          <div class="legend-item">
            <div class="legend-color status-completed"></div>
            <span>完了</span>
          </div>
          <div class="legend-item">
            <div class="legend-color" style="background: rgba(212, 175, 55, 0.1); border: 1px dashed #d4af37;"></div>
            <span>シフト時間</span>
          </div>
          <?php if ($show_current_time): ?>
          <div class="legend-item">
            <div class="legend-color" style="background: #ff0000;"></div>
            <span>現在時刻</span>
          </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function changeDate(days) {
      const currentDate = new Date('<?= $selected_date ?>');
      currentDate.setDate(currentDate.getDate() + days);
      const newDate = currentDate.toISOString().split('T')[0];
      location.href = 'admin_schedule.php?date=' + newDate;
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
      const datetimeEl = document.getElementById('current-datetime');
      if (datetimeEl) {
        datetimeEl.textContent = now.toLocaleString('ja-JP', options);
      }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // 初期スクロール位置を設定（現在時刻の1時間前）
    window.addEventListener('load', function() {
      <?php if ($show_current_time): ?>
        const wrapper = document.getElementById('schedule-wrapper');
        const currentTimePx = <?= isset($current_time_px) ? $current_time_px : 0 ?>;
        
        // 現在時刻の1時間前（2セル = 120px前）の位置を計算
        const scrollPos = Math.max(0, currentTimePx - 120);
        wrapper.scrollLeft = scrollPos;
      <?php endif; ?>
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>