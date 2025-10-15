<?php
// エラーログを有効化
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
session_start();

// パス修正: HENNESSY_C/functions.php を読み込む
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    error_log('Timeline API: Unauthorized access');
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

$pdo = dbConnect();
$current_g_login_id = $_SESSION['CAST']['g_login_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

error_log("Timeline API called: action={$action}, current_g_login_id={$current_g_login_id}");

switch ($action) {
    case 'create_post':
        createPost($pdo, $current_g_login_id);
        break;
    
    case 'get_posts':
        getPosts($pdo, $current_g_login_id);
        break;
    
    case 'update_post':
        updatePost($pdo, $current_g_login_id);
        break;
    
    case 'delete_post':
        deletePost($pdo, $current_g_login_id);
        break;
    
    case 'search_girls':
        searchGirls($pdo);
        break;
    
    default:
        error_log("Timeline API: Invalid action - {$action}");
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * 投稿作成
 */
function createPost($pdo, $g_login_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('Timeline create: Invalid method');
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
        return;
    }

    $content = trim($_POST['content'] ?? '');
    
    error_log("Timeline create: content length = " . mb_strlen($content));
    error_log("Timeline create: files = " . print_r($_FILES, true));

    if (empty($content)) {
        error_log('Timeline create: Content is empty');
        echo json_encode(['success' => false, 'error' => '内容を入力してください']);
        return;
    }

    if (mb_strlen($content) > 500) {
        error_log('Timeline create: Content too long');
        echo json_encode(['success' => false, 'error' => '内容は500文字以内で入力してください']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // 投稿を作成
        $sql = "INSERT INTO timeline_posts (g_login_id, content, created_at) VALUES (:g_login_id, :content, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':g_login_id' => $g_login_id,
            ':content' => $content
        ]);

        $post_id = $pdo->lastInsertId();
        error_log("Timeline create: Post created with ID = {$post_id}");

        // メディアファイルのアップロード処理
        if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
            // パス修正: HENNESSY/img/timeline_uploads/
            $upload_dir = dirname(__FILE__) . '/../../img/timeline_uploads/';
            
            error_log("Timeline create: Upload dir = {$upload_dir}");
            
            // ディレクトリが存在しない場合は作成
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('アップロードディレクトリの作成に失敗しました');
                }
                error_log("Timeline create: Created upload directory");
            }

            $uploaded_count = 0;
            $max_files = 3;

            foreach ($_FILES['media']['tmp_name'] as $index => $tmp_name) {
                if ($uploaded_count >= $max_files) break;
                if (empty($tmp_name)) continue;

                $file_name = $_FILES['media']['name'][$index];
                $file_size = $_FILES['media']['size'][$index];
                $file_error = $_FILES['media']['error'][$index];

                error_log("Timeline create: Processing file {$index}: {$file_name}, size={$file_size}, error={$file_error}");

                // エラーチェック
                if ($file_error !== UPLOAD_ERR_OK) {
                    error_log("Timeline create: File {$index} has upload error {$file_error}");
                    continue;
                }

                // ファイルサイズチェック（10MB）
                if ($file_size > 10 * 1024 * 1024) {
                    error_log("Timeline create: File {$index} too large");
                    continue;
                }

                // ファイル形式チェック
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);

                $allowed_types = [
                    'image/jpeg' => 'image',
                    'image/jpg' => 'image',
                    'image/png' => 'image',
                    'video/mp4' => 'video'
                ];

                if (!isset($allowed_types[$mime_type])) {
                    error_log("Timeline create: File {$index} has invalid MIME type: {$mime_type}");
                    continue;
                }

                $media_type = $allowed_types[$mime_type];

                // ファイル名生成
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = uniqid('timeline_', true) . '.' . $extension;
                $upload_path = $upload_dir . $new_filename;

                error_log("Timeline create: Moving file to {$upload_path}");

                // ファイル移動
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    // DBに保存
                    $media_sql = "INSERT INTO timeline_media (post_id, media_type, file_path, sort_order) 
                                  VALUES (:post_id, :media_type, :file_path, :sort_order)";
                    $media_stmt = $pdo->prepare($media_sql);
                    $media_stmt->execute([
                        ':post_id' => $post_id,
                        ':media_type' => $media_type,
                        ':file_path' => $new_filename,
                        ':sort_order' => $uploaded_count
                    ]);

                    $uploaded_count++;
                    error_log("Timeline create: File {$index} uploaded successfully as {$new_filename}");
                } else {
                    error_log("Timeline create: Failed to move file {$index}");
                }
            }

            error_log("Timeline create: Uploaded {$uploaded_count} files");
        }

        $pdo->commit();
        error_log("Timeline create: Transaction committed");

        echo json_encode([
            'success' => true,
            'post_id' => $post_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Timeline create error: ' . $e->getMessage());
        error_log('Timeline create trace: ' . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'エラーが発生しました: ' . $e->getMessage()]);
    }
}

/**
 * 投稿一覧取得（全キャスト対応 + フィルター）
 */
function getPosts($pdo, $current_g_login_id) {
    $offset = (int)($_GET['offset'] ?? 0);
    $limit = 10;
    $filter_g_login_id = $_GET['filter_g_login_id'] ?? null;

    error_log("Timeline get_posts: offset={$offset}, filter_g_login_id={$filter_g_login_id}");

    try {
        // ベースSQL - 全キャストの投稿を取得
        $sql = "SELECT 
                    p.id,
                    p.g_login_id,
                    p.content,
                    p.created_at,
                    g.name as girl_name,
                    g.img as girl_img,
                    (SELECT COUNT(*) FROM timeline_likes WHERE post_id = p.id) as like_count
                FROM timeline_posts p
                INNER JOIN girl g ON p.g_login_id = g.g_login_id";
        
        // フィルター条件
        $params = [];
        if ($filter_g_login_id) {
            $sql .= " WHERE p.g_login_id = :filter_g_login_id";
            $params[':filter_g_login_id'] = $filter_g_login_id;
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

        error_log("Timeline get_posts: Found " . count($posts) . " posts");

        // 各投稿のメディアを取得
        foreach ($posts as &$post) {
            $media_sql = "SELECT media_type, file_path 
                          FROM timeline_media 
                          WHERE post_id = :post_id 
                          ORDER BY sort_order ASC";
            $media_stmt = $pdo->prepare($media_sql);
            $media_stmt->execute([':post_id' => $post['id']]);
            $post['media'] = $media_stmt->fetchAll(PDO::FETCH_ASSOC);

            // 相対時間表示
            $post['time_ago'] = getTimeAgo($post['created_at']);
            
            // 自分の投稿かどうか
            $post['is_own_post'] = ($post['g_login_id'] == $current_g_login_id);
        }

        echo json_encode([
            'success' => true,
            'posts' => $posts,
            'has_more' => count($posts) === $limit
        ]);

    } catch (PDOException $e) {
        error_log('Timeline get posts error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました']);
    }
}

/**
 * キャスト検索（インクリメンタルサーチ）
 */
function searchGirls($pdo) {
    $query = $_GET['query'] ?? '';
    
    error_log("Timeline search_girls: query={$query}");
    
    try {
        if (empty($query)) {
            // 検索クエリが空の場合は全キャストを返す
            $sql = "SELECT g_login_id, name, img FROM girl ORDER BY name ASC LIMIT 20";
            $stmt = $pdo->query($sql);
        } else {
            // 部分一致検索
            $sql = "SELECT g_login_id, name, img 
                    FROM girl 
                    WHERE name LIKE :query 
                    ORDER BY name ASC 
                    LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':query' => '%' . $query . '%']);
        }
        
        $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Timeline search_girls: Found " . count($girls) . " girls");
        
        echo json_encode([
            'success' => true,
            'girls' => $girls
        ]);
        
    } catch (PDOException $e) {
        error_log('Timeline search girls error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました']);
    }
}

/**
 * 投稿更新
 */
function updatePost($pdo, $g_login_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
        return;
    }

    $post_id = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$post_id) {
        echo json_encode(['success' => false, 'error' => '投稿IDが不正です']);
        return;
    }

    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => '内容を入力してください']);
        return;
    }

    if (mb_strlen($content) > 500) {
        echo json_encode(['success' => false, 'error' => '内容は500文字以内で入力してください']);
        return;
    }

    try {
        // 自分の投稿かチェック
        $check_sql = "SELECT id FROM timeline_posts WHERE id = :post_id AND g_login_id = :g_login_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([
            ':post_id' => $post_id,
            ':g_login_id' => $g_login_id
        ]);

        if (!$check_stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => '権限がありません']);
            return;
        }

        // 更新
        $sql = "UPDATE timeline_posts SET content = :content, updated_at = NOW() 
                WHERE id = :post_id AND g_login_id = :g_login_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':content' => $content,
            ':post_id' => $post_id,
            ':g_login_id' => $g_login_id
        ]);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        error_log('Timeline update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました']);
    }
}

/**
 * 投稿削除
 */
function deletePost($pdo, $g_login_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid method']);
        return;
    }

    $post_id = (int)($_POST['post_id'] ?? 0);

    if (!$post_id) {
        echo json_encode(['success' => false, 'error' => '投稿IDが不正です']);
        return;
    }

    try {
        // 自分の投稿かチェック
        $check_sql = "SELECT id FROM timeline_posts WHERE id = :post_id AND g_login_id = :g_login_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([
            ':post_id' => $post_id,
            ':g_login_id' => $g_login_id
        ]);

        if (!$check_stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => '権限がありません']);
            return;
        }

        // メディアファイルを取得して削除
        $media_sql = "SELECT file_path FROM timeline_media WHERE post_id = :post_id";
        $media_stmt = $pdo->prepare($media_sql);
        $media_stmt->execute([':post_id' => $post_id]);
        $media_files = $media_stmt->fetchAll(PDO::FETCH_COLUMN);

        // パス修正
        $upload_dir = dirname(__FILE__) . '/../../img/timeline_uploads/';
        foreach ($media_files as $file) {
            $file_path = $upload_dir . $file;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // 投稿削除（外部キー制約でメディアといいねも削除される）
        $sql = "DELETE FROM timeline_posts WHERE id = :post_id AND g_login_id = :g_login_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':post_id' => $post_id,
            ':g_login_id' => $g_login_id
        ]);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        error_log('Timeline delete error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'データベースエラーが発生しました']);
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