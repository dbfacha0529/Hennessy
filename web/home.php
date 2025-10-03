<?php 
include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

// DB接続
$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];
$user_name = $_SESSION['USER']['user_name'] ?? 'ゲスト';

// 基準日を取得
$base_date = get_BaseDate();

// 現在時刻
$now = new DateTime();

// シフトデータ取得
$sql = "SELECT s.*, g.* FROM shift s 
        JOIN girl g ON s.g_login_id = g.g_login_id 
        WHERE s.date = :date";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':date', $base_date, PDO::PARAM_STR);
$stmt->execute();
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 予約データ取得
$sql_reserve = "SELECT * FROM reserve WHERE date = :date";
$stmt_reserve = $pdo->prepare($sql_reserve);
$stmt_reserve->bindValue(':date', $base_date, PDO::PARAM_STR);
$stmt_reserve->execute();
$reserves = $stmt_reserve->fetchAll(PDO::FETCH_ASSOC);

// 各女の子の状態を判定
$girls_data = [];

foreach ($shifts as $shift) {
    $girl_data = [];
    $girl_data['img'] = $shift['img'];
    $girl_data['name'] = $shift['name'];
    $girl_data['head_comment'] = $shift['head_comment'];
    $girl_data['g_login_id'] = $shift['g_login_id'];
    
    $shift_in = new DateTime($shift['in_time']);
    $shift_out = new DateTime($shift['out_time']);
    $LO = (int)$shift['LO'];
    
    // === 右上のステータス判定 ===
    $status_label = '';
    $priority = 3; // 3=本日出勤, 2=出勤中, 1=今すぐOK
    
    // 出勤が終了しているかチェック
    $is_shift_ended = ($now >= $shift_out);
    
    if ($is_shift_ended) {
        continue; // 出勤終了した子は表示しない
    }
    
    // 「今すぐOK」判定（60分コース可能か）
    $course_time = 60;
    $prep_time = 10;
    $total_time = $course_time + 2 * $prep_time; // 80分
    
    $slot_start = clone $now;
    $slot_end = clone $now;
    $slot_end->modify("+{$total_time} minutes");
    
    // シフト開始10分前から受付可能
    $shift_in_prep = clone $shift_in;
    $shift_in_prep->modify('-10 minutes');
    
    $is_available = true;
    
    // 1. シフト開始前チェック
    if ($slot_start < $shift_in_prep) {
        $is_available = false;
    }
    
    // 2. シフト終了時間チェック（LO=0の場合のみ）
    if ($LO == 0 && $slot_end > $shift_out) {
        $is_available = false;
    }
    
    // 3. 予約重複チェック
    if ($is_available) {
        foreach ($reserves as $res) {
            if ($res['g_login_id'] != $shift['g_login_id']) continue;
            
            $res_start = new DateTime($res['start_time']);
            $res_end = new DateTime($res['end_time']);
            
            // 重複判定
            if (!($slot_end <= $res_start || $slot_start >= $res_end)) {
                $is_available = false;
                break;
            }
        }
    }
    
    if ($is_available) {
        $status_label = '今すぐOK';
        $priority = 1;
    } else {
        // 出勤中かどうか
        $is_working = ($now >= $shift_in && $now < $shift_out);
        if ($is_working) {
            $status_label = 'Today';
            $priority = 2;
        } else {
            $status_label = 'Today';
            $priority = 3;
        }
    }
    
    $girl_data['status'] = $status_label;
    $girl_data['priority'] = $priority;
    
    // === 右下の時間表示判定 ===
    $time_label = '';
    
    // 出勤中かどうか
    $is_working = ($now >= $shift_in && $now < $shift_out);
    
    if ($is_working) {
        // 出勤中 → シフト終了時間
        $time_label = '～' . $shift_out->format('H:i');
    } elseif ($now < $shift_in) {
        // 未出勤だが本日これから出勤 → シフト開始時間
        $time_label = $shift_in->format('H:i') . '～';
    }
    
    $girl_data['time'] = $time_label;
    
    $girls_data[] = $girl_data;
}

// 優先度でソート
usort($girls_data, function($a, $b) {
    return $a['priority'] - $b['priority'];
});

// 最大3名まで取得
$top_girls = array_slice($girls_data, 0, 3);

// ランク情報取得
$current_point = 0;
$stmt = $pdo->prepare("SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1");
$stmt->execute([':tel' => $tel]);
$current_point = $stmt->fetchColumn() ?: 0;

$usage_count = $_SESSION['RANK']['usage_count'] ?? 0;
$current_rank_name = $_SESSION['RANK']['current_rank'] ?? 'ビギナー';
$next_rank_name = $_SESSION['RANK']['next_rank'] ?? null;
$next_required_count = $_SESSION['RANK']['next_required_count'] ?? null;
$monthly_bonus = $_SESSION['RANK']['monthly_bonus'] ?? 0;

// 今月の利用回数
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

// 店舗情報取得
$stmt = $pdo->prepare("SELECT logo, tel FROM shop LIMIT 1");
$stmt->execute();
$shop_info = $stmt->fetch(PDO::FETCH_ASSOC);
$shop_logo = $shop_info['logo'] ?? 'default_logo.png';
$shop_tel = $shop_info['tel'] ?? '000-0000-0000';


$rank_class = getRankClass($current_rank_name);
$next_count = $next_required_count ? ($next_required_count - $usage_count) : null;

?>
<!--オリジナルCSS-->
<link href="./css/home.css" rel="stylesheet">

<div class="home-container">
    
    <!-- 今日出勤の女の子 -->
    <section class="section-girls">
        <h2 class="section-title">
            <i class="bi bi-calendar-heart"></i> 本日出勤
        </h2>
        
        <?php if (!empty($top_girls)): ?>
            <?php foreach ($top_girls as $girl): ?>
            <a href="girl_detail.php?g_login_id=<?= urlencode($girl['g_login_id']) ?>" class="gcard-link">
                <div class="gcard">
                    <img class="gcardimg" src="../img/<?= htmlspecialchars($girl['img']) ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
                    
                    <div class="gcard-center">
                        <span class="name"><?= htmlspecialchars($girl['name']) ?></span>
                        <span class="headcomment"><?= htmlspecialchars($girl['head_comment']) ?></span>
                    </div>
                    
                    <div class="gcard-right">
                        <?php if ($girl['status']): ?>
                        <span class="status status-<?= $girl['status'] === '今すぐOK' ? 'now' : 'today' ?>">
                            <?= htmlspecialchars($girl['status']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="time"><?= htmlspecialchars($girl['time']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
            
            <a href="girlstable.php?filter=today" class="btn-more">
                もっと見る <i class="bi bi-arrow-right"></i>
            </a>
        <?php else: ?>
            <p class="no-girls">本日出勤予定のキャストはいません</p>
        <?php endif; ?>
    </section>

    <!-- 会員ランク・ポイントカード -->
    <section class="section-rank">
        <h2 class="section-title">
            <i class="bi bi-award"></i> 会員ランク・ポイント
        </h2>
        
        <div class="rank-card-simple <?= $rank_class ?>">
            <div class="rank-header">
                <div class="rank-badge">
                    <i class="bi bi-award-fill"></i>
                    <span><?= htmlspecialchars($current_rank_name) ?></span>
                </div>
                <div class="point-display">
                    <span class="point-value"><?= number_format($current_point) ?></span>
                    <span class="point-unit">P</span>
                </div>
            </div>
            
            <?php if ($next_rank_name): ?>
            <div class="rank-progress">
                <p class="progress-text">
                    <strong><?= htmlspecialchars($next_rank_name) ?></strong>まであと<strong><?= $next_count ?></strong>回
                </p>
                <div class="progress-bar-container">
                    <?php $progress = ($usage_count / $next_required_count) * 100; ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($monthly_bonus > 0 && $monthly_remaining > 0): ?>
            <div class="monthly-bonus">
                <i class="bi bi-gift"></i>
                今月あと<strong><?= $monthly_remaining ?></strong>回で<strong><?= $monthly_bonus ?>P</strong>獲得
            </div>
            <?php endif; ?>
        </div>
        
        <a href="point_rank.php" class="btn-detail">
            詳しく見る <i class="bi bi-arrow-right"></i>
        </a>
    </section>

    <!-- リンクセクション -->
    <section class="section-links">
        <a href="account.php" class="link-card">
            <i class="bi bi-person-circle"></i>
            <span>アカウント情報</span>
            <i class="bi bi-chevron-right"></i>
        </a>
        
        <a href="favorites.php" class="link-card">
            <i class="bi bi-heart-fill" ></i>
            <span>お気に入り</span>
            <i class="bi bi-chevron-right"></i>
        </a>
        
        <a href="contact.php" class="link-card">
            <i class="bi bi-envelope"></i>
            <span>お問い合わせ</span>
            <i class="bi bi-chevron-right"></i>
        </a>
    </section>

    <!-- 店舗情報 -->
    <section class="section-shop">
        <a href="tel:<?= htmlspecialchars($shop_tel) ?>" class="shop-tel">
            <i class="bi bi-telephone-fill"></i>
            <?= htmlspecialchars($shop_tel) ?>
        </a>
    </section>

</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>