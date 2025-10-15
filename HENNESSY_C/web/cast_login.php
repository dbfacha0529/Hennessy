<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = [];

try {
  $pdo = dbConnect();

  if (isset($_SESSION['cast_login'])) {
    header('Location:home.php');
    unset($pdo);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $c_login_id = $_POST['c_login_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$c_login_id) {
      $err['c_login_id'] = 'IDを入力してください';
    }
    if (!$password) {
      $err['password'] = 'パスワードを入力してください';
    }

    if (empty($err)) {
      $sql = "SELECT * FROM girl WHERE c_login_id = :c_login_id LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':c_login_id', $c_login_id, PDO::PARAM_STR);
      $stmt->execute();
      $cast = $stmt->fetch();

      if ($cast && $password === $cast['g_password']) {
        $_SESSION['CAST'] = $cast;
        $_SESSION['cast_login'] = true;
        
        header("Location:home.php");
        unset($pdo);
        exit;
      } else {
        $err['common'] = '認証に失敗しました';
      }
    }
  } else {
    $c_login_id = "";
    $password = "";
  }
} catch (Exception $e) {
  echo ("DBエラー");
  unset($pdo);
  exit;
}
unset($pdo);
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>キャストログイン</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
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

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 1.25rem;
      box-shadow: 0 0.625rem 2.5rem rgba(255, 182, 193, 0.3);
      width: 100%;
      max-width: 28.125rem;
      padding: 2.5rem;
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

    .btn-login {
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

    .btn-login:hover {
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

    .mb-3-with-checkbox {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      gap: 0.625rem;
      width: 100%;
      position: relative;
    }
    
    .mb-3-with-checkbox input[type="checkbox"] {
      width: 1.25rem;
      height: 1.25rem;
      cursor: pointer;
      flex-shrink: 0;
      margin: 0;
      position: absolute;
      left: calc((100% - 70%) / 2 - 1.875rem);
      top: 2.5rem;
      accent-color: #ff69b4;
    }
    
    .mb-3-with-checkbox .mb-3 {
      width: 70%;
      margin: 0;
    }
    
    .mb-3-with-checkbox .form-control {
      width: 100%;
    }

    @media (max-width: 48rem) {
      .login-container {
        padding: 2rem;
      }

      .logo-section h1 {
        font-size: 1.625rem;
      }

      .form-control {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }

      .btn-login {
        font-size: 1rem;
        padding: 0.75rem;
      }
    }

    @media (max-width: 30rem) {
      body {
        padding: 1rem;
      }

      .login-container {
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

      .btn-login {
        font-size: 0.9375rem;
        padding: 0.625rem;
      }

      .links-section a {
        font-size: 0.875rem;
      }
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="logo-section">
      <h1>🌸 CAST LOGIN 🌸</h1>
      <p>キャスト専用ログイン</p>
    </div>

    <?php if (isset($err['common'])): ?>
      <div class="alert">
        <?= htmlspecialchars($err['common']) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <!-- ログインID -->
      <div class="mb-3-with-checkbox">
        <input type="checkbox" id="save_login_id" name="save_login_id">
        <div class="mb-3">
          <label for="c_login_id" class="form-label">ログインID</label>
          <input 
            type="text" 
            class="form-control <?php if (isset($err['c_login_id'])) echo 'is-invalid'; ?>" 
            id="c_login_id" 
            name="c_login_id" 
            value="<?= htmlspecialchars($c_login_id ?? '') ?>"
            placeholder="ログインIDを入力"
          >
          <?php if (isset($err['c_login_id'])): ?>
            <div class="invalid-feedback">
              <?= htmlspecialchars($err['c_login_id']) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- パスワード -->
      <div class="mb-3-with-checkbox">
        <input type="checkbox" id="save_password" name="save_password">
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
      </div>

      <button type="submit" class="btn-login">ログイン</button>
    </form>

    <div class="links-section">
      <a href="cast_forgot.php">ログイン情報をお忘れの方</a>
      <span style="margin: 0 0.625rem; color: #ffb6c1;">|</span>
      <a href="cast_signup.php">新規登録はこちら</a>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const loginIdInput = document.getElementById('c_login_id');
      const passwordInput = document.getElementById('password');
      const saveLoginIdCheckbox = document.getElementById('save_login_id');
      const savePasswordCheckbox = document.getElementById('save_password');

      // ページ読み込み時に保存された値があれば復元
      const savedLoginId = localStorage.getItem('cast_saved_login_id');
      const savedPassword = localStorage.getItem('cast_saved_password');

      if (savedLoginId) {
        loginIdInput.value = savedLoginId;
        saveLoginIdCheckbox.checked = true;
      }

      if (savedPassword) {
        passwordInput.value = savedPassword;
        savePasswordCheckbox.checked = true;
      }

      // login_idのチェックボックス変更時
      saveLoginIdCheckbox.addEventListener('change', function() {
        if (this.checked) {
          if (loginIdInput.value) {
            localStorage.setItem('cast_saved_login_id', loginIdInput.value);
          }
        } else {
          localStorage.removeItem('cast_saved_login_id');
        }
      });

      // login_id入力時の処理
      loginIdInput.addEventListener('input', function() {
        if (saveLoginIdCheckbox.checked) {
          localStorage.setItem('cast_saved_login_id', this.value);
        }
      });

      // passwordのチェックボックス変更時
      savePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
          if (passwordInput.value) {
            localStorage.setItem('cast_saved_password', passwordInput.value);
          }
        } else {
          localStorage.removeItem('cast_saved_password');
        }
      });

      // password入力時の処理
      passwordInput.addEventListener('input', function() {
        if (savePasswordCheckbox.checked) {
          localStorage.setItem('cast_saved_password', this.value);
        }
      });
    });
  </script>
</body>

</html>