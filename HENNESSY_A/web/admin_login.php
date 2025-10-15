<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = []; // 初期化

try {
  // DB接続
  $pdo = dbConnect();

  if (isset($_SESSION['admin_logged_in'])) {
    // ログイン済みなら管理画面へ
    header('Location: admin_home.php');
    unset($pdo);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_POST['admin_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$admin_id) {
      $err['admin_id'] = '管理者IDを入力してください';
    }
    if (!$password) {
      $err['password'] = 'パスワードを入力してください';
    }

    if (empty($err)) {
      // 管理者テーブルから認証（仮のテーブル名：admin_users）
      $sql = "SELECT * FROM admin_users WHERE admin_id = :admin_id LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':admin_id', $admin_id, PDO::PARAM_STR);
      $stmt->execute();
      $admin = $stmt->fetch();

      if ($admin && password_verify($password, $admin['password'])) {
        // 管理者情報をセッションに保存
        $_SESSION['ADMIN'] = $admin;
        $_SESSION['admin_logged_in'] = true;
        
        header("Location: admin_home.php");
        unset($pdo);
        exit;
      } else {
        $err['common'] = '管理者ID またはパスワードが正しくありません';
      }
    }
  } else {
    $admin_id = "";
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
  <title>管理者ログイン - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
  <link href="./css/admin_login.css" rel="stylesheet">
  <style>
    /* チェックボックス付き入力欄のラッパー */
    .mb-3-with-checkbox {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      position: relative;
    }
    
    .mb-3-with-checkbox input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
      flex-shrink: 0;
      margin: 0;
      position: absolute;
      left: calc((100% - 70%) / 2 - 25px);
      top: 14px;
    }
    
    .mb-3-with-checkbox .mb-3 {
      width: 70%;
      margin: 0;
    }
    
    .mb-3-with-checkbox .form-control {
      width: 100%;
    }

    /* チェックボックスのスタイル */
    input[type="checkbox"] {
      appearance: none;
      -webkit-appearance: none;
      width: 20px;
      height: 20px;
      border: 2px solid #8a8a8a;
      border-radius: 3px;
      background-color: #4a4a4a;
      cursor: pointer;
      position: relative;
    }

    input[type="checkbox"]:checked {
      background-color: #d4af37;
      border-color: #d4af37;
    }

    input[type="checkbox"]:checked::after {
      content: '';
      position: absolute;
      left: 6px;
      top: 2px;
      width: 5px;
      height: 10px;
      border: solid #1a1a1a;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }
  </style>
</head>

<body>
  <div class="admin-badge">ADMINISTRATOR</div>
  
  <img src="../../img//logo/adminlogo.png" alt="Hennessy Logo">
  
  <div class="admin-title">Management System</div>
  
  <form method="post">
    <!--アラート表示-->
    <?php if (isset($err['common'])): ?>
      <div class="alert alert-danger" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16" style="margin-right: 8px;">
          <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
        </svg>
        <?= $err['common'] ?>
      </div>
    <?php endif; ?>

    <!-- 管理者ID -->
    <div class="mb-3-with-checkbox">
      <input type="checkbox" id="save_admin_id" title="次回から自動入力">
      <div class="mb-3">
        <input type="text" class="form-control <?php if (isset($err['admin_id'])) echo 'is-invalid'; ?>" 
               id="admin_id" name="admin_id" value="<?= $admin_id ?>" placeholder="ADMIN ID">
        <?php if (isset($err['admin_id'])): ?>
          <div class="invalid-feedback"><?= $err['admin_id'] ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- パスワード -->
    <div class="mb-3-with-checkbox">
      <input type="checkbox" id="save_password" title="次回から自動入力">
      <div class="mb-3">
        <input type="password" class="form-control <?php if (isset($err['password'])) echo 'is-invalid'; ?>" 
               id="password" name="password" placeholder="PASSWORD">
        <?php if (isset($err['password'])): ?>
          <div class="invalid-feedback"><?= $err['password'] ?></div>
        <?php endif; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-Login">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock-fill" viewBox="0 0 16 16" style="margin-right: 8px;">
        <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.159 7.159 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.541 1.541 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99A1.5 1.5 0 0 1 8 5z"/>
      </svg>
      Admin Login
    </button>

    <a type="button" class="btn btn-Back" href="../index.php">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16" style="margin-right: 8px;">
        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
      </svg>
      ユーザーページへ戻る
    </a>
  </form>

  <script>
    // ページ読み込み時の処理
    document.addEventListener('DOMContentLoaded', function() {
      const adminIdInput = document.getElementById('admin_id');
      const passwordInput = document.getElementById('password');
      const saveAdminIdCheckbox = document.getElementById('save_admin_id');
      const savePasswordCheckbox = document.getElementById('save_password');

      // 保存されている値を読み込み
      const savedAdminId = localStorage.getItem('saved_admin_id');
      const savedPassword = localStorage.getItem('saved_admin_password');

      // admin_idの復元
      if (savedAdminId) {
        adminIdInput.value = savedAdminId;
        saveAdminIdCheckbox.checked = true;
      }

      // passwordの復元
      if (savedPassword) {
        passwordInput.value = savedPassword;
        savePasswordCheckbox.checked = true;
      }

      // admin_idのチェックボックス変更時
      saveAdminIdCheckbox.addEventListener('change', function() {
        if (this.checked) {
          if (adminIdInput.value) {
            localStorage.setItem('saved_admin_id', adminIdInput.value);
          }
        } else {
          localStorage.removeItem('saved_admin_id');
        }
      });

      // admin_id入力時の処理
      adminIdInput.addEventListener('input', function() {
        if (saveAdminIdCheckbox.checked) {
          localStorage.setItem('saved_admin_id', this.value);
        }
      });

      // passwordのチェックボックス変更時
      savePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
          if (passwordInput.value) {
            localStorage.setItem('saved_admin_password', passwordInput.value);
          }
        } else {
          localStorage.removeItem('saved_admin_password');
        }
      });

      // password入力時の処理
      passwordInput.addEventListener('input', function() {
        if (savePasswordCheckbox.checked) {
          localStorage.setItem('saved_admin_password', this.value);
        }
      });
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
</body>

</html>