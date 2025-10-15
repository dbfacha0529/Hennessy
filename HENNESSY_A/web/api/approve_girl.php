<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => '認証エラー']);
  exit;
}

$admin = $_SESSION['ADMIN'];

// 管理者システムアカウントの電話番号（固定）
define('ADMIN_SYSTEM_TEL', '00000000000');

// JSONデータ取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['pending_id']) || !isset($data['action'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
  exit;
}

$pending_id = $data['pending_id'];
$g_login_id = $data['g_login_id'] ?? null;
$action = $data['action']; // 'approved' or 'rejected'
$comment = $data['comment'] ?? '';
$request_type = $data['request_type'] ?? '';

// アクションのバリデーション
if (!in_array($action, ['approved', 'rejected'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => '不正なアクション']);
  exit;
}

try {
  $pdo = dbConnect();
  $pdo->beginTransaction();

  // 承認待ちデータ取得
  $sql = "SELECT * FROM girl_pending WHERE id = :pending_id AND status = 'pending'";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':pending_id', $pending_id, PDO::PARAM_INT);
  $stmt->execute();
  $pending_data = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$pending_data) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '承認待ちデータが見つかりません']);
    exit;
  }

  // キャストの電話番号を取得
  $cast_tel = $pending_data['tel'];

  if ($action === 'approved') {
    // 承認処理
    if ($pending_data['request_type'] === 'new') {
      // 新規登録の場合: girlテーブルに挿入
      $sql = "INSERT INTO girl (
        c_login_id, g_password, tel, name, high, age, b, w, h,
        head_comment, comment, store_comment, ichimon, `option`, k_check, img, imgs
      ) VALUES (
        :c_login_id, :g_password, :tel, :name, :high, :age, :b, :w, :h,
        :head_comment, :comment, :store_comment, :ichimon, :option, :k_check, :img, :imgs
      )";
      
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':c_login_id', $pending_data['c_login_id'], PDO::PARAM_STR);
      $stmt->bindValue(':g_password', $pending_data['g_password'], PDO::PARAM_STR);
      $stmt->bindValue(':tel', $pending_data['tel'], PDO::PARAM_STR);
      $stmt->bindValue(':name', $pending_data['name'], PDO::PARAM_STR);
      $stmt->bindValue(':high', $pending_data['high'], PDO::PARAM_STR);
      $stmt->bindValue(':age', $pending_data['age'], PDO::PARAM_STR);
      $stmt->bindValue(':b', $pending_data['b'], PDO::PARAM_STR);
      $stmt->bindValue(':w', $pending_data['w'], PDO::PARAM_STR);
      $stmt->bindValue(':h', $pending_data['h'], PDO::PARAM_STR);
      $stmt->bindValue(':head_comment', $pending_data['head_comment'], PDO::PARAM_STR);
      $stmt->bindValue(':comment', $pending_data['comment'], PDO::PARAM_STR);
      $stmt->bindValue(':store_comment', $pending_data['store_comment'], PDO::PARAM_STR);
      $stmt->bindValue(':ichimon', $pending_data['ichimon'], PDO::PARAM_STR);
      $stmt->bindValue(':option', $pending_data['option'], PDO::PARAM_STR);
      $stmt->bindValue(':k_check', $pending_data['k_check'], PDO::PARAM_STR);
      $stmt->bindValue(':img', $pending_data['img'], PDO::PARAM_STR);
      $stmt->bindValue(':imgs', $pending_data['imgs'], PDO::PARAM_STR);
      $stmt->execute();
      
      $new_girl_id = $pdo->lastInsertId();
      
    } else {
      // 編集の場合: girlテーブルを更新
      $sql = "UPDATE girl SET
        c_login_id = :c_login_id,
        g_password = :g_password,
        tel = :tel,
        name = :name,
        high = :high,
        age = :age,
        b = :b,
        w = :w,
        h = :h,
        head_comment = :head_comment,
        comment = :comment,
        store_comment = :store_comment,
        ichimon = :ichimon,
        `option` = :option,
        k_check = :k_check,
        img = :img,
        imgs = :imgs
      WHERE g_login_id = :g_login_id";
      
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':c_login_id', $pending_data['c_login_id'], PDO::PARAM_STR);
      $stmt->bindValue(':g_password', $pending_data['g_password'], PDO::PARAM_STR);
      $stmt->bindValue(':tel', $pending_data['tel'], PDO::PARAM_STR);
      $stmt->bindValue(':name', $pending_data['name'], PDO::PARAM_STR);
      $stmt->bindValue(':high', $pending_data['high'], PDO::PARAM_STR);
      $stmt->bindValue(':age', $pending_data['age'], PDO::PARAM_STR);
      $stmt->bindValue(':b', $pending_data['b'], PDO::PARAM_STR);
      $stmt->bindValue(':w', $pending_data['w'], PDO::PARAM_STR);
      $stmt->bindValue(':h', $pending_data['h'], PDO::PARAM_STR);
      $stmt->bindValue(':head_comment', $pending_data['head_comment'], PDO::PARAM_STR);
      $stmt->bindValue(':comment', $pending_data['comment'], PDO::PARAM_STR);
      $stmt->bindValue(':store_comment', $pending_data['store_comment'], PDO::PARAM_STR);
      $stmt->bindValue(':ichimon', $pending_data['ichimon'], PDO::PARAM_STR);
      $stmt->bindValue(':option', $pending_data['option'], PDO::PARAM_STR);
      $stmt->bindValue(':k_check', $pending_data['k_check'], PDO::PARAM_STR);
      $stmt->bindValue(':img', $pending_data['img'], PDO::PARAM_STR);
      $stmt->bindValue(':imgs', $pending_data['imgs'], PDO::PARAM_STR);
      $stmt->bindValue(':g_login_id', $pending_data['g_login_id'], PDO::PARAM_INT);
      $stmt->execute();
      
      $new_girl_id = $pending_data['g_login_id'];
    }

    // 承認ステータス更新
    $sql = "UPDATE girl_pending SET status = 'approved' WHERE id = :pending_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':pending_id', $pending_id, PDO::PARAM_INT);
    $stmt->execute();

    // チャット通知メッセージを作成（承認）
    $request_type_text = $pending_data['request_type'] === 'new' ? '新規登録' : 'プロフィール編集';
    $notification_message = "【承認完了】\n\n{$request_type_text}の申請が承認されました。";
    if (!empty($comment)) {
      $notification_message .= "\n\n管理者コメント:\n{$comment}";
    }
    $notification_message .= "\n\nご確認ください。";
    $notification_message .= "\n\n承認者: {$admin['admin_name']}";

  } else {
    // 却下処理
    $sql = "UPDATE girl_pending SET status = 'rejected' WHERE id = :pending_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':pending_id', $pending_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $new_girl_id = $pending_data['g_login_id'];

    // チャット通知メッセージを作成（却下）
    $request_type_text = $pending_data['request_type'] === 'new' ? '新規登録' : 'プロフィール編集';
    $notification_message = "【申請却下】\n\n{$request_type_text}の申請が却下されました。";
    if (!empty($comment)) {
      $notification_message .= "\n\n却下理由:\n{$comment}";
    }
    $notification_message .= "\n\n詳細は管理者にお問い合わせください。";
  }

  // 承認履歴を記録
  $sql = "INSERT INTO girl_approval_log (
    g_login_id, pending_id, admin_id, admin_name, action, comment
  ) VALUES (
    :g_login_id, :pending_id, :admin_id, :admin_name, :action, :comment
  )";
  
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':g_login_id', $new_girl_id, PDO::PARAM_INT);
  $stmt->bindValue(':pending_id', $pending_id, PDO::PARAM_INT);
  $stmt->bindValue(':admin_id', $admin['admin_id'], PDO::PARAM_STR);
  $stmt->bindValue(':admin_name', $admin['admin_name'], PDO::PARAM_STR);
  $stmt->bindValue(':action', $action, PDO::PARAM_STR);
  $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
  $stmt->execute();

  // チャット通知を送信
  sendChatNotification($pdo, $new_girl_id, $cast_tel, $notification_message);

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'message' => $action === 'approved' ? '承認しました' : '却下しました'
  ]);

} catch (Exception $e) {
  if (isset($pdo)) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DBエラー: ' . $e->getMessage()]);
}

/**
 * チャット通知を送信する関数
 */
function sendChatNotification($pdo, $g_login_id, $cast_tel, $message) {
  try {
    // チャットルームを取得または作成
    $sql = "SELECT id FROM chat_rooms 
            WHERE user_tel = :admin_tel 
            AND g_login_id = :g_login_id 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':admin_tel' => ADMIN_SYSTEM_TEL,
      ':g_login_id' => $g_login_id
    ]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    // ルームが存在しない場合は作成
    if (!$room) {
      $sql = "INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at, is_active) 
              VALUES (:admin_tel, :g_login_id, NOW(), NOW(), 1)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':admin_tel' => ADMIN_SYSTEM_TEL,
        ':g_login_id' => $g_login_id
      ]);
      $room_id = $pdo->lastInsertId();
    } else {
      $room_id = $room['id'];
    }

    // メッセージを送信（管理者側から）
    $sql = "INSERT INTO chat_messages 
            (room_id, sender_type, message_type, content, is_read, created_at) 
            VALUES (:room_id, 'user', 'text', :content, 0, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':room_id' => $room_id,
      ':content' => $message
    ]);

    // ルームの最終メッセージ時刻を更新
    $sql = "UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':room_id' => $room_id]);

    return true;
  } catch (Exception $e) {
    error_log('Chat notification error: ' . $e->getMessage());
    return false;
  }
}
?>