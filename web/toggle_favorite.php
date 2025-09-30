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
$g_login_id = $_POST['g_login_id'] ?? '';

if (empty($g_login_id)) {
    echo json_encode(['success' => false, 'message' => '女の子が指定されていません']);
    exit;
}

try {
    // 既にお気に入りに登録されているかチェック
    $sql = "SELECT id FROM favorite_girls WHERE tel = :tel AND g_login_id = :g_login_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
    $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // 既に登録済み → 削除
        $sql = "DELETE FROM favorite_girls WHERE tel = :tel AND g_login_id = :g_login_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
        $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'お気に入りから削除しました']);
    } else {
        // 未登録 → 追加
        $sql = "INSERT INTO favorite_girls (tel, g_login_id, created_at) VALUES (:tel, :g_login_id, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
        $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'お気に入りに追加しました']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました']);
}
?>