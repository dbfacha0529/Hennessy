<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

header('Content-Type: application/json');

// ログインチェック
if (empty($_SESSION['USER']['tel'])) {
    echo json_encode(['success' => false, 'is_favorite' => false]);
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];
$g_login_id = $_GET['g_login_id'] ?? '';

if (empty($g_login_id)) {
    echo json_encode(['success' => false, 'is_favorite' => false]);
    exit;
}

try {
    $sql = "SELECT COUNT(*) FROM favorite_girls WHERE tel = :tel AND g_login_id = :g_login_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
    $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'is_favorite' => $count > 0]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'is_favorite' => false]);
}
?>