<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// キャストログインチェック
if (empty($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

require_once(dirname(__FILE__) . '/../functions.php');

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// 女の子の情報を取得
$sql = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmt->execute();
$girl = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$girl) {
    header('Location: cast_login.php');
    exit;
}

// 承認待ちデータをチェック
$sql = "SELECT * FROM girl_pending WHERE g_login_id = :g_login_id AND status = 'pending'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$pending_data = $stmt->fetch(PDO::FETCH_ASSOC);

$has_pending = !empty($pending_data);

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

// ステータスと時間表示の判定
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
            $time_label = '~' . $shift_out->format('H:i');
        } elseif ($now < $shift_in) {
            $time_label = $shift_in->format('H:i') . '~';
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

// 画像配列をデコード
$images = json_decode($girl['imgs'], true) ?: [];

// 一問一答をデコード
$ichimon = json_decode($girl['ichimon'], true) ?: [];

// オプションをデコード
$options = json_decode($girl['option'], true) ?: [];
// 属性取得
$sql = "SELECT attribute_value FROM girl_attributes WHERE g_login_id = :g_login_id ORDER BY created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_COLUMN);
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

require_once(dirname(__FILE__) . '/header.php');
?>

<link href="./css/cast_profile.css" rel="stylesheet">

<div class="container">
    <!-- 承認待ち通知 -->
    <?php if ($has_pending): ?>
    <div class="pending-notice">
        <div class="pending-notice-icon">⏳</div>
        <div class="pending-notice-content">
            <strong>編集申請中</strong>
            <p>管理者の承認をお待ちください。承認されるまで現在のプロフィールが表示されます。</p>
            <small>申請日時: <?= date('Y年m月d日 H:i', strtotime($pending_data['requested_at'])) ?></small>
        </div>
    </div>
    <?php endif; ?>
<!-- 成功メッセージ -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-notice">
        <div class="success-notice-icon">✓</div>
        <div class="success-notice-content">
            <strong>保存完了</strong>
            <p><?= htmlspecialchars($_SESSION['success_message']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <!-- 情報メッセージ -->
    <?php if (isset($_SESSION['info_message'])): ?>
    <div class="info-notice">
        <div class="info-notice-icon">ℹ</div>
        <div class="info-notice-content">
            <p><?= htmlspecialchars($_SESSION['info_message']) ?></p>
        </div>
    </div>
    <?php unset($_SESSION['info_message']); endif; ?>
    <!-- 上部カード -->
    <div class="gcard">
        <img class="gcardimg" src="../../img/icon/<?= htmlspecialchars($girl['img']) ?>">
        
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

    <!-- 画像スライドショー -->
    <?php if (!empty($images)): ?>
    <div class="slideshow-container">
        <div class="slideshow">
            <?php foreach ($images as $index => $img): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>">
                    <img src="../../img/panels/<?= htmlspecialchars($img) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button class="slide-btn prev" onclick="changeSlide(-1)">◀</button>
        <button class="slide-btn next" onclick="changeSlide(1)">▶</button>
        <div class="slide-dots">
            <?php foreach ($images as $index => $img): ?>
                <span class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="currentSlide(<?= $index ?>)"></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                <?php $created = new DateTime($latestPost['created_at']); ?>
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
                                <video src="../../img/timeline_uploads/<?= htmlspecialchars($firstMedia['file_path']) ?>" controls></video>
                            <?php else: ?>
                                <img src="../../img/timeline_uploads/<?= htmlspecialchars($firstMedia['file_path']) ?>" alt="投稿画像">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="no-post">投稿はまだありません</p>
            <?php endif; ?>
        </div>
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

    <!-- 属性・特徴 -->
    <?php if (!empty($attributes)): ?>
    <div class="section attributes-section">
        <h3 class="section-title">属性・特徴</h3>
        <div class="attributes-list">
            <?php foreach ($attributes as $attr): ?>
                <span class="attribute-tag"><?= htmlspecialchars($attr) ?></span>
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

    <!-- 編集するボタン -->
    <div class="reserve-section">
        <a href="cast_profile_edit.php" class="btn-reserve">
            プロフィールを編集する
        </a>
    </div>
</div>

<style>
/* 承認待ち通知のスタイル */
.pending-notice {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border: 2px solid #ffc107;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
    animation: pulse-border 2s infinite;
}

@keyframes pulse-border {
    0%, 100% {
        border-color: #ffc107;
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
    }
    50% {
        border-color: #ffb300;
        box-shadow: 0 6px 16px rgba(255, 193, 7, 0.3);
    }
}

.pending-notice-icon {
    font-size: 2.5rem;
    flex-shrink: 0;
    animation: spin 3s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.pending-notice-content {
    flex: 1;
}

.pending-notice-content strong {
    display: block;
    color: #856404;
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.pending-notice-content p {
    color: #856404;
    margin: 0 0 0.5rem 0;
    line-height: 1.5;
}

.pending-notice-content small {
    color: #856404;
    opacity: 0.8;
    font-size: 0.85rem;
}

/* レスポンシブ対応 */
@media (max-width: 30rem) {
    .pending-notice {
        flex-direction: column;
        text-align: center;
    }
    
    .pending-notice-icon {
        font-size: 2rem;
    }
}
</style>

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
</script>

<?php require_once(dirname(__FILE__) . '/footer.php'); ?>