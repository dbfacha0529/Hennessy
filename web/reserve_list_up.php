<?php

include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER'])) {
    header('Location: login.php');
    exit();
}

$user_tel = $_SESSION['USER']['tel'];

// 予約IDチェック
if (!isset($_GET['id'])) {
    header('Location: reserve_list.php');
    exit();
}

$reserve_id = $_GET['id'];

// DB接続
$pdo = dbConnect();

// 予約データ取得
$sql = "SELECT * FROM reserve WHERE id = :id AND tel = :tel";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $reserve_id, PDO::PARAM_INT);
$stmt->bindValue(':tel', $user_tel, PDO::PARAM_STR);
$stmt->execute();
$reserve = $stmt->fetch(PDO::FETCH_ASSOC);

// 予約が見つからない場合
if (!$reserve) {
    header('Location: reserve_list.php');
    exit();
}

// 更新処理
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotel = $_POST['hotel'] ?? '';
    $hotel_num = $_POST['hotel_num'] ?? '';
    $hotel_comment = $_POST['hotel_comment'] ?? '';
    
    // doneステータスの更新判定
    $new_done = $reserve['done'];
    if ($reserve['done'] == 1 && !empty($hotel) && !empty($hotel_num)) {
        $new_done = 2;
    }
    
    // 更新SQL
    $update_sql = "UPDATE reserve SET hotel = :hotel, hotel_num = :hotel_num, hotel_comment = :hotel_comment, done = :done WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindValue(':hotel', $hotel, PDO::PARAM_STR);
    $update_stmt->bindValue(':hotel_num', $hotel_num, PDO::PARAM_STR);
    $update_stmt->bindValue(':hotel_comment', $hotel_comment, PDO::PARAM_STR);
    $update_stmt->bindValue(':done', $new_done, PDO::PARAM_INT);
    $update_stmt->bindValue(':id', $reserve_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // データ再取得
    $reserve = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->execute();
    $reserve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $success_message = '予約情報を更新しました';
}

// データの準備
$user_name = $reserve['user_name'];
$date = $reserve['date'];
$date_obj = new DateTime($date);
$date_fmt = $date_obj->format('Y年m月d日 (D)');
$in_time = $reserve['in_time'];
$in_time_fmt = substr($in_time, 0, 5); // HH:MM形式
$out_time = $reserve['out_time'];
$out_time_fmt = substr($out_time, 0, 5);
$c_name = $reserve['c_name'];
$course_time = $reserve['course_time'];
$g_name = $reserve['g_name'];
$place = $reserve['place'];
$place_comment = $reserve['place_comment'];
$area = $reserve['area'];
$area_comment = $reserve['area_comment'];
$options = !empty($reserve['options']) ? json_decode($reserve['options'], true) : [];
$pay = $reserve['pay'];
$pay_label = ($pay == 1) ? '現金' : (($pay == 2) ? 'クレジットカード' : '未設定');
$tel = $reserve['tel'];
$comment = $reserve['comment'];
$total_cost = $reserve['cost'];
$hotel = $reserve['hotel'];
$hotel_num = $reserve['hotel_num'];
$hotel_comment = $reserve['hotel_comment'];
$done = $reserve['done'];

?>

<div class="reserve-detail-container">
    <h1>予約詳細</h1>
    
    <?php if ($success_message): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    
    <?php if ($done == 1): ?>
        <!-- done=1の場合: 上部にフォーム表示 -->
        <div class="hotel-form-top">
            <h2>追加情報を登録してください</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="hotel">ホテル名 <span class="required">*</span></label>
                    <input type="text" id="hotel" name="hotel" value="<?= htmlspecialchars($hotel, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="hotel_num">部屋番号 <span class="required">*</span></label>
                    <input type="text" id="hotel_num" name="hotel_num" value="<?= htmlspecialchars($hotel_num, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="hotel_comment">備考</label>
                    <textarea id="hotel_comment" name="hotel_comment" rows="3"><?= htmlspecialchars($hotel_comment, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <button type="submit" class="btn-submit">登録する</button>
            </form>
        </div>
    <?php endif; ?>
    
    <!-- 予約詳細テーブル -->
    <table cellspacing="0" cellpadding="8">
        <thead>
            <tr>
                <th>要素名</th>
                <th>内容</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ご予約者名</td>
                <td><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>様</td>
            </tr>
            <tr>
                <td>ご予約日</td>
                <td><?= htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>ご予約時間</td>
                <td><?= htmlspecialchars($in_time_fmt, ENT_QUOTES, 'UTF-8') ?>～<?= htmlspecialchars($out_time_fmt, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>コース</td>
                <td><?= htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>コース時間</td>
                <td><?= htmlspecialchars($course_time, ENT_QUOTES, 'UTF-8') ?>分</td>
            </tr>
            <?php if (!empty($g_name)): ?>
            <tr>
                <td>ご指名</td>
                <td><?= htmlspecialchars($g_name, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>ご利用場所</td>
                <td><?= htmlspecialchars($place, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php if (!empty($place_comment)): ?>
            <tr>
                <td>備考欄</td>
                <td><?= htmlspecialchars($place_comment, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>エリア</td>
                <td><?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php if (!empty($area_comment)): ?>
            <tr>
                <td>備考欄</td>
                <td><?= htmlspecialchars($area_comment, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($options)): ?>
            <tr>
                <td>オプション</td>
                <td><?= nl2br(htmlspecialchars(implode("\n", $options), ENT_QUOTES, 'UTF-8')) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($hotel)): ?>
            <tr>
                <td>ホテル名</td>
                <td><?= htmlspecialchars($hotel, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($hotel_num)): ?>
            <tr>
                <td>部屋番号</td>
                <td><?= htmlspecialchars($hotel_num, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($hotel_comment)): ?>
            <tr>
                <td>ホテル備考</td>
                <td><?= htmlspecialchars($hotel_comment, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>お支払方法</td>
                <td><?= htmlspecialchars($pay_label, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <td>ご連絡先</td>
                <td><?= htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php if (!empty($comment)): ?>
            <tr>
                <td>備考欄</td>
                <td><?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 金額テーブル -->
    <table cellspacing="0" cellpadding="8">
        <thead>
            <tr>
                <th>要素名</th>
                <th>金額</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>合計</strong></td>
                <td><strong><?= htmlspecialchars(number_format($total_cost), ENT_QUOTES, 'UTF-8') ?>円</strong></td>
            </tr>
        </tbody>
    </table>
    
    <?php if ($done == 2): ?>
        <!-- done=2の場合: 下部にフォーム表示 -->
        <div class="hotel-form-bottom">
            <h2>ホテル情報を更新</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="hotel">ホテル名</label>
                    <input type="text" id="hotel" name="hotel" value="<?= htmlspecialchars($hotel, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="hotel_num">部屋番号</label>
                    <input type="text" id="hotel_num" name="hotel_num" value="<?= htmlspecialchars($hotel_num, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label for="hotel_comment">備考</label>
                    <textarea id="hotel_comment" name="hotel_comment" rows="3"><?= htmlspecialchars($hotel_comment, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <button type="submit" class="btn-submit">更新する</button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="back-link">
        <a href="reserve_list.php">予約リストに戻る</a>
    </div>
</div>

<?php include 'footer.php'; ?>