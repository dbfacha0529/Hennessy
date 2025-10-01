<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = []; // 初期化


try {

  // DB接続
  $pdo = dbConnect(); // 共通関数で接続

  if (isset($_SESSION['cklog'])) {
    //ログイン済みならHOME画面へ
    header('Location:home.php');//TODO
    unset($pdo);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_id = $_POST['login_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$login_id) {
      $err['login_id'] = 'IDを入力してください';
    }
    if (!$password) {
      $err['password'] = 'パスワードを入力してください';
    }

    if (empty($err)) {
      $sql = "SELECT * FROM users WHERE login_id = :login_id LIMIT 1";
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':login_id', $login_id, PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['password'])) {

        // ユーザー情報をセッションに保存
        $_SESSION['USER'] = $user;
        
        // === ランク情報を取得してセッションに保存 ===
        $tel = $user['tel'];
        
        // 1. 累計利用回数を取得
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reserve WHERE tel = :tel AND done >= 2");
        $stmt->execute([':tel' => $tel]);
        $usage_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // 2. 利用回数に応じたランク情報を取得
        $stmt = $pdo->prepare("
            SELECT * FROM rank 
            WHERE required_count <= :usage_count 
            ORDER BY required_count DESC 
            LIMIT 1
        ");
        $stmt->execute([':usage_count' => $usage_count]);
        $rank_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 3. 次のランク情報を取得
        $stmt = $pdo->prepare("
            SELECT * FROM rank 
            WHERE required_count > :usage_count 
            ORDER BY required_count ASC 
            LIMIT 1
        ");
        $stmt->execute([':usage_count' => $usage_count]);
        $next_rank_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 4. セッションにランク情報を保存
        $_SESSION['RANK'] = [
            'usage_count' => $usage_count,
            'current_rank' => $rank_info['rank_name'],
            'rank_id' => $rank_info['id'],
            'monthly_bonus' => $rank_info['monthly_bonus'],
            'next_rank' => $next_rank_info ? $next_rank_info['rank_name'] : null,
            'next_required_count' => $next_rank_info ? $next_rank_info['required_count'] : null,
            'next_rankup_bonus' => $next_rank_info ? $next_rank_info['rankup_bonus'] : null
        ];
        
        header("Location:home.php");
        unset($pdo);
        exit;
      } else {
        $err['common'] = '認証に失敗しました';
      }
    }
  } else {
    $login_id = "";
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
  <title>ログイン画面</title>
  <!--cssリンク-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
  <!--オリジナルCSS-->
  <link href="./css/login.css" rel="stylesheet">
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
      left: calc((100% - 70%) / 2 - 25px); /* 入力フォームの左側ぴったりに配置 */
      top: 14px; /* 入力フォームの上端から6pxの位置（入力欄の縦中央付近） */
    }
    
    /* 入力フォーム部分は元のスタイルを維持 */
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
    border: 2px solid #adbdb5;
    border-radius: 3px;
    background-color: #adbdb5;
    cursor: pointer;
    position: relative;
}

/* チェック時のボックスの色 */
input[type="checkbox"]:checked {
    background-color: #0f6b3d;
    border-color: #0f6b3d;
}

/* チェックマーク自体の色 */
input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid #adbdb5;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

  </style>
</head>

<body>

  <img src="../img/Hennessy.jpg">
  <form method="post">

    <!--アラート表示-->
    <?php if (isset($err['common'])): ?>
      <div class="alert alert-danger" role="alert"><?= $err['common'] ?></div>
    <?php endif; ?>

    <!-- ID -->
    <div class="mb-3-with-checkbox">
      <input type="checkbox" id="save_login_id" title="次回から自動入力">
      <div class="mb-3">
        <input type="text" class="form-control <?php if (isset($err['login_id']))
          echo 'is-invalid'; ?>" id="login_id" name="login_id" value="<?= $login_id ?>" placeholder="ID">
        <?php if (isset($err['login_id'])): ?>
          <div class="invalid-feedback"><?= $err['login_id'] ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- パスワード -->
    <div class="mb-3-with-checkbox">
      <input type="checkbox" id="save_password" title="次回から自動入力">
      <div class="mb-3">
        <input type="password" class="form-control <?php if (isset($err['password']))
          echo 'is-invalid'; ?>" id="password" name="password" placeholder="PASSWORD">
        <?php if (isset($err['password'])): ?>
          <div class="invalid-feedback"><?= $err['password'] ?></div>
        <?php endif; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-Login">Log in</button>

    <a type="button" class="btn btn-Create" href="sign_up.php">新規登録</a>

    <a type="button" class="btn btn-Forgot" href="forgot.php">お忘れの方</a>

  </form>

  <script>
    // ページ読み込み時の処理
    document.addEventListener('DOMContentLoaded', function() {
      const loginIdInput = document.getElementById('login_id');
      const passwordInput = document.getElementById('password');
      const saveLoginIdCheckbox = document.getElementById('save_login_id');
      const savePasswordCheckbox = document.getElementById('save_password');

      // 保存されている値を読み込み
      const savedLoginId = localStorage.getItem('saved_login_id');
      const savedPassword = localStorage.getItem('saved_password');

      // login_idの復元
      if (savedLoginId) {
        loginIdInput.value = savedLoginId;
        saveLoginIdCheckbox.checked = true;
      }

      // passwordの復元
      if (savedPassword) {
        passwordInput.value = savedPassword;
        savePasswordCheckbox.checked = true;
      }

      // login_idのチェックボックス変更時
      saveLoginIdCheckbox.addEventListener('change', function() {
        if (this.checked) {
          // チェックON: 現在の値を保存
          if (loginIdInput.value) {
            localStorage.setItem('saved_login_id', loginIdInput.value);
          }
        } else {
          // チェックOFF: 保存を削除
          localStorage.removeItem('saved_login_id');
        }
      });

      // login_id入力時の処理（チェックがONなら保存）
      loginIdInput.addEventListener('input', function() {
        if (saveLoginIdCheckbox.checked) {
          localStorage.setItem('saved_login_id', this.value);
        }
      });

      // passwordのチェックボックス変更時
      savePasswordCheckbox.addEventListener('change', function() {
        if (this.checked) {
          // チェックON: 現在の値を保存
          if (passwordInput.value) {
            localStorage.setItem('saved_password', passwordInput.value);
          }
        } else {
          // チェックOFF: 保存を削除
          localStorage.removeItem('saved_password');
        }
      });

      // password入力時の処理（チェックがONなら保存）
      passwordInput.addEventListener('input', function() {
        if (savePasswordCheckbox.checked) {
          localStorage.setItem('saved_password', this.value);
        }
      });
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
</body>

</html>