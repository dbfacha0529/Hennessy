<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();
$err = []; // 初期化

$login_id   = '';
$user_name  = '';
$tel        = '';
$password   = '';
$passwordre = '';



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
    $user_name = $_POST['user_name'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordre = $_POST['passwordre'] ?? '';

    if (!$login_id) {
      $err['login_id'] = 'IDを入力してください';
    }
    
    if (!$user_name) {
      $err['user_name'] = 'ニックネームを入力してください';
    }
    if (!$tel) {
      $err['tel'] = 'telを入力してください';
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

      // チェックボックスのバリデーション
if (empty($_POST['agree_terms'])) {
    $err['agree_terms'] = '利用規約に同意してください';
}
if (empty($_POST['age_check'])) {
    $err['age_check'] = '18歳未満の方はご利用いただけません';
}
    if (empty($err)) {
      //重複チェック
if (empty($err)) {
    $checkSql = 'SELECT login_id, tel FROM users WHERE login_id = ? OR tel = ? LIMIT 1';
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$login_id, $tel]);
    $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($row['login_id'] === $login_id) {
            $err['login_id'] = 'このIDはすでに使用されています';
        }
        if ($row['tel'] === $tel) {
            $err['tel'] = 'この番号はすでに使用されています';
        }
    }
}
    $sql = 'INSERT INTO users (login_id,user_name,tel,password) VALUES (?,?,?,?)';
if (empty($err)) {
   // ユーザーデータを配列に入れる 
    $arr =[];
    $arr[] = $login_id;
    $arr[] = $user_name;
    $arr[] = $tel;
    $arr[] = password_hash($password,PASSWORD_DEFAULT);

    try{
      $stmt = $pdo->prepare($sql);
      $stmt->execute($arr);


  //各種入力値をセッション変数に保存する!
    $_SESSION['USER_DATE']['login_id'] = $login_id;
    $_SESSION['USER_DATE']['user_name'] = $user_name;
    $_SESSION['USER_DATE']['tel'] = $tel;
    $_SESSION['USER_DATE']['password'] = $password;


header('Location: complete.php'); // 成功したら完了画面へ
            exit;
    
    
        } catch(\Exception $e) {
            $err['common'] = "登録に失敗しました";
        }
      }
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
<h1>新規登録画面</h1>


    <!-- ID -->
     <h2>[1]User_ID:お好きな文字列をご入力ください<br>(英数字6文字以上)</h2>
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['login_id']))
        echo 'is-invalid'; ?>" id="login_id" name="login_id" value="<?= $login_id ?>" placeholder="ID">
      <?php if (isset($err['login_id'])): ?>
        <div class="invalid-feedback"><?= $err['login_id'] ?></div>
      <?php endif; ?>
    </div>
    <!-- User_Name -->
     <h2>[2]User_NAME登録:ニックネームをご入力ください</h2>
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['user_name']))
        echo 'is-invalid'; ?>" id="user_name" name="user_name" value="<?= $user_name ?>" placeholder="user_name">
      <?php if (isset($err['user_name'])): ?>
        <div class="invalid-feedback"><?= $err['user_name'] ?></div>
      <?php endif; ?>
    </div>
    <!-- Tel -->
     <h2>[3]電話番号登録:電話番号をご入力ください<br>  (ハイフンは不要です)<br>  (認証に使用いたしますので<br>携帯電話でのご登録をお願いします)</h2>
    <div class="mb-3">
      <input type="text" class="form-control <?php if (isset($err['tel']))
        echo 'is-invalid'; ?>" id="tel" name="tel" value="<?= $tel ?>" placeholder="tel">
      <?php if (isset($err['tel'])): ?>
        <div class="invalid-feedback"><?= $err['tel'] ?></div>
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
        echo 'is-invalid'; ?>" id="passwordre" name="passwordre" value="<?= $passwordre ?>" placeholder="passwordre">
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
    <button type="submit" class="btn btn-Login">登録する</button>
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