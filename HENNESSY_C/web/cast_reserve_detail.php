<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$g_login_id = $_SESSION['CAST']['g_login_id'];
$reserve_id = $_GET['id'] ?? 0;

// DB接続
$pdo = dbConnect();

// 予約詳細を取得
$sql = "SELECT * FROM reserve WHERE id = :id AND g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $reserve_id, PDO::PARAM_INT);
$stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmt->execute();
$reserve = $stmt->fetch(PDO::FETCH_ASSOC);

// 予約が見つからない場合
if (!$reserve) {
    $_SESSION['error_message'] = '予約が見つかりませんでした';
    header('Location: cast_reserve_list.php');
    exit;
}

// 時間のフォーマット
$in_time = new DateTime($reserve['in_time']);
$out_time = new DateTime($reserve['out_time']);
$start_time = new DateTime($reserve['start_time']);
$end_time = new DateTime($reserve['end_time']);

// 日付のフォーマット
$date = new DateTime($reserve['date']);
$weekdays = ['日', '月', '火', '水', '木', '金', '土'];
$date_fmt = $date->format('Y年n月j日') . '（' . $weekdays[$date->format('w')] . '）';

// オプションをデコード
$options = json_decode($reserve['options'], true);

// 料金内訳をデコード
$cost_uchiwake = json_decode($reserve['cost_uchiwake'], true);

// ステータスの判定
$status_class = '';
$status_text = '';
switch ($reserve['done']) {
    case 1:
        $status_class = 'status-pending';
        $status_text = '追加情報待ち';
        break;
    case 2:
        $status_class = 'status-confirmed';
        $status_text = '予約確定';
        break;
    case 3:
        $status_class = 'status-completed';
        $status_text = '完了';
        break;
    case 4:
        $status_class = 'status-checking';
        $status_text = '店舗確認中';
        break;
}

include 'header.php';
?>

<link href="./css/cast_reserve_detail.css" rel="stylesheet">

<div class="reserve-detail-container">
    <div class="detail-header">
        <a href="cast_schedule_list.php" class="btn-back">← スケジュールに戻る</a><br>
        <a href="cast_reserve_list.php" class="btn-back">← 予約一覧に戻る</a>
        <h1>予約詳細</h1>
    </div>

    <div class="detail-card">
        <!-- ステータス -->
        <div class="detail-section status-section">
            <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
        </div>

        <!-- 基本情報 -->
        <div class="detail-section">
            <h2 class="section-title">📅 予約情報</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="item-label">予約日</span>
                    <span class="item-value"><?= $date_fmt ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">出勤時間</span>
                    <span class="item-value"><?= $in_time->format('H:i') ?> ~ <?= $out_time->format('H:i') ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">コース時間</span>
                    <span class="item-value"><?= $start_time->format('H:i') ?> ~ <?= $end_time->format('H:i') ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">コース名</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['c_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">コース時間</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['course_time']) ?>分</span>
                </div>
            </div>
        </div>

        <!-- お客様情報 -->
        <div class="detail-section">
            <h2 class="section-title">👤 お客様情報</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="item-label">お客様名</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['user_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">ログインID</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['login_id']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="item-label">電話番号</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['contact_tel']) ?></span>
                </div>
            </div>
        </div>

        <!-- 場所情報 -->
        <div class="detail-section">
            <h2 class="section-title">📍 場所情報</h2>
            <div class="detail-grid">
                <?php if (!empty($reserve['place'])): ?>
                <div class="detail-item">
                    <span class="item-label">場所</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['place']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['area'])): ?>
                <div class="detail-item">
                    <span class="item-label">エリア</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['area']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['area_comment'])): ?>
                <div class="detail-item full-width">
                    <span class="item-label">エリア詳細</span>
                    <span class="item-value"><?= nl2br(htmlspecialchars($reserve['area_comment'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['hotel'])): ?>
                <div class="detail-item">
                    <span class="item-label">ホテル名</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['hotel']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['hotel_num'])): ?>
                <div class="detail-item">
                    <span class="item-label">部屋番号</span>
                    <span class="item-value"><?= htmlspecialchars($reserve['hotel_num']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['hotel_comment'])): ?>
                <div class="detail-item full-width">
                    <span class="item-label">ホテル詳細</span>
                    <span class="item-value"><?= nl2br(htmlspecialchars($reserve['hotel_comment'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($reserve['place_comment'])): ?>
                <div class="detail-item full-width">
                    <span class="item-label">場所詳細</span>
                    <span class="item-value"><?= nl2br(htmlspecialchars($reserve['place_comment'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- オプション -->
        <?php if (!empty($options)): ?>
        <div class="detail-section">
            <h2 class="section-title">✨ オプション</h2>
            <div class="options-list">
                <?php foreach ($options as $option): ?>
                    <span class="option-tag"><?= htmlspecialchars($option) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

      <!-- 料金情報 -->
<div class="detail-section">
    <h2 class="section-title">💰 料金情報</h2>
    <div class="cost-breakdown">
        <?php if (!empty($cost_uchiwake) && is_array($cost_uchiwake)): ?>
            <?php foreach ($cost_uchiwake as $item): ?>
                <?php if (isset($item['name']) && isset($item['amount'])): ?>
                    <div class="cost-item">
                        <span class="cost-label"><?= htmlspecialchars($item['name']) ?></span>
                        <span class="cost-value"><?= number_format($item['amount']) ?>円</span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($reserve['coupon']) && is_numeric($reserve['coupon']) && $reserve['coupon'] > 0): ?>
        <div class="cost-item">
            <span class="cost-label">クーポン</span>
            <span class="cost-value discount">-<?= number_format($reserve['coupon']) ?>円</span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($reserve['point']) && is_numeric($reserve['point']) && $reserve['point'] > 0): ?>
        <div class="cost-item">
            <span class="cost-label">ポイント使用</span>
            <span class="cost-value discount">-<?= number_format($reserve['point']) ?>円</span>
        </div>
        <?php endif; ?>
        
        <div class="cost-item total">
            <span class="cost-label">合計金額</span>
            <span class="cost-value"><?= number_format($reserve['cost']) ?>円</span>
        </div>
        
        <div class="cost-item">
            <span class="cost-label">支払い方法</span>
            <span class="cost-value">
                <?php
                switch ($reserve['pay']) {
                    case 1:
                        echo '現金';
                        break;
                    case 2:
                        echo 'クレジットカード';
                        break;
                    case 3:
                        echo 'その他';
                        break;
                    default:
                        echo '未設定';
                }
                ?>
            </span>
        </div>
    </div>
</div>
        <!-- コメント -->
        <?php if (!empty($reserve['comment'])): ?>
        <div class="detail-section">
            <h2 class="section-title">💬 お客様からのコメント</h2>
            <div class="comment-box">
                <?= nl2br(htmlspecialchars($reserve['comment'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>