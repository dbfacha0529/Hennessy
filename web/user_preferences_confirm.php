<?php

include 'header.php';

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

// セッションデータチェック
if (!isset($_SESSION['TEMP_PREFERENCES'])) {
    header('Location: user_preferences.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];
$selectedPreferences = $_SESSION['TEMP_PREFERENCES'];

$message = '';
$error = '';

// 確定処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo->beginTransaction();
        
        // 既存の設定を削除
        $stmt = $pdo->prepare("DELETE FROM user_preferences WHERE tel = :tel");
        $stmt->execute([':tel' => $tel]);
        
        // 新しい設定を保存
        if (!empty($selectedPreferences)) {
            $stmt = $pdo->prepare("INSERT INTO user_preferences (tel, preference_value, created_at) VALUES (:tel, :preference_value, NOW())");
            
            foreach ($selectedPreferences as $preference) {
                $stmt->execute([
                    ':tel' => $tel,
                    ':preference_value' => $preference
                ]);
            }
        }
        
        $pdo->commit();
        
        // セッションクリア
        unset($_SESSION['TEMP_PREFERENCES']);
        
        $message = '好み設定を保存しました';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = '設定の保存中にエラーが発生しました';
        error_log('Preferences save error: ' . $e->getMessage());
    }
}
?>

<div class="container">
    <h1>好み設定の確認</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <?php
        // 保存された設定を再取得して表示
        $stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE tel = :tel");
        $stmt->execute([':tel' => $tel]);
        $savedPreferences = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedPreferences[] = $row['preference_value'];
        }
        ?>
        
        <div class="saved-preferences-section">
            <h3>保存された好み設定</h3>
            <?php if (empty($savedPreferences)): ?>
                <p>好みが設定されていません（すべての女の子が対象になります）</p>
            <?php else: ?>
                <div class="preference-list">
                    <?php foreach ($savedPreferences as $preference): ?>
                        <span class="preference-tag"><?= htmlspecialchars($preference) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="completion-section">
            <p>設定が完了しました。フリーコースのご予約時に、お客様の好みに合った女の子を優先的にご案内いたします。</p>
            <div class="button-section">
                <a href="user_preferences.php" class="btn btn-primary">設定をやり直す</a>
                <a href="home.php" class="btn btn-secondary">ホームに戻る</a>
            </div>
        </div>
        
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        
        <div class="button-section">
            <a href="user_preferences.php" class="btn btn-primary">設定画面に戻る</a>
            <a href="home.php" class="btn btn-secondary">ホームに戻る</a>
        </div>
        
    <?php else: ?>
        <p>以下の内容で好み設定を保存します。よろしければ「登録する」ボタンを押してください。</p>
        
        <div class="confirmation-section">
            <h3>選択した好み</h3>
            
            <?php if (empty($selectedPreferences)): ?>
                <p class="no-selection">好みが選択されていません（すべての女の子が対象になります）</p>
            <?php else: ?>
                <div class="selected-preferences">
                    <?php foreach ($selectedPreferences as $preference): ?>
                        <span class="preference-tag"><?= htmlspecialchars($preference) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="button-section">
                <button type="submit" name="confirm" class="btn btn-primary">登録する</button>
                <a href="user_preferences.php" class="btn btn-secondary">戻って修正する</a>
                <a href="index.php" class="btn btn-outline-secondary">ホームに戻る</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>