<?php

include 'header.php';

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// 現在の設定を取得
function getCurrentPreferences($pdo, $tel) {
    $stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE tel = :tel");
    $stmt->execute([':tel' => $tel]);
    $preferences = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $preferences[] = $row['preference_value'];
    }
    return $preferences;
}

$currentPrefs = getCurrentPreferences($pdo, $tel);

// 好み設定の選択肢
$preferenceOptions = [
    'Sっ子',
    'Mっ子', 
    'グラマー',
    'ロリ',
    'ベテラン',
    'ビギナー',
    'かわいさ重視',
    'お話し好き',
    '豊乳',
    '美尻'
];

$error = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selectedPreferences = $_POST['preferences'] ?? [];
        
        // セッションに保存して確認画面へ
        $_SESSION['TEMP_PREFERENCES'] = $selectedPreferences;
        header('Location: user_preferences_confirm.php');
        exit;
        
    } catch (Exception $e) {
        $error = '処理中にエラーが発生しました';
        error_log('Preferences form error: ' . $e->getMessage());
    }
}
?>

<div class="container">
    <h1>好みの設定</h1>
    
    <p>フリーコースでのご予約時に、お客様の好みに合った女の子を優先的にご案内いたします。<br>
    複数選択可能です。設定は後からいつでも変更できます。</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="preference-section">
            <h3>好みのタイプ（複数選択可）</h3>
            <div class="checkbox-grid">
                <?php foreach ($preferenceOptions as $option): ?>
                    <div class="form-check">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            name="preferences[]" 
                            value="<?= htmlspecialchars($option) ?>"
                            id="pref_<?= htmlspecialchars($option) ?>"
                            <?= in_array($option, $currentPrefs) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="pref_<?= htmlspecialchars($option) ?>">
                            <?= htmlspecialchars($option) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="button-section">
            <button type="submit" class="btn btn-primary">設定内容を確認する</button>
            <a href="home.php" class="btn btn-secondary">ホームに戻る</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>