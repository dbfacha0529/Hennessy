<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <!--cssリンク-->
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
  <!--オリジナルCSS-->
  <link href="./css/complete.css" rel="stylesheet">
</head>

<body>


  <!--カード-->
  <div class="card">
    <div class="card-text">
      <i class="bi bi-check"></i>
      <h2 class="card-title">再登録が完了しました</h2>
    </div>
  </div>

  <!--カード-->
  <div class="card">
    <div class="card-text">
      <h3>ユーザーID:<?= $_SESSION['USER_DATE']["login_id"] ?></h3>
      <h3>ニックネーム:<?= $_SESSION['USER_DATE']["user_name"] ?></h3>
      <h3>TEL:<?= $_SESSION['USER_DATE']["tel"] ?></h3>
      <h3>PASSWORD:<?= $_SESSION['USER_DATE']["password"] ?></h3>

    </div>
  </div>

  <!-- TOPへ戻る -->
  <a type="button" class="btn btn-back" href="index.php">TOPへ戻る</a>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>

     <img src="../img/Hennessy.jpg">
</body>

</html>