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

<style>
/* ========================================
   好み設定確認ページスタイル
   ヘッダーに干渉しないようにスコープを限定
======================================== */

.preferences-confirm-container {
    max-width: 50rem;
    margin: 0 auto;
    padding: 2rem 1.25rem;
    padding-bottom: 11vh;
}

.preferences-confirm-container .page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #ec4899;
    margin-bottom: 1.5rem;
    text-align: center;
}

.preferences-confirm-container .page-description {
    text-align: center;
    color: #6b7280;
    line-height: 1.8;
    margin-bottom: 2rem;
    font-size: 1rem;
}

/* アラート */
.preferences-confirm-container .pref-alert {
    padding: 1.25rem 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    font-weight: 500;
    font-size: 1.125rem;
    text-align: center;
}

.preferences-confirm-container .pref-alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 0.25rem solid #10b981;
    box-shadow: 0 0.25rem 0.75rem rgba(16, 185, 129, 0.15);
}

.preferences-confirm-container .pref-alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 0.25rem solid #ef4444;
}

/* 確認セクション */
.preferences-confirm-container .confirmation-section {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
    border: 0.0625rem solid rgba(236, 72, 153, 0.1);
}

.preferences-confirm-container .confirmation-section .section-title {
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

.preferences-confirm-container .confirmation-section .section-title::before {
    content: '✓';
    font-size: 1.5rem;
    color: #ec4899;
}

/* 保存済み設定セクション */
.preferences-confirm-container .saved-preferences-section {
    background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 0.5rem 1.5rem rgba(236, 72, 153, 0.1);
    border: 0.125rem solid #f9a8d4;
}

.preferences-confirm-container .saved-preferences-section .section-title {
    font-size: 1.375rem;
    font-weight: 700;
    color: #9f1239;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 0.125rem solid #f9a8d4;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.preferences-confirm-container .saved-preferences-section .section-title::before {
    content: '💗';
    font-size: 1.5rem;
}

/* 完了セクション */
.preferences-confirm-container .completion-section {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
}

.preferences-confirm-container .completion-section .section-text {
    color: #374151;
    line-height: 1.8;
    margin-bottom: 1.5rem;
    text-align: center;
    font-size: 1rem;
}

/* 好みタグ */
.preferences-confirm-container .preference-list,
.preferences-confirm-container .selected-preferences {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.preferences-confirm-container .preference-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.625rem 1.25rem;
    background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%);
    color: #db2777;
    border-radius: 2rem;
    font-weight: 600;
    font-size: 0.9375rem;
    box-shadow: 0 0.125rem 0.375rem rgba(236, 72, 153, 0.15);
    transition: all 0.3s;
    border: 0.0625rem solid #f9a8d4;
}

.preferences-confirm-container .preference-tag:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.25rem 0.5rem rgba(236, 72, 153, 0.2);
}

.preferences-confirm-container .no-selection {
    color: #6b7280;
    font-style: italic;
    text-align: center;
    padding: 1.5rem;
}

/* ボタンセクション */
.preferences-confirm-container .button-section {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.preferences-confirm-container .pref-btn {
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

.preferences-confirm-container .pref-btn-primary {
    background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
    color: white;
    box-shadow: 0 0.25rem 0.75rem rgba(236, 72, 153, 0.3);
}

.preferences-confirm-container .pref-btn-primary:hover {
    transform: translateY(-0.125rem);
    box-shadow: 0 0.5rem 1rem rgba(236, 72, 153, 0.4);
    color: white;
}

.preferences-confirm-container .pref-btn-secondary {
    background: white;
    color: #6b7280;
    border: 0.125rem solid #e5e7eb;
}

.preferences-confirm-container .pref-btn-secondary:hover {
    background: #f9fafb;
    color: #374151;
    border-color: #d1d5db;
}

.preferences-confirm-container .pref-btn-outline {
    background: transparent;
    color: #6b7280;
    border: 0.125rem solid #e5e7eb;
}

.preferences-confirm-container .pref-btn-outline:hover {
    background: #f9fafb;
    color: #374151;
    border-color: #d1d5db;
}

/* ========================================
   レスポンシブ: タブレット (768px以下)
======================================== */
@media (max-width: 768px) {
    .preferences-confirm-container {
        padding: 1.5rem 1rem;
    }

    .preferences-confirm-container .page-title {
        font-size: 1.75rem;
    }

    .preferences-confirm-container .pref-alert {
        font-size: 1rem;
    }

    .preferences-confirm-container .confirmation-section,
    .preferences-confirm-container .saved-preferences-section,
    .preferences-confirm-container .completion-section {
        padding: 1.5rem;
    }

    .preferences-confirm-container .section-title {
        font-size: 1.25rem;
    }

    .preferences-confirm-container .preference-tag {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
}

/* ========================================
   レスポンシブ: スマホ (480px以下)
======================================== */
@media (max-width: 480px) {
    .preferences-confirm-container {
        padding: 1.25rem 0.75rem;
    }

    .preferences-confirm-container .page-title {
        font-size: 1.625rem;
        margin-bottom: 1.25rem;
    }

    .preferences-confirm-container .page-description {
        font-size: 1rem;
        margin-bottom: 1.75rem;
    }

    .preferences-confirm-container .pref-alert {
        padding: 1.125rem 1.25rem;
        font-size: 1.0625rem;
        margin-bottom: 1.5rem;
    }

    .preferences-confirm-container .confirmation-section,
    .preferences-confirm-container .saved-preferences-section,
    .preferences-confirm-container .completion-section {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .preferences-confirm-container .section-title {
        font-size: 1.25rem;
        margin-bottom: 1.25rem;
    }

    .preferences-confirm-container .completion-section .section-text {
        font-size: 1rem;
        margin-bottom: 1.25rem;
    }

    .preferences-confirm-container .preference-list,
    .preferences-confirm-container .selected-preferences {
        gap: 0.625rem;
    }

    .preferences-confirm-container .preference-tag {
        font-size: 1rem;
        padding: 0.625rem 1.125rem;
    }

    .preferences-confirm-container .no-selection {
        font-size: 1rem;
        padding: 1.25rem;
    }

    .preferences-confirm-container .button-section {
        flex-direction: column;
        gap: 0.875rem;
    }

    .preferences-confirm-container .pref-btn {
        width: 100%;
        padding: 1rem;
        font-size: 1.0625rem;
    }
}
</style>

<div class="preferences-confirm-container">
    <h1 class="page-title">好み設定の確認</h1>
    
    <?php if ($message): ?>
        <div class="pref-alert pref-alert-success">
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
            <h3 class="section-title">保存された好み設定</h3>
            <?php if (empty($savedPreferences)): ?>
                <p class="no-selection">好みが設定されていません（すべての女の子が対象になります）</p>
            <?php else: ?>
                <div class="preference-list">
                    <?php foreach ($savedPreferences as $preference): ?>
                        <span class="preference-tag"><?= htmlspecialchars($preference) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="completion-section">
            <p class="section-text">設定が完了しました。フリーコースのご予約時に、お客様の好みに合った女の子を優先的にご案内いたします。</p>
            <div class="button-section">
                <a href="user_preferences.php" class="pref-btn pref-btn-primary">設定をやり直す</a>
                <a href="home.php" class="pref-btn pref-btn-secondary">ホームに戻る</a>
            </div>
        </div>
        
    <?php elseif ($error): ?>
        <div class="pref-alert pref-alert-danger"><?= htmlspecialchars($error) ?></div>
        
        <div class="button-section">
            <a href="user_preferences.php" class="pref-btn pref-btn-primary">設定画面に戻る</a>
            <a href="home.php" class="pref-btn pref-btn-secondary">ホームに戻る</a>
        </div>
        
    <?php else: ?>
        <p class="page-description">以下の内容で好み設定を保存します。よろしければ「登録する」ボタンを押してください。</p>
        
        <div class="confirmation-section">
            <h3 class="section-title">選択した好み</h3>
            
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
                <button type="submit" name="confirm" class="pref-btn pref-btn-primary">登録する</button>
                <a href="user_preferences.php" class="pref-btn pref-btn-secondary">戻って修正する</a>
                <a href="home.php" class="pref-btn pref-btn-outline">ホームに戻る</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>