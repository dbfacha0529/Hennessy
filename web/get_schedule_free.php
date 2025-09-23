<?php
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

    $courseMinutes = (int)$courseData['time']; // timeカラムから分単位で取得
    $prepTime = 10; // 前後10分

    // シフト取得
    $sql = "SELECT * FROM shift WHERE date = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
    $stmt->execute();
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 予約取得
    $sql = "SELECT * FROM reserve WHERE date = :date";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
    $stmt->execute();
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 30分刻みのスロット生成
    $slotStart = new DateTime($date . ' 17:00');
    $slotEnd = new DateTime($date . ' 03:00');
    $slotEnd->modify('+1 day'); // 翌日3:00まで

    $slots = [];
    $current = clone $slotStart;
    while ($current <= $slotEnd) {
        $slots[] = clone $current;
        $current->modify('+30 minutes');
    }

    // 空き計算
    $availability = [];
    $y = $courseMinutes + 2 * $prepTime; // コース時間 + 前後準備20分

    foreach ($slots as $slot) {
        $slotKey = $slot->format('H:i');
        $slotEndTime = clone $slot;
        $slotEndTime->modify("+{$y} minutes");

        $count = 0;

        foreach ($shifts as $shift) {
            $shiftIn = new DateTime($shift['in_time']);
            $shiftOut = new DateTime($shift['out_time']);
            $LO = $shift['LO'];

            // 前準備10分考慮
            $shiftInPrep = clone $shiftIn;
            $shiftInPrep->modify('-10 minutes');

            // 1. 始業前準備チェック
            if ($slot < $shiftInPrep) continue;

            // 2. 終了時間シフトチェック（LO=1は無視）
            if ($LO == 0 && $slotEndTime > $shiftOut) continue;

            // 3. 予約チェック（重複しないか）
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
    echo json_encode([]);
}
?>