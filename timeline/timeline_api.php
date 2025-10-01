<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// デバッグ用
error_log("timeline_api.php called");
error_log("Action: " . ($_GET['action'] ?? 'none'));
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = dbConnect();
$user_tel = $_SESSION['USER']['tel'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_posts':
        getPosts($pdo, $user_tel);
        break;
    
    case 'toggle_like':
        toggleLike($pdo, $user_tel);
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

/**
 * 投稿取得（無限スクロール対応）
 */
function getPosts($pdo, $user_tel) {
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 10;
    $filter = $_GET['filter'] ?? 'all'; // all, favorite, girl
    $g_login_id = $_GET['g_login_id'] ?? null;
    
    // ベースクエリ
    $sql = "SELECT 
                p.id,
                p.g_login_id,
                p.content,
                p.created_at,
                g.name as girl_name,
                g.img as girl_img,
                (SELECT COUNT(*) FROM timeline_likes WHERE post_id = p.id) as like_count,
                (SELECT COUNT(*) FROM timeline_likes WHERE post_id = p.id AND user_tel = :user_tel) as user_liked
            FROM timeline_posts p
            INNER JOIN girl g ON p.g_login_id = g.g_login_id";
    
    $params = [':user_tel' => $user_tel];
    
    // フィルタ条件
    if ($filter === 'favorite') {
        $sql .= " INNER JOIN favorite_girls f ON p.g_login_id = f.g_login_id AND f.tel = :tel";
        $params[':tel'] = $user_tel;
    } elseif ($filter === 'girl' && $g_login_id) {
        $sql .= " WHERE p.g_login_id = :g_login_id";
        $params[':g_login_id'] = $g_login_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 各投稿のメディアを取得
foreach ($posts as &$post) {
    $mediaStmt = $pdo->prepare("
        SELECT media_type, file_path 
        FROM timeline_media 
        WHERE post_id = :post_id 
        ORDER BY sort_order ASC
    ");
    $mediaStmt->execute([':post_id' => $post['id']]);
    $media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // パスの先頭に uploads/ を追加
    foreach ($media as &$m) {
        $m['file_path'] = 'uploads/' . $m['file_path'];
    }
    
    $post['media'] = $media;
    
    // 日時を相対表示用に変換
    $post['time_ago'] = getTimeAgo($post['created_at']);
    $post['user_liked'] = (bool)$post['user_liked'];
}
    
    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'has_more' => count($posts) === $limit
    ]);
}

/**
 * いいね切り替え
 */
function toggleLike($pdo, $user_tel) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid method']);
        return;
    }
    
    $post_id = (int)($_POST['post_id'] ?? 0);
    
    if (!$post_id) {
        echo json_encode(['error' => 'Invalid post_id']);
        return;
    }
    
    try {
        // 既にいいね済みかチェック
        $checkStmt = $pdo->prepare("
            SELECT id FROM timeline_likes 
            WHERE post_id = :post_id AND user_tel = :user_tel
        ");
        $checkStmt->execute([
            ':post_id' => $post_id,
            ':user_tel' => $user_tel
        ]);
        
        if ($checkStmt->fetch()) {
            // いいね解除
            $deleteStmt = $pdo->prepare("
                DELETE FROM timeline_likes 
                WHERE post_id = :post_id AND user_tel = :user_tel
            ");
            $deleteStmt->execute([
                ':post_id' => $post_id,
                ':user_tel' => $user_tel
            ]);
            $liked = false;
        } else {
            // いいね追加
            $insertStmt = $pdo->prepare("
                INSERT INTO timeline_likes (post_id, user_tel) 
                VALUES (:post_id, :user_tel)
            ");
            $insertStmt->execute([
                ':post_id' => $post_id,
                ':user_tel' => $user_tel
            ]);
            $liked = true;
        }
        
        // 最新のいいね数を取得
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM timeline_likes WHERE post_id = :post_id
        ");
        $countStmt->execute([':post_id' => $post_id]);
        $like_count = (int)$countStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => $like_count
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
}

/**
 * 相対時間表示
 */
function getTimeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . '年前';
    if ($diff->m > 0) return $diff->m . 'ヶ月前';
    if ($diff->d > 0) return $diff->d . '日前';
    if ($diff->h > 0) return $diff->h . '時間前';
    if ($diff->i > 0) return $diff->i . '分前';
    return 'たった今';
}