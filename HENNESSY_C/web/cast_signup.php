<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = [];

$c_login_id = '';
$name = '';
$tel = '';
$password = '';
$passwordre = '';

$pdo = dbConnect();

if (isset($_SESSION['cast_login'])) {
  header('Location:home.php');
  unset($pdo);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $c_login_id = $_POST['c_login_id'] ?? '';
  $name = $_POST['name'] ?? '';
  $tel = $_POST['tel'] ?? '';
  $password = $_POST['password'] ?? '';
  $passwordre = $_POST['passwordre'] ?? '';

  if (!$c_login_id) {
    $err['c_login_id'] = 'ログインIDを入力してください';
  }
  
  if (!$name) {
    $err['name'] = '掲載名を入力してください';
  }
  
  if (!$tel) {
    $err['tel'] = '電話番号を入力してください';
  } elseif (!preg_match('/^\d{11}$/', $tel)) {
    $err['tel'] = '電話番号は11桁の数字で入力してください';
  }
  
  if (!$password) {
    $err['password'] = 'パスワードを入力してください';
  }
  
  if (!$passwordre) {
    $err['passwordre'] = '確認パスワードを入力してください';
  }
  
  if ($password !== $passwordre) {
    $err['passwordre'] = '確認用パスワードが一致しません';
  }

  if (empty($err)) {
    $checkSql = 'SELECT c_login_id, tel, name FROM girl WHERE c_login_id = ? OR tel = ? OR name = ? LIMIT 1';
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$c_login_id, $tel, $name]);
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      if ($row['c_login_id'] === $c_login_id) {
        $err['c_login_id'] = 'このIDはすでに使用されています';
      }
      if ($row['tel'] === $tel) {
        $err['tel'] = 'この電話番号はすでに使用されています';
      }
      if ($row['name'] === $name) {
        $err['name'] = 'この掲載名はすでに使用されています';
      }
    }
  }

  if (empty($err)) {
    $sql = 'INSERT INTO girl (c_login_id, name, tel, g_password) VALUES (?, ?, ?, ?)';
    
    $arr = [];
    $arr[] = $c_login_id;
    $arr[] = $name;
    $arr[] = $tel;
    $arr[] = $password;

    try {
      $stmt = $pdo->prepare($sql);
      $stmt->execute($arr);

      // セッションに登録情報を保存
      $_SESSION['signup_success'] = true;
      $_SESSION['signup_data'] = [
        'c_login_id' => $c_login_id,
        'name' => $name,
        'tel' => $tel
      ];
      
      // デバッグ: セッションに保存されたか確認
      error_log("Session data saved: " . print_r($_SESSION['signup_data'], true));
      
      header('Location: cast_signup_success.php');
      exit;
    } catch (Exception $e) {
      $err['common'] = '登録に失敗しました: ' . $e->getMessage();
    }
  }
}

unset($pdo);
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>キャスト新規登録</title>
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

    .signup-container {
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

    .btn-signup {
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

    .btn-signup:hover {
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

    .links-section {
      margin-top: 1.875rem;
      text-align: center;
      padding-top: 1.25rem;
      border-top: 0.0625rem solid #ffb6c1;
    }

    .links-section a {
      color: #ff69b4;
      text-decoration: none;
      font-size: 0.9375rem;
      transition: color 0.3s ease;
    }

    .links-section a:hover {
      color: #ff1493;
      text-decoration: underline;
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
      .signup-container {
        padding: 2rem;
      }

      .logo-section h1 {
        font-size: 1.625rem;
      }

      .form-control {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }

      .btn-signup {
        font-size: 1rem;
        padding: 0.75rem;
      }
    }

    @media (max-width: 30rem) {
      body {
        padding: 1rem;
      }

      .signup-container {
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

      .btn-signup {
        font-size: 0.9375rem;
        padding: 0.625rem;
      }

      .links-section a {
        font-size: 0.875rem;
      }

      .help-text {
        font-size: 0.75rem;
      }
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <div class="logo-section">
      <h1>🌸 CAST 新規登録 🌸</h1>
      <p>キャスト専用アカウント作成</p>
    </div>

    <?php if (isset($err['common'])): ?>
      <div class="alert">
        <?= htmlspecialchars($err['common']) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label for="c_login_id" class="form-label">ログインID</label>
        <input 
          type="text" 
          class="form-control <?php if (isset($err['c_login_id'])) echo 'is-invalid'; ?>" 
          id="c_login_id" 
          name="c_login_id" 
          value="<?= htmlspecialchars($c_login_id) ?>"
          placeholder="半角英数字6文字以上"
        >
        <div class="help-text">※ログイン時に使用するIDです</div>
        <?php if (isset($err['c_login_id'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['c_login_id']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="name" class="form-label">掲載名（源氏名）</label>
        <input 
          type="text" 
          class="form-control <?php if (isset($err['name'])) echo 'is-invalid'; ?>" 
          id="name" 
          name="name" 
          value="<?= htmlspecialchars($name) ?>"
          placeholder="掲載名を入力"
        >
        <div class="help-text">※お客様に表示される名前です</div>
        <?php if (isset($err['name'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['name']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="tel" class="form-label">電話番号</label>
        <input 
          type="tel" 
          class="form-control <?php if (isset($err['tel'])) echo 'is-invalid'; ?>" 
          id="tel" 
          name="tel" 
          value="<?= htmlspecialchars($tel) ?>"
          placeholder="09012345678"
          maxlength="11"
        >
        <div class="help-text">※ハイフンなし11桁で入力してください</div>
        <?php if (isset($err['tel'])): ?>
          <div class="invalid-feedback">
            <?= htmlspecialchars($err['tel']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">パスワード</label>
        <input 
          type="password" 
          class="form-control <?php if (isset($err['password'])) echo 'is-invalid'; ?>" 
          id="password" 
          name="password"
          placeholder="パスワードを入力"
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

      <button type="submit" class="btn-signup">新規登録</button>
    </form>

    <div class="links-section">
      <a href="cast_login.php">ログイン画面に戻る</a>
    </div>
  </div>
</body>

</html>