<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// 現在のプロフィール情報取得
$sql = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$current_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_profile) {
    header('Location: cast_login.php');
    exit;
}

// 承認待ちデータをチェック
$sql = "SELECT * FROM girl_pending WHERE g_login_id = :g_login_id AND status = 'pending'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);

$has_pending = !empty($pending_data);

// ä¸€å•ä¸€ç­"ãƒ†ãƒ³ãƒ—ãƒ¬ãƒ¼ãƒˆå–å¾—
$sql = "SELECT * FROM ichimon_templates ORDER BY sort_order ASC";
$stmt = $pdo->query($sql);
$ichimon_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ã‚ªãƒ—ã‚·ãƒ§ãƒ³ä¸€è¦§å–å¾—
$sql = "SELECT * FROM options ORDER BY id ASC";
$stmt = $pdo->query($sql);
$all_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 属性選択肢取得
$sql = "SELECT * FROM preferenceoptions ORDER BY sort_order ASC";
$stmt = $pdo->query($sql);
$all_preference_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 現在の属性取得
$sql = "SELECT attribute_value FROM girl_attributes WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$current_attributes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ç¾åœ¨ã®ãƒ‡ãƒ¼ã‚¿ã‚'ãƒ‡ã‚³ãƒ¼ãƒ‰
$current_ichimon = json_decode($current_profile['ichimon'], true) ?: [];
$current_options = json_decode($current_profile['option'], true) ?: [];
$current_imgs = json_decode($current_profile['imgs'], true) ?: [];
// パネル画像を10枚の配列に正規化
$panel_slots = array_fill(0, 10, null);
foreach ($current_imgs as $index => $img) {
    if ($index < 10) {
        $panel_slots[$index] = $img;
    }
}

// エラー配列
$errors = [];

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF対策(簡易版)
    if (!isset($_POST['token']) || $_POST['token'] !== ($_SESSION['token'] ?? '')) {
        $errors[] = '不正なリクエストです';
    } else {
        // 入力値取得
        $name = trim($_POST['name'] ?? '');
        $age = trim($_POST['age'] ?? '');
        $high = trim($_POST['high'] ?? '');
        $b = trim($_POST['b'] ?? '');
        $w = trim($_POST['w'] ?? '');
        $h = trim($_POST['h'] ?? '');
        $head_comment = trim($_POST['head_comment'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        
        // 一問一答の回答取得
        $ichimon_answers = [];
        foreach ($ichimon_templates as $template) {
            $answer = trim($_POST['ichimon_' . $template['id']] ?? '');
            if (!empty($answer)) {
                $ichimon_answers[] = [
                    'q' => $template['question'],
                    'a' => $answer
                ];
            }
        }
        
        // オプション取得
        $selected_options = $_POST['options'] ?? [];
        
        // バリデーション
        // 1. 名前チェック
        if (empty($name)) {
            $errors[] = '名前を入力してください';
        } else {
            // 重複チェック(自分以外)
            $sql = "SELECT COUNT(*) FROM girl WHERE name = :name AND g_login_id != :g_login_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $name, ':g_login_id' => $g_login_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'この名前は既に使用されています';
            }
        }
        
        // 2. 年齢チェック
        if (empty($age)) {
            $errors[] = '年齢を入力してください';
        } elseif (!is_numeric($age) || intval($age) < 18) {
            $errors[] = '年齢は18歳以上の数値を入力してください';
        }
        
        // 3. 必須項目の空白チェック
        if (empty($high)) $errors[] = '身長を入力してください';
        if (empty($b)) $errors[] = 'Bサイズを入力してください';
        if (empty($w)) $errors[] = 'Wサイズを入力してください';
        if (empty($h)) $errors[] = 'Hサイズを入力してください';
        
        // 4. アイコン画像アップロード処理
        $icon_filename = $current_profile['img']; // デフォルトは現在の画像
        if (isset($_FILES['icon_img']) && $_FILES['icon_img']['error'] === UPLOAD_ERR_OK) {
            $icon_result = validateAndUploadImage($_FILES['icon_img'], 'icon', 5);
            if ($icon_result['success']) {
                $icon_filename = $icon_result['filename'];
                // 古い画像は承認後に削除されるため、ここでは削除しない
            } else {
                $errors[] = 'アイコン画像: ' . $icon_result['error'];
            }
        }
        
        // 5. 掲載画像アップロード処理(個別スロット管理)
        $new_panel_slots = $panel_slots; // 現在のスロットをコピー
        
        // 各スロットの削除チェック
        for ($i = 0; $i < 10; $i++) {
            if (isset($_POST['delete_panel_' . $i]) && !empty($new_panel_slots[$i])) {
                $new_panel_slots[$i] = null;
            }
        }
        
        // 各スロットの新規アップロード
        for ($i = 0; $i < 10; $i++) {
            if (isset($_FILES['panel_img_' . $i]) && $_FILES['panel_img_' . $i]['error'] === UPLOAD_ERR_OK) {
                $panel_result = validateAndUploadImage($_FILES['panel_img_' . $i], 'panels', 10);
                if ($panel_result['success']) {
                    $new_panel_slots[$i] = $panel_result['filename'];
                } else {
                    $errors[] = '掲載画像' . ($i + 1) . '枚目: ' . $panel_result['error'];
                }
            }
        }
        
        // 属性取得
        $selected_attributes = $_POST['attributes'] ?? [];
        
        // ã‚¨ãƒ©ãƒ¼ãŒãªã'ã‚Œã°ä¿å­˜å‡¦ç†
        if (empty($errors)) {
            try {
                // nullã‚'é™¤å¤–ã—ãŸé…åˆ—ã«å¤‰æ›
                $final_imgs = array_values(array_filter($new_panel_slots, function($img) {
                    return $img !== null;
                }));
                
                // 変更検知
                $has_basic_changes = false;
                $has_attribute_changes = false;
                
                // 基本情報の変更チェック
                if ($name !== $current_profile['name'] ||
                    intval($age) !== intval($current_profile['age']) ||
                    $high !== $current_profile['high'] ||
                    $b !== $current_profile['b'] ||
                    $w !== $current_profile['w'] ||
                    $h !== $current_profile['h'] ||
                    $head_comment !== $current_profile['head_comment'] ||
                    $comment !== $current_profile['comment'] ||
                    $icon_filename !== $current_profile['img'] ||
                    json_encode($final_imgs) !== $current_profile['imgs'] ||
                    json_encode($ichimon_answers, JSON_UNESCAPED_UNICODE) !== $current_profile['ichimon'] ||
                    json_encode($selected_options) !== $current_profile['option']) {
                    $has_basic_changes = true;
                }
                
                // 属性の変更チェック
                sort($selected_attributes);
                sort($current_attributes);
                if ($selected_attributes !== $current_attributes) {
                    $has_attribute_changes = true;
                }
                
                // 変更がない場合
                if (!$has_basic_changes && !$has_attribute_changes) {
                    $_SESSION['info_message'] = '変更がありませんでした';
                    header('Location: cast_profile.php');
                    exit;
                }
                
                // 基本情報が変更されている場合 → girl_pending に保存
                if ($has_basic_changes) {
                    if ($has_pending) {
                        $sql = "UPDATE girl_pending SET 
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
                            img = :img,
                            imgs = :imgs,
                            ichimon = :ichimon,
                            `option` = :options,
                            k_check = :k_check,
                            requested_at = CURRENT_TIMESTAMP
                            WHERE g_login_id = :g_login_id AND status = 'pending'";
                    } else {
                        $sql = "INSERT INTO girl_pending (
                            g_login_id, c_login_id, g_password, tel, name, high, age, b, w, h,
                            head_comment, comment, store_comment, ichimon, `option`, k_check, img, imgs,
                            request_type, status
                        ) VALUES (
                            :g_login_id, :c_login_id, :g_password, :tel, :name, :high, :age, :b, :w, :h,
                            :head_comment, :comment, :store_comment, :ichimon, :options, :k_check, :img, :imgs,
                            'edit', 'pending'
                        )";
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':g_login_id' => $g_login_id,
                        ':c_login_id' => $current_profile['c_login_id'],
                        ':g_password' => $current_profile['g_password'],
                        ':tel' => $current_profile['tel'],
                        ':name' => $name,
                        ':age' => intval($age),
                        ':high' => $high,
                        ':b' => $b,
                        ':w' => $w,
                        ':h' => $h,
                        ':head_comment' => $head_comment,
                        ':comment' => $comment,
                        ':store_comment' => $current_profile['store_comment'],
                        ':img' => $icon_filename,
                        ':imgs' => json_encode($final_imgs),
                        ':ichimon' => json_encode($ichimon_answers, JSON_UNESCAPED_UNICODE),
                        ':options' => json_encode($selected_options),
                        ':k_check' => $current_profile['k_check']
                    ]);
                }
                
                // 属性が変更されている場合 → girl_attributes に直接保存
                if ($has_attribute_changes) {
                    // 既存の属性を削除
                    $sql = "DELETE FROM girl_attributes WHERE g_login_id = :g_login_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':g_login_id' => $g_login_id]);
                    
                    // 新しい属性を登録
                    if (!empty($selected_attributes)) {
                        $sql = "INSERT INTO girl_attributes (g_login_id, attribute_value) VALUES (:g_login_id, :attribute_value)";
                        $stmt = $pdo->prepare($sql);
                        foreach ($selected_attributes as $attr) {
                            $stmt->execute([
                                ':g_login_id' => $g_login_id,
                                ':attribute_value' => $attr
                            ]);
                        }
                    }
                }
                
                // 成功メッセージ
                if ($has_basic_changes && $has_attribute_changes) {
                    $_SESSION['success_message'] = '編集申請を送信しました。管理者の承認をお待ちください（属性は即座に反映されました）';
                } elseif ($has_basic_changes) {
                    $_SESSION['success_message'] = '編集申請を送信しました。管理者の承認をお待ちください';
                } else {
                    $_SESSION['success_message'] = '属性を保存しました';
                }
                
                header('Location: cast_profile.php');
                exit;
                
            } catch (Exception $e) {
                $errors[] = '保存中にエラーが発生しました: ' . $e->getMessage();
            }
        }
    }
}

// CSRFトークン生成
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

/**
 * 画像のバリデーションとアップロード
 */
function validateAndUploadImage($file, $type, $max_mb) {
    $result = ['success' => false, 'filename' => '', 'error' => ''];
    
    // 1. ファイル形式チェック
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $result['error'] = 'JPG、PNG形式の画像のみアップロード可能です';
        return $result;
    }
    
    // 2. ファイルサイズチェック
    $max_bytes = $max_mb * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        $result['error'] = "ファイルサイズは{$max_mb}MB以下にしてください";
        return $result;
    }
    
    // 3. 画像サイズ(寸法)チェック
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        $result['error'] = '画像ファイルが不正です';
        return $result;
    }
    
    list($width, $height) = $image_info;
    
    if ($type === 'icon') {
        if ($width < 300 || $height < 300) {
            $result['error'] = '画像サイズは最小300px×300px以上にしてください';
            return $result;
        }
        if ($width > 3000 || $height > 3000) {
            $result['error'] = '画像サイズは最大3000px×3000px以下にしてください';
            return $result;
        }
    } else {
        if ($width < 600 || $height < 400) {
            $result['error'] = '画像サイズは最小600px×400px以上にしてください';
            return $result;
        }
        if ($width > 5000 || $height > 5000) {
            $result['error'] = '画像サイズは最大5000px×5000px以下にしてください';
            return $result;
        }
    }
    
    // 4. ファイル名生成(重複防止)
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $extension;
    
    // 5. アップロード先パス
    $upload_dir = $type === 'icon' ? '../../img/icon/' : '../../img/panels/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $filename;
    
    // 6. ファイル移動
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'ファイルのアップロードに失敗しました';
    }
    
    return $result;
}

// エラー時の入力値保持用のヘルパー関数
function getPostValue($key, $default = '') {
    global $current_profile;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return htmlspecialchars($_POST[$key] ?? $default);
    }
    return htmlspecialchars($current_profile[$key] ?? $default);
}

function getIchimonAnswer($template_id) {
    global $current_ichimon, $ichimon_templates;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return htmlspecialchars($_POST['ichimon_' . $template_id] ?? '');
    }
    
    foreach ($ichimon_templates as $template) {
        if ($template['id'] == $template_id) {
            foreach ($current_ichimon as $qa) {
                if ($qa['q'] === $template['question']) {
                    return htmlspecialchars($qa['a']);
                }
            }
        }
    }
    return '';
}

function isOptionChecked($option_name) {
    global $current_options;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected = $_POST['options'] ?? [];
        return in_array($option_name, $selected);
    }
    
    return in_array($option_name, $current_options);
}

function isAttributeChecked($attribute_value) {
    global $current_attributes;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected = $_POST['attributes'] ?? [];
        return in_array($attribute_value, $selected);
    }
    
    return in_array($attribute_value, $current_attributes);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プロフィール編集</title>
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
    padding: 1rem;
    min-height: 100vh;
}

.container {
    max-width: 50rem;
    margin: 0 auto;
    background: #fff;
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.08);
}

h1 {
    font-size: 1.75rem;
    margin-bottom: 2rem;
    color: #333;
    border-bottom: 0.1875rem solid #4a90e2;
    padding-bottom: 0.75rem;
}

.pending-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border: 0.125rem solid #ffc107;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pending-warning:before {
    content: "⚠️";
    font-size: 1.5rem;
}

.pending-warning-text {
    flex: 1;
}

.pending-warning-text strong {
    display: block;
    color: #856404;
    margin-bottom: 0.25rem;
}

.pending-warning-text p {
    color: #856404;
    font-size: 0.9rem;
    margin: 0;
}

.error-messages {
    background: linear-gradient(135deg, #fee 0%, #fdd 100%);
    border: 0.0625rem solid #fcc;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 0.25rem 0.5rem rgba(255,0,0,0.1);
}

.error-messages ul {
    list-style: none;
    color: #c33;
}

.error-messages li {
    margin-bottom: 0.5rem;
    padding-left: 1.25rem;
    position: relative;
}

.error-messages li:before {
    content: "⚠";
    position: absolute;
    left: 0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
    color: #555;
}

.required {
    color: #f00;
    margin-left: 0.25rem;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 0.125rem solid #ddd;
    border-radius: 0.5rem;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 0.1875rem rgba(74,144,226,0.1);
}

.form-group textarea {
    min-height: 8rem;
    resize: vertical;
}

.size-inputs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.image-section {
    background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    border: 0.0625rem solid #e0e0e0;
}

.image-section h3 {
    margin-bottom: 1rem;
    color: #333;
    font-size: 1.25rem;
}

.current-icon {
    margin-bottom: 1rem;
    text-align: center;
}

.current-icon img {
    max-width: 12.5rem;
    max-height: 12.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.1);
}

.panel-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.panel-slot {
    background: #fff;
    border: 0.125rem dashed #ddd;
    border-radius: 0.5rem;
    padding: 1rem;
    min-height: 12rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: all 0.3s;
}

.panel-slot.hidden {
    display: none;
}

.panel-slot:hover {
    border-color: #4a90e2;
    background: #f8f9fa;
}

.panel-slot-number {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background: #4a90e2;
    color: #fff;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: bold;
}

.panel-slot img {
    max-width: 100%;
    max-height: 10rem;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    object-fit: cover;
    transition: opacity 0.3s;
}

.delete-checkbox {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: #fee;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.file-input-wrapper {
    margin-top: 1rem;
}

.file-hint {
    font-size: 0.875rem;
    color: #666;
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fffbea;
    border-left: 0.25rem solid #ffc107;
    border-radius: 0.25rem;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
    gap: 0.75rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.checkbox-item:hover {
    background: #e9ecef;
}

.checkbox-item input {
    margin-right: 0.5rem;
}

.ichimon-section {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    border: 0.0625rem solid #90caf9;
}

.ichimon-section h3 {
    color: #1976d2;
    margin-bottom: 1.5rem;
    font-size: 1.25rem;
}

.ichimon-item {
    background: #fff;
    padding: 1.25rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.05);
    border-left: 0.25rem solid #4a90e2;
    transition: all 0.3s;
}

.ichimon-item:hover {
    box-shadow: 0 0.25rem 1rem rgba(0,0,0,0.1);
    transform: translateX(0.25rem);
}

.ichimon-question {
    font-weight: bold;
    color: #4a90e2;
    margin-bottom: 0.75rem;
    font-size: 1.0625rem;
}

.ichimon-item input {
    width: 100%;
    padding: 1rem;
    border: 0.125rem solid #ddd;
    border-radius: 0.5rem;
    font-size: 1.0625rem;
    transition: all 0.3s;
    min-height: 3rem;
}

.ichimon-item input:focus {
    outline: none;
    border-color: #4a90e2;
    box-shadow: 0 0 0 0.1875rem rgba(74,144,226,0.1);
}

.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn {
    padding: 0.875rem 2rem;
    border: none;
    border-radius: 0.5rem;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.15);
}

.btn:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.375rem 1rem rgba(0,0,0,0.2);
}

.btn-primary {
    background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
    color: #fff;
}

.btn-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    color: #fff;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

/* タブレット対応 (768px以下) */
@media (max-width: 48rem) {
    .container {
        padding: 1.75rem;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    .size-inputs {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .panel-grid {
        grid-template-columns: 1fr;
    }
    
    .checkbox-group {
        grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
    }
}

/* スマホ対応 (480px以下) */
@media (max-width: 30rem) {
    body {
        font-size: 1.0625rem;
        padding: 0.5rem;
    }
    
    .container {
        padding: 1.5rem;
        border-radius: 0.75rem;
    }
    
    h1 {
        font-size: 1.625rem;
        margin-bottom: 1.5rem;
    }
    
    .pending-warning {
        padding: 0.875rem;
        gap: 0.5rem;
    }
    
    .pending-warning:before {
        font-size: 1.25rem;
    }
    
    .pending-warning-text strong {
        font-size: 1.125rem;
    }
    
    .pending-warning-text p {
        font-size: 1.0625rem;
    }
    
    .error-messages li {
        font-size: 1.0625rem;
    }
    
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-group label {
        font-size: 1.0625rem;
        margin-bottom: 0.625rem;
    }
    
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea {
        font-size: 1.0625rem;
        padding: 0.875rem;
    }
    
    .form-group textarea {
        min-height: 10rem;
    }
    
    .size-inputs {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .image-section {
        padding: 1.25rem;
    }
    
    .image-section h3 {
        font-size: 1.125rem;
    }
    
    .current-icon img {
        max-width: 10rem;
        max-height: 10rem;
    }
    
    .panel-grid {
        grid-template-columns: 1fr;
        gap: 0.875rem;
    }
    
    .panel-slot {
        padding: 0.875rem;
        min-height: 11rem;
    }
    
    .file-hint {
        font-size: 1rem;
    }
    
    .checkbox-group {
        grid-template-columns: 1fr;
    }
    
    .checkbox-item {
        font-size: 1.0625rem;
        padding: 0.75rem;
    }
    
    .ichimon-section {
        padding: 1.25rem;
    }
    
    .ichimon-section h3 {
        font-size: 1.125rem;
        margin-bottom: 1.25rem;
    }
    
    .ichimon-item {
        padding: 1rem;
    }
    
    .ichimon-question {
        font-size: 1.125rem;
        margin-bottom: 0.875rem;
    }
    
    .ichimon-item input {
        font-size: 1.125rem;
        padding: 1.125rem;
        min-height: 3.5rem;
    }
    
    .button-group {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .btn {
        font-size: 1.125rem;
        padding: 1rem 1.5rem;
        width: 100%;
    }
}
    </style>
</head>

<body>
    <?php require_once(dirname(__FILE__) . '/header.php'); ?>
    <div class="container">
        <h1>プロフィール編集</h1>
        
        <?php if ($has_pending): ?>
            <div class="pending-warning">
                <div class="pending-warning-text">
                    <strong>承認待ちの編集があります</strong>
                    <p>このまま保存すると、以前の申請内容は上書きされます。</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['token']) ?>">
            
            <!-- 基本情報 -->
            <div class="form-group">
                <label>名前<span class="required">*</span></label>
                <input type="text" name="name" value="<?= getPostValue('name') ?>" required>
            </div>
            
            <div class="form-group">
                <label>年齢<span class="required">*</span></label>
                <input type="number" name="age" value="<?= getPostValue('age') ?>" min="18" required>
            </div>
            
            <div class="form-group">
                <label>身長<span class="required">*</span></label>
                <input type="text" name="high" value="<?= getPostValue('high') ?>" placeholder="例: 160" required>
            </div>
            
            <div class="form-group">
                <label>スリーサイズ<span class="required">*</span></label>
                <div class="size-inputs">
                    <input type="text" name="b" value="<?= getPostValue('b') ?>" placeholder="B" required>
                    <input type="text" name="w" value="<?= getPostValue('w') ?>" placeholder="W" required>
                    <input type="text" name="h" value="<?= getPostValue('h') ?>" placeholder="H" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>ヘッドコメント</label>
                <textarea name="head_comment" placeholder="一言コメントを入力してください"><?= getPostValue('head_comment') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>プロフィール</label>
                <textarea name="comment" placeholder="自己紹介を入力してください"><?= getPostValue('comment') ?></textarea>
            </div>
            
            <!-- アイコン画像 -->
            <div class="image-section">
                <h3>📷 アイコン画像</h3>
                <?php if (!empty($current_profile['img'])): ?>
                    <div class="current-icon">
                        <p style="margin-bottom: 0.5rem; font-weight: bold;">現在の画像:</p>
                        <img src="../../img/icon/<?= htmlspecialchars($current_profile['img']) ?>" alt="現在のアイコン">
                    </div>
                <?php endif; ?>
                <div class="file-input-wrapper">
                    <input type="file" name="icon_img" accept="image/jpeg,image/jpg,image/png">
                    <p class="file-hint">
                        ※ JPG、PNG形式 / 最大5MB / 300×300px～3000×3000px<br>
                        新しい画像をアップロードすると承認後に反映されます
                    </p>
                </div>
            </div>
            
            <!-- 掲載画像 -->
<div class="image-section">
    <h3>🖼️ 掲載画像(最大10枚)</h3>
    <p style="margin-bottom: 1rem; color: #666;">各スロットに1枚ずつ画像を登録できます。削除したいスロットはチェックを入れてください。</p>
    <div class="panel-grid" id="panelGrid">
        <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="panel-slot <?= ($i > 0 && empty($panel_slots[$i-1])) ? 'hidden' : '' ?>" data-slot="<?= $i ?>" id="panel_slot_<?= $i ?>">
                <span class="panel-slot-number"><?= $i + 1 ?>枚目</span>
                
                <?php if (!empty($panel_slots[$i])): ?>
                    <img src="../../img/panels/<?= htmlspecialchars($panel_slots[$i]) ?>" alt="掲載画像<?= $i + 1 ?>" class="panel-preview" data-slot="<?= $i ?>">
                    <label class="delete-checkbox">
                        <input type="checkbox" name="delete_panel_<?= $i ?>" value="1" class="delete-checkbox-input" data-slot="<?= $i ?>">
                        削除する
                    </label>
                <?php else: ?>
                    <p style="color: #999; margin-bottom: 0.5rem;" class="no-image-text">画像なし</p>
                <?php endif; ?>
                
                <input type="file" name="panel_img_<?= $i ?>" accept="image/jpeg,image/jpg,image/png" style="font-size: 0.75rem; margin-top: 0.5rem;" class="panel-file-input" data-slot="<?= $i ?>">
            </div>
        <?php endfor; ?>
    </div>
    <p class="file-hint">
        ※ JPG、PNG形式 / 1枚最大10MB / 600×400px〜5000×5000px<br>
        既に画像があるスロットに新しい画像をアップロードすると承認後に反映されます
    </p>
</div>
            
            <!-- 一問一答 -->
            <?php if (!empty($ichimon_templates)): ?>
                <div class="ichimon-section">
                    <h3>💬 一問一答</h3>
                    <?php foreach ($ichimon_templates as $template): ?>
                        <div class="ichimon-item">
                            <div class="ichimon-question"><?= htmlspecialchars($template['question']) ?></div>
                            <input type="text" name="ichimon_<?= $template['id'] ?>" value="<?= getIchimonAnswer($template['id']) ?>" placeholder="回答を入力(任意)">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- ã‚ªãƒ—ã‚·ãƒ§ãƒ³ -->
            <?php if (!empty($all_options)): ?>
                <div class="form-group">
                    <label>可能オプション</label>
                    <div class="checkbox-group">
                        <?php foreach ($all_options as $option): ?>
                            <div class="checkbox-item">
                                <input 
                                    type="checkbox" 
                                    name="options[]" 
                                    value="<?= htmlspecialchars($option['option_name']) ?>"
                                    id="opt_<?= $option['id'] ?>"
                                    <?= isOptionChecked($option['option_name']) ? 'checked' : '' ?>
                                >
                                <label for="opt_<?= $option['id'] ?>"><?= htmlspecialchars($option['option_name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 属性（承認不要・即反映） -->
            <?php if (!empty($all_preference_options)): ?>
                <div class="form-group">
                    <label>属性・特徴 <span style="color: #10b981; font-size: 0.875rem;">(即座に反映されます)</span></label>
                    <div class="checkbox-group">
                        <?php foreach ($all_preference_options as $pref): ?>
                            <div class="checkbox-item">
                                <input 
                                    type="checkbox" 
                                    name="attributes[]" 
                                    value="<?= htmlspecialchars($pref['options']) ?>"
                                    id="attr_<?= $pref['id'] ?>"
                                    <?= isAttributeChecked($pref['options']) ? 'checked' : '' ?>
                                >
                                <label for="attr_<?= $pref['id'] ?>"><?= htmlspecialchars($pref['options']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- ãƒœã‚¿ãƒ³ -->
            <div class="button-group">
                <button type="submit" class="btn btn-primary">承認申請を送信</button>
                <a href="cast_profile.php" class="btn btn-secondary">キャンセル</a>
            </div>
        </form>
    </div>
    <?php require_once(dirname(__FILE__) . '/footer.php'); ?>

    <script>
// 画像の段階表示制御
document.addEventListener('DOMContentLoaded', function() {
    const panelSlots = document.querySelectorAll('.panel-slot');
    const fileInputs = document.querySelectorAll('.panel-file-input');
    const deleteCheckboxes = document.querySelectorAll('.delete-checkbox-input');
    
    // ファイル選択時の処理
    fileInputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // プレビュー表示
                const reader = new FileReader();
                const slot = this.closest('.panel-slot');
                const existingImg = slot.querySelector('.panel-preview');
                const noImageText = slot.querySelector('.no-image-text');
                
                reader.onload = function(e) {
                    if (existingImg) {
                        existingImg.src = e.target.result;
                    } else {
                        if (noImageText) {
                            noImageText.remove();
                        }
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'panel-preview';
                        img.setAttribute('data-slot', index);
                        img.style.maxWidth = '100%';
                        img.style.maxHeight = '10rem';
                        img.style.borderRadius = '0.5rem';
                        img.style.marginBottom = '0.5rem';
                        img.style.objectFit = 'cover';
                        slot.insertBefore(img, input);
                    }
                    
                    // 次のスロットを表示
                    if (index < panelSlots.length - 1) {
                        panelSlots[index + 1].classList.remove('hidden');
                    }
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // 削除チェックボックスの処理
    deleteCheckboxes.forEach((checkbox, index) => {
        checkbox.addEventListener('change', function() {
            const slot = this.closest('.panel-slot');
            const img = slot.querySelector('.panel-preview');
            
            if (this.checked && img) {
                img.style.opacity = '0.3';
            } else if (img) {
                img.style.opacity = '1';
            }
        });
    });
    
    // 初期状態で画像があるかチェック
    panelSlots.forEach((slot, index) => {
        const hasImage = slot.querySelector('.panel-preview') !== null;
        if (hasImage && index < panelSlots.length - 1) {
            panelSlots[index + 1].classList.remove('hidden');
        }
    });
});
</script>
</body>
</html>