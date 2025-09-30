<?php
// chat_api.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once(dirname(__FILE__) . '/../functions.php');

// データベース接続
try {
    $pdo = dbConnect();
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// 認証チェック（ユーザー側のみ）
function checkUserAuth() {
    if (!isset($_SESSION['USER']['tel'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// リクエストメソッドとアクションの取得
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ルーティング
switch ($action) {
    case 'get_chat_list':
        checkUserAuth();
        getChatList($pdo);
        break;
    
    case 'get_or_create_room':
        checkUserAuth();
        getOrCreateRoom($pdo);
        break;
    
    case 'get_messages':
        checkUserAuth();
        getMessages($pdo);
        break;
    
    case 'send_message':
        checkUserAuth();
        sendMessage($pdo);
        break;
    
    case 'get_unread_count':
        checkUserAuth();
        getUnreadCount($pdo);
        break;
    
    case 'mark_as_read':
        checkUserAuth();
        markAsRead($pdo);
        break;
    
    // 管理者用エンドポイント（将来実装）
    case 'admin_get_all_rooms':
        // TODO: 管理者認証チェック
        adminGetAllRooms($pdo);
        break;
    
    case 'admin_delete_message':
        // TODO: 管理者認証チェック
        adminDeleteMessage($pdo);
        break;
    
    case 'admin_delete_room':
        // TODO: 管理者認証チェック
        adminDeleteRoom($pdo);
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

// ユーザーがチャット可能な女の子一覧を取得
function getChatList($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // 1. 予約完了済みの女の子を取得
        $sql = "SELECT DISTINCT g_login_id 
                FROM reserve 
                WHERE tel = :tel AND done = 3";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tel' => $user_tel]);
        $reserved_girls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. 既にチャットルームがある女の子を取得
        $sql = "SELECT DISTINCT g_login_id 
                FROM chat_rooms 
                WHERE user_tel = :user_tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel]);
        $chat_girls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 3. 重複削除してマージ
        $all_girls = array_unique(array_merge($reserved_girls, $chat_girls));
        
        if (empty($all_girls)) {
            echo json_encode(['success' => true, 'chat_list' => []]);
            return;
        }
        
        // 4. 女の子の詳細情報とチャット情報を取得
        $placeholders = implode(',', array_fill(0, count($all_girls), '?'));
        $sql = "SELECT g.g_login_id, g.name, g.head_comment, g.img,
                       r.id as room_id, r.last_message_at,
                       (SELECT content FROM chat_messages 
                        WHERE room_id = r.id 
                        ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE room_id = r.id 
                        AND sender_type = 'girl' 
                        AND is_read = 0) as unread_count
                FROM girl g
                LEFT JOIN chat_rooms r ON g.g_login_id = r.g_login_id 
                                       AND r.user_tel = ?
                WHERE g.g_login_id IN ($placeholders)
                ORDER BY CASE WHEN r.last_message_at IS NULL THEN 1 ELSE 0 END, r.last_message_at DESC";
        
        $params = array_merge([$user_tel], $all_girls);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $chat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'chat_list' => $chat_list]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// チャットルームを取得または作成
function getOrCreateRoom($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    $g_login_id = $_POST['g_login_id'] ?? null;
    
    if (!$g_login_id) {
        echo json_encode(['error' => 'Missing g_login_id']);
        return;
    }
    
    try {
        // チャット開始権限チェック
        $sql = "SELECT COUNT(*) FROM reserve 
                WHERE tel = :tel AND g_login_id = :g_login_id AND done = 3";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['tel' => $user_tel, 'g_login_id' => $g_login_id]);
        $has_reservation = $stmt->fetchColumn() > 0;
        
        $sql = "SELECT COUNT(*) FROM chat_rooms 
                WHERE user_tel = :user_tel AND g_login_id = :g_login_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel, 'g_login_id' => $g_login_id]);
        $has_room = $stmt->fetchColumn() > 0;
        
        if (!$has_reservation && !$has_room) {
            echo json_encode(['error' => 'No permission to start chat']);
            return;
        }
        
        // 既存のルームを検索
        $sql = "SELECT * FROM chat_rooms 
                WHERE user_tel = :user_tel AND g_login_id = :g_login_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel, 'g_login_id' => $g_login_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ルームが存在しない場合は作成
        if (!$room) {
            $sql = "INSERT INTO chat_rooms (user_tel, g_login_id) 
                    VALUES (:user_tel, :g_login_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_tel' => $user_tel, 'g_login_id' => $g_login_id]);
            $room_id = $pdo->lastInsertId();
            
            $sql = "SELECT * FROM chat_rooms WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $room_id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'room' => $room]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// メッセージ一覧を取得
function getMessages($pdo) {
    $room_id = $_GET['room_id'] ?? null;
    $last_id = $_GET['last_id'] ?? 0;
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // ルームへのアクセス権限チェック
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND user_tel = :user_tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id, 'user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // メッセージ取得（last_idより大きいIDのみ）
        $sql = "SELECT * FROM chat_messages 
                WHERE room_id = :room_id AND id > :last_id
                ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id, 'last_id' => $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'messages' => $messages,
            'has_new' => count($messages) > 0
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// メッセージ送信
function sendMessage($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    // テキストメッセージの文字数チェック
    if ($message_type === 'text') {
        if (mb_strlen($content) > 500) {
            echo json_encode(['error' => 'Message too long (max 500 characters)']);
            return;
        }
        if (empty(trim($content))) {
            echo json_encode(['error' => 'Message is empty']);
            return;
        }
    }
    
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // ルームへのアクセス権限チェック
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND user_tel = :user_tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id, 'user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file'])) {
            // ファイルサイズチェック（20MB）
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                echo json_encode(['error' => 'File too large (max 20MB)']);
                return;
            }
            
            $upload_dir = dirname(__FILE__) . '/uploads/chat/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = 'uploads/chat/' . $file_name;
            $full_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                echo json_encode(['error' => 'File upload failed']);
                return;
            }
        }
        
        // メッセージ挿入（ユーザーは常にsender_type='user'）
        $sql = "INSERT INTO chat_messages (room_id, sender_type, message_type, content, file_path) 
                VALUES (:room_id, 'user', :message_type, :content, :file_path)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'room_id' => $room_id,
            'message_type' => $message_type,
            'content' => $content,
            'file_path' => $file_path
        ]);
        
        $message_id = $pdo->lastInsertId();
        
        // ルームの最終メッセージ時刻を更新
        $sql = "UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        
        // 作成したメッセージを取得
        $sql = "SELECT * FROM chat_messages WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $message_id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// 未読数を取得（バッジ表示用）
function getUnreadCount($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        $sql = "SELECT COUNT(*) FROM chat_messages m
                INNER JOIN chat_rooms r ON m.room_id = r.id
                WHERE r.user_tel = :user_tel 
                AND m.sender_type = 'girl' 
                AND m.is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel]);
        $unread_count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'unread_count' => $unread_count]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// 既読にする
function markAsRead($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // ルームへのアクセス権限チェック
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND user_tel = :user_tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id, 'user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // 女の子からのメッセージを既読にする
        $sql = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = :room_id 
                AND sender_type = 'girl' 
                AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// ============ 管理者用エンドポイント（将来実装） ============

// 全チャットルーム取得
function adminGetAllRooms($pdo) {
    // TODO: 管理者認証チェックを実装
    // if (!isset($_SESSION['ADMIN']) || $_SESSION['ADMIN']['role'] !== 'admin') {
    //     echo json_encode(['error' => 'Admin access required']);
    //     return;
    // }
    
    try {
        $sql = "SELECT r.*, 
                       u.name as user_name,
                       g.name as girl_name,
                       (SELECT COUNT(*) FROM chat_messages WHERE room_id = r.id) as message_count
                FROM chat_rooms r
                LEFT JOIN users u ON r.user_tel = u.tel
                LEFT JOIN girl g ON r.g_login_id = g.g_login_id
                ORDER BY r.last_message_at DESC";
        $stmt = $pdo->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'rooms' => $rooms]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// メッセージ削除
function adminDeleteMessage($pdo) {
    // TODO: 管理者認証チェックを実装
    
    $message_id = $_POST['message_id'] ?? null;
    
    if (!$message_id) {
        echo json_encode(['error' => 'Missing message_id']);
        return;
    }
    
    try {
        // ファイルがある場合は削除
        $sql = "SELECT file_path FROM chat_messages WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $message_id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message && $message['file_path']) {
            $file_path = dirname(__FILE__) . '/' . $message['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // メッセージ削除
        $sql = "DELETE FROM chat_messages WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $message_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// チャットルーム削除
function adminDeleteRoom($pdo) {
    // TODO: 管理者認証チェックを実装
    
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルーム内のファイルを全て削除
        $sql = "SELECT file_path FROM chat_messages WHERE room_id = :room_id AND file_path IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($files as $file_path) {
            $full_path = dirname(__FILE__) . '/' . $file_path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        
        // ルーム削除（メッセージはCASCADEで自動削除）
        $sql = "DELETE FROM chat_rooms WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

unset($pdo);
?>