<?php
// config.phpを絶対パスで読み込み
require_once($_SERVER['DOCUMENT_ROOT'] . '/HENNESSY/HENNESSY_C/config/config.php');

/**
 * データベース接続
 */
function dbConnect() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        die('データベース接続エラーが発生しました');
    }
}

/**
 * XSS対策のためのHTMLエスケープ
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF対策トークン生成
 */
function generateToken() {
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

/**
 * CSRF対策トークン検証
 */
function validateToken($token) {
    return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $token);
}

/**
 * ログイン認証チェック（キャスト用）
 */
function requireCastLogin() {
    if (!isset($_SESSION['cast_login']) || !isset($_SESSION['CAST'])) {
        header('Location: ' . WEB_URL . 'cast_login.php');
        exit;
    }
}

/**
 * ログイン認証チェック（ユーザー用）
 */
function requireUserLogin() {
    if (!isset($_SESSION['user_login']) || !isset($_SESSION['USER'])) {
        header('Location: /HENNESSY/HENNESSY/user/login.php');
        exit;
    }
}

/**
 * 画像アップロード処理
 * @param array $file $_FILESの要素
 * @param string $type 'icon' or 'panels'
 * @param int $maxSize 最大ファイルサイズ(MB)
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function uploadImage($file, $type = 'icon', $maxSize = 5) {
    $result = ['success' => false, 'filename' => '', 'error' => ''];
    
    // アップロード先ディレクトリを決定
    $uploadDir = ($type === 'icon') ? IMG_ICON_DIR : IMG_PANELS_DIR;
    
    // エラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'ファイルのアップロードに失敗しました';
        return $result;
    }
    
    // ファイル形式チェック
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $result['error'] = 'JPG、PNG形式の画像のみアップロード可能です';
        return $result;
    }
    
    // ファイルサイズチェック
    $maxBytes = $maxSize * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        $result['error'] = "ファイルサイズは{$maxSize}MB以下にしてください";
        return $result;
    }
    
    // ユニークなファイル名を生成
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $extension;
    
    // ディレクトリが存在しない場合は作成
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    // ファイル移動
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'ファイルの保存に失敗しました';
    }
    
    return $result;
}

/**
 * 画像削除処理
 * @param string $filename ファイル名
 * @param string $type 'icon' or 'panels'
 * @return bool
 */
function deleteImage($filename, $type = 'icon') {
    $dir = ($type === 'icon') ? IMG_ICON_DIR : IMG_PANELS_DIR;
    $filepath = $dir . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * 日時フォーマット
 * @param string $datetime
 * @param string $format
 * @return string
 */
function formatDateTime($datetime, $format = 'Y/m/d H:i') {
    if (empty($datetime)) {
        return '';
    }
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * 相対時間表示（○分前、○時間前など）
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->getTimestamp() - $past->getTimestamp();
    
    if ($diff < 60) {
        return 'たった今';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '時間前';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . '日前';
    } else {
        return formatDateTime($datetime, 'Y/m/d');
    }
}

/**
 * ページネーション用のデータを生成
 * @param int $total 総件数
 * @param int $perPage 1ページあたりの件数
 * @param int $currentPage 現在のページ
 * @return array
 */
function getPagination($total, $perPage, $currentPage) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * 基準日取得関数
 * 営業時間を考慮した「今日」の日付を返す
 * 営業開始時刻より前の時間帯は「前日」として扱う
 * 
 * @return string Y-m-d形式の日付
 */
function getBaseDate() {
    // 現在時刻を取得
    $now = new DateTime();
    $hour = (int)$now->format('H');
    
    // デフォルトの営業開始時刻（6時）
    $shop_start_hour = 6;
    
    // データベースから営業開始時刻を取得
    try {
        $pdo = dbConnect();
        $sql = "SELECT start_time FROM shop LIMIT 1";
        $stmt = $pdo->query($sql);
        $shop = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shop && !empty($shop['start_time'])) {
            $start_time = new DateTime($shop['start_time']);
            $shop_start_hour = (int)$start_time->format('H');
        }
    } catch (Exception $e) {
        // エラー時はデフォルト値（6時）を使用
        error_log('getBaseDate error: ' . $e->getMessage());
    }
    
    // 営業開始時刻より前なら前日の日付を返す
    if ($hour < $shop_start_hour) {
        $now->modify('-1 day');
    }
    
    return $now->format('Y-m-d');
}