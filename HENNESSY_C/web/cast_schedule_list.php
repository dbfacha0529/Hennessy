<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// キャストログインチェック
if (!isset($_SESSION['cast_login'])) {
  header('Location: cast_login.php');
  exit;
}

$cast = $_SESSION['CAST'];
$g_login_id = $cast['g_login_id'];

try {
  $pdo = dbConnect();
  
  // 現在時刻から基準日を計算（管理者用と同じロジック）
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
  
  // shop情報（営業時間）を取得
  $stmt = $pdo->query("SELECT start_time, end_time FROM shop LIMIT 1");
  $shop = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // 自分のシフト情報を取得
  $stmt = $pdo->prepare("
    SELECT * FROM shift 
    WHERE date = :date AND g_login_id = :g_login_id
    LIMIT 1
  ");
  $stmt->execute([
    ':date' => $selected_date,
    ':g_login_id' => $g_login_id
  ]);
  $shift = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // 自分の予約情報を取得
  $stmt = $pdo->prepare("
    SELECT * FROM reserve 
    WHERE date = :date AND g_login_id = :g_login_id AND done != 5
    ORDER BY in_time
  ");
  $stmt->execute([
    ':date' => $selected_date,
    ':g_login_id' => $g_login_id
  ]);
  $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // シフトがある場合の時間範囲計算
  $display_start = null;
  $display_end = null;
  $shift_start = null;
  $shift_end = null;
  
  if ($shift) {
    // シフト開始30分前から表示
    $shift_start = strtotime($shift['in_time']);
    $shift_end = strtotime($shift['out_time']);
    $display_start = strtotime('-30 minutes', $shift_start);
    $display_end = $shift_end;
    
    // 予約がシフト終了時刻を超える場合は拡張
    foreach ($reservations as $res) {
      $res_end = strtotime($res['out_time']);
      if ($res_end > $display_end) {
        $display_end = $res_end;
      }
    }
  }
  
  // 30分単位の時間軸を生成
  $time_slots = [];
  if ($display_start && $display_end) {
    $current_time = $display_start;
    // 最初の時間を30分単位に切り捨て
    $current_time = strtotime(date('Y-m-d H:i:00', $current_time));
    $minutes = (int)date('i', $current_time);
    if ($minutes < 30) {
      $current_time = strtotime(date('Y-m-d H:00:00', $current_time));
    } else {
      $current_time = strtotime(date('Y-m-d H:30:00', $current_time));
    }
    
    while ($current_time <= $display_end + 1800) { // 終了時刻+30分まで表示
      $time_slots[] = $current_time;
      $current_time = strtotime('+30 minutes', $current_time);
    }
  }
  
  // 現在時刻が選択された基準日の範囲内かチェック
  $now_timestamp = time();
  $show_current_time = false;
  
  // 基準日の範囲：選択日の6:00から翌日の5:59まで
  $base_date_start = strtotime($selected_date . ' 06:00:00');
  $base_date_end = strtotime($selected_date . ' +1 day 06:00:00') - 1;
  
  if ($now_timestamp >= $base_date_start && $now_timestamp <= $base_date_end) {
    $show_current_time = true;
  }
  
  // サマリー情報の計算
  $summary = [
    'shift_time' => '',
    'reservation_count' => count($reservations),
    'next_reservation' => null
  ];
  
  if ($shift) {
    $summary['shift_time'] = date('H:i', $shift_start) . ' ～ ' . date('H:i', $shift_end);
    
    // 次の予約を探す
    foreach ($reservations as $res) {
      if (strtotime($res['in_time']) > $now_timestamp) {
        $summary['next_reservation'] = $res;
        break;
      }
    }
  }
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// ステータスに応じたクラス名とラベルを返す
function getStatusInfo($done) {
  switch ($done) {
    case 1: return ['class' => 'status-waiting', 'label' => '情報待ち'];
    case 2: return ['class' => 'status-confirmed', 'label' => '予約確定'];
    case 3: return ['class' => 'status-completed', 'label' => '完了'];
    case 4: return ['class' => 'status-processing', 'label' => '対応中'];
    default: return ['class' => 'status-cancelled', 'label' => 'キャンセル'];
  }
}

// 予約が特定の時間スロットに重なっているかチェック
function isReservationInSlot($reservation, $slot_time) {
  $res_start = strtotime($reservation['in_time']);
  $res_end = strtotime($reservation['out_time']);
  $slot_end = strtotime('+30 minutes', $slot_time);
  
  // 予約の開始時刻がスロット内、または予約がスロットを完全に覆っている
  return ($res_start < $slot_end && $res_end > $slot_time);
}

// 予約カードの開始スロットかチェック（in_timeを参照）
function isReservationStart($reservation, $slot_time) {
  $res_start = strtotime($reservation['in_time']);
  $slot_end = strtotime('+30 minutes', $slot_time);
  
  return ($res_start >= $slot_time && $res_start < $slot_end);
}

// 予約の表示スロット数を計算（in_timeとout_timeを参照）
function getReservationSlotCount($reservation, $slot_time) {
  $res_start = strtotime($reservation['in_time']);
  $res_end = strtotime($reservation['out_time']);
  
  $duration_minutes = ($res_end - $res_start) / 60;
  $slots = ceil($duration_minutes / 30);
  
  return $slots;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>スケジュール - <?= htmlspecialchars($cast['name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #ffe4e9 0%, #ffd4e0 100%);
      min-height: 100vh;
      padding-bottom: 11vh;
    }

    .container {
      max-width: 50rem;
      margin: 0 auto;
      padding: 1.25rem;
    }

        /* 日付選択バー */
    .date-selector {
      background: white;
      padding: 1rem;
      border-radius: 0.75rem;
      margin-bottom: 1rem;
      box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.75rem;
    }

    .date-nav-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      min-width: 100%;
    }

    .date-bottom-group {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      gap: 0.75rem;
    }

    .date-nav-btn {
      background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
      border: none;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 0.25rem;
      white-space: nowrap;
    }

    .date-nav-btn:hover {
      background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
      transform: translateY(-0.125rem);
    }

    .date-nav-btn:active {
      transform: translateY(0);
    }

    .date-input {
      padding: 0.5rem 0.75rem;
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 600;
      color: #333;
      flex: 1;
    }

    .current-date-label {
      font-size: 1rem;
      font-weight: bold;
      color: #ff1493;
      flex: 1;
      text-align: right;
    }

    /* サマリー情報 */
    .summary-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .summary-card {
      background: white;
      padding: 1rem;
      border-radius: 0.75rem;
      box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .summary-card-label {
      font-size: 0.75rem;
      color: #666;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    .summary-card-value {
      font-size: 1.5rem;
      font-weight: bold;
      color: #ff1493;
    }

    .summary-card-icon {
      font-size: 1.5rem;
      color: #ff69b4;
      margin-bottom: 0.5rem;
    }

    /* タイムテーブル */
    .schedule-table {
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.1);
      overflow: hidden;
      max-height: calc(100vh - 18rem);
      overflow-y: auto;
    }

    .time-slot {
      display: flex;
      border-bottom: 0.0625rem solid #f0f0f0;
      min-height: 5rem;
      position: relative;
    }

    .time-label {
      width: 4.5rem;
      padding: 0.75rem;
      background: #f8f9fa;
      border-right: 0.125rem solid #ffb6c1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      font-weight: 600;
      font-size: 0.875rem;
      color: #666;
      flex-shrink: 0;
    }

    .time-content {
      flex: 1;
      position: relative;
      padding: 0.5rem;
      background: white;
    }

    /* シフト時間の背景 */
    .time-slot.in-shift .time-content {
      background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
    }

    /* 予約カード */
    .reservation-card {
      background: white;
      border-radius: 0.5rem;
      padding: 0.75rem;
      box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.15);
      cursor: pointer;
      transition: all 0.3s;
      border-left: 0.25rem solid #ff69b4;
    }

    .reservation-card:hover {
      transform: translateX(0.25rem);
      box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.2);
    }

    .reservation-card.status-waiting {
      border-left-color: #ff9800;
      background: linear-gradient(135deg, #fff8f0 0%, #ffe4cc 100%);
    }

    .reservation-card.status-confirmed {
      border-left-color: #4caf50;
      background: linear-gradient(135deg, #f0fff4 0%, #d4f4dd 100%);
    }

    .reservation-card.status-completed {
      border-left-color: #9e9e9e;
      background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
      opacity: 0.7;
    }

    .reservation-card.status-processing {
      border-left-color: #ffc107;
      background: linear-gradient(135deg, #fffbea 0%, #fff4cc 100%);
    }

    .reservation-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .reservation-customer {
      font-weight: bold;
      font-size: 1rem;
      color: #333;
    }

    .reservation-status {
      font-size: 0.6875rem;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-weight: 600;
      background: rgba(255, 255, 255, 0.8);
    }

    .reservation-info {
      font-size: 0.8125rem;
      color: #666;
      line-height: 1.6;
    }

    .reservation-info-item {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      margin-bottom: 0.25rem;
    }

    .reservation-info-item i {
      color: #ff69b4;
      font-size: 0.875rem;
    }

    /* 現在時刻ライン */
    .current-time-line {
      position: absolute;
      left: 4.5rem;
      right: 0;
      height: 0.125rem;
      background: #ff0000;
      z-index: 100;
      pointer-events: none;
    }

    .current-time-line::before {
      content: '現在';
      position: absolute;
      left: 0.5rem;
      top: -0.75rem;
      background: #ff0000;
      color: white;
      padding: 0.125rem 0.375rem;
      border-radius: 0.25rem;
      font-size: 0.625rem;
      font-weight: bold;
    }

    /* シフトなし */
    .no-shift {
      text-align: center;
      padding: 3rem 1rem;
      color: #999;
    }

    .no-shift i {
      font-size: 3rem;
      color: #ddd;
      margin-bottom: 1rem;
    }

    .no-shift p {
      font-size: 1rem;
      margin-bottom: 0.5rem;
    }

    /* レスポンシブ対応 */
    @media (max-width: 30rem) {
      .container {
        padding: 0.75rem;
      }

      .date-selector {
        padding: 0.75rem;
        gap: 0.5rem;
      }

      .date-nav-group {
        justify-content: space-between;
      }

      .date-nav-btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.9375rem;
      }

      .date-input {
        font-size: 0.9375rem;
        padding: 0.5rem 0.625rem;
      }

      .date-bottom-group {
        gap: 0.5rem;
      }

      .current-date-label {
        font-size: 1rem;
        text-align: right;
      }

      .summary-cards {
        grid-template-columns: 1fr;
      }

      .summary-card-value {
        font-size: 1.25rem;
      }

      .time-label {
        width: 3.5rem;
        font-size: 0.875rem;
        padding: 0.5rem;
      }

      .reservation-card {
        padding: 0.5rem;
      }

      .reservation-customer {
        font-size: 1rem;
      }

      .reservation-status {
        font-size: 0.75rem;
      }

      .reservation-info {
        font-size: 0.875rem;
      }

      .reservation-info-item i {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <?php require_once(dirname(__FILE__) . '/header.php'); ?>

  <div class="container">
    <!-- 日付選択 -->
    <div class="date-selector">
      <div class="date-nav-group">
        <button class="date-nav-btn" onclick="changeDate(-1)">
          <i class="bi bi-chevron-left"></i> 前日
        </button>
        
        <input type="date" class="date-input" id="date-picker" value="<?= $selected_date ?>" 
               onchange="location.href='cast_schedule_list.php?date=' + this.value">
        
        <button class="date-nav-btn" onclick="changeDate(1)">
          次日 <i class="bi bi-chevron-right"></i>
        </button>
      </div>
      
      <div class="date-bottom-group">
        <button class="date-nav-btn" onclick="location.href='cast_schedule_list.php'">
          <i class="bi bi-calendar-today"></i> 今日
        </button>
        
        <div class="current-date-label">
          <?= date('Y年n月j日 (D)', strtotime($selected_date)) ?>
        </div>
      </div>
    </div>

    <?php if (!$shift): ?>
      <!-- シフトなし -->
      <div class="schedule-table">
        <div class="no-shift">
          <i class="bi bi-calendar-x"></i>
          <p>この日はシフト登録がありません</p>
        </div>
      </div>
    <?php else: ?>
      <!-- サマリー情報 -->
      <div class="summary-cards">
        <div class="summary-card">
          <div class="summary-card-icon">
            <i class="bi bi-clock"></i>
          </div>
          <div class="summary-card-label">出勤時間</div>
          <div class="summary-card-value"><?= $summary['shift_time'] ?></div>
        </div>
        
        <div class="summary-card">
          <div class="summary-card-icon">
            <i class="bi bi-calendar-check"></i>
          </div>
          <div class="summary-card-label">予約件数</div>
          <div class="summary-card-value"><?= $summary['reservation_count'] ?>件</div>
        </div>
        
        <?php if ($summary['next_reservation']): ?>
        <div class="summary-card">
          <div class="summary-card-icon">
            <i class="bi bi-alarm"></i>
          </div>
          <div class="summary-card-label">次の予約</div>
          <div class="summary-card-value" style="font-size: 1rem;">
            <?= date('H:i', strtotime($summary['next_reservation']['in_time'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- タイムテーブル -->
      <div class="schedule-table" id="schedule-table">
        <?php foreach ($time_slots as $slot): ?>
          <?php
          $is_in_shift = ($slot >= $shift_start && $slot < $shift_end);
          $is_current_slot = false;
          
          // 現在時刻がこのスロット内か判定
          if ($show_current_time && $now_timestamp >= $slot && $now_timestamp < strtotime('+30 minutes', $slot)) {
            $is_current_slot = true;
          }
          ?>
          
          <div class="time-slot <?= $is_in_shift ? 'in-shift' : '' ?>" id="slot-<?= date('Hi', $slot) ?>">
            <div class="time-label">
              <?= date('H:i', $slot) ?>
            </div>
            
            <div class="time-content">
              <?php
              // このスロットで開始する予約を表示
              foreach ($reservations as $res):
                if (isReservationStart($res, $slot)):
                  $status_info = getStatusInfo($res['done']);
                  $slot_count = getReservationSlotCount($res, $slot);
                  
                  // 場所情報の整理
                  $location_text = '';
                  if ($res['place'] === 'ホテル' && !empty($res['hotel'])) {
                    $location_text = $res['hotel'];
                  } elseif ($res['place'] === 'その他' && !empty($res['place_comment'])) {
                    $location_text = $res['place_comment'];
                  } elseif ($res['place'] === 'ご自宅') {
                    $location_text = 'ご自宅';
                  } else {
                    $location_text = $res['place'];
                  }
                  
                  // エリア情報の整理
                  $area_text = '';
                  if (!empty($res['area']) && $res['area'] !== 'その他') {
                    $area_text = $res['area'];
                  } elseif ($res['area'] === 'その他' && !empty($res['area_comment'])) {
                    $area_text = $res['area_comment'];
                  }
              ?>
                <div class="reservation-card <?= $status_info['class'] ?>" 
                     onclick="location.href='cast_reserve_detail.php?id=<?= $res['id'] ?>'">
                  <div class="reservation-header">
                    <div class="reservation-customer">
                      <?= htmlspecialchars($res['user_name']) ?> 様
                    </div>
                    <div class="reservation-status">
                      <?= $status_info['label'] ?>
                    </div>
                  </div>
                  
                  <div class="reservation-info">
                    <div class="reservation-info-item">
                      <i class="bi bi-clock"></i>
                      <?= date('H:i', strtotime($res['in_time'])) ?> ～ 
                      <?= date('H:i', strtotime($res['out_time'])) ?>
                      (<?= $res['course_time'] ?>分)
                    </div>
                    
                    <div class="reservation-info-item">
                      <i class="bi bi-ticket-perforated"></i>
                      <?= htmlspecialchars($res['c_name']) ?>
                    </div>
                    
                    <?php if ($location_text): ?>
                    <div class="reservation-info-item">
                      <i class="bi bi-geo-alt"></i>
                      <?= htmlspecialchars($location_text) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($area_text)): ?>
                    <div class="reservation-info-item">
                      <i class="bi bi-map"></i>
                      <?= htmlspecialchars($area_text) ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php
                endif;
              endforeach;
              ?>
            </div>
            
            <?php if ($is_current_slot): ?>
              <div class="current-time-line"></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function changeDate(days) {
      const currentDate = new Date('<?= $selected_date ?>');
      currentDate.setDate(currentDate.getDate() + days);
      const newDate = currentDate.toISOString().split('T')[0];
      location.href = 'cast_schedule_list.php?date=' + newDate;
    }
  </script>

  <?php require_once(dirname(__FILE__) . '/footer.php'); ?>
</body>
</html>