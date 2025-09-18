<?php
require_once(dirname(__FILE__) . "/../functions.php");

session_start();
$err = [];

if (!isset($_SESSION['user'])) {
    // 前画面を飛ばして直接来た場合のガード
    header("Location: forgot.php");
    exit;
}

// DB接続
$pdo = dbConnect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id   = trim($_POST['login_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $passwordre = $_POST['passwordre'] ?? '';

    // バリデーション
    if ($login_id === '') {
        $err['login_id'] = 'ログインIDを入力してください';
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

    if (empty($_POST['agree_terms'])) {
        $err['agree_terms'] = '利用規約に同意してください';
    }
    if (empty($_POST['age_check'])) {
        $err['age_check'] = '18歳以上である必要があります';
    }

 if (empty($err)) {
    // login_id が他のユーザーと重複していないかチェック
    $sql = "SELECT id FROM users WHERE login_id = ? AND id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id, $_SESSION['user']['id']]);
    $exists = $stmt->fetch();

    if ($exists) {
        $err['login_id'] = 'このログインIDはすでに使用されています';
    }
}

if (empty($err)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // UPDATE実行
    $sql = 'UPDATE users SET login_id = ?, password = ? WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$login_id, $hashed_password, $_SESSION['user']['id']]);



        // セッションも更新
        $_SESSION['USER_DATE']['login_id'] = $login_id;
        $_SESSION['USER_DATE']['password'] = $password;

        header('Location: resuccess.php'); // 完了画面や SMS認証など
        exit;
    }
}
?>


<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>新規登録</title>
  <!--cssリンク-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
  <!--オリジナルCSS-->
  <link href="./css/sign_up.css" rel="stylesheet">
</head>

<body>

  <form method="post">
    <?php if (isset($err['common'])): ?>
  <div class="alert alert-danger"><?= $err['common'] ?></div>
<?php endif; ?>
<h1>再登録画面</h1>


    <!-- ID -->
     <h2>[1]User_ID:お好きな文字列をご入力ください<br>(英数字6文字以上)</h2>
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['login_id']))
        echo 'is-invalid'; ?>" id="login_id" name="login_id" value="<?= $login_id ?>" placeholder="ID">
      <?php if (isset($err['login_id'])): ?>
        <div class="invalid-feedback"><?= $err['login_id'] ?></div>
      <?php endif; ?>
    </div>

    <!-- Pass word -->
     <h2>[4]Password登録:パスワードをご入力ください</h2>
    <div class="mb-2">
      <input type="password" class="form-control <?php if (isset($err['password']))
        echo 'is-invalid'; ?>" id="password" name="password" value="<?= $password ?>" placeholder="password">
      <?php if (isset($err['password'])): ?>
        <div class="invalid-feedback"><?= $err['password'] ?></div>
      <?php endif; ?>
    </div>
    <!-- Pass word re -->
    <div class="mb-3">
      <input type="password" class="form-control <?php if (isset($err['passwordre']))
        echo 'is-invalid'; ?>" id="passwordre" name="passwordre" value="<?= $passwordre ?>" placeholder="password">
      <?php if (isset($err['passwordre'])): ?>
        <div class="invalid-feedback"><?= $err['passwordre'] ?></div>
      <?php endif; ?>
    </div>

<!-- 利用規約 -->
 <a href="riyoukiyaku.php">利用規約</a>
<div class="form-check mb-2">
  <input class="form-check-input <?php if(isset($err['agree_terms'])) echo 'is-invalid'; ?>" 
         type="checkbox" id="agree_terms" name="agree_terms" value="1" 
         <?php if(!empty($_POST['agree_terms'])) echo 'checked'; ?>>
  <label class="form-check-label" for="agree_terms">利用規約に同意します</label>
  <?php if (isset($err['agree_terms'])): ?>
    <div class="invalid-feedback d-block"><?= $err['agree_terms'] ?></div>
  <?php endif; ?>
</div>

<!-- 18歳確認 -->
<div class="form-check mb-3">
  <input class="form-check-input <?php if(isset($err['age_check'])) echo 'is-invalid'; ?>" 
         type="checkbox" id="age_check" name="age_check" value="1"
         <?php if(!empty($_POST['age_check'])) echo 'checked'; ?>>
  <label class="form-check-label" for="age_check">私は18才以上です</label>
  <?php if (isset($err['age_check'])): ?>
    <div class="invalid-feedback d-block"><?= $err['age_check'] ?></div>
  <?php endif; ?>
</div>

    <!-- 登録 -->
    <button type="submit" class="btn btn-Login">再登録する</button>
    <h3>SMS認証に進みます</h3>
    <!-- TOPへ戻る -->
    <a type="button" class="btn btn-back" href="index.php">TOPへ戻る</a>
  </form>
  <img src="../img/Hennessy.jpg">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
</body>

</html>