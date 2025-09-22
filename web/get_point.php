<?php
session_start();
include '../functions.php';
$pdo = dbConnect();

header('Content-Type: application/json');

// セッションから tel を取得
if (!isset($_SESSION['USER']['tel'])) {
    echo json_encode(['success'=>false, 'message'=>'ログイン情報がありません']);
    exit;
}

$tel = $_SESSION['USER']['tel'];

try {
    // 最新レコード取得
    $stmt = $pdo->prepare("
        SELECT balance_after 
        FROM point 
        WHERE tel = :tel 
        ORDER BY created DESC 
        LIMIT 1
    ");
    $stmt->execute([':tel' => $tel]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success'=>true, 'balance'=>$row['balance_after']]);
    } else {
        // まだポイントがない場合
        echo json_encode(['success'=>true, 'balance'=>0]);
    }

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>'ポイント取得に失敗しました']);
}
