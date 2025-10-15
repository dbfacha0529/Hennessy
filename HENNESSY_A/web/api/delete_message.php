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
  
  if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'メッセージIDが指定されていません']);
    exit;
  }
  
  // メッセージ情報を取得（ファイルパスとメッセージタイプを含む）
  $stmt = $pdo->prepare("SELECT id, message_type, file_path FROM chat_messages WHERE id = :message_id");
  $stmt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
  $stmt->execute();
  $message = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$message) {
    echo json_encode(['success' => false, 'message' => 'メッセージが見つかりません']);
    exit;
  }
  
  // ファイルが存在する場合は削除
  if ($message['file_path']) {
    // 複数の可能性のあるファイルパスを試行
    $possible_paths = [
      // パターン1: 絶対パス形式（/から始まる場合）
      $_SERVER['DOCUMENT_ROOT'] . $message['file_path'],
      
      // パターン2: 相対パス形式（img/chat_uploads/から始まる場合）
      dirname(__FILE__) . '/../../../' . $message['file_path'],
      
      // パターン3: ファイル名のみの場合
      dirname(__FILE__) . '/../../../img/chat_uploads/' . basename($message['file_path'])
    ];
    
    $file_deleted = false;
    foreach ($possible_paths as $file_path) {
      if (file_exists($file_path)) {
        if (unlink($file_path)) {
          $file_deleted = true;
          error_log("ファイル削除成功: " . $file_path);
          break;
        } else {
          error_log("ファイル削除失敗: " . $file_path);
        }
      }
    }
    
    // ファイルが見つからなかった場合でもDB削除は続行（エラーログのみ出力）
    if (!$file_deleted) {
      error_log("ファイルが見つかりませんでした: " . $message['file_path']);
    }
  }
  
  // メッセージをDBから削除（物理削除）
  $stmt = $pdo->prepare("DELETE FROM chat_messages WHERE id = :message_id");
  $stmt->bindValue(':message_id', $message_id, PDO::PARAM_INT);
  $stmt->execute();
  
  // メッセージタイプに応じたメッセージ
  $delete_type = '';
  switch ($message['message_type']) {
    case 'image':
      $delete_type = '画像メッセージ';
      break;
    case 'video':
      $delete_type = '動画メッセージ';
      break;
    case 'voice':
      $delete_type = '音声メッセージ';
      break;
    default:
      $delete_type = 'メッセージ';
  }
  
  echo json_encode([
    'success' => true,
    'message' => $delete_type . 'を削除しました'
  ]);
  
  unset($pdo);
} catch (Exception $e) {
  error_log("delete_message.php エラー: " . $e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => 'データベースエラー: ' . $e->getMessage()
  ]);
}