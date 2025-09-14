<?php
require_once(dirname(__FILE__) . '/../functions.php');

try {
  session_start();
  $err = []; // 初期化

  // DB接続
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASSWORD
  );
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

 if (isset($_SESSION['cklog'])) {
    //ログイン済みならHOME画面へ
    header('Location:home.php');
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

      if($user && password_verify($password, $user['password'])) {

      
        $_SESSION['USER'] = $user;
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
  echo("DBエラー");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
        <!--オリジナルCSS-->
    <link href = "./css/login.css" rel="stylesheet">
  </head>
  <body>

    <!--<img src="../img/Hennessy.jpg">-->
    <form method="post">

<!--アラート表示-->
    <?php if (isset($err['common'])): ?>
        <div class="alert alert-danger" role="alert"><?= $err['common'] ?></div>
      <?php endif; ?>

      <!-- ID -->
       <div class="mb-3">
 <input type="text" class="form-control <?php if (isset($err['login_id']))
          echo 'is-invalid'; ?>" id="login_id" name="login_id" value="<?= $login_id ?>" placeholder="ID">
        <?php if (isset($err['login_id'])): ?>
          <div class="invalid-feedback"><?= $err['login_id'] ?></div>
        <?php endif; ?>
      </div>

      <!-- パスワード -->
    <div class="mb-3">
        <input type="password" class="form-control <?php if (isset($err['password']))
          echo 'is-invalid'; ?>" id="password" name="password" placeholder="PASSWORD">
        <?php if (isset($err['password'])): ?>
          <div class="invalid-feedback"><?= $err['password'] ?></div>
        <?php endif; ?>
</div>

<button type="submit" class="btn btn-Login">Log in</button>

<a type="button" class="btn btn-Create" href="sign_up.php">新規登録</a>

<a type="button" class="btn btn-Forgot" href="forgot.php">お忘れの方</a>

</form>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
  </body>
</html>