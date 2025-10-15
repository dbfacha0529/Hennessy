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

// DBから好み設定の選択肢を取得
$stmt = $pdo->query("SELECT options FROM preferenceoptions ORDER BY sort_order ASC");
$preferenceOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

<style>
/* ========================================
   好み設定ページスタイル
   ヘッダーに干渉しないようにスコープを限定
======================================== */

.preferences-page-container {
    max-width: 50rem;
    margin: 0 auto;
    padding: 2rem 1.25rem;
    padding-bottom: 11vh;
}

.preferences-page-container .page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #ec4899;
    margin-bottom: 1.5rem;
    text-align: center;
}

.preferences-page-container .page-description {
    text-align: center;
    color: #6b7280;
    line-height: 1.8;
    margin-bottom: 2.5rem;
    font-size: 1rem;
}

/* エラー表示 */
.preferences-page-container .alert {
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.preferences-page-container .alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 0.25rem solid #ef4444;
}

/* 好み設定セクション */
.preferences-page-container .preference-section {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
    border: 0.0625rem solid rgba(236, 72, 153, 0.1);
}

.preferences-page-container .preference-section .section-title {
    font-size: 1.375rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 0.125rem solid #fce7f3;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.preferences-page-container .preference-section .section-title::before {
    content: '';
    font-size: 1.5rem;
}

/* チェックボックスグリッド */
.preferences-page-container .checkbox-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.preferences-page-container .pref-check-item {
    background: #fafafa;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    border: 0.125rem solid #e5e7eb;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    display: flex;
    align-items: center;
}

.preferences-page-container .pref-check-item:hover {
    border-color: #ec4899;
    box-shadow: 0 0.25rem 0.75rem rgba(236, 72, 153, 0.15);
    transform: translateY(-0.125rem);
}

.preferences-page-container .pref-check-input {
    width: 1.375rem;
    height: 1.375rem;
    border: 0.125rem solid #d1d5db;
    border-radius: 0.25rem;
    cursor: pointer;
    transition: all 0.3s;
    margin-right: 0.75rem;
    flex-shrink: 0;
    appearance: none;
    background: white;
    position: relative;
}

.preferences-page-container .pref-check-input:checked {
    background: #ec4899;
    border-color: #ec4899;
}

.preferences-page-container .pref-check-input:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1;
}

.preferences-page-container .pref-check-input:checked ~ .pref-check-label {
    color: #ec4899;
    font-weight: 600;
}

.preferences-page-container .pref-check-item:has(.pref-check-input:checked) {
    background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
    border-color: #ec4899;
}

.preferences-page-container .pref-check-input:focus {
    outline: none;
    box-shadow: 0 0 0 0.25rem rgba(236, 72, 153, 0.25);
}

.preferences-page-container .pref-check-label {
    font-size: 1rem;
    color: #374151;
    cursor: pointer;
    font-weight: 500;
    transition: color 0.3s;
}

/* ボタンセクション */
.preferences-page-container .button-section {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.preferences-page-container .pref-btn {
    padding: 0.875rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 0.625rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.preferences-page-container .pref-btn-primary {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
    color: white;
    box-shadow: 0 0.25rem 0.75rem rgba(236, 72, 153, 0.3);
}

.preferences-page-container .pref-btn-primary:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.5rem 1rem rgba(236, 72, 153, 0.4);
    color: white;
}

.preferences-page-container .pref-btn-secondary {
    background: white;
    color: #6b7280;
    border: 0.125rem solid #e5e7eb;
}

.preferences-page-container .pref-btn-secondary:hover {
    background: #f9fafb;
    color: #374151;
    border-color: #d1d5db;
}

/* ========================================
   レスポンシブ: タブレット (768px以下)
======================================== */
@media (max-width: 768px) {
    .preferences-page-container {
        padding: 1.5rem 1rem;
    }

    .preferences-page-container .page-title {
        font-size: 1.75rem;
    }

    .preferences-page-container .page-description {
        font-size: 0.9375rem;
    }

    .preferences-page-container .preference-section {
        padding: 1.5rem;
    }

    .preferences-page-container .preference-section .section-title {
        font-size: 1.25rem;
    }

    .preferences-page-container .checkbox-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
    }

    .preferences-page-container .pref-check-item {
        padding: 0.875rem 1rem;
    }

    .preferences-page-container .pref-check-label {
        font-size: 0.9375rem;
    }
}

/* ========================================
   レスポンシブ: スマホ (480px以下)
======================================== */
@media (max-width: 480px) {
    .preferences-page-container {
        padding: 1.25rem 0.75rem;
    }

    .preferences-page-container .page-title {
        font-size: 1.625rem;
        margin-bottom: 1.25rem;
    }

    .preferences-page-container .page-description {
        font-size: 1rem;
        margin-bottom: 2rem;
    }

    .preferences-page-container .preference-section {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .preferences-page-container .preference-section .section-title {
        font-size: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .preferences-page-container .checkbox-grid {
        grid-template-columns: 1fr;
        gap: 0.875rem;
    }

    .preferences-page-container .pref-check-item {
        padding: 1rem 1.125rem;
    }

    .preferences-page-container .pref-check-input {
        width: 1.5rem;
        height: 1.5rem;
    }

    .preferences-page-container .pref-check-label {
        font-size: 1.0625rem;
    }

    .preferences-page-container .button-section {
        flex-direction: column;
        gap: 0.875rem;
    }

    .preferences-page-container .pref-btn {
        width: 100%;
        padding: 1rem;
        font-size: 1.0625rem;
    }
}
</style>

<div class="preferences-page-container">
    <h1 class="page-title">好みの設定</h1>
    
    <p class="page-description">フリーコースでのご予約時に、お客様の好みに合った女の子を優先的にご案内いたします。<br>
    複数選択可能です。設定は後からいつでも変更できます。</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="preference-section">
            <h3 class="section-title">好みのタイプ（複数選択可）</h3>
            <div class="checkbox-grid">
                <?php foreach ($preferenceOptions as $option): ?>
                    <label class="pref-check-item">
                        <input 
                            class="pref-check-input" 
                            type="checkbox" 
                            name="preferences[]" 
                            value="<?= htmlspecialchars($option) ?>"
                            <?= in_array($option, $currentPrefs) ? 'checked' : '' ?>
                        >
                        <span class="pref-check-label">
                            <?= htmlspecialchars($option) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="button-section">
            <button type="submit" class="pref-btn pref-btn-primary">設定内容を確認する</button>
            <a href="home.php" class="pref-btn pref-btn-secondary">ホームに戻る</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>