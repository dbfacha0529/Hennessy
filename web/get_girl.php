<?php
include '../functions.php';

// DB接続
$pdo = dbConnect();

$g_login_id = $_GET['gid'] ?? '';

$result = null;

if ($g_login_id) {
    // girlテーブルから取得
    $stmt = $pdo->prepare("SELECT * FROM girl WHERE g_login_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $g_login_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $today = date('Y-m-d');

        // まず今日のout_timeを取得
        $stmt = $pdo->prepare("
            SELECT out_time 
            FROM shift 
            WHERE g_login_id = :gid 
              AND date = :today
            LIMIT 1
        ");
        $stmt->execute([':gid' => $g_login_id, ':today' => $today]);
        $shiftToday = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($shiftToday) {
            // 今日の出勤がある場合はout_timeを設定
            $result['out_time'] = $shiftToday['out_time'];
        } else {
            // 今日の出勤がなければ最も近い未来の日付を取得
            $stmt = $pdo->prepare("
                SELECT date 
                FROM shift 
                WHERE g_login_id = :gid 
                  AND date > :today
                ORDER BY date ASC
                LIMIT 1
            ");
            $stmt->execute([':gid' => $g_login_id, ':today' => $today]);
            $nextShift = $stmt->fetch(PDO::FETCH_ASSOC);

            $result['out_time'] = $nextShift['date'] ?? ''; // 未来のdateをout_timeとして返す
        }
    }
}

header('Content-Type: application/json');
echo json_encode($result);
