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
</head>

<body>

  <form method="post">
    <!-- ID -->
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['login_id']))
        echo 'is-invalid'; ?>" id="login_id" name="login_id" value="<?= $login_id ?>" placeholder="ID">
      <?php if (isset($err['login_id'])): ?>
        <div class="invalid-feedback"><?= $err['login_id'] ?></div>
      <?php endif; ?>
    </div>
    <!-- User_Name -->
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['user_name']))
        echo 'is-invalid'; ?>" id="user_name" name="user_name" value="<?= $user_name ?>" placeholder="user_name">
      <?php if (isset($err['user_name'])): ?>
        <div class="invalid-feedback"><?= $err['user_name'] ?></div>
      <?php endif; ?>
    </div>
    <!-- Tel -->
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['tel']))
        echo 'is-invalid'; ?>" id="tel" name="tel" value="<?= $tel ?>" placeholder="tel">
      <?php if (isset($err['tel'])): ?>
        <div class="invalid-feedback"><?= $err['tel'] ?></div>
      <?php endif; ?>
    </div>
    <!-- Pass word -->
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['password']))
        echo 'is-invalid'; ?>" id="password" name="password" value="<?= $password ?>" placeholder="password">
      <?php if (isset($err['password'])): ?>
        <div class="invalid-feedback"><?= $err['password'] ?></div>
      <?php endif; ?>
    </div>
    <!-- Pass word re -->
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['passwordre']))
        echo 'is-invalid'; ?>" id="passwordre" name="passwordre" value="<?= $passwordre ?>" placeholder="passwordre">
      <?php if (isset($err['passwordre'])): ?>
        <div class="invalid-feedback"><?= $err['passwordre'] ?></div>
      <?php endif; ?>
    </div>
    <!-- 登録 -->
    <button type="submit" class="btn btn-Login">登録する</button>
    <!-- TOPへ戻る -->
    <a type="button" class="btn btn-Create" href="sign_up.php">TOPへ戻る</a>
  </form>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
</body>

</html>