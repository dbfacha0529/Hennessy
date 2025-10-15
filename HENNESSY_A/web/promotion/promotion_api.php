<?php
// promotion_api.php - 販促チャット用API
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once(dirname(__FILE__) . '/../../functions.php');

// データベース接続
try {
    $pdo = dbConnect();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// リクエストメソッドとアクションの取得
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ルーティング
switch ($action) {
    // ========== ユーザー側エンドポイント ==========
    case 'get_promotion_room':
        checkUserAuth();
        getPromotionRoom($pdo);
        break;
    
    case 'get_promotion_messages':
        checkUserAuth();
        getPromotionMessages($pdo);
        break;
    
    case 'mark_promotion_read':
        checkUserAuth();
        markPromotionRead($pdo);
        break;
    
    // ========== 管理者側エンドポイント ==========
    case 'admin_get_promotion_rooms':
        checkAdminAuth();
        adminGetPromotionRooms($pdo);
        break;
    
    case 'admin_send_promotion_message':
        checkAdminAuth();
        adminSendPromotionMessage($pdo);
        break;
    
    case 'admin_get_target_users':
        checkAdminAuth();
        adminGetTargetUsers($pdo);
        break;
    
    case 'admin_create_broadcast':
        checkAdminAuth();
        adminCreateBroadcast($pdo);
        break;
    
    case 'admin_get_broadcasts':
        checkAdminAuth();
        adminGetBroadcasts($pdo);
        break;
    
    case 'admin_get_broadcast_detail':
        checkAdminAuth();
        adminGetBroadcastDetail($pdo);
        break;
    
    case 'admin_save_template':
        checkAdminAuth();
        adminSaveTemplate($pdo);
        break;
    
    case 'admin_get_templates':
        checkAdminAuth();
        adminGetTemplates($pdo);
        break;
    
    case 'admin_delete_template':
        checkAdminAuth();
        adminDeleteTemplate($pdo);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

// ========== 認証関数 ==========

function checkUserAuth() {
    if (!isset($_SESSION['USER']['tel'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function checkAdminAuth() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        echo json_encode(['success' => false, 'error' => 'Admin unauthorized']);
        exit;
    }
}

// ========== ユーザー側の機能 ==========

// 販促チャットルームを取得
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 販促メッセージ一覧を取得
function getPromotionMessages($pdo) {
    $room_id = $_GET['room_id'] ?? null;
    $last_id = $_GET['last_id'] ?? 0;
    $user_tel = $_SESSION['USER']['tel'];
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルームへのアクセス権限チェック
        $sql = "SELECT * FROM chat_rooms 
                WHERE id = :id 
                AND user_tel = :user_tel 
                AND room_type = 'promotion'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id, 'user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 既読にする
function markPromotionRead($pdo) {
    $room_id = $_POST['room_id'] ?? null;
    $user_tel = $_SESSION['USER']['tel'];
    
    error_log("=== markPromotionRead called ===");
    error_log("room_id: " . $room_id);
    error_log("user_tel: " . $user_tel);
    
    if (!$room_id) {
        echo json_encode(['success' => false, 'error' => 'Missing room_id']);
        return;
    }
    
    try {
        // ルームへのアクセス権限チェック
        $sql = "SELECT * FROM chat_rooms 
                WHERE id = :id 
                AND user_tel = :user_tel 
                AND room_type = 'promotion'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $room_id, 'user_tel' => $user_tel]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$room) {
            error_log("ERROR: Room not found or access denied");
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        error_log("Room found: " . print_r($room, true));
        
        // メッセージを既読にする
        $sql = "UPDATE chat_messages 
                SET is_read = 1 
                WHERE room_id = :room_id 
                AND sender_type = 'girl' 
                AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        
        $updated_count = $stmt->rowCount();
        error_log("Updated messages count: " . $updated_count);
        
        // 該当するブロードキャストIDを取得
        $sql = "SELECT DISTINCT broadcast_id 
                FROM chat_messages 
                WHERE room_id = :room_id 
                AND broadcast_id IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['room_id' => $room_id]);
        $broadcasts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        error_log("Found broadcast IDs: " . print_r($broadcasts, true));
        
        // 各ブロードキャストの統計を更新
        foreach ($broadcasts as $broadcast_id) {
            error_log("Updating stats for broadcast_id: " . $broadcast_id);
            updateBroadcastStats($pdo, $broadcast_id);
        }
        
        echo json_encode(['success' => true, 'updated' => $updated_count, 'broadcasts' => count($broadcasts)]);
    } catch (PDOException $e) {
        error_log("ERROR in markPromotionRead: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ブロードキャスト統計を更新
function updateBroadcastStats($pdo, $broadcast_id) {
    if (!$broadcast_id) {
        error_log("updateBroadcastStats: broadcast_id is empty");
        return;
    }
    
    error_log("=== updateBroadcastStats called for ID: " . $broadcast_id . " ===");
    
    try {
        // まず、このブロードキャストIDのメッセージが存在するか確認
        $sql = "SELECT COUNT(*) as msg_count, 
                       SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
                       COUNT(DISTINCT room_id) as total_rooms,
                       COUNT(DISTINCT CASE WHEN is_read = 1 THEN room_id END) as read_rooms
                FROM chat_messages
                WHERE broadcast_id = :broadcast_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['broadcast_id' => $broadcast_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Message stats for broadcast {$broadcast_id}: " . print_r($stats, true));
        
        $total_read = $stats['read_rooms'] ?? 0;
        
        error_log("Total read count (distinct rooms): " . $total_read);
        
        // ブロードキャストの配信対象数(total_sent)を取得
        $sql = "SELECT total_sent FROM promotion_broadcasts WHERE id = :broadcast_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['broadcast_id' => $broadcast_id]);
        $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_sent = $broadcast['total_sent'] ?? 0;
        
        // 開封率をPHP側で計算
        $open_rate = $total_sent > 0 ? ($total_read * 100.0) / $total_sent : 0;
        
        error_log("Calculated open_rate: {$open_rate}% (read: {$total_read}, sent: {$total_sent})");
        
        // 開封率を計算・更新
        $sql = "UPDATE promotion_broadcasts
                SET total_read = :total_read,
                    open_rate = :open_rate
                WHERE id = :broadcast_id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'total_read' => $total_read,
            'open_rate' => $open_rate,
            'broadcast_id' => $broadcast_id
        ]);
        
        $affected = $stmt->rowCount();
        error_log("Update result: " . ($result ? 'SUCCESS' : 'FAILED') . ", affected rows: " . $affected);
        
        // 更新後の値を確認
        $sql = "SELECT id, title, total_read, open_rate, total_sent, total_target FROM promotion_broadcasts WHERE id = :broadcast_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['broadcast_id' => $broadcast_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("After update for broadcast {$broadcast_id}: " . print_r($result, true));
        
    } catch (PDOException $e) {
        error_log("ERROR in updateBroadcastStats: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
    }
}

// ========== 管理者側の機能 ==========

// 販促チャットルーム一覧を取得
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
                LEFT JOIN users u ON r.user_tel = u.tel
                WHERE r.room_type = 'promotion' AND r.g_login_id = 'promotion'
                ORDER BY 
                  CASE WHEN (SELECT COUNT(*) FROM chat_messages 
                             WHERE room_id = r.id 
                             AND sender_type = 'user' 
                             AND is_read = 0) > 0 THEN 0 ELSE 1 END,
                  r.last_message_at DESC";
        
        $stmt = $pdo->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'rooms' => $rooms]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
// 個別メッセージ送信
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
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file'])) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File too large (max 20MB)']);
                return;
            }
            
            $upload_dir = dirname(__FILE__) . '/../../../img/chat_uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $file_name;
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
                break;
                
            case 'rank':
                $joins .= " LEFT JOIN user_rank_info uri ON BINARY u.tel = BINARY uri.tel";
                $where .= " AND BINARY uri.user_rank = BINARY :rank";
                $params['rank'] = $conditions;
                break;
                
            case 'usage_count':
                $cond = json_decode($conditions, true);
                $joins .= " LEFT JOIN user_rank_info uri ON BINARY u.tel = BINARY uri.tel";
                
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
                $joins .= " LEFT JOIN user_usage_stats uus ON BINARY u.tel = BINARY uus.tel";
                
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
                $joins .= " INNER JOIN favorite_girls fg ON BINARY u.tel = BINARY fg.tel";
                $where .= " AND BINARY fg.g_login_id = BINARY :g_login_id";
                $params['g_login_id'] = $conditions;
                break;
                
            case 'preference':
                $prefs = json_decode($conditions, true);
                if (!empty($prefs)) {
                    $joins .= " INNER JOIN user_preferences up ON BINARY u.tel = BINARY up.tel";
                    $placeholders = implode(',', array_fill(0, count($prefs), '?'));
                    $where .= " AND up.preference_value IN ($placeholders)";
                    foreach ($prefs as $pref) {
                        $params[] = $pref;
                    }
                }
                break;
                
            case 'girl_attribute_match':
                $g_login_id = $conditions;
                
                $attrSql = "SELECT attribute_value FROM girl_attributes WHERE BINARY g_login_id = BINARY :g_login_id";
                $attrStmt = $pdo->prepare($attrSql);
                $attrStmt->execute(['g_login_id' => $g_login_id]);
                $attributes = $attrStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($attributes)) {
                    $joins .= " INNER JOIN user_preferences up ON BINARY u.tel = BINARY up.tel";
                    $placeholders = implode(',', array_fill(0, count($attributes), '?'));
                    $where .= " AND up.preference_value IN ($placeholders)";
                    foreach ($attributes as $attr) {
                        $params[] = $attr;
                    }
                }
                break;
        }
        
        $finalSql = $sql . $joins . $where . " ORDER BY u.user_name";
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

// ヘルパー関数: 販促ルームを取得または作成
function getOrCreatePromotionRoom($pdo, $user_tel) {
    $sql = "SELECT id FROM chat_rooms 
            WHERE user_tel = :user_tel 
            AND g_login_id = 'promotion' 
            AND room_type = 'promotion'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_tel' => $user_tel]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($room) {
        return $room['id'];
    }
    
    $sql = "INSERT INTO chat_rooms (user_tel, g_login_id, room_type, is_active, is_subscribed) 
            VALUES (:user_tel, 'promotion', 'promotion', 1, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_tel' => $user_tel]);
    
    return $pdo->lastInsertId();
}

// ヘルパー関数: ブロードキャスト用の対象ユーザーを取得
function getTargetUsersForBroadcast($pdo, $target_type, $conditions) {
    $sql = "SELECT DISTINCT u.tel, u.user_name
            FROM users u
            LEFT JOIN user_rank_info uri ON BINARY u.tel = BINARY uri.tel
            LEFT JOIN user_usage_stats uus ON BINARY u.tel = BINARY uus.tel
            WHERE 1=1";
    
    $params = [];
    
    if ($target_type === 'rank' && $conditions) {
        $sql .= " AND BINARY uri.user_rank = BINARY :rank";
        $params['rank'] = $conditions;
    } elseif ($target_type === 'usage_count' && $conditions) {
        $cond = json_decode($conditions, true);
        if (isset($cond['min_count']) && $cond['min_count']) {
            $sql .= " AND uri.visit_count >= :min_count";
            $params['min_count'] = $cond['min_count'];
        }
        if (isset($cond['max_count']) && $cond['max_count']) {
            $sql .= " AND uri.visit_count <= :max_count";
            $params['max_count'] = $cond['max_count'];
        }
    } elseif ($target_type === 'days_since_visit' && $conditions) {
        $cond = json_decode($conditions, true);
        if (isset($cond['min_days'])) {
            $sql .= " AND uus.days_since_last_visit >= :min_days";
            $params['min_days'] = $cond['min_days'];
        }
        if (isset($cond['max_days'])) {
            $sql .= " AND uus.days_since_last_visit <= :max_days";
            $params['max_days'] = $cond['max_days'];
        }
    } elseif ($target_type === 'favorite_girl' && $conditions) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM favorite_girls fg 
            WHERE BINARY fg.tel = BINARY u.tel
            AND BINARY fg.g_login_id = BINARY :g_login_id
        )";
        $params['g_login_id'] = $conditions;
    } elseif ($target_type === 'preference' && $conditions) {
        $prefs = json_decode($conditions, true);
        if (!empty($prefs)) {
            $placeholders = [];
            foreach ($prefs as $i => $pref) {
                $key = "pref_{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $pref;
            }
            $sql .= " AND EXISTS (
                SELECT 1 FROM user_preferences up 
                WHERE BINARY up.tel = BINARY u.tel
                AND up.preference_value IN (" . implode(',', $placeholders) . ")
            )";
        }
    } elseif ($target_type === 'girl_attribute_match' && $conditions) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM girl_attributes ga
            INNER JOIN user_preferences up ON ga.attribute_value = up.preference_value
            WHERE BINARY ga.g_login_id = BINARY :g_login_id
            AND BINARY up.tel = BINARY u.tel
        )";
        $params['g_login_id'] = $conditions;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 一斉配信を作成・送信
function adminCreateBroadcast($pdo) {
    $title = $_POST['title'] ?? '';
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    $link_url = $_POST['link_url'] ?? null;
    $link_text = $_POST['link_text'] ?? null;
    $target_type = $_POST['target_type'] ?? 'all';
    $target_conditions = $_POST['target_conditions'] ?? null;
    $scheduled_at = $_POST['scheduled_at'] ?? null;
    $admin_id = $_SESSION['ADMIN']['admin_id'];
    
    try {
        $pdo->beginTransaction();
        
        // ファイルアップロード処理
        $file_path = null;
        if ($message_type !== 'text' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'ファイルサイズが大きすぎます（最大20MB）']);
                return;
            }
            
            $upload_dir = dirname(__FILE__) . '/../../../img/chat_uploads/';
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'ディレクトリの作成に失敗: ' . $upload_dir]);
                    return;
                }
            }
            
            if (!is_writable($upload_dir)) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'ディレクトリに書き込み権限がありません: ' . $upload_dir]);
                return;
            }
            
            $file_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $file_name = 'broadcast_' . uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $file_name;
            $full_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'ファイルのアップロードに失敗しました']);
                return;
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
        $total_sent = 0;
        
        // 即時配信の場合
        if (!$scheduled_at) {
            $target_users = getTargetUsersForBroadcast($pdo, $target_type, $target_conditions);
            $total_target = count($target_users);
            
            foreach ($target_users as $user) {
                try {
                    $room_id = getOrCreatePromotionRoom($pdo, $user['tel']);
                    
                    if ($room_id) {
                        $sql = "INSERT INTO chat_messages 
                                (room_id, sender_type, sender_admin_id, message_type, content, file_path, link_url, link_text, broadcast_id) 
                                VALUES (:room_id, 'girl', :admin_id, :message_type, :content, :file_path, :link_url, :link_text, :broadcast_id)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'room_id' => $room_id,
                            'admin_id' => $admin_id,
                            'message_type' => $message_type,
                            'content' => $content,
                            'file_path' => $file_path,
                            'link_url' => $link_url,
                            'link_text' => $link_text,
                            'broadcast_id' => $broadcast_id
                        ]);
                        
                        $sql = "UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['id' => $room_id]);
                        
                        $total_sent++;
                    }
                } catch (Exception $e) {
                    error_log("Broadcast message failed for user {$user['tel']}: " . $e->getMessage());
                }
            }
            
            $sql = "UPDATE promotion_broadcasts 
                    SET status = 'sent', 
                        sent_at = NOW(),
                        total_target = :total_target,
                        total_sent = :total_sent
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'total_target' => $total_target,
                'total_sent' => $total_sent,
                'id' => $broadcast_id
            ]);
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'broadcast_id' => $broadcast_id,
            'total_sent' => $total_sent
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 配信履歴を取得
function adminGetBroadcasts($pdo) {
    try {
        $sql = "SELECT * FROM promotion_broadcasts ORDER BY created_at DESC LIMIT 100";
        $stmt = $pdo->query($sql);
        $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'broadcasts' => $broadcasts]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// 配信詳細を取得
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
        
        echo json_encode(['success' => true, 'broadcast' => $broadcast]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// テンプレートを保存
function adminSaveTemplate($pdo) {
    $template_name = $_POST['template_name'] ?? '';
    $message_type = $_POST['message_type'] ?? 'text';
    $content = $_POST['content'] ?? '';
    $link_url = $_POST['link_url'] ?? null;
    $link_text = $_POST['link_text'] ?? null;
    $category = $_POST['category'] ?? null;
    $admin_id = $_SESSION['ADMIN']['admin_id'];
    
    try {
        $sql = "INSERT INTO promotion_templates 
                (template_name, message_type, content, link_url, link_text, category, created_by) 
                VALUES (:template_name, :message_type, :content, :link_url, :link_text, :category, :created_by)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'template_name' => $template_name,
            'message_type' => $message_type,
            'content' => $content,
            'link_url' => $link_url,
            'link_text' => $link_text,
            'category' => $category,
            'created_by' => $admin_id
        ]);
        
        $template_id = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'template_id' => $template_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// テンプレート一覧を取得
function adminGetTemplates($pdo) {
    try {
        $sql = "SELECT * FROM promotion_templates ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'templates' => $templates]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// テンプレートを削除
function adminDeleteTemplate($pdo) {
    $template_id = $_POST['template_id'] ?? null;
    
    if (!$template_id) {
        echo json_encode(['success' => false, 'error' => 'Missing template_id']);
        return;
    }
    
    try {
        $sql = "DELETE FROM promotion_templates WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $template_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

unset($pdo);
?>