<?php
include 'header.php';

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// 最新の予約を取得（現金決済のもの）
$stmt = $pdo->prepare("
    SELECT * FROM reserve 
    WHERE tel = :tel AND pay = 1 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([':tel' => $tel]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    header('Location: home.php');
    exit;
}

// 予約データの整理
$c_name = $reservation['c_name'];
$g_name = $reservation['g_name'];
$date = $reservation['date'];
$in_time = new DateTime($reservation['in_time']);
$out_time = new DateTime($reservation['out_time']);
$place = $reservation['place'];
$place_comment = $reservation['place_comment'];
$area = $reservation['area'];
$area_comment = $reservation['area_comment'];
$contact_tel = $reservation['contact_tel'];
$comment = $reservation['comment'];
$options = json_decode($reservation['options'], true) ?: [];
$cost_uchiwake = json_decode($reservation['cost_uchiwake'], true) ?: [];
$total_cost = $reservation['cost'];

// フリーコースかどうかを判定
$stmt = $pdo->prepare("SELECT free_check FROM course WHERE c_name = :c_name");
$stmt->execute([':c_name' => $c_name]);
$courseData = $stmt->fetch(PDO::FETCH_ASSOC);
$isFreeCheck = $courseData ? (int)$courseData['free_check'] : 0;

// 日付フォーマット
$weekdays = ['日', '月', '火', '水', '木', '金', '土'];
$date_fmt = (new DateTime($date))->format('Y年n月j日（') 
           . $weekdays[(new DateTime($date))->format('w')] 
           . '）';
$time_fmt = $in_time->format('H:i') . '～' . $out_time->format('H:i');
?>

<div class="container">
    <div class="completion-header">
        <div class="success-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1>ご予約が完了しました</h1>
        <p class="sub-message">お決まりになりましたら、予約リストからホテル名と部屋番号をご登録ください。</p>
    </div>

    <!-- 予約内容テーブル -->
    <div class="reservation-details">
        <h2>予約内容</h2>
        <table class="details-table">
            <tr>
                <td class="label">ご予約日</td>
                <td><?= htmlspecialchars($date_fmt) ?></td>
            </tr>
            <tr>
                <td class="label">ご予約時間</td>
                <td><?= htmlspecialchars($time_fmt) ?></td>
            </tr>
            <tr>
                <td class="label">コース</td>
                <td><?= htmlspecialchars($c_name) ?></td>
            </tr>
            <?php if ($isFreeCheck === 0 && !empty($g_name)): ?>
            <tr>
                <td class="label">ご指名</td>
                <td><?= htmlspecialchars($g_name) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="label">ご利用場所</td>
                <td>
                    <?= htmlspecialchars($place) ?>
                    <?php if (!empty($place_comment)): ?>
                        <br><small><?= htmlspecialchars($place_comment) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label">エリア</td>
                <td>
                    <?= htmlspecialchars($area) ?>
                    <?php if (!empty($area_comment)): ?>
                        <br><small><?= htmlspecialchars($area_comment) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($options)): ?>
            <tr>
                <td class="label">オプション</td>
                <td><?= htmlspecialchars(implode(', ', $options)) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="label">お支払方法</td>
                <td>現金</td>
            </tr>
            <tr>
                <td class="label">ご連絡先</td>
                <td><?= htmlspecialchars($contact_tel) ?></td>
            </tr>
            <?php if (!empty($comment)): ?>
            <tr>
                <td class="label">備考</td>
                <td><?= nl2br(htmlspecialchars($comment)) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 支払い金額テーブル -->
    <div class="payment-details">
        <h2>お支払い金額</h2>
        <table class="payment-table">
            <?php if (!empty($cost_uchiwake) && is_array($cost_uchiwake)): ?>
    <?php foreach ($cost_uchiwake as $item): ?>
        <?php
        $name = $item['name'] ?? '';
        $amount = $item['amount'] ?? 0;
        
        // 0円の項目はスキップ
        if ($amount == 0) continue;
        
        // 合計行の判定
        $is_total = ($name === '合計');
        $row_class = $is_total ? 'total-row' : '';
        ?>
        <tr class="<?= $row_class ?>">
            <td><?= htmlspecialchars($name) ?></td>
            <td class="amount" style="<?= $amount < 0 ? 'color: red;' : '' ?>">
                <?= number_format($amount) ?>円
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr class="total-row">
        <td><strong><?= ($pay == 2) ? '決済完了金額' : '合計金額' ?></strong></td>
        <td class="amount"><strong><?= number_format($total_cost) ?>円</strong></td>
    </tr>
<?php endif; ?>
    </div>

    <!-- ボタンエリア -->
    <div class="button-area">
        <a href="reserve_list.php" class="btn btn-primary btn-lg">予約リストへ</a>
        <a href="home.php" class="btn btn-secondary btn-lg">ホームに戻る</a>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>