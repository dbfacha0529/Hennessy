<?php
include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// ユーザー情報を取得
$sql = "SELECT login_id, user_name, tel FROM users WHERE tel = :tel LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':tel' => $tel]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

// 現在のポイント残高取得
$stmt = $pdo->prepare("SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1");
$stmt->execute([':tel' => $tel]);
$current_point = $stmt->fetchColumn() ?: 0;

// セッションからランク情報を取得
$current_rank = $_SESSION['RANK']['current_rank'] ?? 'ビギナー';


$rank_class = getRankClass($current_rank);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="./css/account.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <h1><i class="bi bi-person-circle"></i> マイアカウント</h1>

    <!-- 基本情報カード -->
    <div class="account-card">
        <h2><i class="bi bi-info-circle"></i> 基本情報</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">ユーザー名</span>
                <span class="info-value"><?= htmlspecialchars($user['user_name']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">ログインID</span>
                <span class="info-value"><?= htmlspecialchars($user['login_id']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">電話番号</span>
                <span class="info-value"><?= htmlspecialchars($user['tel']) ?></span>
            </div>
        </div>
    </div>

    <!-- ランク・ポイントカード -->
    <div class="account-card">
        <h2><i class="bi bi-trophy"></i> ランク・ポイント</h2>
        <div class="rank-point-display">
            <div class="rank-badge-large <?= $rank_class ?>">
                <?= htmlspecialchars($current_rank) ?>
            </div>
            <div class="point-display">
                <span class="point-label">現在のポイント</span>
                <span class="point-value"><?= number_format($current_point) ?> P</span>
            </div>
        </div>
        <a href="point_rank.php" class="btn btn-outline-primary mt-3">
            <i class="bi bi-arrow-right-circle"></i> 詳細を見る
        </a>
    </div>

    <!-- リンクメニュー -->
    <div class="account-card">
        <h2><i class="bi bi-list-ul"></i> メニュー</h2>
        <div class="menu-list">
            <a href="reserve_list.php" class="menu-item">
                <i class="bi bi-calendar-check"></i>
                <span>予約履歴</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="point_rank.php" class="menu-item">
                <i class="bi bi-trophy"></i>
                <span>ポイント・ランク</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="point_history.php" class="menu-item">
                <i class="bi bi-clock-history"></i>
                <span>ポイント履歴</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="contact.php" class="menu-item">
                <i class="bi bi-headset"></i>
                <span>お問い合わせ</span>
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- アカウント設定カード -->
    <div class="account-card">
        <h2><i class="bi bi-gear"></i> アカウント設定</h2>
        <div class="menu-list">
            <a href="#" class="menu-item" data-bs-toggle="modal" data-bs-target="#editNameModal">
                <i class="bi bi-pencil"></i>
                <span>ユーザー名を変更</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="#" class="menu-item" data-bs-toggle="modal" data-bs-target="#editLoginIdModal">
                <i class="bi bi-person-badge"></i>
                <span>ログインIDを変更</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="#" class="menu-item" data-bs-toggle="modal" data-bs-target="#editPasswordModal">
                <i class="bi bi-key"></i>
                <span>パスワードを変更</span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <div class="menu-item disabled">
                <i class="bi bi-telephone"></i>
                <div>
                    <span>電話番号を変更</span>
                    <small class="text-muted d-block">店舗へお問い合わせください</small>
                </div>
            </div>
            <a href="logout.php" class="menu-item text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>ログアウト</span>
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- ユーザー名変更モーダル -->
<div class="modal fade" id="editNameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ユーザー名を変更</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="account_edit.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_name">
                    <div class="mb-3">
                        <label for="new_user_name" class="form-label">新しいユーザー名</label>
                        <input type="text" class="form-control" id="new_user_name" name="new_user_name" 
                               value="<?= htmlspecialchars($user['user_name']) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">変更する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ログインID変更モーダル -->
<div class="modal fade" id="editLoginIdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ログインIDを変更</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="account_edit.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_login_id">
                    <div class="mb-3">
                        <label for="new_login_id" class="form-label">新しいログインID</label>
                        <input type="text" class="form-control" id="new_login_id" name="new_login_id" 
                               value="<?= htmlspecialchars($user['login_id']) ?>" required>
                        <small class="text-muted">英数字6文字以上で入力してください</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">変更する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- パスワード変更モーダル -->
<div class="modal fade" id="editPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">パスワードを変更</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="account_edit.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">現在のパスワード</label>
                        <input type="password" class="form-control" id="current_password" 
                               name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">新しいパスワード</label>
                        <input type="password" class="form-control" id="new_password" 
                               name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password_confirm" class="form-label">新しいパスワード（確認）</label>
                        <input type="password" class="form-control" id="new_password_confirm" 
                               name="new_password_confirm" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-primary">変更する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>