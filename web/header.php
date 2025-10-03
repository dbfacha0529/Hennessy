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
<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hennessy</title>
<!-- CSS読み込み -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $base ?>css/common.css" rel="stylesheet">
<link href="<?= $base ?>css/header.css" rel="stylesheet">
</head>
<body class="has-header has-footer">


<header class="site-header">
  <div class="container">
    <div class="left-group">
      <a href="<?= $base ?>index.php" class="logo"><img src="<?= $img_base ?>Hennessylogo.jpg" class="logoimg"></a>
      <h1>
        <?= htmlspecialchars($_SESSION['USER']['user_name'] ?? '') ?>様<br>
         <span class="rank-badge-small <?= getRankClass($current_rank) ?>"><?= htmlspecialchars($current_rank) ?></span> 
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
      <button class="menu-toggle"><i class="bi bi-sliders"></i></button>
      <nav class="nav">
        <ul class="nav-list">
          <li><a href="<?= $base ?>home.php">ホーム</a></li>
          <li><a href="<?= $base ?>account.php">アカウント</a></li>
          <li><a href="<?= $base ?>point_rank.php">ポイント・ランク</a></li>
          <li><a href="<?= $base ?>riyoukiyaku.php">利用規約</a></li>
          <li><a href="<?= $base ?>contact.php">お問い合わせ</a></li>
          <li><a href="<?= $base ?>logout.php">ログアウト</a></li>
          
        </ul>
      </nav>
    </div>
  </div>
</header>

<script src="<?= $base ?>script.js"></script>