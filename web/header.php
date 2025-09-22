<?php
session_start();
include '../functions.php';

$login_id = $_SESSION['USER']['login_id'] ?? null;
$available_point = '-';
$point_err = '';

if (isset($_SESSION['USER']['tel']) && $_SESSION['USER']['tel'] !== '') {
    $tel = $_SESSION['USER']['tel'];

    try {
        // DB接続（dbConnect() は既に用意されている前提）
        $pdo = dbConnect();

        // tel で最新のポイントを取得
        $sql = "SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
        $stmt->execute();
        $point = $stmt->fetchColumn();

        if ($point !== false) {
            $available_point = (int)$point;
        } else {
            $available_point = 0; // データなしの場合は0表示
        }
    } catch (PDOException $e) {
        $point_err = 'ポイント情報の取得中にエラーが発生しました';
    }
} else {
    $point_err = 'ログイン情報が取得できません';
}
?>
<header class="site-header">
  <div class="container">
    <div class="left-group">
      <a href="index.php" class="logo"><img src="../img/Hennessylogo.jpg" class="logoimg"></a>
      <h1>
        Membername: <?= htmlspecialchars($_SESSION['USER']['user_name'] ?? '') ?>様<br>
        ご利用可能ポイント: 
        <?php
            if ($point_err) {
                echo '<span style="color:red;">' . htmlspecialchars($point_err) . '</span>';
            } else {
                echo htmlspecialchars($available_point);
            }
        ?>
      </h1>
    </div>

    <div class="right-group">
      <button class="menu-toggle"><i class="bi bi-list"></i></button>
      <nav class="nav">
        <ul class="nav-list">
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
