<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(dirname(__FILE__) . '/../functions.php');

header('Content-Type: application/json');

// パラメータ取得
$date = $_GET['date'] ?? '';
$c_name = $_GET['c_name'] ?? '';

if (!$date || !$c_name) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = dbConnect();

    // コース時間取得
    $sql = "SELECT time FROM course WHERE c_name = :c_name";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':c_name', $c_name, PDO::PARAM_STR);
    $stmt->execute();
    $courseData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$courseData) {
        echo json_encode([]);
        exit;
    }

    $courseMinutes = (int)$courseData['time'];
    $prepTime = 10;

    // シフト取得(get_schedule.phpと同じ方式)
    $sql = "SELECT * FROM shift WHERE DATE(in_time) = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
    $stmt->execute();
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // シフトデータがない場合
    if (empty($shifts)) {
        echo json_encode(['no_data' => true]);
        exit;
    }

    // 予約取得(get_schedule.phpと同じ方式)
    $sql = "SELECT * FROM reserve WHERE 
            (DATE(start_time) = :date OR DATE(start_time) = DATE_ADD(:date2, INTERVAL 1 DAY))
            AND done != 5";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
    $stmt->bindValue(':date2', $date, PDO::PARAM_STR);
    $stmt->execute();
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // シフトから最小開始時刻と最大終了時刻を取得
    $shiftStartTimes = [];
    $shiftEndTimes = [];
    
    foreach ($shifts as $shift) {
        $shiftStartTimes[] = new DateTime($shift['in_time']);
        $shiftEndTimes[] = new DateTime($shift['out_time']);
    }
    
    $slotStart = min($shiftStartTimes);
    $slotEnd = max($shiftEndTimes);

    // 現在時刻を取得し、表示開始時刻を調整
    $now = new DateTime();
    $displayStart = max($slotStart, $now);
    $minutes = (int)$displayStart->format('i');
    if ($minutes % 30 !== 0) {
        $displayStart->modify('+' . (30 - $minutes % 30) . ' minutes');
    }

    // 30分刻みのスロット生成
    $slots = [];
    $current = clone $displayStart;
    while ($current <= $slotEnd) {
        $slots[] = clone $current;
        $current->modify('+30 minutes');
    }

    $availability = [];
    $y = $courseMinutes + 2 * $prepTime;

    foreach ($slots as $slot) {
        $slotKey = $slot->format('H:i');
        $slotEndTime = clone $slot;
        $slotEndTime->modify("+{$y} minutes");

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftIn = new DateTime($shift['in_time']);
            $shiftOut = new DateTime($shift['out_time']);
            $LO = (int)($shift['LO'] ?? 0);

            // 前準備10分考慮
            $shiftInPrep = clone $shiftIn;
            $shiftInPrep->modify('-10 minutes');

            // 1. 始業前準備チェック
            if ($slot < $shiftInPrep) continue;

            // 2. 終了時間シフトチェック(LO=1は無視)
            if ($LO === 0 && $slotEndTime > $shiftOut) continue;

            // 3. 予約チェック(重複しないか)
            $conflict = false;
            foreach ($reserves as $res) {
                if ($res['g_login_id'] != $shift['g_login_id']) continue;

                $resStart = new DateTime($res['start_time']);
                $resEnd = new DateTime($res['end_time']);

                if (!($slotEndTime <= $resStart || $slot >= $resEnd)) {
                    $conflict = true;
                    break;
                }
            }
            if (!$conflict) $count++;
        }

        // 結果を記号に変換
        if ($count > 0) {
            $availability[$slotKey] = '〇';
        } else {
            $availability[$slotKey] = '✖';
        }
    }

    echo json_encode($availability);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>