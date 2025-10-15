<?php
session_start();

if (!isset($_SESSION['signup_success'])) {
  header('Location: cast_signup.php');
  exit;
}

// セッションデータを変数に保存してから削除
$signup_data = $_SESSION['signup_data'] ?? null;

// セッションをクリア（変数に保存した後）
unset($_SESSION['signup_success']);
unset($_SESSION['signup_data']);
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>登録完了</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 1.25rem;
    }

    .success-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 1.25rem;
      box-shadow: 0 0.625rem 2.5rem rgba(255, 182, 193, 0.3);
      width: 100%;
      max-width: 31.25rem;
      padding: 3.125rem 2.5rem;
      text-align: center;
    }

    .success-icon {
      font-size: 5rem;
      margin-bottom: 1.5rem;
    }

    .success-title {
      color: #ff69b4;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .success-message {
      color: #666;
      font-size: 1rem;
      line-height: 1.6;
      margin-bottom: 2rem;
    }

    .btn-login {
      background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
      border: none;
      color: white;
      font-weight: 600;
      font-size: 1.125rem;
      padding: 0.875rem 2.5rem;
      border-radius: 0.625rem;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
      transform: translateY(-0.125rem);
      box-shadow: 0 0.375rem 1.25rem rgba(255, 105, 180, 0.3);
      color: white;
    }

    .info-table {
      width: 100%;
      margin: 2rem 0;
      border-collapse: collapse;
      text-align: left;
    }

    .info-table th {
      background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
      color: white;
      padding: 0.875rem 1rem;
      font-weight: 600;
      font-size: 0.9375rem;
      width: 40%;
    }

    .info-table td {
      background: #fff;
      border: 0.125rem solid #ffb6c1;
      padding: 0.875rem 1rem;
      color: #333;
      font-size: 0.9375rem;
    }

    .info-table tr:first-child th {
      border-top-left-radius: 0.5rem;
    }

    .info-table tr:first-child td {
      border-top-right-radius: 0.5rem;
    }

    .info-table tr:last-child th {
      border-bottom-left-radius: 0.5rem;
    }

    .info-table tr:last-child td {
      border-bottom-right-radius: 0.5rem;
    }

    @media (max-width: 48rem) {
      .success-container {
        padding: 2.5rem 2rem;
      }

      .success-icon {
        font-size: 4rem;
      }

      .success-title {
        font-size: 1.75rem;
      }

      .success-message {
        font-size: 0.9375rem;
      }

      .btn-login {
        font-size: 1rem;
        padding: 0.75rem 2rem;
      }

      .info-table th,
      .info-table td {
        padding: 0.75rem;
        font-size: 0.875rem;
      }

      .info-table th {
        width: 35%;
      }
    }

    @media (max-width: 30rem) {
      .success-container {
        padding: 2rem 1.5rem;
      }

      .success-icon {
        font-size: 3.5rem;
      }

      .success-title {
        font-size: 1.5rem;
      }

      .success-message {
        font-size: 0.875rem;
      }

      .btn-login {
        font-size: 0.9375rem;
        padding: 0.625rem 1.5rem;
      }

      .info-table th,
      .info-table td {
        padding: 0.625rem;
        font-size: 0.8125rem;
      }
    }
  </style>
</head>

<body>
  <div class="success-container">
    <div class="success-icon">🌸</div>
    <h1 class="success-title">登録完了!</h1>
    <p class="success-message">
      キャストアカウントの登録が完了しました。<br>
      以下の情報で登録されました。
    </p>

    <?php if ($signup_data): ?>
    <table class="info-table">
      <tr>
        <th>ログインID</th>
        <td><?= htmlspecialchars($signup_data['c_login_id']) ?></td>
      </tr>
      <tr>
        <th>掲載名（源氏名）</th>
        <td><?= htmlspecialchars($signup_data['name']) ?></td>
      </tr>
      <tr>
        <th>電話番号</th>
        <td><?= htmlspecialchars($signup_data['tel']) ?></td>
      </tr>
    </table>
    <?php else: ?>
    <p style="color: red; margin: 2rem 0;">登録データが見つかりませんでした</p>
    <?php endif; ?>

    <p class="success-message">
      ログイン画面から、登録した情報でログインしてください。
    </p>
    <a href="cast_login.php" class="btn-login">ログイン画面へ</a>
  </div>
</body>

</html>