<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];
$action = $_POST['action'] ?? '';

$err = [];
$success = false;

// ユーザー名変更
if ($action === 'edit_name' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user_name = trim($_POST['new_user_name'] ?? '');

    if (empty($new_user_name)) {
        $err[] = 'ユーザー名を入力してください';
    }

    if (empty($err)) {
        $sql = "UPDATE users SET user_name = :user_name WHERE tel = :tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_name' => $new_user_name,
            ':tel' => $tel
        ]);

        // セッション更新
        $_SESSION['USER']['user_name'] = $new_user_name;
        $success = true;
        $success_message = 'ユーザー名を変更しました';
    }
}

// ログインID変更
if ($action === 'edit_login_id' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_login_id = trim($_POST['new_login_id'] ?? '');

    if (empty($new_login_id)) {
        $err[] = 'ログインIDを入力してください';
    } elseif (strlen($new_login_id) < 6) {
        $err[] = 'ログインIDは6文字以上で入力してください';
    }

    if (empty($err)) {
        // 重複チェック(自分以外)
        $sql = "SELECT id FROM users WHERE login_id = :login_id AND tel != :tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':login_id' => $new_login_id,
            ':tel' => $tel
        ]);
        
        if ($stmt->fetch()) {
            $err[] = 'このログインIDは既に使用されています';
        }
    }

    if (empty($err)) {
        $sql = "UPDATE users SET login_id = :login_id WHERE tel = :tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':login_id' => $new_login_id,
            ':tel' => $tel
        ]);

        // セッション更新
        $_SESSION['USER']['login_id'] = $new_login_id;
        $success = true;
        $success_message = 'ログインIDを変更しました';
    }
}

// パスワード変更
if ($action === 'edit_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';

    if (empty($current_password)) {
        $err[] = '現在のパスワードを入力してください';
    }
    if (empty($new_password)) {
        $err[] = '新しいパスワードを入力してください';
    }
    if (empty($new_password_confirm)) {
        $err[] = '新しいパスワード(確認)を入力してください';
    }
    if ($new_password !== $new_password_confirm) {
        $err[] = 'パスワードが一致しません';
    }

    if (empty($err)) {
        // 現在のパスワードを確認
        $sql = "SELECT password FROM users WHERE tel = :tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tel' => $tel]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $user['password'])) {
            $err[] = '現在のパスワードが正しくありません';
        }
    }

    if (empty($err)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = :password WHERE tel = :tel";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':password' => $hashed_password,
            ':tel' => $tel
        ]);

        $success = true;
        $success_message = 'パスワードを変更しました';
    }
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>設定変更</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .result-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .result-icon.success {
            color: #28a745;
        }

        .result-icon.error {
            color: #dc3545;
        }

        h1 {
            color: #003018;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .error-list {
            text-align: left;
            margin-bottom: 20px;
        }

        .btn-back {
            background-color: #003018;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            margin-top: 20px;
        }

        .btn-back:hover {
            background-color: #005030;
            color: white;
        }
    </style>
</head>
<body>

<div class="result-card">
    <?php if ($success): ?>
        <div class="result-icon success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1><?= htmlspecialchars($success_message) ?></h1>
        <p>変更が完了しました</p>
    <?php else: ?>
        <div class="result-icon error">
            <i class="bi bi-x-circle-fill"></i>
        </div>
        <h1>変更に失敗しました</h1>
        <?php if (!empty($err)): ?>
            <div class="error-list">
                <ul class="text-danger">
                    <?php foreach ($err as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <a href="account.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> アカウントページに戻る
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>