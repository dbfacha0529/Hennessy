<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['cast_login'])) {
  header('Location: cast_login.php');
  exit;
}

$cast = $_SESSION['CAST'];

require_once(dirname(__FILE__) . '/header.php');
?>



  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
      background: linear-gradient(135deg, #ffe4e9 0%, #ffd4e0 100%);
      min-height: 100vh;
      
    }

    .home-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 1.25rem;
      box-shadow: 0 0.625rem 2.5rem rgba(255, 182, 193, 0.3);
      width: 100%;
      max-width: 50rem;
      margin: 0 auto;
      padding: 2.5rem;
    }

    .header-section {
      text-align: center;
      margin-bottom: 2.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 0.125rem solid #ffb6c1;
    }

    .header-section h1 {
      color: #ff69b4;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .welcome-text {
      color: #ff1493;
      font-size: 1.125rem;
      font-weight: 500;
    }

    .cast-info {
      background: #fff5f7;
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border-left: 0.25rem solid #ff69b4;
    }

    .cast-info h2 {
      color: #ff69b4;
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .info-item {
      display: flex;
      padding: 0.75rem 0;
      border-bottom: 0.0625rem solid #ffe4e9;
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      color: #ff69b4;
      font-weight: 600;
      width: 10rem;
      flex-shrink: 0;
    }

    .info-value {
      color: #333;
      flex: 1;
    }

    .menu-section {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
      gap: 1.25rem;
      margin-top: 2rem;
    }

    .menu-card {
      background: white;
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.75rem;
      padding: 1.5rem;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
      text-decoration: none;
      display: block;
    }

    .menu-card:hover {
      background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
      border-color: #ff69b4;
      transform: translateY(-0.25rem);
      box-shadow: 0 0.5rem 1.25rem rgba(255, 105, 180, 0.2);
    }

    .menu-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }

    .menu-title {
      color: #ff69b4;
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    .menu-description {
      color: #666;
      font-size: 0.875rem;
    }

    .logout-section {
      margin-top: 2.5rem;
      padding-top: 1.5rem;
      border-top: 0.125rem solid #ffb6c1;
      text-align: center;
    }

    .btn-logout {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      border: none;
      color: white;
      font-weight: 600;
      font-size: 1rem;
      padding: 0.75rem 2rem;
      border-radius: 0.625rem;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-logout:hover {
      background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
      transform: translateY(-0.125rem);
      box-shadow: 0 0.375rem 1.25rem rgba(220, 53, 69, 0.3);
      color: white;
    }

    @media (max-width: 48rem) {
      .home-container {
        padding: 2rem;
      }

      .header-section h1 {
        font-size: 1.75rem;
      }

      .welcome-text {
        font-size: 1rem;
      }

      .menu-section {
        grid-template-columns: 1fr;
      }

      .info-label {
        width: 8rem;
      }
    }

    @media (max-width: 30rem) {
      
      .home-container {
        padding: 1.5rem;
      }

      .header-section h1 {
        font-size: 1.5rem;
      }

      .welcome-text {
        font-size: 0.9375rem;
      }

      .cast-info {
        padding: 1rem;
      }

      .info-label {
        width: 7rem;
        font-size: 0.875rem;
      }

      .info-value {
        font-size: 0.875rem;
      }

      .menu-icon {
        font-size: 2.5rem;
      }

      .menu-title {
        font-size: 1rem;
      }
    }
  </style>

  <div class="home-container">
    <div class="header-section">
      <h1>🌸 キャストホーム 🌸</h1>
      <p class="welcome-text">ようこそ、<?= htmlspecialchars($cast['name']) ?>さん</p>
    </div>

    <div class="cast-info">
      <h2>基本情報</h2>
      <div class="info-item">
        <div class="info-label">ログインID:</div>
        <div class="info-value"><?= htmlspecialchars($cast['c_login_id']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">掲載名:</div>
        <div class="info-value"><?= htmlspecialchars($cast['name']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">電話番号:</div>
        <div class="info-value"><?= htmlspecialchars($cast['tel']) ?></div>
      </div>
    </div>

    <div class="menu-section">
  <a href="cast_profile_edit.php" class="menu-card">
    <div class="menu-icon">✏️</div>
    <div class="menu-title">プロフィール編集</div>
    <div class="menu-description">プロフィール情報を編集</div>
  </a>
<a href="cast_shift_list.php" class="menu-card" >
    <div class="menu-icon">📊</div>
    <div class="menu-title">シフト管理</div>
    <div class="menu-description">シフトを確認・編集</div>
  </a>
  <a href="cast_schedule_list.php" class="menu-card">
    <div class="menu-icon">📅</div>
    <div class="menu-title">スケジュール</div>
    <div class="menu-description">シフトと予約を確認</div>
  </a>

  <a href="cast_reserve_list.php" class="menu-card" >
    <div class="menu-icon">📋</div>
    <div class="menu-title">予約一覧</div>
    <div class="menu-description">予約状況を確認</div>
  </a>
</div>

    <div class="logout-section">
      <a href="cast_logout.php" class="btn-logout">ログアウト</a>
    </div>
  </div>
  <?php require_once(dirname(__FILE__) . '/footer.php'); ?>
