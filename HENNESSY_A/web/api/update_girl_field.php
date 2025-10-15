<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => '認証エラー']);
  exit;
}

// JSONデータを取得
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['girl_id']) || !isset($data['field']) || !isset($data['value'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
  exit;
}

$girl_id = $data['girl_id'];
$field = $data['field'];
$value = $data['value'];

// 更新可能なフィールドのホワイトリスト
$allowed_fields = [
  'name',
  'age',
  'high',
  'b',
  'w',
  'h',
  'head_comment',
  'comment',
  'store_comment'
];

if (!in_array($field, $allowed_fields)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '更新できないフィールドです']);
  exit;
}

try {
  $pdo = dbConnect();
  
  // 源氏名の重複チェック
  if ($field === 'name') {
    $checkSql = "SELECT COUNT(*) as count FROM girl WHERE name = :name AND g_login_id != :g_login_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindValue(':name', $value, PDO::PARAM_STR);
    $checkStmt->bindValue(':g_login_id', $girl_id, PDO::PARAM_STR);
    $checkStmt->execute();
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
      echo json_encode(['success' => false, 'message' => 'この源氏名は既に使用されています']);
      exit;
    }
    
    // 源氏名が空の場合はエラー
    if (empty(trim($value))) {
      echo json_encode(['success' => false, 'message' => '源氏名は必須です']);
      exit;
    }
  }
  
  // 数値フィールドのバリデーション
  if (in_array($field, ['age', 'high'])) {
    if (!is_numeric($value) || $value < 0) {
      echo json_encode(['success' => false, 'message' => '正しい数値を入力してください']);
      exit;
    }
  }
  
  // 更新処理
  $sql = "UPDATE girl SET {$field} = :value WHERE g_login_id = :g_login_id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':value', $value, PDO::PARAM_STR);
  $stmt->bindValue(':g_login_id', $girl_id, PDO::PARAM_STR);
  
  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => '更新しました',
      'field' => $field,
      'value' => $value
    ]);
  } else {
    throw new Exception('更新に失敗しました');
  }
  
  unset($pdo);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'エラーが発生しました: ' . $e->getMessage()
  ]);
}
?>