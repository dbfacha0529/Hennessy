<?php
include '../functions.php';
$pdo = dbConnect();

$g_name = $_GET['g_name'] ?? '';
$date   = $_GET['date'] ?? '';
$c_name = $_GET['c_name'] ?? '';

header('Content-Type: application/json');

if (!$g_name || !$date || !$c_name) {
    echo json_encode([]);
    exit;
}

// g_login_id取得
$k_checkgirls = k_checkgirls($pdo);
$girl = array_filter($k_checkgirls, fn($g)=>$g['name'] === $g_name);
$girl = reset($girl);
if (!$girl) { echo json_encode([]); exit; }
$g_login_id = $girl['g_login_id'];

// コース所要時間（分）
$stmt = $pdo->prepare("SELECT time FROM course WHERE c_name=?");
$stmt->execute([$c_name]);
$courseTime = (int)$stmt->fetchColumn();
if (!$courseTime) $courseTime = 60;

// 出勤時間取得（DATETIME + LO）
$stmt = $pdo->prepare("SELECT in_time,out_time,LO FROM shift WHERE g_login_id=? AND DATE(in_time)=?");
$stmt->execute([$g_login_id, $date]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shift) { echo json_encode(['no_data' => true]); exit; }

$LO = (int)($shift['LO'] ?? 0);

// shift範囲（in_timeだけ20分前に調整）
$shiftStart = (new DateTime($shift['in_time']));
$shiftEnd   = new DateTime($shift['out_time']);

// 表示開始時刻の自動調整（現在時刻の切り上げ30分単位）
$now = new DateTime();
$displayStart = max($shiftStart, $now);
$minutes = (int)$displayStart->format('i');
if ($minutes % 30 !== 0) {
    $displayStart->modify('+' . (30 - $minutes % 30) . ' minutes');
}

// 表示用の時間リスト（H:i 形式）
$times = [];
$timeObj = clone $displayStart;
while ($timeObj <= $shiftEnd) {
    $times[] = $timeObj->format('H:i');
    $timeObj->modify('+30 minutes');
}

// 判定用の時間リスト（DateTime）
$check_times = [];
foreach ($times as $t) {
    list($h, $i) = explode(':', $t);

    // 翌日判定
    $baseDate = $date;
    if ((int)$h < 5 && (int)$h < (int)explode(':', $shiftStart->format('H:i'))[0]) {
        // 24:00以降（深夜）は翌日扱いにする
        $baseDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
    }

    $check_times[] = new DateTime("$baseDate $h:$i:00");
}

// 予約済み取得
$stmt = $pdo->prepare("SELECT start_time,end_time FROM reserve WHERE g_login_id=? 
AND (DATE(start_time)=? OR DATE(start_time)=DATE_ADD(?, INTERVAL 1 DAY))
AND done != 5");
$stmt->execute([$g_login_id, $date, $date]);
$reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 状態判定
$result = [];
foreach ($times as $idx => $label) {
    // ①現在の時間枠
    $currStart = (clone $check_times[$idx])->modify('-10 minutes'); // 開始10分前
    $currEnd   = (clone $check_times[$idx])->modify("+{$courseTime} minutes")->modify('+10 minutes'); // 終了10分後
    $available = true;

    // ②既存予約との重複チェック
    foreach ($reserves as $r) {
        $rStart = new DateTime($r['start_time']); // reserveに既にバッファ込みならそのまま
        $rEnd   = new DateTime($r['end_time']);
        if ($currStart < $rEnd && $currEnd > $rStart) {
            $available = false;
            break;
        }
    }

    // ③シフト範囲チェック
    if ($available) {
        if ($currStart < ($shiftStart->modify('-10 minutes'))) {
            $available = false;
        } elseif (
          $LO === 0 && $currEnd > $shiftEnd) {
            $available = false;
        }
    }

    $result[$label] = $available ? '〇' : '✖';
}

echo json_encode($result);

