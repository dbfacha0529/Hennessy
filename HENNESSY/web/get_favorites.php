<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

header('Content-Type: application/json');

// ログインチェック
if (empty($_SESSION['USER']['tel'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

try {
    $sql = "SELECT g.g_login_id, g.name, g.img, g.head_comment 
            FROM favorite_girls f 
            INNER JOIN girl g ON f.g_login_id = g.g_login_id 
            WHERE f.tel = :tel 
            ORDER BY f.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
    $stmt->execute();
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'favorites' => $favorites]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました']);
}
?>