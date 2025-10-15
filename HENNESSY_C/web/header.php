<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= CSS_PATH ?>common.css" rel="stylesheet">
</head>
<body>
<?php
// DB接続 - 絶対パスで読み込み
require_once($_SERVER['DOCUMENT_ROOT'] . '/HENNESSY/HENNESSY_C/functions.php');
$pdo = dbConnect();

// キャスト情報取得
$cast_name = isset($_SESSION['CAST']['name']) ? $_SESSION['CAST']['name'] : 'ゲスト'; 

// ショップ情報取得（ロゴ）
$sql = "SELECT logo_cast FROM shop LIMIT 1";
$stmt = $pdo->query($sql);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);
$logo_filename = $shop['logo_cast'] ?? 'default_logo.png';
?>
<style>
  .cast-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background-color: #ff69b4;
    height: 8vh;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    box-shadow: 0 0.125rem 0.5rem rgba(255, 105, 180, 0.3);
    z-index: 1000;
  }

  .cast-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
  }

  .cast-header-logo {
    height: 8vh;
    max-width: 10rem;
    object-fit: contain;
    flex-shrink: 0;
  }

  .cast-header-name {
    color: white;
    font-size: 1rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .cast-header-right {
    flex-shrink: 0;
  }

  .cast-menu-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.75rem;
    cursor: pointer;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s ease;
  }

  .cast-menu-toggle:hover {
    transform: scale(1.1);
  }

  .cast-menu-toggle:active {
    transform: scale(0.95);
  }

  .cast-nav {
    position: relative;
  }

  .cast-nav-list {
    list-style: none;
    display: none;
    flex-direction: column;
    margin: 0;
    padding: 0;
    background-color: #ff69b4;
    position: fixed;
    right: 0;
    top: 8vh;
    width: 15.625rem;
    max-width: 60vw;
    box-shadow: -0.125rem 0.125rem 0.5rem rgba(0, 0, 0, 0.2);
    max-height: calc(100vh - 8vh);
    overflow-y: auto;
  }

  .cast-nav-list.show {
    display: flex;
    animation: slideIn 0.3s ease;
  }

  @keyframes slideIn {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  .cast-nav-list li {
    margin: 0;
    border-bottom: 0.0625rem solid rgba(255, 255, 255, 0.3);
  }

  .cast-nav-list li:last-child {
    border-bottom: none;
  }

  .cast-nav-list li a {
    color: white;
    text-decoration: none;
    display: block;
    padding: 0.9375rem 1.25rem;
    font-size: 1rem;
    transition: background-color 0.2s ease;
  }

  .cast-nav-list li a:hover {
    background-color: rgba(255, 255, 255, 0.2);
  }

  /* ヘッダー分のマージン確保 */
  body {
    padding-top: 8vh;
  }

  /* スマホ対応 (480px以下) */
  @media (max-width: 30rem) {
    .cast-header {
      padding: 0 1rem;
    }

    .cast-header-left {
      gap: 0.5rem;
    }

    .cast-header-name {
      font-size: 0.875rem;
    }

    .cast-menu-toggle {
      font-size: 1.5rem;
      padding: 0.375rem;
    }
  }

  /* タブレット対応 (768px以下) */
  @media (max-width: 48rem) {
    .cast-header-name {
      font-size: 0.9375rem;
    }
  }
</style>

<header class="cast-header">
  <!-- 左側: ロゴ + キャスト名 -->
  <div class="cast-header-left">
    <img src="<?= IMG_LOGO_PATH . htmlspecialchars($logo_filename) ?>" alt="店舗ロゴ" class="cast-header-logo">
    <div class="cast-header-name">
      <?= htmlspecialchars($cast_name) ?>さん
    </div>
  </div>

  <!-- 右側: ハンバーガーメニュー -->
  <div class="cast-header-right">
    <button class="cast-menu-toggle">☰</button>
    <nav class="cast-nav">
      <ul class="cast-nav-list">
        <li><a href="<?= WEB_URL ?>home.php">ホーム</a></li>
        <li><a href="<?= WEB_URL ?>cast_profile_edit.php">プロフィール編集</a></li>
        <li><a href="<?= TIMELINE_URL ?>cast_timeline.php">タイムライン</a></li>
        <li><a href="<?= WEB_URL ?>cast_chat_list.php">チャット</a></li>
        <li><a href="#">予約一覧</a></li>
        <li><a href="<?= WEB_URL ?>cast_logout.php">ログアウト</a></li>
      </ul>
    </nav>
  </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.querySelector('.cast-menu-toggle');
  const navList = document.querySelector('.cast-nav-list');

  menuToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    navList.classList.toggle('show');
  });

  // メニュー外クリックで閉じる
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.cast-nav') && !e.target.closest('.cast-menu-toggle')) {
      navList.classList.remove('show');
    }
  });

  // メニュー内のリンククリックで閉じる
  navList.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', function() {
      navList.classList.remove('show');
    });
  });
});
</script>