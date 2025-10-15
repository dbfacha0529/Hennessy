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
$action = $_GET['action'] ?? '';

// 管理者システムアカウントの電話番号
define('ADMIN_SYSTEM_TEL', '00000000000');

try {
    switch ($action) {
        // 新規ユーザー取得（チャットルーム未作成）
        case 'get_new_users':
            // === 1. ご利用済みユーザー（予約完了済みでチャットルームなし） ===
            $sql_reserve = "
        SELECT DISTINCT u.tel, u.user_name, u.login_id, r.created_at
        FROM users u
        INNER JOIN reserve r ON u.tel COLLATE utf8mb4_unicode_ci = r.tel
        WHERE r.g_login_id = :g_login_id
        AND r.done = 3
        AND NOT EXISTS (
            SELECT 1 FROM chat_rooms cr 
            WHERE cr.user_tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
            AND cr.g_login_id = :g_login_id2
        )
        ORDER BY r.created_at DESC
    ";
            
            $stmt_reserve = $pdo->prepare($sql_reserve);
            $stmt_reserve->execute([
                ':g_login_id' => $g_login_id,
                ':g_login_id2' => $g_login_id
            ]);
            $reserve_users = $stmt_reserve->fetchAll(PDO::FETCH_ASSOC);
            
            // === 2. お気に入りユーザー（チャットルームなし） ===
            $sql_favorite = "
                SELECT DISTINCT u.tel, u.user_name, u.login_id, f.created_at
                FROM users u
                INNER JOIN favorite_girls f ON u.tel COLLATE utf8mb4_unicode_ci = f.tel
                WHERE f.g_login_id = :g_login_id
                AND NOT EXISTS (
                    SELECT 1 FROM chat_rooms cr 
                    WHERE cr.user_tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                    AND cr.g_login_id = :g_login_id2
                )
                ORDER BY f.created_at DESC
            ";
            
            $stmt_favorite = $pdo->prepare($sql_favorite);
            $stmt_favorite->execute([
                ':g_login_id' => $g_login_id,
                ':g_login_id2' => $g_login_id
            ]);
            $favorite_users = $stmt_favorite->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'reserve_users' => $reserve_users,
                'favorite_users' => $favorite_users
            ]);
            break;

        // 未読数取得
        case 'get_unread_count':
            $sql = "SELECT COUNT(*) as count FROM chat_messages m
                    INNER JOIN chat_rooms r ON m.room_id = r.id
                    WHERE r.g_login_id = :g_login_id 
                    AND m.sender_type = 'user' 
                    AND m.is_read = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':g_login_id' => $g_login_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unread_count' => (int)$result['count']
            ]);
            break;

        // チャット可能なユーザー一覧取得
        case 'get_chat_users':
            // === 1. 管理者システムアカウントとのチャットを取得 ===
            $sql_admin = "
                SELECT DISTINCT 
                       :admin_tel as tel,
                       'システム管理者' as user_name,
                       'admin_system' as login_id,
                       (SELECT COUNT(*) FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = :admin_tel2
                        AND r.g_login_id = :g_login_id1
                        AND m.sender_type = 'user' 
                        AND m.is_read = 0) as unread_count,
                       (SELECT cr.id FROM chat_rooms cr 
                        WHERE cr.user_tel = :admin_tel3
                        AND cr.g_login_id = :g_login_id2
                        LIMIT 1) as room_id,
                       (SELECT m.content FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = :admin_tel4
                        AND r.g_login_id = :g_login_id3
                        ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT m.created_at FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = :admin_tel5
                        AND r.g_login_id = :g_login_id4
                        ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                       'system' as source,
                       0 as is_favorite,
                       0 as is_reserve
                FROM chat_rooms cr
                WHERE cr.user_tel = :admin_tel6
                AND cr.g_login_id = :g_login_id5
                LIMIT 1
            ";
            
            $stmt_admin = $pdo->prepare($sql_admin);
            $stmt_admin->bindValue(':admin_tel', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':admin_tel2', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':admin_tel3', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':admin_tel4', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':admin_tel5', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':admin_tel6', ADMIN_SYSTEM_TEL, PDO::PARAM_STR);
            $stmt_admin->bindValue(':g_login_id1', $g_login_id, PDO::PARAM_STR);
            $stmt_admin->bindValue(':g_login_id2', $g_login_id, PDO::PARAM_STR);
            $stmt_admin->bindValue(':g_login_id3', $g_login_id, PDO::PARAM_STR);
            $stmt_admin->bindValue(':g_login_id4', $g_login_id, PDO::PARAM_STR);
            $stmt_admin->bindValue(':g_login_id5', $g_login_id, PDO::PARAM_STR);
            $stmt_admin->execute();
            $admin_chat = $stmt_admin->fetch(PDO::FETCH_ASSOC);
            
            // === 2. お気に入り登録しているユーザー ===
            $sql_favorite = "
                SELECT DISTINCT u.tel, u.user_name, u.login_id,
                       (SELECT COUNT(*) FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id1
                        AND m.sender_type = 'user' 
                        AND m.is_read = 0) as unread_count,
                       (SELECT cr.id FROM chat_rooms cr 
                        WHERE cr.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND cr.g_login_id = :g_login_id2
                        LIMIT 1) as room_id,
                       (SELECT m.content FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id3
                        ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT m.created_at FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id4
                        ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                       'favorite' as source,
                       1 as is_favorite,
                       (SELECT COUNT(*) FROM reserve res 
                        WHERE res.tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                        AND res.g_login_id = :g_login_id5
                        AND res.done = 3) as is_reserve
                FROM users u
                INNER JOIN favorite_girls f ON u.tel COLLATE utf8mb4_unicode_ci = f.tel
                WHERE f.g_login_id = :g_login_id6
            ";
            
            // === 3. 予約履歴があるユーザー(done=2またはdone=3) ===
            $sql_reserve = "
                SELECT DISTINCT u.tel, u.user_name, u.login_id,
                       (SELECT COUNT(*) FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id7
                        AND m.sender_type = 'user' 
                        AND m.is_read = 0) as unread_count,
                       (SELECT cr.id FROM chat_rooms cr 
                        WHERE cr.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND cr.g_login_id = :g_login_id8
                        LIMIT 1) as room_id,
                       (SELECT m.content FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id9
                        ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT m.created_at FROM chat_messages m 
                        INNER JOIN chat_rooms r ON m.room_id = r.id 
                        WHERE r.user_tel = u.tel COLLATE utf8mb4_general_ci
                        AND r.g_login_id = :g_login_id10
                        ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                       'reserve' as source,
                       (SELECT COUNT(*) FROM favorite_girls f 
                        WHERE f.tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                        AND f.g_login_id = :g_login_id11) as is_favorite,
                       1 as is_reserve
                FROM users u
                INNER JOIN reserve res ON u.tel COLLATE utf8mb4_unicode_ci = res.tel
                WHERE res.g_login_id = :g_login_id12
                AND res.done = 3
            ";
            
            // UNION で結合してソート
            $sql = "SELECT * FROM (
                        ($sql_favorite) UNION ($sql_reserve)
                    ) AS combined
                    ORDER BY 
                        CASE WHEN last_message_at IS NULL THEN 1 ELSE 0 END,
                        last_message_at DESC,
                        user_name ASC";
            
            $stmt = $pdo->prepare($sql);
            // 各パラメータに同じ値をバインド
            for ($i = 1; $i <= 12; $i++) {
                $stmt->bindValue(":g_login_id{$i}", $g_login_id, PDO::PARAM_STR);
            }
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 重複を除去し、is_favoriteとis_reserveを集約
            $unique_users = [];
            $user_map = [];
            foreach ($users as $user) {
                $tel = $user['tel'];
                if (!isset($user_map[$tel])) {
                    $user_map[$tel] = $user;
                } else {
                    // 既存ユーザーがいる場合、is_favoriteとis_reserveを統合
                    $user_map[$tel]['is_favorite'] = max($user_map[$tel]['is_favorite'], $user['is_favorite']);
                    $user_map[$tel]['is_reserve'] = max($user_map[$tel]['is_reserve'], $user['is_reserve']);
                }
            }
            $unique_users = array_values($user_map);
            
            // 管理者チャットが存在する場合は最上部に追加
            if ($admin_chat && $admin_chat['room_id']) {
                array_unshift($unique_users, $admin_chat);
            }
            
            echo json_encode([
                'success' => true,
                'users' => $unique_users
            ]);
            break;

        // チャットルーム取得または作成
        case 'get_or_create_room':
            $user_tel = $_POST['user_tel'] ?? '';
            
            if (empty($user_tel)) {
                echo json_encode(['success' => false, 'error' => 'ユーザーが指定されていません']);
                exit;
            }
            
            // 管理者システムアカウントの場合は特別処理（チェックスキップ）
            if ($user_tel !== ADMIN_SYSTEM_TEL) {
                // チャット可能か確認(お気に入りまたはdone=2,3の予約履歴)
                $check_sql = "
                    SELECT COUNT(*) as count FROM (
                        SELECT 1 FROM favorite_girls 
                        WHERE tel COLLATE utf8mb4_general_ci = :tel AND g_login_id = :g_login_id
                        UNION
                        SELECT 1 FROM reserve 
                        WHERE tel COLLATE utf8mb4_general_ci = :tel2 AND g_login_id = :g_login_id2 AND done = 3
                    ) as allowed
                ";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([
                    ':tel' => $user_tel,
                    ':g_login_id' => $g_login_id,
                    ':tel2' => $user_tel,
                    ':g_login_id2' => $g_login_id
                ]);
                $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($check_result['count'] == 0) {
                    echo json_encode(['success' => false, 'error' => 'このユーザーとはチャットできません']);
                    exit;
                }
            }
            
            // 既存のルームを検索
            $sql = "SELECT * FROM chat_rooms 
                    WHERE user_tel COLLATE utf8mb4_general_ci = :user_tel 
                    AND g_login_id = :g_login_id 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_tel' => $user_tel,
                ':g_login_id' => $g_login_id
            ]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ルームが存在しない場合は作成
            if (!$room) {
                $insert_sql = "INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at, is_active) 
                               VALUES (:user_tel, :g_login_id, NOW(), NOW(), 1)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    ':user_tel' => $user_tel,
                    ':g_login_id' => $g_login_id
                ]);
                
                $room_id = $pdo->lastInsertId();
                
                // 作成したルームを取得
                $stmt->execute([
                    ':user_tel' => $user_tel,
                    ':g_login_id' => $g_login_id
                ]);
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
            
            // メッセージ取得
            $sql = "SELECT * FROM chat_messages 
                    WHERE room_id = :room_id";
            
            if ($before_id) {
                $sql .= " AND id < :before_id";
            }
            
            $sql .= " ORDER BY created_at DESC, id DESC LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
            if ($before_id) {
                $stmt->bindValue(':before_id', $before_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
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
            $message = trim($_POST['message'] ?? '');
            $message_type = $_POST['message_type'] ?? 'text';
            
            if (empty($room_id)) {
                echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
                exit;
            }
            
            if (empty($message) && $message_type === 'text') {
                echo json_encode(['success' => false, 'error' => 'メッセージを入力してください']);
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
            
            // メッセージ送信
            $insert_sql = "INSERT INTO chat_messages 
                           (room_id, sender_type, message_type, content, is_read, created_at) 
                           VALUES (:room_id, 'girl', :message_type, :content, 0, NOW())";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                ':room_id' => $room_id,
                ':message_type' => $message_type,
                ':content' => $message
            ]);
            
            // ルームの最終メッセージ時刻を更新
            $update_sql = "UPDATE chat_rooms 
                           SET last_message_at = NOW() 
                           WHERE id = :room_id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([':room_id' => $room_id]);
            
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId()
            ]);
            break;

        // 既読更新
        case 'mark_as_read':
            $room_id = $_POST['room_id'] ?? 0;
            
            if (empty($room_id)) {
                echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
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
            
            // ユーザーからのメッセージを既読に
            $update_sql = "UPDATE chat_messages 
                           SET is_read = 1 
                           WHERE room_id = :room_id 
                           AND sender_type = 'user' 
                           AND is_read = 0";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([':room_id' => $room_id]);
            
            echo json_encode([
                'success' => true,
                'updated_count' => $update_stmt->rowCount()
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
            break;
    }
    
} catch (PDOException $e) {
    error_log('Cast Chat API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラーが発生しました',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Cast Chat API General Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'エラーが発生しました',
        'debug' => $e->getMessage()
    ]);
}
?>