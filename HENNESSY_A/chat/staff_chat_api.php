<?php
// staff_chat_api.php - スタッフチャット管理用API (g_login_id=1)

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// functions.phpのパスを確認
$functions_path = dirname(__FILE__) . '/../functions.php';
if (!file_exists($functions_path)) {
    die(json_encode(['success' => false, 'error' => 'functions.php not found at: ' . $functions_path]));
}
require_once($functions_path);

header('Content-Type: application/json');

error_log("=== staff_chat_api.php called ===");

// 管理者ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

$pdo = dbConnect();
$action = $_GET['action'] ?? '';

error_log("Action: " . $action);

// スタッフアカウントのg_login_id
define('STAFF_G_LOGIN_ID', '1');

try {
    switch ($action) {
        // 全ユーザー一覧取得(顧客一覧)
        case 'get_all_users':
            error_log("Getting all users...");
            
            $filter = $_GET['filter'] ?? 'all';
            
            $sql = "SELECT DISTINCT u.tel, u.user_name, u.login_id";
            $joins = " FROM users u";
            $where = " WHERE 1=1";
            
            switch ($filter) {
                case 'reserved':
                    // 予約済みユーザー(done=3)
                    $joins .= " INNER JOIN reserve r ON u.tel COLLATE utf8mb4_unicode_ci = r.tel COLLATE utf8mb4_unicode_ci";
                    $where .= " AND r.done = 3";
                    break;
                    
                case 'favorite':
                    // お気に入りに登録されているユーザー
                    $joins .= " INNER JOIN favorite_girls f ON u.tel COLLATE utf8mb4_unicode_ci = f.tel COLLATE utf8mb4_unicode_ci";
                    break;
                    
                case 'all':
                default:
                    // 全ユーザー
                    break;
            }
            
            $sql .= $joins . $where . " ORDER BY u.user_name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($users) . " users with filter: " . $filter);
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);
            break;

        // チャットルーム一覧取得
        case 'get_room_list':
            error_log("Getting room list...");
            
            $sql = "
                SELECT 
                    r.id,
                    r.user_tel,
                    u.user_name,
                    u.login_id,
                    COALESCE((SELECT COUNT(*) FROM chat_messages m 
                     WHERE m.room_id = r.id
                     AND m.sender_type = 'user' 
                     AND m.is_read = 0), 0) as unread_count,
                    (SELECT m.content FROM chat_messages m 
                     WHERE m.room_id = r.id
                     ORDER BY m.created_at DESC LIMIT 1) as last_message,
                    (SELECT m.created_at FROM chat_messages m 
                     WHERE m.room_id = r.id
                     ORDER BY m.created_at DESC LIMIT 1) as last_message_at
                FROM chat_rooms r
                INNER JOIN users u ON r.user_tel COLLATE utf8mb4_unicode_ci = u.tel COLLATE utf8mb4_unicode_ci
                WHERE r.g_login_id = ?
                AND r.room_type = 'staff'
                ORDER BY 
                    CASE WHEN (SELECT COUNT(*) FROM chat_messages m 
                               WHERE m.room_id = r.id
                               AND m.sender_type = 'user' 
                               AND m.is_read = 0) > 0 THEN 0 ELSE 1 END,
                    CASE WHEN (SELECT m.created_at FROM chat_messages m 
                               WHERE m.room_id = r.id
                               ORDER BY m.created_at DESC LIMIT 1) IS NULL THEN 1 ELSE 0 END,
                    (SELECT m.created_at FROM chat_messages m 
                     WHERE m.room_id = r.id
                     ORDER BY m.created_at DESC LIMIT 1) DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([STAFF_G_LOGIN_ID]);
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($rooms) . " rooms");
            
            echo json_encode([
                'success' => true,
                'rooms' => $rooms
            ]);
            break;

        // チャットルーム取得または作成
        case 'get_or_create_room':
            $user_tel = $_POST['user_tel'] ?? '';
            
            if (empty($user_tel)) {
                echo json_encode(['success' => false, 'error' => 'ユーザーが指定されていません']);
                exit;
            }
            
            // 既存のルームを検索
            $sql = "SELECT * FROM chat_rooms 
                    WHERE user_tel = ? 
                    AND g_login_id = ? 
                    AND room_type = 'staff'
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_tel, STAFF_G_LOGIN_ID]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ルームが存在しない場合は作成
            if (!$room) {
                $insert_sql = "INSERT INTO chat_rooms 
                               (user_tel, g_login_id, room_type, created_at, last_message_at, is_active) 
                               VALUES (?, ?, 'staff', NOW(), NOW(), 1)";
                
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$user_tel, STAFF_G_LOGIN_ID]);
                
                // 作成したルームを取得
                $stmt->execute([$user_tel, STAFF_G_LOGIN_ID]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'room' => $room
            ]);
            break;

        // メッセージ一覧取得
        case 'get_messages':
            $room_id = $_GET['room_id'] ?? 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
            
            if (empty($room_id)) {
                echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
                exit;
            }
            
            // ルームの確認
            $check_sql = "SELECT id FROM chat_rooms 
                          WHERE id = ? 
                          AND g_login_id = ?
                          AND room_type = 'staff'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$room_id, STAFF_G_LOGIN_ID]);
            
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
                exit;
            }
            
            // メッセージ取得
            $sql = "SELECT * FROM chat_messages 
                    WHERE room_id = ?";
            $params = [$room_id];
            
            if ($before_id) {
                $sql .= " AND id < ?";
                $params[] = $before_id;
            }
            
            $sql .= " ORDER BY created_at DESC, id DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 時系列順に並び替え
            $messages = array_reverse($messages);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;

        // メッセージ送信
        case 'send_message':
            $room_id = $_POST['room_id'] ?? 0;
            $user_tel = $_POST['user_tel'] ?? '';
            $content = trim($_POST['content'] ?? '');
            $message_type = $_POST['message_type'] ?? 'text';
            $link_url = trim($_POST['link_url'] ?? '');
            $link_text = trim($_POST['link_text'] ?? '');
            $admin_id = $_SESSION['ADMIN']['admin_id'] ?? null;
            
            if (empty($content) && $message_type === 'text') {
                echo json_encode(['success' => false, 'error' => 'メッセージを入力してください']);
                exit;
            }
            
            // ファイルアップロード処理
            $file_path = null;
            if ($message_type !== 'text' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                // ファイルサイズチェック(20MB)
                if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'error' => 'ファイルサイズが大きすぎます(最大20MB)']);
                    exit;
                }
                
                // アップロードディレクトリ (htdocs/HENNESSY/img/chat_uploads/)
                $upload_dir = dirname(__FILE__) . '/../../img/chat_uploads/';
                
                // ディレクトリが存在しない場合は作成
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        echo json_encode(['success' => false, 'error' => 'アップロードディレクトリの作成に失敗しました']);
                        exit;
                    }
                }
                
                // ファイル名生成
                $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $file_name = 'staff_' . uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = 'img/chat_uploads/' . $file_name;
                $full_path = $upload_dir . $file_name;
                
                // ファイル移動
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                    echo json_encode(['success' => false, 'error' => 'ファイルのアップロードに失敗しました']);
                    exit;
                }
            }
            
            // ルームが存在しない場合は作成
            if (empty($room_id)) {
                // 既存のルームを検索
                $sql = "SELECT id FROM chat_rooms 
                        WHERE user_tel = ? 
                        AND g_login_id = ? 
                        AND room_type = 'staff'
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_tel, STAFF_G_LOGIN_ID]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$room) {
                    $insert_sql = "INSERT INTO chat_rooms 
                                   (user_tel, g_login_id, room_type, created_at, last_message_at, is_active) 
                                   VALUES (?, ?, 'staff', NOW(), NOW(), 1)";
                    
                    $insert_stmt = $pdo->prepare($insert_sql);
                    $insert_stmt->execute([$user_tel, STAFF_G_LOGIN_ID]);
                    
                    $room_id = $pdo->lastInsertId();
                } else {
                    $room_id = $room['id'];
                }
            }
            
            // ルームの所有確認
            $check_sql = "SELECT id FROM chat_rooms 
                          WHERE id = ? 
                          AND g_login_id = ?
                          AND room_type = 'staff'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$room_id, STAFF_G_LOGIN_ID]);
            
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
                exit;
            }
            
            // メッセージ送信 (sender_type='staff')
            $insert_sql = "INSERT INTO chat_messages 
                           (room_id, sender_type, sender_admin_id, message_type, content, file_path, link_url, link_text, is_read, created_at) 
                           VALUES (?, 'staff', ?, ?, ?, ?, ?, ?, 0, NOW())";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                $room_id, 
                $admin_id, 
                $message_type, 
                $content, 
                $file_path, 
                $link_url, 
                $link_text
            ]);
            
            $message_id = $pdo->lastInsertId();
            
            // ルームの最終メッセージ時刻を更新
            $update_sql = "UPDATE chat_rooms 
                           SET last_message_at = NOW() 
                           WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$room_id]);
            
            // 送信したメッセージを取得
            $select_sql = "SELECT * FROM chat_messages WHERE id = ?";
            $select_stmt = $pdo->prepare($select_sql);
            $select_stmt->execute([$message_id]);
            $message = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            break;

        // 既読更新
        case 'mark_as_read':
            $room_id = $_POST['room_id'] ?? 0;
            
            if (empty($room_id)) {
                echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
                exit;
            }
            
            // ルームの確認
            $check_sql = "SELECT id FROM chat_rooms 
                          WHERE id = ? 
                          AND g_login_id = ?
                          AND room_type = 'staff'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$room_id, STAFF_G_LOGIN_ID]);
            
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
                exit;
            }
            
            // ユーザーからのメッセージを既読にする
            $sql = "UPDATE chat_messages 
                    SET is_read = 1 
                    WHERE room_id = ? 
                    AND sender_type = 'user' 
                    AND is_read = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room_id]);
            
            echo json_encode(['success' => true]);
            break;

        // 新着メッセージ取得(ポーリング用)
        case 'get_new_messages':
            $room_id = $_GET['room_id'] ?? 0;
            $last_id = $_GET['last_id'] ?? 0;
            
            if (empty($room_id)) {
                echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
                exit;
            }
            
            // ルームの確認
            $check_sql = "SELECT id FROM chat_rooms 
                          WHERE id = ? 
                          AND g_login_id = ?
                          AND room_type = 'staff'";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$room_id, STAFF_G_LOGIN_ID]);
            
            if (!$check_stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
                exit;
            }
            
            // 新着メッセージ取得
            $sql = "SELECT * FROM chat_messages 
                    WHERE room_id = ? 
                    AND id > ? 
                    ORDER BY created_at ASC, id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room_id, $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'has_new' => count($messages) > 0,
                'messages' => $messages
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
            break;
    }
    
} catch (PDOException $e) {
    error_log('Database error in staff_chat_api.php: ' . $e->getMessage());
    error_log('SQL Error: ' . $e->errorInfo[2]);
    echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Error in staff_chat_api.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'エラーが発生しました: ' . $e->getMessage()]);
}