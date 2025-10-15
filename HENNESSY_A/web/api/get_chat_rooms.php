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
  
  $girl_id = $_GET['girl_id'] ?? 'all';
  
  // チャットルーム一覧を取得
  if ($girl_id === 'all') {
    // 全体の新着順
    $sql = "
      SELECT 
        cr.id,
        cr.user_tel,
        cr.g_login_id,
        cr.last_message_at,
        u.user_name,
        g.name as girl_name,
        (SELECT content FROM chat_messages WHERE room_id = cr.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND sender_type = 'user' AND is_read = 0) as unread_count
      FROM chat_rooms cr
      LEFT JOIN users u ON cr.user_tel COLLATE utf8mb4_unicode_ci = u.tel COLLATE utf8mb4_unicode_ci
      LEFT JOIN girl g ON CAST(cr.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
      WHERE cr.is_active = 1
      ORDER BY cr.last_message_at DESC
    ";
    $stmt = $pdo->prepare($sql);
  } else {
    // 特定のキャストのチャットのみ
    $sql = "
      SELECT 
        cr.id,
        cr.user_tel,
        cr.g_login_id,
        cr.last_message_at,
        u.user_name,
        g.name as girl_name,
        (SELECT content FROM chat_messages WHERE room_id = cr.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND sender_type = 'user' AND is_read = 0) as unread_count
      FROM chat_rooms cr
      LEFT JOIN users u ON cr.user_tel COLLATE utf8mb4_unicode_ci = u.tel COLLATE utf8mb4_unicode_ci
      LEFT JOIN girl g ON CAST(cr.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(g.g_login_id AS CHAR) COLLATE utf8mb4_unicode_ci
      WHERE cr.is_active = 1 AND cr.g_login_id = :girl_id
      ORDER BY cr.last_message_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':girl_id', $girl_id, PDO::PARAM_STR);
  }
  
  $stmt->execute();
  $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 未読数の集計
  $unread_counts = [
    'total' => 0,
    'girls' => []
  ];
  
  // 全体の未読数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages WHERE sender_type = 'user' AND is_read = 0");
  $unread_counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // 各キャストの未読数
  $stmt = $pdo->query("
    SELECT 
      cr.g_login_id,
      COUNT(cm.id) as unread_count
    FROM chat_rooms cr
    LEFT JOIN chat_messages cm ON cr.id = cm.room_id AND cm.sender_type = 'user' AND cm.is_read = 0
    WHERE cr.is_active = 1
    GROUP BY cr.g_login_id
  ");
  
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $unread_counts['girls'][$row['g_login_id']] = $row['unread_count'];
  }
  
  echo json_encode([
    'success' => true,
    'rooms' => $rooms,
    'unread_counts' => $unread_counts
  ]);
  
  unset($pdo);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'データベースエラー: ' . $e->getMessage()
  ]);
}