<?php 
include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: index.php');
    exit;
}

// DB接続
$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// 基準日を取得
$base_date = get_BaseDate();
$now = new DateTime();

// 今週の範囲を計算
$week_start = new DateTime();
$week_start->setISODate((int)$now->format('o'), (int)$now->format('W'));
$week_end = clone $week_start;
$week_end->modify('+6 days');

// フィルター取得
$filter = $_GET['filter'] ?? 'all';

// k_check='y'の女の子を全取得
$sql = "SELECT * FROM girl WHERE k_check = 'y'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$all_girls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// お気に入り取得
$sql_fav = "SELECT g_login_id FROM favorite_girls WHERE tel = :tel";
$stmt_fav = $pdo->prepare($sql_fav);
$stmt_fav->bindValue(':tel', $tel, PDO::PARAM_STR);
$stmt_fav->execute();
$favorites = $stmt_fav->fetchAll(PDO::FETCH_COLUMN);

// ご利用済み取得（done >= 2）
$sql_used = "SELECT DISTINCT g_login_id FROM reserve WHERE tel = :tel AND done >= 2";
$stmt_used = $pdo->prepare($sql_used);
$stmt_used->bindValue(':tel', $tel, PDO::PARAM_STR);
$stmt_used->execute();
$used_girls = $stmt_used->fetchAll(PDO::FETCH_COLUMN);

// シフトデータ取得（今日〜今週末）
$sql_shift = "SELECT * FROM shift WHERE date BETWEEN :today AND :week_end";
$stmt_shift = $pdo->prepare($sql_shift);
$stmt_shift->bindValue(':today', $base_date, PDO::PARAM_STR);
$stmt_shift->bindValue(':week_end', $week_end->format('Y-m-d'), PDO::PARAM_STR);
$stmt_shift->execute();
$shifts = $stmt_shift->fetchAll(PDO::FETCH_ASSOC);

// 予約データ取得（本日のみ）
$sql_reserve = "SELECT * FROM reserve WHERE date = :date";
$stmt_reserve = $pdo->prepare($sql_reserve);
$stmt_reserve->bindValue(':date', $base_date, PDO::PARAM_STR);
$stmt_reserve->execute();
$reserves = $stmt_reserve->fetchAll(PDO::FETCH_ASSOC);

// 各女の子のデータを整理
$girls_data = [];

foreach ($all_girls as $girl) {
    $g_id = $girl['g_login_id'];
    
    // この子のシフトを抽出
    $girl_shifts = array_filter($shifts, function($s) use ($g_id) {
        return $s['g_login_id'] == $g_id;
    });
    
    // 今日のシフトがあるか
    $today_shift = null;
    foreach ($girl_shifts as $shift) {
        if ($shift['date'] == $base_date) {
            $today_shift = $shift;
            break;
        }
    }
    
    // 今週のシフトがあるか（今日以降）
    $has_week_shift = !empty($girl_shifts);
    
    // お気に入りか
    $is_favorite = in_array($g_id, $favorites);
    
    // ご利用済みか
    $is_used = in_array($g_id, $used_girls);
    
    // フィルタリング判定
    $should_display = false;
    switch ($filter) {
        case 'all':
            $should_display = true;
            break;
        case 'today':
            $should_display = ($today_shift !== null);
            break;
        case 'week':
            $should_display = $has_week_shift;
            break;
        case 'favorite':
            $should_display = $is_favorite;
            break;
        case 'used':
            $should_display = $is_used;
            break;
    }
    
    if (!$should_display) continue;
    
    // === ステータスと時間の判定 ===
    $status_label = '';
    $time_label = '';
    $time_color = 'black';
    $priority = 999; // デフォルト優先度
    $next_shift_date = null;
    
    if ($today_shift) {
        $shift_in = new DateTime($today_shift['in_time']);
        $shift_out = new DateTime($today_shift['out_time']);
        $LO = (int)$today_shift['LO'];
        
        // 出勤終了チェック
        $is_shift_ended = ($now >= $shift_out);
        
        if (!$is_shift_ended) {
            // 「今すぐOK」判定
            $course_time = 60;
            $prep_time = 10;
            $total_time = $course_time + 2 * $prep_time;
            
            $slot_start = clone $now;
            $slot_end = clone $now;
            $slot_end->modify("+{$total_time} minutes");
            
            $shift_in_prep = clone $shift_in;
            $shift_in_prep->modify('-10 minutes');
            
            $is_available = true;
            
            if ($slot_start < $shift_in_prep) {
                $is_available = false;
            }
            
            if ($LO == 0 && $slot_end > $shift_out) {
                $is_available = false;
            }
            
            if ($is_available) {
                foreach ($reserves as $res) {
                    if ($res['g_login_id'] != $g_id) continue;
                    
                    $res_start = new DateTime($res['start_time']);
                    $res_end = new DateTime($res['end_time']);
                    
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
                $status_label = 'Today';
                $priority = 2;
            }
            
            // 時間表示
            $is_working = ($now >= $shift_in && $now < $shift_out);
            if ($is_working) {
                $time_label = '〜' . $shift_out->format('H:i');
                $time_color = 'green';
            } else {
                $time_label = $shift_in->format('H:i') . '〜';
                $time_color = 'blue';
            }
        }
    } else if ($has_week_shift) {
        // 今週の最も近いシフトを取得
        $future_shifts = array_filter($girl_shifts, function($s) use ($base_date) {
            return $s['date'] > $base_date;
        });
        
        if (!empty($future_shifts)) {
            usort($future_shifts, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
            
            $next_shift = $future_shifts[0];
            $next_shift_date = new DateTime($next_shift['date']);
            $days_diff = $now->diff($next_shift_date)->days;
            
            $time_label = $next_shift_date->format('m/d');
            $time_color = 'black';
            $priority = 100 + $days_diff; // 日付が近いほど優先
        }
    }
    
    // データ格納
    $girls_data[] = [
        'g_login_id' => $g_id,
        'img' => $girl['img'],
        'name' => $girl['name'],
        'head_comment' => $girl['head_comment'],
        'status' => $status_label,
        'time' => $time_label,
        'time_color' => $time_color,
        'priority' => $priority,
        'is_favorite' => $is_favorite
    ];
}

// ソート
usort($girls_data, function($a, $b) {
    return $a['priority'] - $b['priority'];
});

?>
<link href="./css/girlstable.css" rel="stylesheet">

<div class="girls-container">
    
    <!-- ヘッダー -->
    <div class="girls-header">
        <h1 class="section-title">
            <i class="bi bi-person-hearts"></i> 女の子一覧
        </h1>
    </div>
    
    <!-- 絞り込み・検索エリア -->
    <div class="filter-search-area">
        <div class="filter-select-wrapper">
            <i class="bi bi-funnel-fill"></i>
            <select id="filterSelect" class="filter-select" onchange="location.href='?filter='+this.value">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>すべて</option>
                <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>今日出勤</option>
                <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>今週出勤</option>
                <option value="favorite" <?= $filter === 'favorite' ? 'selected' : '' ?>>お気に入り</option>
                <option value="used" <?= $filter === 'used' ? 'selected' : '' ?>>ご利用済み</option>
            </select>
        </div>
        
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="名前で検索...">
        </div>
    </div>
    
    <!-- 女の子一覧 -->
    <div class="girls-list" id="girlsList">
        <?php if (!empty($girls_data)): ?>
            <?php foreach ($girls_data as $girl): ?>
            <a href="girl_detail.php?g_login_id=<?= urlencode($girl['g_login_id']) ?>" 
               class="gcard-link" 
               data-name="<?= htmlspecialchars($girl['name']) ?>">
                <div class="gcard">
                    <?php if ($girl['is_favorite']): ?>
                    <i class="bi bi-heart-fill favorite-icon"></i>
                    <?php endif; ?>
                    
                    <img class="gcardimg" 
                         src="../../img/icon/<?= htmlspecialchars($girl['img']) ?>" 
                         alt="<?= htmlspecialchars($girl['name']) ?>">
                    
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
                        
                        <?php if ($girl['time']): ?>
                        <span class="time time-<?= $girl['time_color'] ?>">
                            <?= htmlspecialchars($girl['time']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-girls">該当するキャストがいません</p>
        <?php endif; ?>
    </div>
    
    <!-- 検索候補エリア -->
    <div class="search-results" id="searchResults" style="display: none;"></div>
    
</div>

<script>
// リアルタイム検索機能
const searchInput = document.getElementById('searchInput');
const girlsList = document.getElementById('girlsList');
const searchResults = document.getElementById('searchResults');
const allCards = Array.from(document.querySelectorAll('.gcard-link'));

searchInput.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    
    if (query === '') {
        // 検索クリア時は全て表示
        girlsList.style.display = 'block';
        searchResults.style.display = 'none';
        allCards.forEach(card => card.style.display = 'block');
        return;
    }
    
    // 検索にマッチするカードを抽出
    const matchedCards = allCards.filter(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        return name.includes(query);
    });
    
    if (matchedCards.length > 0) {
        // 元のリストは非表示
        girlsList.style.display = 'none';
        
        // 候補エリアに表示
        searchResults.style.display = 'block';
        searchResults.innerHTML = '';
        
        matchedCards.forEach(card => {
            const clone = card.cloneNode(true);
            searchResults.appendChild(clone);
        });
    } else {
        // マッチなし
        girlsList.style.display = 'none';
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<p class="no-girls">該当するキャストがいません</p>';
    }
});
</script>

<?php include 'footer.php'; ?>