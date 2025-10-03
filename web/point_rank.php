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
$user_name = $_SESSION['USER']['user_name'] ?? 'ゲスト';

// 現在のポイント残高取得
$stmt = $pdo->prepare("SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1");
$stmt->execute([':tel' => $tel]);
$current_point = $stmt->fetchColumn() ?: 0;

// セッションからランク情報を取得
$usage_count = $_SESSION['RANK']['usage_count'] ?? 0;
$current_rank_name = $_SESSION['RANK']['current_rank'] ?? 'ビギナー';
$next_rank_name = $_SESSION['RANK']['next_rank'] ?? null;
$next_required_count = $_SESSION['RANK']['next_required_count'] ?? null;
$next_rankup_bonus = $_SESSION['RANK']['next_rankup_bonus'] ?? null;
$monthly_bonus = $_SESSION['RANK']['monthly_bonus'] ?? 0;
// 今月の利用回数を取得
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT COUNT(*) as monthly_count 
    FROM reserve 
    WHERE tel = :tel 
    AND done >= 2 
    AND DATE_FORMAT(date, '%Y-%m') = :current_month
");
$stmt->execute([
    ':tel' => $tel,
    ':current_month' => $current_month
]);
$monthly_usage_count = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_count'];
$monthly_remaining = max(0, 3 - $monthly_usage_count);
// ランククラス名を取得


// 現在のランク特典を取得
function getCurrentRankBenefits($rank_name, $monthly_bonus) {
    $base_benefits = [
        'ビギナー' => ['基本サービス'],
        'ブロンズ' => ['マンスリーボーナス ' . $monthly_bonus . 'P'],
        'シルバー' => ['マンスリーボーナス ' . $monthly_bonus . 'P'],
        'ゴールド' => ['マンスリーボーナス ' . $monthly_bonus . 'P'],
        'ダイヤモンド' => ['マンスリーボーナス ' . $monthly_bonus . 'P']
    ];
    return $base_benefits[$rank_name] ?? ['基本サービス'];
}


// 次のランク特典を取得
function getNextRankBenefits($pdo, $next_rank_name) {
    if (!$next_rank_name) return [];
    
    $stmt = $pdo->prepare("SELECT rankup_bonus, monthly_bonus FROM rank WHERE rank_name = :rank_name LIMIT 1");
    $stmt->execute([':rank_name' => $next_rank_name]);
    $next_rank = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$next_rank) return [];
    
    return [
        'ランクアップボーナス ' . number_format($next_rank['rankup_bonus']) . 'P獲得',
        'マンスリーボーナス ' . $next_rank['monthly_bonus'] . 'Pに増額'
    ];
}

$rank_class = getRankClass($current_rank_name);
$current_benefits = getCurrentRankBenefits($current_rank_name, $monthly_bonus);
$next_count = $next_required_count ? ($next_required_count - $usage_count) : null;

// ★★★ ここが修正ポイント ★★★
// $rank_info 配列を作成
$rank_info = [
    'name' => $current_rank_name,
    'class' => $rank_class
];

// 最近のポイント履歴(5件)
$stmt = $pdo->prepare("
    SELECT type, point, balance_after, created 
    FROM point 
    WHERE tel = :tel 
    ORDER BY created DESC 
    LIMIT 5
");
$stmt->execute([':tel' => $tel]);
$recent_history = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

<link href="./css/point_rank.css" rel="stylesheet">

<div class="container point-rank-container">
    
    <!-- ランク情報カード -->
    <div class="rank-card <?= $rank_info['class'] ?>">
        <div class="rank-badge">
            <i class="bi bi-award-fill"></i>
            <span class="rank-name"><?= $rank_info['name'] ?>会員</span>
        </div>
        <div class="user-info">
            <p><?= htmlspecialchars($user_name) ?> 様</p>
            <p class="usage-count">ご利用回数: <strong><?= $usage_count ?>回</strong></p>
        </div>
    </div>

    <!-- ランクアップ進捗 -->
    <?php if ($next_rank_name): ?>
    <div class="progress-card">
        <h2 class="section-title">
            <i class="bi bi-graph-up-arrow"></i>
            ランクアップまで
        </h2>
        <div class="progress-info">
            <p class="next-rank-text">
                あと<strong class="highlight"><?= $next_count ?>回</strong>のご利用で
                <strong class="next-rank"><?= htmlspecialchars($next_rank_name) ?></strong>にランクアップ!
            </p>
            <div class="progress-bar-container">
                <?php 
                $progress = ($usage_count / $next_required_count) * 100;
                ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                </div>
                <p class="progress-text"><?= $usage_count ?> / <?= $next_required_count ?>回</p>
            </div>
        </div>
        
        <!-- 次のランクの特典 -->
        <div class="next-benefits">
            <h3>ランクアップで得られる特典</h3>
            <ul>
                <?php foreach (getNextRankBenefits($pdo, $next_rank_name) as $benefit): ?>
                <li><i class="bi bi-gift-fill"></i> <?= htmlspecialchars($benefit) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php else: ?>
    <div class="progress-card max-rank">
        <h2 class="section-title">
            <i class="bi bi-trophy-fill"></i>
            最高ランク達成!
        </h2>
        <p class="congrats-text">おめでとうございます!最高ランクのダイヤモンド会員です</p>
    </div>
    <?php endif; ?>

    <!-- ポイント情報カード -->
    <div class="point-card">
        <h2 class="section-title">
            <i class="bi bi-coin"></i>
            保有ポイント
        </h2>
        <div class="point-display">
            <span class="point-value"><?= number_format($current_point) ?></span>
            <span class="point-unit">P</span>
        </div>
    </div>

    <!-- 現在のランク特典 -->
<div class="benefits-card">
    <h2 class="section-title">
        <i class="bi bi-star-fill"></i>
        現在のランク特典
    </h2>
    <ul class="benefits-list">
        <?php foreach ($current_benefits as $benefit): ?>
        <li><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($benefit) ?></li>
        <?php endforeach; ?>
    </ul>
    
    <?php if ($monthly_bonus > 0): ?>
<div class="monthly-bonus-progress">
    <h3 class="bonus-title">
        <i class="bi bi-calendar-check"></i> マンスリーボーナス獲得条件
    </h3>
    <?php if ($monthly_remaining > 0): ?>
    <div class="bonus-remaining">
        <p class="bonus-text">
            今月あと<span class="bonus-count"><?= $monthly_remaining ?></span>回のご利用で
            <span class="bonus-points"><?= $monthly_bonus ?>P</span>獲得!
        </p>
        <div class="usage-status">
            今月の利用: <strong><?= $monthly_usage_count ?>/3回</strong>
        </div>
    </div>
    <?php else: ?>
    <div class="bonus-achieved">
        <p class="achieved-text">
            <i class="bi bi-check-circle-fill"></i> 今月の獲得条件達成済み!
        </p>
        <p class="achieved-detail">
            次月初めに<strong><?= $monthly_bonus ?>P</strong>が付与されます
        </p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>

    <!-- ポイント履歴(簡易版) -->
    <div class="history-card">
        <h2 class="section-title">
            <i class="bi bi-clock-history"></i>
            最近のポイント履歴
        </h2>
        <?php if (!empty($recent_history)): ?>
        <div class="history-list">
            <?php foreach ($recent_history as $history): ?>
            <?php $type_info = getTypeDisplay($history['type']); ?>
            <div class="history-item">
                <div class="history-left">
                    <span class="history-type <?= $type_info['class'] ?>">
                        <i class="<?= $type_info['icon'] ?>"></i>
                        <?= $type_info['name'] ?>
                    </span>
                    <span class="history-date">
                        <?= date('Y/m/d H:i', strtotime($history['created'])) ?>
                    </span>
                </div>
                <div class="history-right">
                    <span class="history-point <?= $history['type'] === 'use' ? 'minus' : 'plus' ?>">
                        <?= $history['type'] === 'use' ? '-' : '+' ?><?= abs($history['point']) ?>P
                    </span>
                    <span class="history-balance">残高: <?= number_format($history['balance_after']) ?>P</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="point_history.php" class="btn-view-all">
            すべてのポイント履歴を見る <i class="bi bi-arrow-right"></i>
        </a>
        <?php else: ?>
        <p class="no-history">まだポイント履歴がありません</p>
        <?php endif; ?>
    </div>

    <!-- リンク -->
    <div class="links-card">
        <a href="rank_detail.php" class="info-link">
            <i class="bi bi-info-circle-fill"></i>
            ランク制度詳細
        </a>
    </div>



<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>