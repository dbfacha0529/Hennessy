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
        // 指定週の承認済みシフト取得
        case 'get_week_shifts':
            $start_date = $_GET['start_date'] ?? date('Y-m-d');
            $end_date = date('Y-m-d', strtotime($start_date . ' +7 days'));
            
            // 承認済みシフト取得（in_time と out_time を使用）
            $sql = "SELECT s.date, s.in_time, s.out_time, s.LO,
                    (SELECT COUNT(*) FROM reserve r 
                     WHERE r.g_login_id = s.g_login_id 
                     AND r.date = s.date 
                     AND r.done IN (0, 1, 2, 3)) as reserve_count
                    FROM shift s 
                    WHERE s.g_login_id = :g_login_id 
                    AND s.date >= :start_date 
                    AND s.date < :end_date
                    ORDER BY s.date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':g_login_id' => $g_login_id,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 日付ごとに整理
            $organized_shifts = [];
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($start_date . " +{$i} day"));
                $organized_shifts[$date] = null;
                
                foreach ($shifts as $shift) {
                    if ($shift['date'] === $date) {
                        $organized_shifts[$date] = $shift;
                        break;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'shifts' => $organized_shifts
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Shift List API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラーが発生しました: ' . $e->getMessage()
    ]);
}
?>