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
  
  $room_id = $_POST['room_id'] ?? null;
  $content = $_POST['content'] ?? '';
  
  if (!$room_id) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
    exit;
  }
  
  // ルームが存在するか確認
  $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE id = :room_id");
  $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
  $stmt->execute();
  
  if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'ルームが見つかりません']);
    exit;
  }
  
  // ファイルアップロード処理
  $uploaded_files = [];
  if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
    $upload_dir = dirname(__FILE__) . '/../../../img/chat_uploads/';
    
    // ディレクトリが存在しない場合は作成
    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0755, true);
    }
    
    $file_count = count($_FILES['files']['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
      $file_name = $_FILES['files']['name'][$i];
      $file_tmp = $_FILES['files']['tmp_name'][$i];
      $file_size = $_FILES['files']['size'][$i];
      $file_error = $_FILES['files']['error'][$i];
      $file_type = $_FILES['files']['type'][$i];
      
      if ($file_error !== UPLOAD_ERR_OK) {
        continue;
      }
      
      // ファイルサイズチェック（10MB）
      if ($file_size > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'ファイルサイズが大きすぎます（最大10MB）']);
        exit;
      }
      
      // ファイルタイプチェック
      $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/quicktime', 'video/webm'];
      if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => '許可されていないファイル形式です']);
        exit;
      }
      
      // ファイル名を一意にする
      $extension = pathinfo($file_name, PATHINFO_EXTENSION);
      $unique_name = uniqid('chat_', true) . '.' . $extension;
      $upload_path = $upload_dir . $unique_name;
      
      if (move_uploaded_file($file_tmp, $upload_path)) {
        $uploaded_files[] = [
          'path' => '/HENNESSY/img/chat_uploads/' . $unique_name,
          'type' => strpos($file_type, 'image') !== false ? 'image' : 'video'
        ];
      }
    }
  }
  
  // テキストメッセージがある場合は保存
  if (!empty($content)) {
    $sql = "
      INSERT INTO chat_messages (room_id, sender_type, message_type, content, created_at)
      VALUES (:room_id, 'girl', 'text', :content, NOW())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
    $stmt->bindValue(':content', $content, PDO::PARAM_STR);
    $stmt->execute();
  }
  
  // ファイルメッセージを保存
  foreach ($uploaded_files as $file) {
    $sql = "
      INSERT INTO chat_messages (room_id, sender_type, message_type, file_path, created_at)
      VALUES (:room_id, 'girl', :message_type, :file_path, NOW())
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
    $stmt->bindValue(':message_type', $file['type'], PDO::PARAM_STR);
    $stmt->bindValue(':file_path', $file['path'], PDO::PARAM_STR);
    $stmt->execute();
  }
  
  // メッセージまたはファイルがある場合のみ最終メッセージ時刻を更新
  if (!empty($content) || !empty($uploaded_files)) {
    $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id");
    $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode([
      'success' => true,
      'message' => 'メッセージを送信しました'
    ]);
  } else {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
  }
  
  unset($pdo);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'データベースエラー: ' . $e->getMessage()
  ]);
}