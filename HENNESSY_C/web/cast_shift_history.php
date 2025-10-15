<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// キャスト情報取得
$sql = "SELECT name FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$cast_info = $stmt->fetch(PDO::FETCH_ASSOC);
$cast_name = $cast_info['name'] ?? 'キャスト';

// 最近の申請履歴を取得（過去4週間分）
$sql = "SELECT sr.*, 
        DATE_FORMAT(sr.date, '%Y年%m月%d日') as date_formatted,
        DATE_FORMAT(sr.in_time, '%H:%i') as in_time_formatted,
        DATE_FORMAT(sr.out_time, '%H:%i') as out_time_formatted,
        DATE_FORMAT(sr.created_at, '%Y/%m/%d %H:%i') as created_at_formatted,
        DATE_FORMAT(sr.approved_at, '%Y/%m/%d %H:%i') as approved_at_formatted
        FROM shift_requests sr
        WHERE sr.g_login_id = :g_login_id
        AND sr.date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
        ORDER BY sr.date DESC, sr.updated_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>シフト提出履歴 - <?= htmlspecialchars($cast_name) ?></title>
</head>
<body>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background-color: #f5f5f5;
        padding-bottom: 11vh;
    }

    /* ヘッダー */
    .history-header {
        background-color: #fff;
        padding: 1rem;
        border-bottom: 0.0625rem solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
    }

    .history-header h1 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .back-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: #0f6b3d;
        text-decoration: none;
        font-size: 0.875rem;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: #0c5530;
    }

    /* コンテナ */
    .container {
        max-width: 50rem;
        margin: 0 auto;
        padding: 1rem;
    }

    /* 履歴カード */
    .history-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
    }

    .history-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 0.0625rem solid #f0f0f0;
    }

    .history-date {
        font-size: 1rem;
        font-weight: 600;
        color: #333;
    }

    .status-badge {
        padding: 0.25rem 0.625rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-approved {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .history-content {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .history-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .history-label {
        font-size: 0.75rem;
        color: #666;
        font-weight: 600;
    }

    .history-value {
        font-size: 0.9375rem;
        color: #333;
    }

    .lo-badge {
        display: inline-block;
        padding: 0.125rem 0.5rem;
        background: #e8f5e9;
        color: #2e7d32;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .reject-reason {
        background: #fff3cd;
        border: 0.0625rem solid #ffc107;
        border-radius: 0.375rem;
        padding: 0.625rem;
        margin-top: 0.5rem;
    }

    .reject-reason-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #856404;
        margin-bottom: 0.25rem;
    }

    .reject-reason-text {
        font-size: 0.8125rem;
        color: #666;
    }

    .history-meta {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 0.0625rem solid #f0f0f0;
        font-size: 0.75rem;
        color: #999;
    }

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #999;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ddd;
        margin-bottom: 1rem;
    }

    .empty-state p {
        font-size: 0.9375rem;
        line-height: 1.6;
    }

    .btn-shift {
        display: inline-block;
        margin-top: 1rem;
        padding: 0.75rem 2rem;
        background: #0f6b3d;
        color: white;
        text-decoration: none;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background 0.2s;
    }

    .btn-shift:hover {
        background: #0c5530;
    }

    /* レスポンシブ */
    @media (max-width: 30rem) {
        .container {
            padding: 0.75rem;
        }

        .history-header {
            padding: 0.75rem;
        }

        .history-card {
            padding: 0.875rem;
        }

        .history-content {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="history-header">
    <h1>📋 シフト提出履歴</h1>
    <a href="cast_shift.php" class="back-link">← シフト提出画面に戻る</a>
</div>

<div class="container">
    <?php if (empty($history)): ?>
        <div class="empty-state">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📅</div>
            <p>
                まだシフトを提出していません。<br>
                シフト提出画面から登録してください。
            </p>
            <a href="cast_shift.php" class="btn-shift">シフトを提出する</a>
        </div>
    <?php else: ?>
        <?php foreach ($history as $item): ?>
            <div class="history-card">
                <div class="history-card-header">
                    <div class="history-date"><?= htmlspecialchars($item['date_formatted']) ?></div>
                    <span class="status-badge status-<?= htmlspecialchars($item['status']) ?>">
                        <?php
                        switch ($item['status']) {
                            case 'pending':
                                echo '承認待ち';
                                break;
                            case 'approved':
                                echo '承認済み';
                                break;
                            case 'rejected':
                                echo '却下';
                                break;
                        }
                        ?>
                    </span>
                </div>

                <div class="history-content">
                    <div class="history-item">
                        <div class="history-label">出勤時間</div>
                        <div class="history-value">
                            <?php
                            // 0～6時の場合は24を加算して表示
                            $in_time = new DateTime($item['in_time']);
                            $in_hour = (int)$in_time->format('H');
                            if ($in_hour < 7) {
                                $in_hour += 24;
                            }
                            echo sprintf('%02d:%s', $in_hour, $in_time->format('i'));
                            ?>
                        </div>
                    </div>
                    <div class="history-item">
                        <div class="history-label">退勤時間</div>
                        <div class="history-value">
                            <?php
                            $out_time = new DateTime($item['out_time']);
                            $out_hour = (int)$out_time->format('H');
                            if ($out_hour < 7) {
                                $out_hour += 24;
                            }
                            echo sprintf('%02d:%s', $out_hour, $out_time->format('i'));
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($item['LO'] == 1): ?>
                    <div style="margin-bottom: 0.75rem;">
                        <span class="lo-badge">ラストオーダー可</span>
                    </div>
                <?php endif; ?>

                <?php if ($item['status'] === 'rejected' && !empty($item['reject_reason'])): ?>
                    <div class="reject-reason">
                        <div class="reject-reason-label">却下理由:</div>
                        <div class="reject-reason-text"><?= nl2br(htmlspecialchars($item['reject_reason'])) ?></div>
                    </div>
                <?php endif; ?>

                <div class="history-meta">
                    提出日時: <?= htmlspecialchars($item['created_at_formatted']) ?>
                    <?php if ($item['status'] === 'approved' && !empty($item['approved_at'])): ?>
                        <br>承認日時: <?= htmlspecialchars($item['approved_at_formatted']) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

</body>
</html>