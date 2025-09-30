<?php
// DB接続
include '../functions.php'; // ← dbConnect() が定義されている前提
include 'assign_functions.php'; // ← アサイン関数をインクルード
include 'base_date_functions.php'; // ← 基準日関数をインクルード

session_start();

$pdo = dbConnect();

// POSTデータ取得
$data = [
    ':pay'           => isset($_POST['pay']) ? (int)$_POST['pay'] : null,
    ':c_name'        => $_POST['c_name'] ?? null,
    ':date'          => !empty($_POST['date']) ? date('Y-m-d', strtotime($_POST['date'])) : null,
    ':in_time'       => !empty($_POST['in_time']) ? date('Y-m-d H:i:s', strtotime($_POST['in_time'])) : null,
    ':out_time'      => !empty($_POST['out_time']) ? date('Y-m-d H:i:s', strtotime($_POST['out_time'])) : null,
    ':start_time'    => !empty($_POST['start_time']) ? date('Y-m-d H:i:s', strtotime($_POST['start_time'])) : null,
    ':end_time'      => !empty($_POST['end_time']) ? date('Y-m-d H:i:s', strtotime($_POST['end_time'])) : null,
    ':g_name'        => $_POST['g_name'] ?? null,
    ':contact_tel'   => $_POST['contact_tel'] ?? null,
    ':tel'           => $_POST['tel'] ?? null,
    ':options'       => is_array($_POST['options']) ? json_encode($_POST['options'], JSON_UNESCAPED_UNICODE) : $_POST['options'],
    ':place'         => $_POST['place'] ?? null,
    ':place_comment' => $_POST['place_comment'] ?? null,
    ':area'          => $_POST['area'] ?? null,
    ':area_comment'  => $_POST['area_comment'] ?? null,
    ':comment'       => $_POST['comment'] ?? null,
    ':cost_uchiwake' => is_array($_POST['cost_uchiwake']) ? json_encode($_POST['cost_uchiwake'], JSON_UNESCAPED_UNICODE) : $_POST['cost_uchiwake'],
    ':g_login_id'    => $_POST['g_login_id'] ?? null,
    ':login_id'      => $_POST['login_id'] ?? null,
    ':user_name'     => $_POST['user_name'] ?? null,
    ':coupon'        => $_POST['coupon'] ?? null,
    ':point'         => isset($_POST['point']) ? (int)$_POST['point'] : null,
    ':course_time'   => isset($_POST['course_time']) ? (int)$_POST['course_time'] : null,
    ':cost'          => isset($_POST['cost']) ? (int)$_POST['cost'] : null,
];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 基準日を取得（予約時間から算出）
    $baseDate = get_BaseDateFromReservation($_POST['start_time']);
    
    // === デバッグコード開始 ===
    error_log("=== RESERVE SMS DEBUG START ===");
    error_log("Base Date: " . $baseDate);
    error_log("POST Data Check:");
    error_log("c_name: " . ($_POST['c_name'] ?? 'NULL'));
    error_log("start_time: " . ($_POST['start_time'] ?? 'NULL'));
    error_log("end_time: " . ($_POST['end_time'] ?? 'NULL'));
    error_log("course_time: " . ($_POST['course_time'] ?? 'NULL'));
    error_log("tel: " . ($_POST['tel'] ?? 'NULL'));
    error_log("g_name: " . ($_POST['g_name'] ?? 'NULL'));
    // === デバッグコード終了 ===
    
    // フリーコース判定
    if (!empty($_POST['c_name'])) {
        $stmt = $pdo->prepare("SELECT free_check FROM course WHERE c_name = :c_name");
        $stmt->execute([':c_name' => $_POST['c_name']]);
        $courseData = $stmt->fetch(PDO::FETCH_ASSOC);
        $isFreeCheck = $courseData ? (int)$courseData['free_check'] : 0;
        
        // === デバッグコード ===
        error_log("Free Check: " . $isFreeCheck);
        error_log("G_name empty: " . (empty($_POST['g_name']) ? 'YES' : 'NO'));
        // === デバッグコード終了 ===
        
        // フリーコースで女の子が未指定の場合、自動アサイン
        if ($isFreeCheck === 1 && empty($_POST['g_name'])) {
            error_log("=== STARTING AUTO ASSIGN ===");
            
            $assignedGirl = assignGirlToReservation($pdo, $_POST, $baseDate);
            
            if ($assignedGirl) {
                // アサイン結果をdataに反映
                $data[':g_name'] = $assignedGirl['g_name'];
                $data[':g_login_id'] = $assignedGirl['g_login_id'];
                
                // デバッグログ
                error_log("Auto-assigned SUCCESS: " . $assignedGirl['g_name'] . " (Match: " . $assignedGirl['match_rate'] . "%, Reservations: " . $assignedGirl['reservation_count'] . ")");
            } else {
                error_log("Auto-assign FAILED: No available girls found");
                
                // ロールバック
                $pdo->rollBack();
                $_SESSION['RESERVE_ERRORS']['touroku'][] = '申し訳ございません。現在対応可能なスタッフがおりません。お時間を変更してお試しください。';
                header("Location: reserve.php");
                exit;
            }
        }
    }

    // === 最終重複チェック ===
    if (!checkFinalReservationConflict($pdo, $data)) {
        error_log("Final conflict check FAILED");
        $pdo->rollBack();
        $_SESSION['RESERVE_ERRORS']['touroku'][] = '申し訳ございません。選択された時間は既に予約済みです。お時間を変更してお試しください。';
        header("Location: reserve.php");
        exit;
    }
    
    error_log("Final conflict check PASSED");

    // 予約登録
    $sql = "INSERT INTO reserve (
        pay, c_name, date, in_time, out_time, start_time, end_time, g_name, contact_tel, tel,
        options, place, place_comment, area, area_comment, comment, cost_uchiwake, 
        g_login_id, login_id, user_name, coupon, point, course_time, cost, created_at
    ) VALUES (
        :pay, :c_name, :date, :in_time, :out_time, :start_time, :end_time, :g_name, :contact_tel, :tel,
        :options, :place, :place_comment, :area, :area_comment, :comment, :cost_uchiwake,
        :g_login_id, :login_id, :user_name, :coupon, :point, :course_time, :cost, NOW()
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    // 登録ID取得
    $lastId = $pdo->lastInsertId();

    // doneカラム更新
    $updateSql = "UPDATE reserve SET done = 1 WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':id' => $lastId]);

    // ポイント処理をtry-catchで囲む
    try {
        if (!empty($_POST['point']) && (int)$_POST['point'] > 0) {
            $usePoint = (int)$_POST['point'];
            $tel = $_POST['tel'];

            // 最新ポイント取得
            $stmtPoint = $pdo->prepare("SELECT * FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1");
            $stmtPoint->execute([':tel' => $tel]);
            $lastPointData = $stmtPoint->fetch(PDO::FETCH_ASSOC);

            $balanceBefore = $lastPointData ? (int)$lastPointData['balance_after'] : 0;
            $balanceAfter = $balanceBefore - $usePoint;

            // ポイント使用レコード追加
            $insertPoint = $pdo->prepare("
                INSERT INTO point (tel, type, point, balance_before, balance_after, created)
                VALUES (:tel, :type, :point, :balance_before, :balance_after, NOW())
            ");
            $insertPoint->execute([
                ':tel' => $tel,
                ':type' => 'use',
                ':point' => $usePoint,
                ':balance_before' => $balanceBefore,
                ':balance_after' => $balanceAfter,
            ]);
        }
    } catch (Exception $ePoint) {
        // ロールバック
        $pdo->rollBack();
        error_log("Point Error: " . $ePoint->getMessage());
        $_SESSION['RESERVE_ERRORS']['touroku'][] = '予約登録中にエラーが発生しました。';
        header("Location: reserve.php");
        exit;
    }

    // コミット
    $pdo->commit();

    // セッション削除
    unset($_SESSION['RESERVE_ERRORS'], $_SESSION['RESERVE_INPUT']);

    // 画面遷移
    if ((int)($_POST['pay'] ?? 0) === 1) {
        header("Location: reserve_complete.php");
    } elseif ((int)($_POST['pay'] ?? 0) === 2) {
        header("Location: reserve_complete_pay2.php");
    } else {
        header("Location: reserve.php");
    }
    exit;

} catch (Exception $e) {
    // ロールバック
    $pdo->rollBack();
    error_log("DB Error: " . $e->getMessage());
    $_SESSION['RESERVE_ERRORS']['touroku'][] = '予約登録中にエラーが発生しました。';
    header("Location: reserve.php");
    exit;
}
?>