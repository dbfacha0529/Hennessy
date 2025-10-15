<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = [];

if (!isset($_SESSION['cast_reset'])) {
    header("Location: cast_forgot.php");
    exit;
}

$pdo = dbConnect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c_login_id   = trim($_POST['c_login_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $passwordre = $_POST['passwordre'] ?? '';

    if ($c_login_id === '') {
        $err['c_login_id'] = 'ログインIDを入力してください';
    }
    if ($password === '') {
        $err['password'] = 'パスワードを入力してください';
    }
    if ($passwordre === '') {
        $err['passwordre'] = '確認用パスワードを入力してください';
    }
    if ($password !== '' && $passwordre !== '' && $password !== $passwordre) {
        $err['passwordre'] = 'パスワードが一致しません';
    }

    if (empty($err)) {
        // c_login_id が他のキャストと重複していないかチェック
        $sql = "SELECT g_login_id FROM girl WHERE c_login_id = ? AND g_login_id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$c_login_id, $_SESSION['cast_reset']['g_login_id']]);
        $exists = $stmt->fetch();

        if ($exists) {
            $err['c_login_id'] = 'このログインIDはすでに使用されています';
        }
    }

    if (empty($err)) {
        $sql = 'UPDATE girl SET c_login_id = ?, g_password = ? WHERE g_login_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$c_login_id, $password, $_SESSION['cast_reset']['g_login_id']]);

        $_SESSION['reset_success'] = true;
        unset($_SESSION['cast_reset']);
        header('Location: cast_reset_success.php');
        exit;
    }
}
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>パスワード再設定</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
      background: linear-gradient(135deg, #ffe4e9 0%, #ffd4e0 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 1.25rem;
    }

    .reset-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 1.25rem;
      box-shadow: 0 0.625rem 2.5rem rgba(255, 182, 193, 0.3);
      width: 100%;
      max-width: 34.375rem;
      padding: 2.5rem;
      margin: 1.25rem auto;
    }

    .logo-section {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo-section h1 {
      color: #ff69b4;
      font-size: 1.875rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .logo-section p {
      color: #ff1493;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .form-label {
      color: #ff69b4;
      font-weight: 600;
      font-size: 0.9375rem;
      margin-bottom: 0.5rem;
    }

    .form-control {
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.625rem;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: #ff69b4;
      box-shadow: 0 0 0 0.25rem rgba(255, 105, 180, 0.1);
      outline: none;
    }

    .form-control.is-invalid {
      border-color: #dc3545;
    }

    .invalid-feedback {
      color: #dc3545;
      font-size: 0.875rem;
      margin-top: 0.25rem;
      display: block;
    }

    .btn-reset {
      background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
      border: none;
      color: white;
      font-weight: 600;
      font-size: 1.125rem;
      padding: 0.875rem;
      border-radius: 0.625rem;
      width: 100%;
      margin-top: 1.5rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-reset:hover {
      background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
      transform: translateY(-0.125rem);
      box-shadow: 0 0.375rem 1.25rem rgba(255, 105, 180, 0.3);
    }

    .alert {
      background: #ffe4e9;
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.625rem;
      color: #c71585;
      padding: 0.875rem;
      margin-bottom: 1.25rem;
      font-size: 0.9375rem;
    }

    .mb-3 {
      margin-bottom: 1.25rem;
    }

    .help-text {
      font-size: 0.8125rem;
      color: #ff69b4;
      margin-top: 0.25rem;
    }

    @media (max-width: 48rem) {
      .reset-container {
        padding: 2rem;
      }

      .logo-section h1 {
        font-size: 1.625rem;
      }

      .form-control {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }

      .btn-reset {
        font-size: 1rem;
        padding: 0.75rem;
      }
    }

    @media (max-width: 30rem) {
      body {
        padding: 1rem;
      }

      .reset-container {
        padding: 1.5rem;
      }

      .logo-section h1 {
        font-size: 1.5rem;
      }

      .logo-section p {
        font-size: 0.8125rem;
      }

      .form-label {
        font-size: 0.875rem;
      }

      .form-control {
        font-size: 0.875rem;
        padding: 0.625rem;
      }

      .btn-reset {
        font-size: 0.9375rem;
        padding: 0.625rem;
      }

      .help-text {
        font-size: 0.75rem;
      }
    }
  </style>
</head>

<body>
  <div class="reset-container">
    <div class="logo-section">
      <h1>🌸 パスワード再設定 🌸</h1>
      <p>新しいログイン情報を設定してください</p>
    </div>

    <?php if (isset($err['common'])): ?>
      <div class="alert">
        <?= htmlspecialchars($err['common']) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label for="c_login_id" class="form-label">新しいログインID</label>
        <input 
          type="text" 
          class="form-control <?php if (isset($err['c_login_id'])) echo 'is-invalid'; ?>" 
          id="c_login_id" 
          name="c_login_id" 
          value="<?= htmlspecialchars($c_login_id ?? '') ?>"
          placeholder="新しいログインIDを入力"
        >
        <div class="help-text">※半角英数字6文字以上</div>
        <?php if (isset($err['c_login_id'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['c_login_id']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">新しいパスワード</label>
        <input 
          type="password" 
          class="form-control <?php if (isset($err['password'])) echo 'is-invalid'; ?>" 
          id="password" 
          name="password"
          placeholder="新しいパスワードを入力"
        >
        <?php if (isset($err['password'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['password']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="passwordre" class="form-label">パスワード(確認)</label>
        <input 
          type="password" 
          class="form-control <?php if (isset($err['passwordre'])) echo 'is-invalid'; ?>" 
          id="passwordre" 
          name="passwordre"
          placeholder="もう一度パスワードを入力"
        >
        <?php if (isset($err['passwordre'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['passwordre']) ?>
          </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-reset">再設定する</button>
    </form>
  </div>
</body>

</html>