<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => '認証エラー']);
  exit;
}

// JSONデータ取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['girl_id']) || !isset($data['status'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
  exit;
}

$girl_id = $data['girl_id'];
$new_status = $data['status'];

// ステータスのバリデーション
if (!in_array($new_status, ['y', 'n'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '不正なステータス値です']);
  exit;
}

try {
  $pdo = dbConnect();
  
  // ステータス更新
  $sql = "UPDATE girl SET k_check = :status WHERE g_login_id = :girl_id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':status', $new_status, PDO::PARAM_STR);
  $stmt->bindValue(':girl_id', $girl_id, PDO::PARAM_INT);
  
  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'ステータスを更新しました',
      'new_status' => $new_status
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '更新に失敗しました']);
  }
  
  unset($pdo);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DBエラー: ' . $e->getMessage()]);
}
?>