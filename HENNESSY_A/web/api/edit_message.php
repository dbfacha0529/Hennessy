<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  echo json_encode(['success' => false, 'message' => '認証エラー']);
  exit;
}

header('Content-Type: application/json');

try {
  $pdo = dbConnect();
  
  $message_id = $_POST['message_id'] ?? null;
  $content = $_POST['content'] ?? null;
  
  if (!$message_id || !$content) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
    exit;
  }
  
  // メッセージが存在するか確認（テキストメッセージのみ編集可能）
  $stmt = $pdo->prepare("SELECT id, message_type FROM chat_messages WHERE id = :message_id");
  $stmt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
  $stmt->execute();
  $message = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$message) {
    echo json_encode(['success' => false, 'message' => 'メッセージが見つかりません']);
    exit;
  }
  
  if ($message['message_type'] !== 'text') {
    echo json_encode(['success' => false, 'message' => 'テキストメッセージのみ編集できます']);
    exit;
  }
  
  // メッセージを更新
  $sql = "UPDATE chat_messages SET content = :content WHERE id = :message_id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':content', $content, PDO::PARAM_STR);
  $stmt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
  $stmt->execute();
  
  echo json_encode([
    'success' => true,
    'message' => 'メッセージを編集しました'
  ]);
  
  unset($pdo);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'データベースエラー: ' . $e->getMessage()
  ]);
}