<?php
// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once(dirname(__FILE__) . '/../functions.php');

header('Content-Type: application/json');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// アップロード先ディレクトリ（相対パス）
$upload_dir = dirname(__FILE__) . '/../../img/chat_uploads/';

// ディレクトリが存在しない場合は作成
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'アップロードディレクトリの作成に失敗しました']);
        exit;
    }
}

try {
    $room_id = $_POST['room_id'] ?? 0;
    $message_type = $_POST['message_type'] ?? '';
    
    if (empty($room_id)) {
        echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
        exit;
    }
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'ファイルが選択されていません']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // ファイルアップロードエラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'ファイルのアップロードに失敗しました']);
        exit;
    }
    
    // ファイルサイズチェック（10MB = 10 * 1024 * 1024 bytes）
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'ファイルサイズが大きすぎます（最大10MB）']);
        exit;
    }
    
    // ファイルタイプチェック
    $allowed_image_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowed_video_types = ['video/mp4', 'video/quicktime', 'video/x-msvideo'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if ($message_type === 'image' && !in_array($file_type, $allowed_image_types)) {
        echo json_encode(['success' => false, 'error' => '許可されていない画像形式です']);
        exit;
    }
    
    if ($message_type === 'video' && !in_array($file_type, $allowed_video_types)) {
        echo json_encode(['success' => false, 'error' => '許可されていない動画形式です']);
        exit;
    }
    
    // ルームの所有確認
    $check_sql = "SELECT id FROM chat_rooms 
                  WHERE id = :room_id 
                  AND g_login_id = :g_login_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':room_id' => $room_id,
        ':g_login_id' => $g_login_id
    ]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
        exit;
    }
    
    // ファイル拡張子を取得
    $original_filename = $file['name'];
    $path_info = pathinfo($original_filename);
    $extension = strtolower($path_info['extension']);
    
    // ユニークなファイル名を生成（タイムスタンプ + ランダム文字列）
    $unique_filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $upload_dir . $unique_filename;
    
    // ファイルを移動
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'error' => 'ファイルの保存に失敗しました']);
        exit;
    }
    
    // データベースにメッセージを保存（file_pathにはファイル名のみ）
    $insert_sql = "INSERT INTO chat_messages 
                   (room_id, sender_type, message_type, content, file_path, is_read, created_at) 
                   VALUES (:room_id, 'girl', :message_type, '', :file_path, 0, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([
        ':room_id' => $room_id,
        ':message_type' => $message_type,
        ':file_path' => $unique_filename  // ファイル名のみ
    ]);
    
    // ルームの最終メッセージ時刻を更新
    $update_sql = "UPDATE chat_rooms 
                   SET last_message_at = NOW() 
                   WHERE id = :room_id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([':room_id' => $room_id]);
    
    echo json_encode([
        'success' => true,
        'message_id' => $pdo->lastInsertId(),
        'filename' => $unique_filename
    ]);
    
} catch (PDOException $e) {
    error_log('Chat Upload API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラーが発生しました'
    ]);
} catch (Exception $e) {
    error_log('Chat Upload General Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'エラーが発生しました'
    ]);
}
?>