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
        // 基準日を取得
        $base_date = get_BaseDate();
        $now = new DateTime();

        // 今日のシフト情報を取得
        $stmt = $pdo->prepare("
            SELECT in_time, out_time, LO
            FROM shift 
            WHERE g_login_id = :gid 
              AND date = :base_date
            LIMIT 1
        ");
        $stmt->execute([':gid' => $g_login_id, ':base_date' => $base_date]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            // シフトがある場合
            $shift_in = new DateTime($shift['in_time']);
            $shift_out = new DateTime($shift['out_time']);
            $LO = (int)$shift['LO'];

            // 出勤終了チェック
            $is_shift_ended = ($now >= $shift_out);

            if (!$is_shift_ended) {
                // === ステータス判定 ===
                // 「今すぐOK」判定（60分コース可能か）
                $course_time = 60;
                $prep_time = 10;
                $total_time = $course_time + 2 * $prep_time; // 80分

                $slot_start = clone $now;
                $slot_end = clone $now;
                $slot_end->modify("+{$total_time} minutes");

                // シフト開始10分前から受付可能
                $shift_in_prep = clone $shift_in;
                $shift_in_prep->modify('-10 minutes');

                $is_available = true;

                // 1. シフト開始前チェック
                if ($slot_start < $shift_in_prep) {
                    $is_available = false;
                }

                // 2. シフト終了時間チェック（LO=0の場合のみ）
                if ($LO == 0 && $slot_end > $shift_out) {
                    $is_available = false;
                }

                // 3. 予約重複チェック
                if ($is_available) {
                    $sql_reserve = "SELECT * FROM reserve WHERE date = :date AND g_login_id = :gid";
                    $stmt_reserve = $pdo->prepare($sql_reserve);
                    $stmt_reserve->execute([':date' => $base_date, ':gid' => $g_login_id]);
                    $reserves = $stmt_reserve->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($reserves as $res) {
                        $res_start = new DateTime($res['start_time']);
                        $res_end = new DateTime($res['end_time']);

                        // 重複判定
                        if (!($slot_end <= $res_start || $slot_start >= $res_end)) {
                            $is_available = false;
                            break;
                        }
                    }
                }

                if ($is_available) {
                    $result['status'] = '今すぐOK';
                } else {
                    $result['status'] = 'Today';
                }

                // === 時間表示判定 ===
                $is_working = ($now >= $shift_in && $now < $shift_out);

                if ($is_working) {
                    // 出勤中 → シフト終了時間
                    $result['time'] = '～' . $shift_out->format('H:i');
                } elseif ($now < $shift_in) {
                    // 未出勤だが本日これから出勤 → シフト開始時間
                    $result['time'] = $shift_in->format('H:i') . '～';
                } else {
                    $result['time'] = '';
                }

                // out_timeもセット（互換性のため）
                $result['out_time'] = $shift_out->format('H:i');
            } else {
                // 出勤終了済み
                $result['status'] = '';
                $result['time'] = '';
                $result['out_time'] = '';
            }
        } else {
            // 今日の出勤がない場合は最も近い未来の日付を取得
            $stmt = $pdo->prepare("
                SELECT date 
                FROM shift 
                WHERE g_login_id = :gid 
                  AND date > :base_date
                ORDER BY date ASC
                LIMIT 1
            ");
            $stmt->execute([':gid' => $g_login_id, ':base_date' => $base_date]);
            $nextShift = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nextShift) {
                // 未来の出勤日を表示（M/D形式）
                $next_date = new DateTime($nextShift['date']);
                $result['time'] = $next_date->format('n/j');
                $result['status'] = '';
                $result['out_time'] = $nextShift['date'];
            } else {
                $result['status'] = '';
                $result['time'] = '';
                $result['out_time'] = '';
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($result);