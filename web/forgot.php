<?php
require_once(dirname(__FILE__) . '/../functions.php');
session_start();

$err = []; // エラー格納用

// DB接続
$pdo = dbConnect(); // 共通関数で接続

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $check = $_POST['check'] ?? '';
    $checka = $_POST['checka'] ?? '';
    $key   = $_POST['key'] ?? '';
    $keya  = $_POST['keya'] ?? '';

    // バリデーション
    if ($check === '') $err['check'] = '1つ目の項目を選択してください';
    if ($checka === '') $err['checka'] = '1つ目の入力欄を入力してください';
    if ($key === '') $err['key'] = '2つ目の項目を選択してください';
    if ($keya === '') $err['keya'] = '2つ目の入力欄を入力してください';

    // 1つ目と2つ目が同じ場合のエラー
    if ($check !== '' && $key !== '' && $check === $key) {
        $err['key'] = '違う項目を選択してください';
    }

    // バリデーション通過後
    if (empty($err)) {
        try {
            // 動的カラムを決めるためのホワイトリスト
            $columns = ['login_id', 'user_name', 'tel', 'password'];
            if (!in_array($check, $columns, true) || !in_array($key, $columns, true)) {
                throw new Exception();
            }

            // SQL準備
            $sql = "SELECT * FROM users WHERE {$check} = :checka LIMIT 1";
            $stmt = $pdo->prepare($sql);

            // checkがpasswordならハッシュ化して検索（入力値そのままでは検索できない）
            if ($check === 'password') {
                // DB上のハッシュ全件取得して照合する
                $stmt = $pdo->prepare("SELECT * FROM users");
                $stmt->execute();
                $found = false;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (password_verify($checka, $row['password'])) {
                        // key側の確認
                        if ($key === 'password') {
                            if (password_verify($keya, $row['password'])) {
                                $_SESSION['USER_DATE'] = $row;
                                header("Location: re_sign_up.php");
                                exit;
                            }
                        } else {
                            if ($row[$key] === $keya) {
                                $_SESSION['USER_DATE'] = $row;
                                header("Location: re_sign_up.php");
                                exit;
                            }
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $err['common'] = '認証に失敗しました';
                }
            } else {
                // checkがpasswordでない場合
                $stmt->bindValue(':checka', $checka, PDO::PARAM_STR);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    if ($key === 'password') {
                        if (password_verify($keya, $row['password'])) {
                            $_SESSION['USER_DATE'] = $row;
                            header("Location: re_sign_up.php");
                            exit;
                        } else {
                            $err['common'] = '認証に失敗しました';
                        }
                    } else {
                        if ($row[$key] === $keya) {
                            $_SESSION['USER_DATE'] = $row;
                            header("Location: re_sign_up.php");
                            exit;
                        } else {
                            $err['common'] = '認証に失敗しました';
                        }
                    }
                } else {
                    $err['common'] = '認証に失敗しました';
                }
            }
        } catch (Exception $e) {
            $err['common'] = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>



<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>再登録</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--オリジナルCSS-->
  <link href="./css/forgot.css" rel="stylesheet">
</head>
<body class="p-3">

  <h2>再登録</h2>
  <h3>覚えているもの２つをご入力ください</h3>

  <form method="post">
      <!-- 共通エラー表示 -->
  <?php if (isset($err['common'])): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($err['common']) ?>
    </div>
  <?php endif; ?>
<!-- 1つ目のプルダウン -->
<div class="mb-3">
  <select id="check" name="check" 
          class="form-select <?php if (isset($err['check'])) echo 'is-invalid'; ?>">
    <option value="">選択してください</option>
    <option value="login_id" <?= $check === 'login_id' ? 'selected' : '' ?>>ログインID</option>
    <option value="user_name" <?= $check === 'user_name' ? 'selected' : '' ?>>ユーザー名</option>
    <option value="tel" <?= $check === 'tel' ? 'selected' : '' ?>>TEL</option>
    <option value="password" <?= $check === 'password' ? 'selected' : '' ?>>パスワード</option>
  </select>
  <?php if (isset($err['check'])): ?>
    <div class="invalid-feedback"><?= $err['check'] ?></div>
  <?php endif; ?>
</div>

<!-- 1つ目の入力欄 -->
<div class="mb-3">
  <input type="text" class="form-control <?php if (isset($err['checka'])) echo 'is-invalid'; ?>"
         id="checka" name="checka" value="<?= htmlspecialchars($checka ?? '') ?>">
  <?php if (isset($err['checka'])): ?>
    <div class="invalid-feedback"><?= $err['checka'] ?></div>
  <?php endif; ?>
</div>

<!-- 2つ目のプルダウン -->
<div class="mb-3">
  <select id="key" name="key"
          class="form-select <?php if (isset($err['key'])) echo 'is-invalid'; ?>">
    <option value="">選択してください</option>
    <option value="login_id" <?= $key === 'login_id' ? 'selected' : '' ?>>ログインID</option>
    <option value="user_name" <?= $key === 'user_name' ? 'selected' : '' ?>>ユーザー名</option>
    <option value="tel" <?= $key === 'tel' ? 'selected' : '' ?>>TEL</option>
    <option value="password" <?= $key === 'password' ? 'selected' : '' ?>>パスワード</option>
  </select>
  <?php if (isset($err['key'])): ?>
    <div class="invalid-feedback"><?= $err['key'] ?></div>
  <?php endif; ?>
</div>

<!-- 2つ目の入力欄 -->
<div class="mb-3">
  <input type="text" class="form-control <?php if (isset($err['keya'])) echo 'is-invalid'; ?>"
         id="keya" name="keya" value="<?= htmlspecialchars($keya ?? '') ?>">
  <?php if (isset($err['keya'])): ?>
    <div class="invalid-feedback"><?= $err['keya'] ?></div>
  <?php endif; ?>
</div>


    <button type="submit" class="btn btn-primary">認証する</button>
        <!-- TOPへ戻る -->
    <a type="button" class="btn btn-back" href="index.php">TOPへ戻る</a>
  </form>

<script>
  const checkSelect = document.getElementById("check");
  const keySelect   = document.getElementById("key");

  const allOptions = Array.from(keySelect.options);

  // PHPで選択済みの値を渡す
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

  // 初期表示時に実行（エラーで戻ってきた時用）
  if (selectedCheck) {
    updateKeyOptions(selectedCheck);
  }

  // ユーザーが変更したとき
  checkSelect.addEventListener("change", () => {
    updateKeyOptions(checkSelect.value);
  });
</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
  integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
  crossorigin="anonymous"></script>

</body>
</html>