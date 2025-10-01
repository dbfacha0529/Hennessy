<?php
session_start();
include dirname(__FILE__) . '/../functions.php';

$login_id = $_SESSION['USER']['login_id'] ?? null;
$available_point = '-';
$point_err = '';
$current_rank = '-';

if (isset($_SESSION['USER']['tel']) && $_SESSION['USER']['tel'] !== '') {
    $tel = $_SESSION['USER']['tel'];

    try {
        $pdo = dbConnect();

        $sql = "SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
        $stmt->execute();
        $point = $stmt->fetchColumn();

        if ($point !== false) {
            $available_point = (int)$point;
        } else {
            $available_point = 0;
        }
    } catch (PDOException $e) {
        $point_err = 'ポイント情報の取得中にエラーが発生しました';
    }
    
    // ランク情報を取得
    $current_rank = $_SESSION['RANK']['current_rank'] ?? '-';
} else {
    $point_err = 'ログイン情報が取得できません';
}

// 現在のディレクトリに応じてベースパスを設定
$current_dir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$base = ($current_dir === 'chat' || $current_dir === 'timeline') ? '../web/' : './';
$img_base = ($current_dir === 'chat' || $current_dir === 'timeline') ? '../img/' : '../img/';
?>
<header class="site-header">
  <div class="container">
    <div class="left-group">
      <a href="<?= $base ?>index.php" class="logo"><img src="<?= $img_base ?>Hennessylogo.jpg" class="logoimg"></a>
      <h1>
        Membername: <?= htmlspecialchars($_SESSION['USER']['user_name'] ?? '') ?>様<br>
        ランク: <span class="rank-badge-small"><?= htmlspecialchars($current_rank) ?></span> | 
        ポイント: 
        <?php
            if ($point_err) {
                echo '<span style="color:red;">' . htmlspecialchars($point_err) . '</span>';
            } else {
                echo htmlspecialchars($available_point) . 'P';
            }
        ?>
      </h1>
    </div>

    <div class="right-group">
      <button class="menu-toggle"><i class="bi bi-sliders"></i></i></button>
      <nav class="nav">
        <ul class="nav-list">
          <li><a href="<?= $base ?>index.php">ホーム</a></li>
          <li><a href="<?= $base ?>point_rank.php">ポイント・ランク</a></li>
          <li><a href="<?= $base ?>about.php">当店について</a></li>
          <li><a href="<?= $base ?>contact.php">お問い合わせ</a></li>
          <?php if ($login_id): ?>
            <li><a href="<?= $base ?>logout.php">ログアウト</a></li>
          <?php else: ?>
            <li><a href="<?= $base ?>login.php">ログイン</a></li>
            <li><a href="<?= $base ?>signup.php">新規登録</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </div>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= $base ?>css/header.css" rel="stylesheet">
  <script src="<?= $base ?>script.js"></script>
</header>