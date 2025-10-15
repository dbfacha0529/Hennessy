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
  
  $room_id = $_GET['room_id'] ?? null;
  
  if (!$room_id) {
    echo json_encode(['success' => false, 'message' => 'ルームIDが指定されていません']);
    exit;
  }
  
  // ルーム情報を取得
  $sql = "
    SELECT 
      cr.user_tel,
      cr.g_login_id,
      u.user_name,
      g.name as girl_name
    FROM chat_rooms cr
    LEFT JOIN users u ON cr.user_tel COLLATE utf8mb4_unicode_ci = u.tel COLLATE utf8mb4_unicode_ci
    LEFT JOIN girl g ON CAST(cr.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
    WHERE cr.id = :room_id
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
  $stmt->execute();
  $room_info = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$room_info) {
    echo json_encode(['success' => false, 'message' => 'ルームが見つかりません']);
    exit;
  }
  
  // メッセージ一覧を取得（管理者が閲覧してもis_readは変更しない）
  $sql = "
    SELECT 
      id,
      sender_type,
      message_type,
      content,
      file_path,
      is_read,
      created_at
    FROM chat_messages
    WHERE room_id = :room_id
    ORDER BY created_at ASC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
  $stmt->execute();
  $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo json_encode([
    'success' => true,
    'room_info' => $room_info,
    'messages' => $messages
  ]);
  
  unset($pdo);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'データベースエラー: ' . $e->getMessage()
  ]);
}