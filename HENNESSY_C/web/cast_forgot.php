<?php
require_once(dirname(__FILE__) . '/../functions.php');
session_start();

$err = [];
$check = '';
$checka = '';
$key = '';
$keya = '';

$pdo = dbConnect();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $check = $_POST['check'] ?? '';
    $checka = $_POST['checka'] ?? '';
    $key   = $_POST['key'] ?? '';
    $keya  = $_POST['keya'] ?? '';

    if ($check === '') $err['check'] = '1つ目の項目を選択してください';
    if ($checka === '') $err['checka'] = '1つ目の入力欄を入力してください';
    if ($key === '') $err['key'] = '2つ目の項目を選択してください';
    if ($keya === '') $err['keya'] = '2つ目の入力欄を入力してください';

    if ($check !== '' && $key !== '' && $check === $key) {
        $err['key'] = '違う項目を選択してください';
    }

    if (empty($err)) {
        try {
            $columns = ['c_login_id', 'name', 'tel', 'g_password'];
            if (!in_array($check, $columns, true) || !in_array($key, $columns, true)) {
                throw new Exception();
            }

            $sql = "SELECT * FROM girl WHERE {$check} = :checka LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':checka', $checka, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if ($row[$key] === $keya) {
                    $_SESSION['cast_reset'] = $row;
                    header("Location: cast_reset_password.php");
                    exit;
                } else {
                    $err['common'] = '認証に失敗しました';
                }
            } else {
                $err['common'] = '認証に失敗しました';
            }
        } catch (Exception $e) {
            $err['common'] = 'エラーが発生しました';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ログイン情報再設定</title>
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

    .forgot-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 1.25rem;
      box-shadow: 0 0.625rem 2.5rem rgba(255, 182, 193, 0.3);
      width: 100%;
      max-width: 34.375rem;
      padding: 2.5rem;
      margin: 1.25rem auto;
    }

    .logo-section {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo-section h1 {
      color: #ff69b4;
      font-size: 1.875rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .logo-section p {
      color: #ff1493;
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 1rem;
    }

    .instruction {
      background: #fff5f7;
      border-left: 0.25rem solid #ff69b4;
      padding: 1rem;
      margin-bottom: 1.5rem;
      border-radius: 0.5rem;
      font-size: 0.9375rem;
      color: #666;
    }

    .form-label {
      color: #ff69b4;
      font-weight: 600;
      font-size: 0.9375rem;
      margin-bottom: 0.5rem;
    }

    .form-select,
    .form-control {
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.625rem;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .form-select:focus,
    .form-control:focus {
      border-color: #ff69b4;
      box-shadow: 0 0 0 0.25rem rgba(255, 105, 180, 0.1);
      outline: none;
    }

    .form-select.is-invalid,
    .form-control.is-invalid {
      border-color: #dc3545;
    }

    .invalid-feedback {
      color: #dc3545;
      font-size: 0.875rem;
      margin-top: 0.25rem;
      display: block;
    }

    .btn-verify {
      background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
      border: none;
      color: white;
      font-weight: 600;
      font-size: 1.125rem;
      padding: 0.875rem;
      border-radius: 0.625rem;
      width: 100%;
      margin-top: 1.5rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-verify:hover {
      background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
      transform: translateY(-0.125rem);
      box-shadow: 0 0.375rem 1.25rem rgba(255, 105, 180, 0.3);
    }

    .alert {
      background: #ffe4e9;
      border: 0.125rem solid #ffb6c1;
      border-radius: 0.625rem;
      color: #c71585;
      padding: 0.875rem;
      margin-bottom: 1.25rem;
      font-size: 0.9375rem;
    }

    .links-section {
      margin-top: 1.875rem;
      text-align: center;
      padding-top: 1.25rem;
      border-top: 0.0625rem solid #ffb6c1;
    }

    .links-section a {
      color: #ff69b4;
      text-decoration: none;
      font-size: 0.9375rem;
      transition: color 0.3s ease;
    }

    .links-section a:hover {
      color: #ff1493;
      text-decoration: underline;
    }

    .mb-3 {
      margin-bottom: 1.25rem;
    }

    @media (max-width: 48rem) {
      .forgot-container {
        padding: 2rem;
      }

      .logo-section h1 {
        font-size: 1.625rem;
      }

      .form-select,
      .form-control {
        font-size: 0.9375rem;
        padding: 0.625rem 0.875rem;
      }

      .btn-verify {
        font-size: 1rem;
        padding: 0.75rem;
      }
    }

    @media (max-width: 30rem) {
      body {
        padding: 1rem;
      }

      .forgot-container {
        padding: 1.5rem;
      }

      .logo-section h1 {
        font-size: 1.5rem;
      }

      .logo-section p {
        font-size: 0.8125rem;
      }

      .instruction {
        font-size: 0.875rem;
        padding: 0.75rem;
      }

      .form-label {
        font-size: 0.875rem;
      }

      .form-select,
      .form-control {
        font-size: 0.875rem;
        padding: 0.625rem;
      }

      .btn-verify {
        font-size: 0.9375rem;
        padding: 0.625rem;
      }

      .links-section a {
        font-size: 0.875rem;
      }
    }
  </style>
</head>
<body>
  <div class="forgot-container">
    <div class="logo-section">
      <h1>🌸 ログイン情報再設定 🌸</h1>
      <p>キャスト専用</p>
    </div>

    <div class="instruction">
      覚えている情報を2つ選択して入力してください
    </div>

    <?php if (isset($err['common'])): ?>
      <div class="alert">
        <?= htmlspecialchars($err['common']) ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <!-- 1つ目 -->
      <div class="mb-3">
        <label class="form-label">1つ目の項目</label>
        <select id="check" name="check" 
                class="form-select <?php if (isset($err['check'])) echo 'is-invalid'; ?>">
          <option value="">選択してください</option>
          <option value="c_login_id" <?= $check === 'c_login_id' ? 'selected' : '' ?>>ログインID</option>
          <option value="name" <?= $check === 'name' ? 'selected' : '' ?>>掲載名（源氏名）</option>
          <option value="tel" <?= $check === 'tel' ? 'selected' : '' ?>>電話番号</option>
          <option value="g_password" <?= $check === 'g_password' ? 'selected' : '' ?>>パスワード</option>
        </select>
        <?php if (isset($err['check'])): ?>
          <div class="invalid-feedback"><?= htmlspecialchars($err['check']) ?></div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">1つ目の入力</label>
        <input type="text" class="form-control <?php if (isset($err['checka'])) echo 'is-invalid'; ?>"
               id="checka" name="checka" value="<?= htmlspecialchars($checka) ?>"
               placeholder="選択した項目の値を入力">
        <?php if (isset($err['checka'])): ?>
          <div class="invalid-feedback"><?= htmlspecialchars($err['checka']) ?></div>
        <?php endif; ?>
      </div>

      <!-- 2つ目 -->
      <div class="mb-3">
        <label class="form-label">2つ目の項目</label>
        <select id="key" name="key"
                class="form-select <?php if (isset($err['key'])) echo 'is-invalid'; ?>">
          <option value="">選択してください</option>
          <option value="c_login_id" <?= $key === 'c_login_id' ? 'selected' : '' ?>>ログインID</option>
          <option value="name" <?= $key === 'name' ? 'selected' : '' ?>>掲載名（源氏名）</option>
          <option value="tel" <?= $key === 'tel' ? 'selected' : '' ?>>電話番号</option>
          <option value="g_password" <?= $key === 'g_password' ? 'selected' : '' ?>>パスワード</option>
        </select>
        <?php if (isset($err['key'])): ?>
          <div class="invalid-feedback"><?= htmlspecialchars($err['key']) ?></div>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label">2つ目の入力</label>
        <input type="text" class="form-control <?php if (isset($err['keya'])) echo 'is-invalid'; ?>"
               id="keya" name="keya" value="<?= htmlspecialchars($keya) ?>"
               placeholder="選択した項目の値を入力">
        <?php if (isset($err['keya'])): ?>
          <div class="invalid-feedback"><?= htmlspecialchars($err['keya']) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-verify">認証する</button>
    </form>

    <div class="links-section">
      <a href="cast_login.php">ログイン画面に戻る</a>
    </div>
  </div>

  <script>
    const checkSelect = document.getElementById("check");
    const keySelect   = document.getElementById("key");
    const allOptions = Array.from(keySelect.options);
    const selectedCheck = "<?= $check ?? '' ?>";

    function updateKeyOptions(selected) {
      keySelect.innerHTML = "";
      allOptions.forEach(opt => {
        if (opt.value !== selected) {
          keySelect.appendChild(opt.cloneNode(true));
        }
      });
      keySelect.insertAdjacentHTML("afterbegin", '<option value="">選択してください</option>');
    }

    if (selectedCheck) {
      updateKeyOptions(selectedCheck);
    }

    checkSelect.addEventListener("change", () => {
      updateKeyOptions(checkSelect.value);
    });
  </script>
</body>
</html>