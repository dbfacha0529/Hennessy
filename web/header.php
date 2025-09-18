<?php
session_start();
$login_id = $_SESSION['user']['login_id'] ?? null;
?>
<header class="site-header">
  <div class="container">
    <div class="left-group">
      <a href="index.php" class="logo"><img src="../img/Hennessylogo.jpg" class="logoimg"></a>
      <h1>Membername: <?= htmlspecialchars($_SESSION['USER']['user_name'] ?? '') ?>様<br>
           ご利用可能ポイント</h1><!--TODO-->
    </div>

    <div class="right-group">
      <button class="menu-toggle"><i class="bi bi-list"></i></button>
      <nav class="nav">
        <ul class="nav-list"><!--TODO-->
          <li><a href="index.php">ホーム</a></li>
          <li><a href="about.php">当店について</a></li>
          <li><a href="contact.php">お問い合わせ</a></li>
          <?php if ($login_id): ?>
            <li><a href="logout.php">ログアウト</a></li>
          <?php else: ?>
            <li><a href="login.php">ログイン</a></li>
            <li><a href="signup.php">新規登録</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </div>
  <!-- Bootstrap Icons 読み込み -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Bootstrap 本体 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- オリジナルCSS -->
  <link href="./css/header.css" rel="stylesheet">
</header>