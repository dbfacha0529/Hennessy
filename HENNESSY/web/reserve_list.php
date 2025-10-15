<?php

include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER'])) {
    header('Location: index.php');
    exit();
}

$tel = $_SESSION['USER']['tel'];

// DB接続
$pdo = dbConnect();

// 選択された年月を取得（デフォルトは現在の年月）
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// 月の最初と最後の日を計算
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start)); // 月末日を取得

// 予約リストを取得（選択された月のみ、最新順）
$sql = "SELECT * FROM reserve 
        WHERE tel = :tel 
        AND date >= :month_start 
        AND date <= :month_end 
        ORDER BY date DESC, in_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
$stmt->bindValue(':month_start', $month_start, PDO::PARAM_STR);
$stmt->bindValue(':month_end', $month_end, PDO::PARAM_STR);
$stmt->execute();
$reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 過去6ヶ月と未来1ヶ月の選択肢を生成
$month_options = [];
for ($i = -6; $i <= 1; $i++) {
    $month_value = date('Y-m', strtotime("$i months"));
    $month_label = date('Y年n月', strtotime("$i months"));
    $month_options[] = ['value' => $month_value, 'label' => $month_label];
}
?>
<!--オリジナルCSS-->
<link href="./css/reserve_list.css" rel="stylesheet">

<div class="reserve-list-container">
    <h1>予約リスト</h1>
    
    <!-- 月選択プルダウン -->
    <div class="month-selector">
        <form method="GET" id="monthForm">
            <label for="month">表示月:</label>
            <select name="month" id="month" onchange="document.getElementById('monthForm').submit();">
                <?php foreach ($month_options as $option): ?>
                    <option value="<?= $option['value'] ?>" <?= $option['value'] === $selected_month ? 'selected' : '' ?>>
                        <?= $option['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (isset($_SESSION['cancel_success'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['cancel_success']) ?>
    </div>
    <?php unset($_SESSION['cancel_success']); ?>
    <?php endif; ?>
    
    <?php if (empty($reserves)): ?>
        <p><?= date('Y年n月', strtotime($selected_month)) ?>の予約がありません</p>
    <?php else: ?>
        <?php foreach ($reserves as $reserve): ?>
            <?php
            // フリーコースかどうかを判定
            $stmt = $pdo->prepare("SELECT free_check FROM course WHERE c_name = :c_name");
            $stmt->execute([':c_name' => $reserve['c_name']]);
            $courseData = $stmt->fetch(PDO::FETCH_ASSOC);
            $isFreeCheck = $courseData ? (int)$courseData['free_check'] : 0;
            
            // 時間のフォーマット
            $in_time = new DateTime($reserve['in_time']);
            $out_time = new DateTime($reserve['out_time']);
            $in_time_fmt = $in_time->format('H:i');
            $out_time_fmt = $out_time->format('H:i');
            
            // 日付のフォーマット
            $date = new DateTime($reserve['date']);
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $date_fmt = $date->format('Y年n月j日') . '（' . $weekdays[$date->format('w')] . '）';
            ?>
            <a href="reserve_list_up.php?id=<?= htmlspecialchars($reserve['id']) ?>" class="reserve-card-link">
                <div class="reserve-card">
                    <div class="reserve-card-left">
                        <div class="reserve-date">
                            <?= $date_fmt ?>
                        </div>
                        <div class="reserve-time">
                            <?= $in_time_fmt ?> ~ <?= $out_time_fmt ?>
                        </div>
                        <div class="reserve-course">
                            コース: <?= htmlspecialchars($reserve['c_name']) ?> / コース時間: <?= htmlspecialchars($reserve['course_time']) ?>分
                        </div>
                        <?php if ($isFreeCheck === 1): ?>
                            <div class="reserve-girl">
                                ご指名: フリー
                            </div>
                        <?php elseif (!empty($reserve['g_name'])): ?>
                            <div class="reserve-girl">
                                ご指名: <?= htmlspecialchars($reserve['g_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($reserve['hotel'])): ?>
                            <div class="reserve-hotel">
                                ホテル: <?= htmlspecialchars($reserve['hotel']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($reserve['hotel_num'])): ?>
                            <div class="reserve-room">
                                部屋番号: <?= htmlspecialchars($reserve['hotel_num']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reserve-card-right">
                        <div class="reserve-status">
                            <?php
                            if ($reserve['done'] == 5) {
                                echo '<span class="status-cancelled">キャンセル済み</span>';
                            } elseif ($reserve['done'] == 3) {
                                echo '<span class="status-completed">完了済み</span>';
                            } elseif ($reserve['done'] == 2) {
                                echo '<span class="status-confirmed">ご予約完了</span>';
                            } elseif ($reserve['done'] == 4) {
                                echo '<span class="status-admin">店舗確認中</span>';
                            } elseif ($reserve['done'] == 1) {
                                echo '<span class="status-pending">追加情報を登録してください</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>