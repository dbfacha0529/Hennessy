<?php 
include 'header.php';
include 'base_date_functions.php';

// g_login_idチェック
if (empty($_GET['g_login_id'])) {
    header('Location: favorites.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_GET['g_login_id'];

// 女の子の情報を取得
$sql = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmt->execute();
$girl = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$girl) {
    header('Location: favorites.php');
    exit;
}

// 基準日取得
$baseDate = getBaseDate();
$now = new DateTime();

// 今日のシフト情報取得
$sqlToday = "SELECT in_time, out_time, LO FROM shift WHERE g_login_id = :g_login_id AND date = :base_date LIMIT 1";
$stmtToday = $pdo->prepare($sqlToday);
$stmtToday->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmtToday->bindValue(':base_date', $baseDate, PDO::PARAM_STR);
$stmtToday->execute();
$todayShift = $stmtToday->fetch(PDO::FETCH_ASSOC);

// 予約データ取得
$sql_reserve = "SELECT * FROM reserve WHERE date = :date AND g_login_id = :g_login_id";
$stmt_reserve = $pdo->prepare($sql_reserve);
$stmt_reserve->bindValue(':date', $baseDate, PDO::PARAM_STR);
$stmt_reserve->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmt_reserve->execute();
$reserves = $stmt_reserve->fetchAll(PDO::FETCH_ASSOC);

// ステータスと時間表示の判定 (home.phpと同じロジック)
$status_label = '';
$time_label = '';
$has_today_shift = false;

if ($todayShift) {
    $has_today_shift = true;
    $shift_in = new DateTime($todayShift['in_time']);
    $shift_out = new DateTime($todayShift['out_time']);
    $LO = (int)$todayShift['LO'];
    
    // 出勤が終了しているかチェック
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
        
        // シフト開始前チェック
        if ($slot_start < $shift_in_prep) {
            $is_available = false;
        }
        
        // シフト終了時間チェック
        if ($LO == 0 && $slot_end > $shift_out) {
            $is_available = false;
        }
        
        // 予約重複チェック
        if ($is_available) {
            foreach ($reserves as $res) {
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
        } else {
            $status_label = 'Today';
        }
        
        // 時間表示判定
        $is_working = ($now >= $shift_in && $now < $shift_out);
        
        if ($is_working) {
            $time_label = '～' . $shift_out->format('H:i');
        } elseif ($now < $shift_in) {
            $time_label = $shift_in->format('H:i') . '～';
        }
    }
}

// 本日シフトがない場合、直近未来の出勤日を取得
if (!$has_today_shift || empty($time_label)) {
    $sqlNext = "SELECT date FROM shift WHERE g_login_id = :g_login_id AND date > :base_date ORDER BY date ASC LIMIT 1";
    $stmtNext = $pdo->prepare($sqlNext);
    $stmtNext->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmtNext->bindValue(':base_date', $baseDate, PDO::PARAM_STR);
    $stmtNext->execute();
    $nextShift = $stmtNext->fetch(PDO::FETCH_ASSOC);
    
    if ($nextShift && empty($time_label)) {
        $date = new DateTime($nextShift['date']);
        $time_label = $date->format('n/j');
    }
}

// お気に入り状態チェック
$isFavorite = false;
if (!empty($_SESSION['USER']['tel'])) {
    $sqlFav = "SELECT COUNT(*) FROM favorite_girls WHERE tel = :tel AND g_login_id = :g_login_id";
    $stmtFav = $pdo->prepare($sqlFav);
    $stmtFav->bindValue(':tel', $_SESSION['USER']['tel'], PDO::PARAM_STR);
    $stmtFav->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmtFav->execute();
    $isFavorite = $stmtFav->fetchColumn() > 0;
}

// 画像配列をデコード
$images = json_decode($girl['imgs'], true) ?: [];

// 一問一答をデコード
$ichimon = json_decode($girl['ichimon'], true) ?: [];

// オプションをデコード
$options = json_decode($girl['option'], true) ?: [];

// 向こう一週間の出勤予定
$schedules = [];
for ($i = 0; $i < 7; $i++) {
    $date = new DateTime($baseDate);
    $date->modify("+$i day");
    $dateStr = $date->format('Y-m-d');
    
    $sqlSchedule = "SELECT in_time, out_time FROM shift WHERE g_login_id = :g_login_id AND date = :date LIMIT 1";
    $stmtSchedule = $pdo->prepare($sqlSchedule);
    $stmtSchedule->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmtSchedule->bindValue(':date', $dateStr, PDO::PARAM_STR);
    $stmtSchedule->execute();
    $shift = $stmtSchedule->fetch(PDO::FETCH_ASSOC);
    
    $schedules[] = [
        'date' => $date->format('n/j'),
        'dayOfWeek' => ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')],
        'hasShift' => (bool)$shift
    ];
}

// タイムライン最新1件取得
$sqlTimeline = "SELECT * FROM timeline_posts WHERE g_login_id = :g_login_id ORDER BY created_at DESC LIMIT 1";
$stmtTimeline = $pdo->prepare($sqlTimeline);
$stmtTimeline->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmtTimeline->execute();
$latestPost = $stmtTimeline->fetch(PDO::FETCH_ASSOC);

// 投稿がある場合、メディアを取得
$latestMedia = [];
if ($latestPost) {
    $sqlMedia = "SELECT * FROM timeline_media WHERE post_id = :post_id ORDER BY sort_order ASC";
    $stmtMedia = $pdo->prepare($sqlMedia);
    $stmtMedia->bindValue(':post_id', $latestPost['id'], PDO::PARAM_INT);
    $stmtMedia->execute();
    $latestMedia = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);
}

?>

<link href="./css/girl_detail.css" rel="stylesheet">

<div class="container">
    <!-- 上部カード (home.phpと同じスタイル) -->
    <a href="girl_detail.php?g_login_id=<?= urlencode($g_login_id) ?>" class="gcard-link">
        <div class="gcard">
            <img class="gcardimg" src="../img/<?= htmlspecialchars($girl['img']) ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
            
            <div class="gcard-center">
                <span class="name"><?= htmlspecialchars($girl['name']) ?></span>
                <span class="headcomment"><?= htmlspecialchars($girl['head_comment']) ?></span>
            </div>
            
            <div class="gcard-right">
                <?php if ($status_label): ?>
                <span class="status status-<?= $status_label === '今すぐOK' ? 'now' : 'today' ?>">
                    <?= htmlspecialchars($status_label) ?>
                </span>
                <?php endif; ?>
                <?php if ($time_label): ?>
                <span class="time"><?= htmlspecialchars($time_label) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>

    <!-- 画像スライドショー -->
    <?php if (!empty($images)): ?>
    <div class="slideshow-container">
        <div class="slideshow">
            <?php foreach ($images as $index => $img): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>">
                    <img src="../imgs/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button class="slide-btn prev" onclick="changeSlide(-1)">❮</button>
        <button class="slide-btn next" onclick="changeSlide(1)">❯</button>
        <div class="slide-dots">
            <?php foreach ($images as $index => $img): ?>
                <span class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="currentSlide(<?= $index ?>)"></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- お気に入りボタン -->
    <div class="favorite-section">
        <button class="favorite-btn-large <?= $isFavorite ? 'active' : '' ?>" 
                onclick="toggleFavorite('<?= htmlspecialchars($g_login_id) ?>', this)">
            <?php if ($isFavorite): ?>
                ♡ お気に入り登録済み
            <?php else: ?>
                ♡ お気に入りに追加
            <?php endif; ?>
        </button>
    </div>

    <!-- スタイル表示 -->
    <div class="section style-section">
        <h3 class="section-title">スタイル</h3>
        <div class="style-display">
            <div class="style-item">
                <span class="style-label">年齢</span>
                <span class="style-value"><?= htmlspecialchars($girl['age']) ?>歳</span>
            </div>
            <div class="style-item">
                <span class="style-label">身長</span>
                <span class="style-value"><?= htmlspecialchars($girl['high']) ?>cm</span>
            </div>
            <div class="style-item">
                <span class="style-label">B</span>
                <span class="style-value"><?= htmlspecialchars($girl['b']) ?></span>
            </div>
            <div class="style-item">
                <span class="style-label">W</span>
                <span class="style-value"><?= htmlspecialchars($girl['w']) ?></span>
            </div>
            <div class="style-item">
                <span class="style-label">H</span>
                <span class="style-value"><?= htmlspecialchars($girl['h']) ?></span>
            </div>
        </div>
    </div>

    <!-- タイムライン最新投稿 -->
    <div class="section timeline-section">
        <h3 class="section-title">タイムライン</h3>
        <div class="timeline-preview">
            <?php if ($latestPost): ?>
                <?php
                $created = new DateTime($latestPost['created_at']);
                ?>
                <div class="timeline-post">
                    <div class="post-time"><?= $created->format('Y/m/d H:i') ?></div>
                    <div class="post-content"><?= nl2br(htmlspecialchars($latestPost['content'])) ?></div>
                    <?php if (!empty($latestMedia)): ?>
                        <div class="post-media">
                            <?php 
                            $firstMedia = $latestMedia[0];
                            $isVideo = $firstMedia['media_type'] === 'video';
                            ?>
                            <?php if ($isVideo): ?>
    <video src="../timeline/uploads/<?= htmlspecialchars($firstMedia['file_path']) ?>" controls></video>
<?php else: ?>
    <img src="../timeline/uploads/<?= htmlspecialchars($firstMedia['file_path']) ?>" alt="投稿画像">
<?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="no-post">投稿はまだありません</p>
            <?php endif; ?>
        </div>
        <a href="../timeline/timeline.php?filter=girl&g_login_id=<?= htmlspecialchars($g_login_id) ?>" class="btn-more">もっと見る</a>
    </div>

    <!-- コメント -->
    <?php if (!empty($girl['comment'])): ?>
    <div class="section comment-section">
        <h3 class="section-title">プロフィール</h3>
        <div class="content-box">
            <?= nl2br(htmlspecialchars($girl['comment'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 一問一答 -->
    <?php if (!empty($ichimon)): ?>
    <div class="section qa-section">
        <h3 class="section-title">一問一答</h3>
        <div class="qa-list">
            <?php foreach ($ichimon as $qa): ?>
                <div class="qa-item">
                    <div class="question">Q. <?= htmlspecialchars($qa['q']) ?></div>
                    <div class="answer">A. <?= htmlspecialchars($qa['a']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 店長コメント -->
    <?php if (!empty($girl['store_comment'])): ?>
    <div class="section store-comment-section">
        <h3 class="section-title">店長コメント</h3>
        <div class="content-box">
            <?= nl2br(htmlspecialchars($girl['store_comment'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 可能オプション -->
    <?php if (!empty($options)): ?>
    <div class="section options-section">
        <h3 class="section-title">可能オプション</h3>
        <div class="options-list">
            <?php foreach ($options as $option): ?>
                <span class="option-tag"><?= htmlspecialchars($option) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 向こう一週間の出勤予定 -->
    <div class="section schedule-section">
        <h3 class="section-title">出勤予定</h3>
        <div class="schedule-wrapper">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <?php foreach ($schedules as $schedule): ?>
                            <th>
                                <div class="date"><?= htmlspecialchars($schedule['date']) ?></div>
                                <div class="day"><?= htmlspecialchars($schedule['dayOfWeek']) ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($schedules as $schedule): ?>
                            <td class="<?= $schedule['hasShift'] ? 'available' : 'unavailable' ?>">
                                <?= $schedule['hasShift'] ? '◯' : '✕' ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 予約するボタン -->
    <div class="reserve-section">
        <a href="reserve.php?g_login_id=<?= htmlspecialchars($g_login_id) ?>" class="btn-reserve">
            この子を予約する
        </a>
    </div>
</div>

<script>
// スライドショー
let slideIndex = 0;
let slideTimer;

function showSlide(n) {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    
    if (n >= slides.length) slideIndex = 0;
    if (n < 0) slideIndex = slides.length - 1;
    
    slides.forEach((slide, index) => {
        slide.classList.remove('active', 'prev-slide');
        if (index < slideIndex) {
            slide.classList.add('prev-slide');
        }
    });
    
    dots.forEach(dot => dot.classList.remove('active'));
    
    slides[slideIndex].classList.add('active');
    dots[slideIndex].classList.add('active');
}

function changeSlide(n) {
    clearInterval(slideTimer);
    slideIndex += n;
    showSlide(slideIndex);
    startSlideShow();
}

function currentSlide(n) {
    clearInterval(slideTimer);
    slideIndex = n;
    showSlide(slideIndex);
    startSlideShow();
}

function startSlideShow() {
    slideTimer = setInterval(() => {
        slideIndex++;
        showSlide(slideIndex);
    }, 3000);
}

// 自動スライド開始
if (document.querySelectorAll('.slide').length > 1) {
    startSlideShow();
}

// お気に入りトグル
function toggleFavorite(gLoginId, button) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'g_login_id=' + encodeURIComponent(gLoginId)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                button.classList.add('active');
                button.innerHTML = '♡ お気に入り登録済み';
            } else {
                button.classList.remove('active');
                button.innerHTML = '♡ お気に入りに追加';
            }
        } else {
            alert(data.message || 'エラーが発生しました');
        }
    })
    .catch(err => {
        console.error(err);
        alert('通信エラーが発生しました');
    });
}
</script>

<script>
// 現在のキャストIDをJavaScriptに渡す
const currentGirlId = '<?= htmlspecialchars($g_login_id, ENT_QUOTES, 'UTF-8') ?>';

// ページ読み込み時にフッターの予約リンクを更新
document.addEventListener('DOMContentLoaded', function() {
  const footerReserveLinks = document.querySelectorAll('footer a[href*="reserve.php"]');
  footerReserveLinks.forEach(function(link) {
    if (currentGirlId) {
      const url = new URL(link.href, window.location.origin);
      url.searchParams.set('g_login_id', currentGirlId);
      link.href = url.toString();
    }
  });
  
  const reserveButtons = document.querySelectorAll('a.btn-reserve, .reserve-btn');
  reserveButtons.forEach(function(btn) {
    if (currentGirlId && btn.href.includes('reserve.php')) {
      const url = new URL(btn.href, window.location.origin);
      url.searchParams.set('g_login_id', currentGirlId);
      btn.href = url.toString();
    }
  });
});
</script>
<?php include 'footer.php'; ?>