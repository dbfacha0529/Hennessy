
<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$user_tel = $_SESSION['USER']['tel'];

// 予約IDチェック
if (!isset($_POST['reserve_id'])) {
    header('Location: reserve_list.php');
    exit;
}

$reserve_id = (int)$_POST['reserve_id'];

// DB接続
$pdo = dbConnect();

try {
    $pdo->beginTransaction();
    
    // 予約情報取得
    $stmt = $pdo->prepare("SELECT * FROM reserve WHERE id = :id AND tel = :tel");
    $stmt->execute([':id' => $reserve_id, ':tel' => $user_tel]);
    $reserve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserve) {
        throw new Exception('予約が見つかりません');
    }
    
   
    // 既にキャンセル済みまたは完了済みチェック
if ($reserve['done'] == 5) {
    throw new Exception('この予約は既にキャンセル済みです');
}

if ($reserve['done'] == 3) {
    throw new Exception('完了済みの予約はキャンセルできません');
}
    
    // 1時間前チェック
    $in_time = new DateTime($reserve['in_time']);
    $now = new DateTime();
    $diff = $in_time->getTimestamp() - $now->getTimestamp();
    
    if ($diff < 3600) { // 3600秒 = 1時間
        throw new Exception('予約開始時刻の1時間前を過ぎているためキャンセルできません');
    }
    
    // done=5に更新（キャンセル）
    $updateStmt = $pdo->prepare("UPDATE reserve SET done = 5 WHERE id = :id");
    $updateStmt->execute([':id' => $reserve_id]);
    
    // ポイント返却処理
    $used_point = (int)$reserve['point'];
    
    if ($used_point > 0) {
        // 最新ポイント残高取得
        $pointStmt = $pdo->prepare("SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1");
        $pointStmt->execute([':tel' => $user_tel]);
        $lastBalance = $pointStmt->fetchColumn();
        
        $balance_before = $lastBalance !== false ? (int)$lastBalance : 0;
        $balance_after = $balance_before + $used_point;
        
        // ポイント返却レコード追加
        $insertPointSql = "INSERT INTO point (tel, type, point, balance_before, balance_after, created) 
                  VALUES (:tel, 'refund', :point, :balance_before, :balance_after, NOW())";
$insertPointStmt = $pdo->prepare($insertPointSql);
$insertPointStmt->execute([
    ':tel' => $user_tel,
    ':point' => $used_point,
    ':balance_before' => $balance_before,
    ':balance_after' => $balance_after
]);
    }
    
    $pdo->commit();
    
    // 成功メッセージ
    $_SESSION['cancel_success'] = '予約をキャンセルしました';
    if ($used_point > 0) {
        $_SESSION['cancel_success'] .= "（{$used_point}ポイント返還）";
    }
    
    header('Location: reserve_list.php');
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['cancel_error'] = $e->getMessage();
    header('Location: reserve_list_up.php?id=' . $reserve_id);
    exit;
}


