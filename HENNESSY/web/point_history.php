<?php

include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// フィルター取得
$filter_month = $_GET['month'] ?? '';
$filter_type = $_GET['type'] ?? 'all'; // all, earn, use

// SQL構築
$sql = "SELECT type, point, balance_after, balance_before, created FROM point WHERE tel = :tel";
$params = [':tel' => $tel];

// 月フィルター
if ($filter_month) {
    $sql .= " AND DATE_FORMAT(created, '%Y-%m') = :month";
    $params[':month'] = $filter_month;
}

// タイプフィルター
if ($filter_type === 'use') {
    $sql .= " AND type = 'use'";
} elseif ($filter_type === 'rankup') {
    $sql .= " AND type = 'rankup'";
} elseif ($filter_type === 'rank') {
    $sql .= " AND type = 'rank'";
} elseif ($filter_type === 'refund') {
    $sql .= " AND type = 'refund'";
}
$sql .= " ORDER BY created DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 月のリスト取得(過去12ヶ月)
$months = [];
for ($i = 0; $i < 12; $i++) {
    $date = new DateTime();
    $date->modify("-{$i} month");
    $months[] = $date->format('Y-m');
}

// 統計
$total_earned = 0;
$total_used = 0;
$total_rankup = 0;
$total_rank_bonus = 0;
$total_refund = 0;

foreach ($history_list as $item) {
    $point_abs = abs($item['point']);
    if ($item['type'] === 'use') {
        $total_used += $point_abs;
    } elseif ($item['type'] === 'rankup') {
        $total_rankup += $point_abs;
        $total_earned += $point_abs;
    } elseif ($item['type'] === 'rank') {
        $total_rank_bonus += $point_abs;
        $total_earned += $point_abs;
    } elseif ($item['type'] === 'refund') {
        $total_refund += $point_abs;
        $total_earned += $point_abs;
    }
}
// typeの表示名を取得する関数
function getTypeDisplay($type) {
    $types = [
        'use' => ['name' => '使用', 'class' => 'use', 'icon' => 'bi-cart-fill'],
        'rankup' => ['name' => 'ランクアップ報酬', 'class' => 'rankup', 'icon' => 'bi-trophy-fill'],
        'rank' => ['name' => 'ランクボーナス', 'class' => 'rank', 'icon' => 'bi-gift-fill'],
        'refund' => ['name' => 'キャンセル返還', 'class' => 'refund', 'icon' => 'bi-arrow-counterclockwise'],
        'signup_bonus' => ['name' => '新規登録ボーナス', 'class' => 'signup', 'icon' => 'bi-star-fill']  // ← この行を追加
    ];
    return $types[$type] ?? ['name' => $type, 'class' => 'other', 'icon' => 'bi-coin'];
}

?>

<link href="./css/point_history.css" rel="stylesheet">

<div class="container history-container">
    
    <h1 class="page-title">
        <i class="bi bi-clock-history"></i>
        ポイント履歴
    </h1>

    <!-- 統計カード -->
    <div class="stats-card">
        <div class="stat-item earn">
            <span class="stat-label">累計獲得</span>
            <span class="stat-value">+<?= number_format($total_earned) ?>P</span>
            <span class="stat-detail">ランクアップ: <?= number_format($total_rankup) ?>P</span>
            <span class="stat-detail">ランクボーナス: <?= number_format($total_rank_bonus) ?>P</span>
        </div>
        <div class="stat-item use">
            <span class="stat-label">累計使用</span>
            <span class="stat-value">-<?= number_format($total_used) ?>P</span>
        </div>
    </div>

    <!-- フィルターカード -->
    <div class="filter-card">
        <form method="get" action="" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="month"><i class="bi bi-calendar3"></i> 月で絞り込み</label>
                    <select name="month" id="month" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">すべて</option>
                        <?php foreach ($months as $month): ?>
                        <option value="<?= $month ?>" <?= $filter_month === $month ? 'selected' : '' ?>>
                            <?= date('Y年m月', strtotime($month . '-01')) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="type"><i class="bi bi-funnel"></i> タイプで絞り込み</label>
                    <select name="type" id="type" class="filter-select" onchange="document.getElementById('filterForm').submit()">
    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>すべて</option>
    <option value="use" <?= $filter_type === 'use' ? 'selected' : '' ?>>使用</option>
    <option value="rankup" <?= $filter_type === 'rankup' ? 'selected' : '' ?>>ランクアップ報酬</option>
    <option value="rank" <?= $filter_type === 'rank' ? 'selected' : '' ?>>ランクボーナス</option>
    <option value="refund" <?= $filter_type === 'refund' ? 'selected' : '' ?>>キャンセル返還</option>
</select>
                </div>
            </div>

            <?php if ($filter_month || $filter_type !== 'all'): ?>
            <a href="point_history.php" class="btn-reset">
                <i class="bi bi-x-circle"></i> フィルターをクリア
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 履歴リスト -->
    <div class="history-card">
        <?php if (!empty($history_list)): ?>
        <div class="history-list">
            <?php foreach ($history_list as $item): ?>
            <?php $type_info = getTypeDisplay($item['type']); ?>
            <div class="history-item">
                <div class="history-header">
                    <span class="history-type <?= $type_info['class'] ?>">
                        <i class="<?= $type_info['icon'] ?>"></i>
                        <?= $type_info['name'] ?>
                    </span>
                    <span class="history-date">
                        <?= date('Y年m月d日 H:i', strtotime($item['created'])) ?>
                    </span>
                </div>
                <div class="history-body">
                    <div class="history-point <?= $item['type'] === 'use' ? 'minus' : 'plus' ?>">
                        <?= $item['type'] === 'use' ? '-' : '+' ?><?= abs($item['point']) ?>P
                    </div>
                    <div class="history-balance">
                        <span class="balance-label">残高:</span>
                        <span class="balance-before"><?= number_format($item['balance_before']) ?>P</span>
                        <i class="bi bi-arrow-right"></i>
                        <span class="balance-after"><?= number_format($item['balance_after']) ?>P</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="result-count">
            <?= count($history_list) ?>件の履歴が見つかりました
        </div>
        <?php else: ?>
        <div class="no-history">
            <i class="bi bi-inbox"></i>
            <p>履歴が見つかりませんでした</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 戻るボタン -->
    <a href="point_rank.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> ポイント・ランクページに戻る
    </a>

</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>