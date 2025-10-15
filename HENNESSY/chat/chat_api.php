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

    case 'get_available_girls':
    checkUserAuth();
    getAvailableGirls($pdo);
    break;

    case 'pin_chat':
    checkUserAuth();
    pinChat($pdo);
    break;

case 'hide_chat':
    checkUserAuth();
    hideChat($pdo);
    break;

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
    
    // ========== 管理者用エンドポイント ==========
    
    case 'admin_get_support_rooms':
        checkAdminAuth();
        adminGetSupportRooms($pdo);
        break;
    
    case 'admin_get_support_messages':
        checkAdminAuth();
        adminGetSupportMessages($pdo);
        break;
    
    case 'admin_send_support_message':
        checkAdminAuth();
        adminSendSupportMessage($pdo);
        break;
    
    case 'admin_mark_support_read':
        checkAdminAuth();
        adminMarkSupportRead($pdo);
        break;
    
    case 'admin_get_support_unread_count':
        checkAdminAuth();
        adminGetSupportUnreadCount($pdo);
        break;
    
    // ========== その他の管理者用エンドポイント ==========
    
    case 'admin_get_all_rooms':
        adminGetAllRooms($pdo);
        break;
    
    case 'admin_delete_message':
        adminDeleteMessage($pdo);
        break;
    
    case 'admin_delete_room':
        adminDeleteRoom($pdo);
        break;
    // 販促チャット用エンドポイント
    case 'get_promotion_room':
        checkUserAuth();
        getPromotionRoom($pdo);
        break;
    
    case 'get_promotion_unread_count':
        checkUserAuth();
        getPromotionUnreadCount($pdo);
        break;
    
    case 'admin_get_promotion_rooms':
        checkAdminAuth();
        adminGetPromotionRooms($pdo);
        break;
    
    case 'admin_get_promotion_messages':
        checkAdminAuth();
        adminGetPromotionMessages($pdo);
        break;
    
    case 'admin_send_promotion_message':
        checkAdminAuth();
        adminSendPromotionMessage($pdo);
        break;
    
    case 'admin_mark_promotion_read':
        checkAdminAuth();
        adminMarkPromotionRead($pdo);
        break;
    case 'admin_get_target_users':
        checkAdminAuth();
        adminGetTargetUsers($pdo);
        break;
    
    case 'admin_create_broadcast':
        checkAdminAuth();
        adminCreateBroadcast($pdo);
        break;
    case 'admin_get_broadcast_detail':
    checkAdminAuth();
    adminGetBroadcastDetail($pdo);
    break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;

    
}

// getChatList関数を以下に置き換える

function getChatList($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        $chat_list = [];
        
        // 1. 既存のチャットルームのみ取得（ルームが存在するキャストのみ）
       $sql = "SELECT g.g_login_id, g.name, g.head_comment, g.img,
       r.id as room_id, r.last_message_at, r.user_pin_order,
       (SELECT CASE 
            WHEN message_type = 'text' THEN content
            WHEN message_type = 'image' THEN '画像'
            WHEN message_type = 'video' THEN '動画'
            WHEN message_type = 'audio' THEN '音声'
            WHEN message_type = 'file' THEN 'ファイル'
            ELSE content
        END
        FROM chat_messages 
        WHERE room_id = r.id 
        ORDER BY created_at DESC LIMIT 1) as last_message,
       (SELECT COUNT(*) FROM chat_messages 
        WHERE room_id = r.id 
        AND sender_type = 'girl' 
        AND is_read = 0) as unread_count
FROM chat_rooms r
INNER JOIN girl g ON r.g_login_id = g.g_login_id
WHERE r.user_tel = :user_tel 
AND r.room_type = 'user_girl'
AND (r.user_view = 0 OR r.user_view IS NULL)
ORDER BY 
    CASE WHEN r.user_pin_order IS NOT NULL THEN 0 ELSE 1 END,
    r.user_pin_order ASC,
    r.last_message_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel]);
        $chat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        // 5. 販促チャット(お知らせ)を取得
$sql = "SELECT 
            'promotion' as g_login_id,
            '店舗からのお知らせ' as name,
            'キャンペーン情報や新着情報をお届けします' as head_comment,
            'promotion_icon.png' as img,
            r.id as room_id,
            r.last_message_at,
            (SELECT CASE 
                WHEN message_type = 'text' THEN content
                WHEN message_type = 'image' THEN '画像'
                WHEN message_type = 'video' THEN '動画'
                WHEN message_type = 'audio' THEN '音声'
                WHEN message_type = 'file' THEN 'ファイル'
                ELSE content
            END
             FROM chat_messages 
             WHERE room_id = r.id 
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE room_id = r.id 
             AND sender_type = 'girl' 
             AND is_read = 0) as unread_count
        FROM chat_rooms r
        WHERE r.user_tel = :user_tel 
        AND r.g_login_id = 'promotion' 
        AND r.room_type = 'promotion'";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_tel' => $user_tel]);
$promotion_chat = $stmt->fetch(PDO::FETCH_ASSOC);

// 販促チャットが存在する場合は先頭に追加
if ($promotion_chat) {
    array_unshift($chat_list, $promotion_chat);
}

// 6. サポートチャット(お問い合わせ)を取得 ← ★これを追加★
$sql = "SELECT 
            '0' as g_login_id,
            'サポート' as name,
            'お問い合わせ窓口' as head_comment,
            'support.png' as img,
            r.id as room_id,
            r.last_message_at,
            (SELECT CASE 
                WHEN message_type = 'text' THEN content
                WHEN message_type = 'image' THEN '画像'
                WHEN message_type = 'video' THEN '動画'
                WHEN message_type = 'audio' THEN '音声'
                WHEN message_type = 'file' THEN 'ファイル'
                ELSE content
            END
             FROM chat_messages 
             WHERE room_id = r.id 
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM chat_messages 
             WHERE room_id = r.id 
             AND sender_type = 'girl' 
             AND is_read = 0) as unread_count
        FROM chat_rooms r
        WHERE r.user_tel = :user_tel 
        AND r.g_login_id = '0' 
        AND r.room_type = 'support'";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_tel' => $user_tel]);
$support_chat = $stmt->fetch(PDO::FETCH_ASSOC);

// サポートチャットが存在する場合は先頭に追加
if ($support_chat) {
    array_unshift($chat_list, $support_chat);
}

echo json_encode(['success' => true, 'chat_list' => $chat_list]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}


// チャットルームを取得または作成
function getOrCreateRoom($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    $g_login_id = $_POST['g_login_id'] ?? null;
    
    if ($g_login_id === null || $g_login_id === '') {
        echo json_encode(['error' => 'Missing g_login_id']);
        return;
    }
    
    try {
        // サポートチャットの場合
        $is_support = ($g_login_id === '0' || $g_login_id === 0);
        // 販促チャットの場合
        $is_promotion = ($g_login_id === 'promotion');
        
        // 通常の女の子の場合のみ権限チェック
        if (!$is_support && !$is_promotion) {
            // 予約完了チェック
            $sql = "SELECT COUNT(*) FROM reserve 
                    WHERE tel = :tel AND g_login_id = :g_login_id AND done = 3";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tel' => $user_tel, 'g_login_id' => $g_login_id]);
            $has_reservation = $stmt->fetchColumn() > 0;
            
            // 既存ルームチェック
            $sql = "SELECT COUNT(*) FROM chat_rooms 
                    WHERE user_tel = :user_tel AND g_login_id = :g_login_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_tel' => $user_tel, 'g_login_id' => $g_login_id]);
            $has_room = $stmt->fetchColumn() > 0;
            
            // お気に入りチェック
            $sql = "SELECT COUNT(*) FROM favorite_girls 
                    WHERE tel = :tel AND g_login_id = :g_login_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['tel' => $user_tel, 'g_login_id' => $g_login_id]);
            $has_favorite = $stmt->fetchColumn() > 0;
            
            // いずれかの条件を満たせばOK
            if (!$has_reservation && !$has_room && !$has_favorite) {
                echo json_encode(['error' => 'No permission to start chat']);
                return;
            }
        }
        
        // 既存のルームを検索
$sql = "SELECT * FROM chat_rooms 
        WHERE user_tel = :user_tel AND g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_tel' => $user_tel, 'g_login_id' => $g_login_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

// ルームが存在しない場合は作成
if (!$room) {
    // room_typeを設定
    $room_type = 'user_girl';
    if ($is_support) {
        $room_type = 'support';
    } elseif ($is_promotion) {
        $room_type = 'promotion';
    }
    
    $sql = "INSERT INTO chat_rooms (user_tel, g_login_id, room_type) 
            VALUES (:user_tel, :g_login_id, :room_type)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_tel' => $user_tel, 
        'g_login_id' => $g_login_id,
        'room_type' => $room_type
    ]);
    $room_id = $pdo->lastInsertId();
    
    $sql = "SELECT * FROM chat_rooms WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // ルームが存在する場合、user_viewが1なら0に更新（再表示）
    if ($room['user_view'] == 1) {
        $sql = "UPDATE chat_rooms SET user_view = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room['id']]);
        
        // 更新後のルーム情報を再取得
        $sql = "SELECT * FROM chat_rooms WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room['id']]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
    }
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
            
            $upload_dir = dirname(__FILE__) . '../../../img/chat_uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $file_name;
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

// ============ 管理者用エンドポイント（将来実装） ============

// 管理者認証チェック
function checkAdminAuth() {
    if (!isset($_SESSION['ADMIN'])) {
        echo json_encode(['error' => 'Admin unauthorized']);
        exit;
    }
}

// サポートチャット一覧取得（管理者用）

function adminGetSupportRooms($pdo) {
    try {
        $sql = "SELECT r.*, 
                       u.user_name,
                       u.tel,
                       (SELECT content FROM chat_messages 
                        WHERE room_id = r.id 
                        ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE room_id = r.id 
                        AND sender_type = 'user' 
                        AND is_read = 0) as unread_count
                FROM chat_rooms r
                LEFT JOIN users u ON r.user_tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                WHERE r.g_login_id = 0
                ORDER BY r.last_message_at DESC";
        $stmt = $pdo->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'rooms' => $rooms]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// サポートチャットのメッセージ取得（管理者用）
function adminGetSupportMessages($pdo) {
    $room_id = $_GET['room_id'] ?? null;
    $last_id = $_GET['last_id'] ?? 0;
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルームがサポートチャットか確認
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND g_login_id = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['error' => 'Room not found or not a support chat']);
            return;
        }
        
        // メッセージ取得
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

// 管理者からメッセージ送信
function adminSendSupportMessage($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
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
    
    try {
        // ルームがサポートチャットか確認
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND g_login_id = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['error' => 'Room not found or not a support chat']);
            return;
        }
        
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file'])) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                echo json_encode(['error' => 'File too large (max 20MB)']);
                return;
            }
            
            $upload_dir = dirname(__FILE__) . '../../../img/chat_uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $file_name;
            $full_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                echo json_encode(['error' => 'File upload failed']);
                return;
            }
        }
        
        // メッセージ挿入（管理者はsender_type='girl'として送信）
        $sql = "INSERT INTO chat_messages (room_id, sender_type, message_type, content, file_path) 
                VALUES (:room_id, 'girl', :message_type, :content, :file_path)";
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

// 管理者側で既読にする
function adminMarkSupportRead($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        echo json_encode(['error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ユーザーからのメッセージを既読にする
        $sql = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = :room_id 
                AND sender_type = 'user' 
                AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// サポートチャットの未読数取得（管理者用）
function adminGetSupportUnreadCount($pdo) {
    try {
        $sql = "SELECT COUNT(*) FROM chat_messages m
                INNER JOIN chat_rooms r ON m.room_id = r.id
                WHERE r.g_login_id = 0
                AND m.sender_type = 'user' 
                AND m.is_read = 0";
        $stmt = $pdo->query($sql);
        $unread_count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'unread_count' => $unread_count]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getPromotionRoom($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // 販促チャットルームを取得または作成
        $sql = "SELECT * FROM chat_rooms 
                WHERE user_tel = :user_tel 
                AND g_login_id = 'promotion' 
                AND room_type = 'promotion'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ルームが存在しない場合は作成
        if (!$room) {
            $sql = "INSERT INTO chat_rooms (user_tel, g_login_id, room_type, is_active, is_subscribed) 
                    VALUES (:user_tel, 'promotion', 'promotion', 1, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_tel' => $user_tel]);
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

// 販促チャットの未読数を取得
function getPromotionUnreadCount($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        $sql = "SELECT COUNT(*) FROM chat_messages m
                INNER JOIN chat_rooms r ON m.room_id = r.id
                WHERE r.user_tel = :user_tel 
                AND r.room_type = 'promotion'
                AND r.g_login_id = 'promotion'
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
// 管理者用：販促チャットルーム一覧を取得
function adminGetPromotionRooms($pdo) {
    try {
        $sql = "SELECT r.*, 
                       u.user_name,
                       u.tel,
                       (SELECT content FROM chat_messages 
                        WHERE room_id = r.id 
                        ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE room_id = r.id 
                        AND sender_type = 'user' 
                        AND is_read = 0) as unread_count
                FROM chat_rooms r
                LEFT JOIN users u ON r.user_tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                WHERE r.room_type = 'promotion' AND r.g_login_id = 'promotion'
                ORDER BY r.last_message_at DESC";
        $stmt = $pdo->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'rooms' => $rooms]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 管理者用：販促チャットのメッセージ取得
function adminGetPromotionMessages($pdo) {
    $room_id = $_GET['room_id'] ?? null;
    $last_id = $_GET['last_id'] ?? 0;
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルームが販促チャットか確認
        $sql = "SELECT r.*, u.user_name 
                FROM chat_rooms r
                LEFT JOIN users u ON r.user_tel COLLATE utf8mb4_general_ci = u.tel COLLATE utf8mb4_general_ci
                WHERE r.id = :id AND r.room_type = 'promotion'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found or not a promotion chat']);
            return;
        }
        
        // メッセージ取得
        $sql = "SELECT * FROM chat_messages 
                WHERE room_id = :room_id AND id > :last_id
                ORDER BY created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id, 'last_id' => $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'messages' => $messages,
            'room_info' => [
                'user_name' => $room['user_name'] ?? '不明なユーザー',
                'user_tel' => $room['user_tel']
            ],
            'has_new' => count($messages) > 0
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 管理者用：販促メッセージ送信
function adminSendPromotionMessage($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    $link_url = $_POST['link_url'] ?? null;
    $link_text = $_POST['link_text'] ?? null;
    $admin_id = $_SESSION['ADMIN']['admin_id'];
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルームが販促チャットか確認
        $sql = "SELECT * FROM chat_rooms WHERE id = :id AND room_type = 'promotion'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Room not found or not a promotion chat']);
            return;
        }
        
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file'])) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 20MB)']);
                return;
            }
            
            $upload_dir = dirname(__FILE__) . '../../img/chat_uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = 'img/chat_uploads/' . $file_name;
            $full_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                echo json_encode(['success' => false, 'error' => 'File upload failed']);
                return;
            }
        }
        
        // メッセージ挿入
        $sql = "INSERT INTO chat_messages 
                (room_id, sender_type, sender_admin_id, message_type, content, file_path, link_url, link_text) 
                VALUES (:room_id, 'girl', :admin_id, :message_type, :content, :file_path, :link_url, :link_text)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'room_id' => $room_id,
            'admin_id' => $admin_id,
            'message_type' => $message_type,
            'content' => $content,
            'file_path' => $file_path,
            'link_url' => $link_url,
            'link_text' => $link_text
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 管理者用：販促チャットを既読にする
function adminMarkPromotionRead($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ユーザーからのメッセージを既読にする
        $sql = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = :room_id 
                AND sender_type = 'user' 
                AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
// セグメント対象ユーザーを取得
function adminGetTargetUsers($pdo) {
    $target_type = $_GET['target_type'] ?? 'all';
    $conditions = $_GET['conditions'] ?? null;
    
    try {
        $sql = "SELECT DISTINCT u.tel, u.user_name, u.login_id";
        $joins = " FROM users u";
        $where = " WHERE 1=1";
        $params = [];
        
        switch ($target_type) {
            case 'all':
                // 全員
                break;
                
            case 'rank':
                // ランク別
                $joins .= " LEFT JOIN user_rank_info uri ON u.tel COLLATE utf8mb4_general_ci = uri.tel COLLATE utf8mb4_general_ci";
                $where .= " AND uri.user_rank COLLATE utf8mb4_general_ci = :rank";
                $params['rank'] = $conditions;
                break;
                
            case 'usage_count':
                // 利用回数別
                $cond = json_decode($conditions, true);
                $joins .= " LEFT JOIN user_rank_info uri ON u.tel COLLATE utf8mb4_general_ci = uri.tel COLLATE utf8mb4_general_ci";
                
                if (isset($cond['min_count']) && $cond['min_count']) {
                    $where .= " AND uri.visit_count >= :min_count";
                    $params['min_count'] = $cond['min_count'];
                }
                if (isset($cond['max_count']) && $cond['max_count']) {
                    $where .= " AND uri.visit_count <= :max_count";
                    $params['max_count'] = $cond['max_count'];
                }
                break;
                
            case 'days_since_visit':
                // 最終来店日別
                $cond = json_decode($conditions, true);
                $joins .= " LEFT JOIN user_usage_stats uus ON u.tel COLLATE utf8mb4_general_ci = uus.tel COLLATE utf8mb4_general_ci";
                
                if (isset($cond['min_days'])) {
                    $where .= " AND uus.days_since_last_visit >= :min_days";
                    $params['min_days'] = $cond['min_days'];
                }
                if (isset($cond['max_days'])) {
                    $where .= " AND uus.days_since_last_visit <= :max_days";
                    $params['max_days'] = $cond['max_days'];
                }
                break;
                
            case 'favorite_girl':
                // お気に入りキャスト別
                $joins .= " INNER JOIN favorite_girls fg ON u.tel COLLATE utf8mb4_general_ci = fg.tel COLLATE utf8mb4_general_ci";
                $where .= " AND fg.g_login_id COLLATE utf8mb4_general_ci = :g_login_id";
                $params['g_login_id'] = $conditions;
                break;
                
            case 'preference':
                // プリファレンス別（複数選択可能）
                $prefs = json_decode($conditions, true);
                if (!empty($prefs)) {
                    $joins .= " INNER JOIN user_preferences up ON u.tel COLLATE utf8mb4_general_ci = up.tel COLLATE utf8mb4_general_ci";
                    $placeholders = implode(',', array_fill(0, count($prefs), '?'));
                    $where .= " AND up.preference_value COLLATE utf8mb4_general_ci IN ($placeholders)";
                    // paramsは後で配列として追加
                    foreach ($prefs as $pref) {
                        $params[] = $pref;
                    }
                }
                break;
                
            case 'girl_attribute_match':
                // キャストの属性とマッチするユーザー
                $g_login_id = $conditions;
                
                // キャストの属性を取得
                $attrSql = "SELECT attribute_value FROM girl_attributes WHERE g_login_id COLLATE utf8mb4_general_ci = :g_login_id";
                $attrStmt = $pdo->prepare($attrSql);
                $attrStmt->execute(['g_login_id' => $g_login_id]);
                $attributes = $attrStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($attributes)) {
                    $joins .= " INNER JOIN user_preferences up ON u.tel COLLATE utf8mb4_general_ci = up.tel COLLATE utf8mb4_general_ci";
                    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
                    $where .= " AND up.preference_value COLLATE utf8mb4_general_ci IN ($placeholders)";
                    // paramsは後で配列として追加
                    foreach ($attributes as $attr) {
                        $params[] = $attr;
                    }
                }
                break;
        }
        
        $finalSql = $sql . $joins . $where . " ORDER BY u.user_name";
        
        $stmt = $pdo->prepare($finalSql);
        
        // パラメータバインド
        if (!empty($params)) {
            $i = 1;
            foreach ($params as $key => $value) {
                if (is_string($key)) {
                    // 名前付きパラメータ
                    $stmt->bindValue(":$key", $value);
                } else {
                    // 位置パラメータ（プリファレンスや属性マッチ用）
                    $stmt->bindValue($i++, $value);
                }
            }
        }
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'users' => $users, 
            'count' => count($users)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
// 一斉配信を実行
function adminCreateBroadcast($pdo) {
    $admin_id = $_SESSION['ADMIN']['admin_id'];
    $title = $_POST['title'] ?? '';
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    $link_url = $_POST['link_url'] ?? null;
    $link_text = $_POST['link_text'] ?? null;
    $target_type = $_POST['target_type'] ?? 'all';
    $target_conditions = $_POST['target_conditions'] ?? null;
    $scheduled_at = $_POST['scheduled_at'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file'])) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                throw new Exception('File too large (max 20MB)');
            }
            
            $upload_dir = dirname(__FILE__) . '../../img/chat_uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = 'img/chat_uploads/' . $file_name;
            $full_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                throw new Exception('File upload failed');
            }
        }
        
        // 配信レコード作成
        $sql = "INSERT INTO promotion_broadcasts 
                (admin_id, title, message_type, content, file_path, link_url, link_text, 
                 target_type, target_conditions, scheduled_at, status) 
                VALUES (:admin_id, :title, :message_type, :content, :file_path, :link_url, :link_text,
                        :target_type, :target_conditions, :scheduled_at, :status)";
        $stmt = $pdo->prepare($sql);
        $status = $scheduled_at ? 'scheduled' : 'sending';
        $stmt->execute([
            'admin_id' => $admin_id,
            'title' => $title,
            'message_type' => $message_type,
            'content' => $content,
            'file_path' => $file_path,
            'link_url' => $link_url,
            'link_text' => $link_text,
            'target_type' => $target_type,
            'target_conditions' => $target_conditions,
            'scheduled_at' => $scheduled_at,
            'status' => $status
        ]);
        
        $broadcast_id = $pdo->lastInsertId();
        
        // 即時配信の場合
        if (!$scheduled_at) {
            // 対象ユーザーを取得
            $users = getTargetUsersForBroadcast($pdo, $target_type, $target_conditions);
            
            $sent_count = 0;
            
            foreach ($users as $user) {
                try {
                    // ユーザーの販促ルームを取得または作成
                    $roomSql = "SELECT id FROM chat_rooms 
                                WHERE user_tel = :user_tel 
                                AND g_login_id = 'promotion' 
                                AND room_type = 'promotion'";
                    $roomStmt = $pdo->prepare($roomSql);
                    $roomStmt->execute(['user_tel' => $user['tel']]);
                    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // ルームがなければ作成
                    if (!$room) {
                        $createRoomSql = "INSERT INTO chat_rooms (user_tel, g_login_id, room_type, is_active, is_subscribed) 
                                          VALUES (:user_tel, 'promotion', 'promotion', 1, 1)";
                        $createRoomStmt = $pdo->prepare($createRoomSql);
                        $createRoomStmt->execute(['user_tel' => $user['tel']]);
                        $room_id = $pdo->lastInsertId();
                    } else {
                        $room_id = $room['id'];
                    }
                    
                    // メッセージを挿入
                    $msgSql = "INSERT INTO chat_messages 
                               (room_id, sender_type, sender_admin_id, message_type, content, file_path, link_url, link_text, broadcast_id) 
                               VALUES (:room_id, 'girl', :admin_id, :message_type, :content, :file_path, :link_url, :link_text, :broadcast_id)";
                    $msgStmt = $pdo->prepare($msgSql);
                    $msgStmt->execute([
                        'room_id' => $room_id,
                        'admin_id' => $admin_id,
                        'message_type' => $message_type,
                        'content' => $content,
                        'file_path' => $file_path,
                        'link_url' => $link_url,
                        'link_text' => $link_text,
                        'broadcast_id' => $broadcast_id
                    ]);
                    
                    // ルームの最終メッセージ時刻を更新
                    $updateRoomSql = "UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :id";
                    $updateRoomStmt = $pdo->prepare($updateRoomSql);
                    $updateRoomStmt->execute(['id' => $room_id]);
                    
                    $sent_count++;
                } catch (PDOException $e) {
                    // 個別エラーはログに記録して続行
                    error_log("Broadcast message failed for user {$user['tel']}: " . $e->getMessage());
                }
            }
            
            // 配信統計を更新
            $updateBroadcastSql = "UPDATE promotion_broadcasts 
                                   SET status = 'sent', 
                                       sent_at = NOW(), 
                                       total_target = :total_target,
                                       total_sent = :total_sent
                                   WHERE id = :id";
            $updateBroadcastStmt = $pdo->prepare($updateBroadcastSql);
            $updateBroadcastStmt->execute([
                'total_target' => count($users),
                'total_sent' => $sent_count,
                'id' => $broadcast_id
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'broadcast_id' => $broadcast_id, 'sent_count' => $sent_count ?? 0]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 配信用の対象ユーザー取得（内部関数）
function getTargetUsersForBroadcast($pdo, $target_type, $conditions) {
    $sql = "SELECT DISTINCT u.tel, u.user_name";
    $joins = " FROM users u";
    $where = " WHERE 1=1";
    $params = [];
    
    switch ($target_type) {
        case 'all':
            break;
            
        case 'rank':
            $joins .= " LEFT JOIN user_rank_info uri ON u.tel COLLATE utf8mb4_general_ci = uri.tel COLLATE utf8mb4_general_ci";
            $where .= " AND uri.user_rank COLLATE utf8mb4_general_ci = :rank";
            $params['rank'] = $conditions;
            break;
            
        case 'usage_count':
            $cond = json_decode($conditions, true);
            $joins .= " LEFT JOIN user_rank_info uri ON u.tel COLLATE utf8mb4_general_ci = uri.tel COLLATE utf8mb4_general_ci";
            
            if (isset($cond['min_count']) && $cond['min_count']) {
                $where .= " AND uri.visit_count >= :min_count";
                $params['min_count'] = $cond['min_count'];
            }
            if (isset($cond['max_count']) && $cond['max_count']) {
                $where .= " AND uri.visit_count <= :max_count";
                $params['max_count'] = $cond['max_count'];
            }
            break;
            
        case 'days_since_visit':
            $cond = json_decode($conditions, true);
            $joins .= " LEFT JOIN user_usage_stats uus ON u.tel COLLATE utf8mb4_general_ci = uus.tel COLLATE utf8mb4_general_ci";
            
            if (isset($cond['min_days'])) {
                $where .= " AND uus.days_since_last_visit >= :min_days";
                $params['min_days'] = $cond['min_days'];
            }
            if (isset($cond['max_days'])) {
                $where .= " AND uus.days_since_last_visit <= :max_days";
                $params['max_days'] = $cond['max_days'];
            }
            break;
            
        case 'favorite_girl':
            $joins .= " INNER JOIN favorite_girls fg ON u.tel COLLATE utf8mb4_general_ci = fg.tel COLLATE utf8mb4_general_ci";
            $where .= " AND fg.g_login_id COLLATE utf8mb4_general_ci = :g_login_id";
            $params['g_login_id'] = $conditions;
            break;
            
        case 'preference':
            $prefs = json_decode($conditions, true);
            if (!empty($prefs)) {
                $joins .= " INNER JOIN user_preferences up ON u.tel COLLATE utf8mb4_general_ci = up.tel COLLATE utf8mb4_general_ci";
                $placeholders = implode(',', array_fill(0, count($prefs), '?'));
                $where .= " AND up.preference_value COLLATE utf8mb4_general_ci IN ($placeholders)";
                foreach ($prefs as $pref) {
                    $params[] = $pref;
                }
            }
            break;
            
        case 'girl_attribute_match':
            $g_login_id = $conditions;
            
            $attrSql = "SELECT attribute_value FROM girl_attributes WHERE g_login_id COLLATE utf8mb4_general_ci = :g_login_id";
            $attrStmt = $pdo->prepare($attrSql);
            $attrStmt->execute(['g_login_id' => $g_login_id]);
            $attributes = $attrStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($attributes)) {
                $joins .= " INNER JOIN user_preferences up ON u.tel COLLATE utf8mb4_general_ci = up.tel COLLATE utf8mb4_general_ci";
                $placeholders = implode(',', array_fill(0, count($attributes), '?'));
                $where .= " AND up.preference_value COLLATE utf8mb4_general_ci IN ($placeholders)";
                foreach ($attributes as $attr) {
                    $params[] = $attr;
                }
            }
            break;
    }
    
    $finalSql = $sql . $joins . $where;
    $stmt = $pdo->prepare($finalSql);
    
    if (!empty($params)) {
        $i = 1;
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $stmt->bindValue(":$key", $value);
            } else {
                $stmt->bindValue($i++, $value);
            }
        }
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// この関数をchat_api.phpに追加
function updateBroadcastReadStats($pdo, $room_id) {
    try {
        // この部屋で既読にされたbroadcast_idを取得
        $sql = "SELECT DISTINCT broadcast_id 
                FROM chat_messages 
                WHERE room_id = :room_id 
                AND broadcast_id IS NOT NULL 
                AND is_read = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        $broadcast_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($broadcast_ids as $broadcast_id) {
            // この配信の既読数を集計
            $countSql = "SELECT COUNT(DISTINCT room_id) as read_count
                         FROM chat_messages 
                         WHERE broadcast_id = :broadcast_id 
                         AND is_read = 1";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute(['broadcast_id' => $broadcast_id]);
            $read_count = $countStmt->fetch(PDO::FETCH_ASSOC)['read_count'];
            
            // promotion_broadcastsを更新
            $updateSql = "UPDATE promotion_broadcasts 
                          SET total_read = :total_read,
                              open_rate = CASE 
                                  WHEN total_sent > 0 THEN (:total_read / total_sent * 100)
                                  ELSE 0 
                              END
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                'total_read' => $read_count,
                'id' => $broadcast_id
            ]);
        }
    } catch (PDOException $e) {
        error_log("Update broadcast stats failed: " . $e->getMessage());
    }
}


// 関数を追加
function adminGetBroadcastDetail($pdo) {
    $broadcast_id = $_GET['broadcast_id'] ?? null;
    
    if (!$broadcast_id) {
        echo json_encode(['success' => false, 'error' => 'Missing broadcast_id']);
        return;
    }
    
    try {
        $sql = "SELECT * FROM promotion_broadcasts WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $broadcast_id]);
        $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$broadcast) {
            echo json_encode(['success' => false, 'error' => 'Broadcast not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'broadcast' => $broadcast]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// チャット可能なキャスト一覧を取得（ユーザー側）
function getAvailableGirls($pdo) {
    $user_tel = $_SESSION['USER']['tel'];
    
    try {
        // === 1. お気に入り登録しているキャスト ===
        $sql_favorite = "
            SELECT DISTINCT g.g_login_id, g.name, g.head_comment, g.img,
                   (SELECT COUNT(*) FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel1
                    AND r.g_login_id = g.g_login_id
                    AND m.sender_type = 'girl' 
                    AND m.is_read = 0) as unread_count,
                   (SELECT cr.id FROM chat_rooms cr 
                    WHERE cr.user_tel = :user_tel2
                    AND cr.g_login_id = g.g_login_id
                    LIMIT 1) as room_id,
                   (SELECT m.content FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel3
                    AND r.g_login_id = g.g_login_id
                    ORDER BY m.created_at DESC LIMIT 1) as last_message,
                   (SELECT m.created_at FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel4
                    AND r.g_login_id = g.g_login_id
                    ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                   'favorite' as source,
                   1 as is_favorite,
                   (SELECT COUNT(*) FROM reserve res 
                    WHERE res.tel = :user_tel5
                    AND res.g_login_id = g.g_login_id
                    AND res.done = 3) as is_reserve,
                   (SELECT MAX(f.created_at) FROM favorite_girls f 
                    WHERE f.tel = :user_tel13
                    AND f.g_login_id = g.g_login_id) as action_date
            FROM girl g
            INNER JOIN favorite_girls f ON g.g_login_id = f.g_login_id
            WHERE f.tel = :user_tel6
        ";
        
        // === 2. 予約完了済みのキャスト(done=3) ===
        $sql_reserve = "
            SELECT DISTINCT g.g_login_id, g.name, g.head_comment, g.img,
                   (SELECT COUNT(*) FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel7
                    AND r.g_login_id = g.g_login_id
                    AND m.sender_type = 'girl' 
                    AND m.is_read = 0) as unread_count,
                   (SELECT cr.id FROM chat_rooms cr 
                    WHERE cr.user_tel = :user_tel8
                    AND cr.g_login_id = g.g_login_id
                    LIMIT 1) as room_id,
                   (SELECT m.content FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel9
                    AND r.g_login_id = g.g_login_id
                    ORDER BY m.created_at DESC LIMIT 1) as last_message,
                   (SELECT m.created_at FROM chat_messages m 
                    INNER JOIN chat_rooms r ON m.room_id = r.id 
                    WHERE r.user_tel = :user_tel10
                    AND r.g_login_id = g.g_login_id
                    ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                   'reserve' as source,
                   (SELECT COUNT(*) FROM favorite_girls f 
                    WHERE f.tel = :user_tel11
                    AND f.g_login_id = g.g_login_id) as is_favorite,
                   1 as is_reserve,
                   (SELECT MAX(res.created_at) FROM reserve res 
                    WHERE res.tel = :user_tel14
                    AND res.g_login_id = g.g_login_id
                    AND res.done = 3) as action_date
            FROM girl g
            INNER JOIN reserve res ON g.g_login_id = res.g_login_id
            WHERE res.tel = :user_tel12
            AND res.done = 3
        ";
        
        // UNION で結合してソート（最近のアクション順）
        $sql = "SELECT * FROM (
                    ($sql_favorite) UNION ($sql_reserve)
                ) AS combined
                ORDER BY 
                    CASE WHEN last_message_at IS NOT NULL THEN 0 ELSE 1 END,
                    last_message_at DESC,
                    action_date DESC,
                    name ASC";
        
        $stmt = $pdo->prepare($sql);
        // 各パラメータに同じ値をバインド (14個に増えた)
        for ($i = 1; $i <= 14; $i++) {
            $stmt->bindValue(":user_tel{$i}", $user_tel, PDO::PARAM_STR);
        }
        $stmt->execute();
        $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 重複を除去し、is_favoriteとis_reserveを集約
        $unique_girls = [];
        $girl_map = [];
        foreach ($girls as $girl) {
            $g_login_id = $girl['g_login_id'];
            if (!isset($girl_map[$g_login_id])) {
                $girl_map[$g_login_id] = $girl;
            } else {
                // 既存キャストがいる場合、is_favoriteとis_reserveを統合
                $girl_map[$g_login_id]['is_favorite'] = max($girl_map[$g_login_id]['is_favorite'], $girl['is_favorite']);
                $girl_map[$g_login_id]['is_reserve'] = max($girl_map[$g_login_id]['is_reserve'], $girl['is_reserve']);
            }
        }
        $unique_girls = array_values($girl_map);
        
        echo json_encode([
            'success' => true,
            'girls' => $unique_girls
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// チャットをピン留め
function pinChat($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    $pin_order = isset($_POST['pin_order']) && $_POST['pin_order'] !== '' ? $_POST['pin_order'] : null;
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
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
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        // ピン留め順序を更新
        $sql = "UPDATE chat_rooms SET user_pin_order = :pin_order WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['pin_order' => $pin_order, 'id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// チャットを非表示
function hideChat($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
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
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        // user_viewを1に更新（非表示）
        $sql = "UPDATE chat_rooms SET user_view = 1 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

unset($pdo);
?>