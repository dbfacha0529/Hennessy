<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

header('Content-Type: application/json');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // 指定週のシフト取得
        case 'get_week_shifts':
            $start_date = $_GET['start_date'] ?? date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($start_date . ' +7 days'));
            
            // 承認済みシフト取得（shiftテーブルから）
            $sql_approved = "SELECT s.*, 'approved' as source 
                           FROM shift s 
                           WHERE s.g_login_id = :g_login_id 
                           AND s.date >= :start_date 
                           AND s.date < :end_date";
            $stmt = $pdo->prepare($sql_approved);
            $stmt->execute([
                ':g_login_id' => $g_login_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $approved_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 申請中シフト取得（shift_requestsテーブルから、pendingのみ）
            $sql_requests = "SELECT * FROM shift_requests 
                           WHERE g_login_id = :g_login_id 
                           AND date >= :start_date 
                           AND date < :end_date
                           AND status = 'pending'
                           ORDER BY updated_at DESC";
            $stmt = $pdo->prepare($sql_requests);
            $stmt->execute([
                ':g_login_id' => $g_login_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $request_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 日付ごとに整理
            $shifts = [];
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($start_date . " +{$i} day"));
                $shifts[$date] = [
                    'approved' => null,
                    'request' => null
                ];
                
                // 承認済みシフト
                foreach ($approved_shifts as $shift) {
                    if ($shift['date'] === $date) {
                        $shifts[$date]['approved'] = $shift;
                        break;
                    }
                }
                
                // 申請中シフト
                foreach ($request_shifts as $request) {
                    if ($request['date'] === $date) {
                        $shifts[$date]['request'] = $request;
                        break;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'shifts' => $shifts
            ]);
            break;

        // シフト提出（新ロジック）
        case 'submit_shifts':
            $shifts = json_decode(file_get_contents('php://input'), true);
            
            if (empty($shifts)) {
                echo json_encode(['success' => false, 'error' => 'シフトデータが不正です']);
                exit;
            }
            
            $pdo->beginTransaction();
            
            try {
                $submitted_count = 0;
                
                foreach ($shifts as $shift_data) {
                    $date = $shift_data['date'];
                    $in_hour = $shift_data['in_hour'] ?? null;
                    $out_hour = $shift_data['out_hour'] ?? null;
                    $lo = $shift_data['lo'] ?? 0;
                    
                    // 出勤なしの場合（チェックボックスOFF）
                    $is_working = !empty($in_hour) && !empty($out_hour);
                    
                    // === ステップ1: 承認済みシフト（shiftテーブル）を取得 ===
                    $check_approved_sql = "SELECT * FROM shift 
                                          WHERE g_login_id = :g_login_id 
                                          AND date = :date 
                                          LIMIT 1";
                    $check_approved_stmt = $pdo->prepare($check_approved_sql);
                    $check_approved_stmt->execute([
                        ':g_login_id' => $g_login_id,
                        ':date' => $date
                    ]);
                    $approved_shift = $check_approved_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // === ステップ2: 入力値と承認済みシフトを比較 ===
                    $is_same_as_approved = false;
                    
                    if ($approved_shift && $is_working) {
                        // 時刻をDATETIME型に変換して比較
                        $input_in_time = convertToDatetime($date, $in_hour);
                        $input_out_time = convertToDatetime($date, $out_hour);
                        
                        $is_same_as_approved = (
    date('Y-m-d H:i:s', strtotime($input_in_time)) === date('Y-m-d H:i:s', strtotime($approved_shift['in_time'])) &&
    date('Y-m-d H:i:s', strtotime($input_out_time)) === date('Y-m-d H:i:s', strtotime($approved_shift['out_time'])) &&
    $lo == $approved_shift['LO']
);
                    }
                    
                    // === ステップ3: shift_requestsテーブルの既存リクエストを確認 ===
                    $check_request_sql = "SELECT id FROM shift_requests 
                                         WHERE g_login_id = :g_login_id 
                                         AND date = :date 
                                         AND status = 'pending'
                                         LIMIT 1";
                    $check_request_stmt = $pdo->prepare($check_request_sql);
                    $check_request_stmt->execute([
                        ':g_login_id' => $g_login_id,
                        ':date' => $date
                    ]);
                    $existing_request = $check_request_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // === ステップ4: ロジック分岐 ===
                    
                    // パターンA: 承認済みと同じ値
                    if ($is_same_as_approved) {
                        // リクエストがあれば削除
                        if ($existing_request) {
                            $delete_sql = "DELETE FROM shift_requests 
                                         WHERE id = :id";
                            $delete_stmt = $pdo->prepare($delete_sql);
                            $delete_stmt->execute([':id' => $existing_request['id']]);
                        }
                        // リクエスト送信なし
                        continue;
                    }
                    
                    // パターンB: 出勤なし（チェックOFF）
                    if (!$is_working) {
                        // 承認済みシフトがある場合は「削除リクエスト」として扱う
                        if ($approved_shift) {
                            // リクエストがあれば更新、なければ新規登録
                            if ($existing_request) {
                                $update_sql = "UPDATE shift_requests 
                                             SET in_time = NULL, 
                                                 out_time = NULL, 
                                                 LO = 0,
                                                 status = 'pending',
                                                 updated_at = NOW()
                                             WHERE id = :id";
                                $update_stmt = $pdo->prepare($update_sql);
                                $update_stmt->execute([':id' => $existing_request['id']]);
                            } else {
                                $insert_sql = "INSERT INTO shift_requests 
                                             (date, in_time, out_time, g_login_id, LO, status, created_at, updated_at) 
                                             VALUES (:date, NULL, NULL, :g_login_id, 0, 'pending', NOW(), NOW())";
                                $insert_stmt = $pdo->prepare($insert_sql);
                                $insert_stmt->execute([
                                    ':date' => $date,
                                    ':g_login_id' => $g_login_id
                                ]);
                            }
                            $submitted_count++;
                        } else {
                            // 承認済みシフトもなく、チェックOFFなら何もしない
                            // 既存リクエストがあれば削除
                            if ($existing_request) {
                                $delete_sql = "DELETE FROM shift_requests WHERE id = :id";
                                $delete_stmt = $pdo->prepare($delete_sql);
                                $delete_stmt->execute([':id' => $existing_request['id']]);
                            }
                        }
                        continue;
                    }
                    
                    // パターンC: 承認済みと違う値を入力
                    // 時刻をDATETIME型に変換
                    $in_time = convertToDatetime($date, $in_hour);
                    $out_time = convertToDatetime($date, $out_hour);
                    
                    // 出勤時間 < 退勤時間チェック
                    if (strtotime($in_time) >= strtotime($out_time)) {
                        throw new Exception("出勤時間は退勤時間より前に設定してください（{$date}）");
                    }
                    
                    // リクエストがあれば更新、なければ新規登録
                    if ($existing_request) {
                        $update_sql = "UPDATE shift_requests 
                                     SET in_time = :in_time, 
                                         out_time = :out_time, 
                                         LO = :lo,
                                         status = 'pending',
                                         updated_at = NOW()
                                     WHERE id = :id";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([
                            ':in_time' => $in_time,
                            ':out_time' => $out_time,
                            ':lo' => $lo,
                            ':id' => $existing_request['id']
                        ]);
                    } else {
                        $insert_sql = "INSERT INTO shift_requests 
                                     (date, in_time, out_time, g_login_id, LO, status, created_at, updated_at) 
                                     VALUES (:date, :in_time, :out_time, :g_login_id, :lo, 'pending', NOW(), NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([
                            ':date' => $date,
                            ':in_time' => $in_time,
                            ':out_time' => $out_time,
                            ':g_login_id' => $g_login_id,
                            ':lo' => $lo
                        ]);
                    }
                    
                    $submitted_count++;
                }
                
                $pdo->commit();
                
                if ($submitted_count === 0) {
                    echo json_encode([
                        'success' => true, 
                        'message' => '変更がないため、提出するデータがありません',
                        'count' => 0
                    ]);
                } else {
                    echo json_encode([
                        'success' => true, 
                        'message' => "シフトを提出しました（{$submitted_count}件）",
                        'count' => $submitted_count
                    ]);
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Shift API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * 時刻文字列（17:00～27:00形式）をDATETIME型に変換
 * @param string $date 基準日（Y-m-d）
 * @param string $hour 時刻（17:00, 25:30など）
 * @return string DATETIME形式の文字列
 */
function convertToDatetime($date, $hour) {
    // 「25:30」のような文字列を分解
    list($h, $m) = explode(':', $hour);
    $h = intval($h);
    $m = intval($m);
    
    // 24時間を超える場合は翌日に繰り越し
    if ($h >= 24) {
        $h -= 24;
        $date = date('Y-m-d', strtotime($date . ' +1 day'));
    }
    
    return sprintf('%s %02d:%02d:00', $date, $h, $m);
}
?>