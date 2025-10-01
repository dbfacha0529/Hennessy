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
    // POSTデータ取得
    $hotel = $_POST['hotel'] ?? '';
    $hotel_num = $_POST['hotel_num'] ?? '';
    $hotel_comment = $_POST['hotel_comment'] ?? '';
    $new_place = $_POST['place'] ?? $reserve['place']; // placeが送信されていれば取得、なければ既存値
    $new_area = $_POST['area'] ?? $reserve['area']; // areaが送信されていれば取得、なければ既存値
    $new_area_comment = $_POST['area_comment'] ?? $reserve['area_comment'];
    
    // 変更チェック
    $place_changed = ($new_place !== $reserve['place']);
    $area_changed = ($new_area !== $reserve['area']);
    
    // doneステータスの更新判定
    $new_done = $reserve['done'];

    




    
    // まず管理者対応が必要かチェック
    if ($new_place === 'その他' || $new_area === 'その他' || $new_place === 'ご自宅') {
        $new_done = 4; // 管理者対応待ち
    } 
    // 必要な情報が揃っているかチェック
    elseif ($reserve['done'] == 1) {
        // place確定 かつ 必要な追加情報が入力されているか
        if (($new_place === 'ビジネスホテル' || $new_place === 'ラブホテル') && !empty($hotel) && !empty($hotel_num)) {
            $new_done = 2; // 予約確定
        }
    }
    
    // 更新SQL
    $update_sql = "UPDATE reserve SET hotel = :hotel, hotel_num = :hotel_num, hotel_comment = :hotel_comment, place = :place, area = :area, area_comment = :area_comment, done = :done WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindValue(':hotel', $hotel, PDO::PARAM_STR);
    $update_stmt->bindValue(':hotel_num', $hotel_num, PDO::PARAM_STR);
    $update_stmt->bindValue(':hotel_comment', $hotel_comment, PDO::PARAM_STR);
    $update_stmt->bindValue(':place', $new_place, PDO::PARAM_STR);
    $update_stmt->bindValue(':area', $new_area, PDO::PARAM_STR);
    $update_stmt->bindValue(':area_comment', $new_area_comment, PDO::PARAM_STR);
    $update_stmt->bindValue(':done', $new_done, PDO::PARAM_INT);
    $update_stmt->bindValue(':id', $reserve_id, PDO::PARAM_INT);
    $update_stmt->execute();
    
    // データ再取得
$stmt->execute();
$reserve = $stmt->fetch(PDO::FETCH_ASSOC);

// done=2に更新された場合はリダイレクトして入力欄をクリア
if ($new_done == 2) {
    header("Location: reserve_list_up.php?id=" . $reserve_id . "&updated=1");
    exit;
}

$success_message = '予約情報を更新しました';
}

// データの準備
$user_name = $reserve['user_name'];
// start_timeから実際の予約日を取得
$date_obj = new DateTime($reserve['start_time']);
$weekdays = ['日', '月', '火', '水', '木', '金', '土'];
$date_fmt = $date_obj->format('Y年m月d日') . ' (' . $weekdays[$date_obj->format('w')] . ')';
$in_time = $reserve['in_time'];
$in_time_fmt = date('H:i', strtotime($in_time)); // HH:MM形式
$out_time = $reserve['out_time'];
$out_time_fmt = date('H:i', strtotime($out_time));
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

// キャンセル可否判定
$can_cancel = false;
$cancel_message = '';

if ($done != 5 && $done != 3) { 
    $in_time_dt = new DateTime($in_time);
    $now = new DateTime();
    $diff_seconds = $in_time_dt->getTimestamp() - $now->getTimestamp();
    
    if ($diff_seconds >= 3600) { 
        $can_cancel = true;
       
    } 
}

// キャンセルエラーメッセージ
$cancel_error = '';
if (isset($_SESSION['cancel_error'])) {
    $cancel_error = $_SESSION['cancel_error'];
    unset($_SESSION['cancel_error']);
}
?>

<div class="reserve-detail-container">
    <h1>予約詳細</h1>
    
    <?php if ($success_message): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if ($cancel_error): ?>
    <div class="error-message" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb;">
        <strong>エラー:</strong> <?= htmlspecialchars($cancel_error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>
    <?php if ($done == 1): ?>
    <!-- done=1の場合: place/areaに応じた入力フォーム表示 -->
    <div class="hotel-form-top">
        <h2>追加情報を登録してください</h2>
        <form method="POST">
            
            <?php if ($place === '未定'): ?>
                <!-- place未定の場合: place選択 -->
                <div class="form-group">
                    <label for="place">ご利用場所 <span class="required">*</span></label>
                    <select id="place" name="place" required>
    <option value="">-- 選択してください --</option>
    <option value="ラブホテル">ラブホテル</option>
    <option value="ビジネスホテル">ビジネスホテル</option>
    <option value="ご自宅">ご自宅</option>
    <option value="その他">その他</option>
</select>
                </div>
                
                <!-- place選択に応じて動的に表示する入力欄（JavaScript制御） -->
                <div id="hotel-fields" style="display:none;">
                    <div class="form-group">
                        <label for="hotel">ホテル名 <span class="required">*</span></label>
                        <input type="text" id="hotel" name="hotel" value="<?= htmlspecialchars($hotel, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="hotel_num">部屋番号 <span class="required">*</span></label>
                        <input type="text" id="hotel_num" name="hotel_num" value="<?= htmlspecialchars($hotel_num, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                
                <div id="jitaku-fields" style="display:none;">
                    <div class="form-group">
                        <label for="area_comment">ご住所 <span class="required">*</span></label>
                        <textarea id="area_comment" name="area_comment" rows="3"><?= htmlspecialchars($area_comment, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                
            <?php elseif ($place === 'ビジネスホテル' || $place === 'ラブホテル'): ?>
                <!-- place確定済み（ホテル/ラブホ）: ホテル名・部屋番号入力 -->
                <div class="form-group">
                    <label for="hotel">ホテル名 <span class="required">*</span></label>
                    <input type="text" id="hotel" name="hotel" value="<?= htmlspecialchars($hotel, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="hotel_num">部屋番号 <span class="required">*</span></label>
                    <input type="text" id="hotel_num" name="hotel_num" value="<?= htmlspecialchars($hotel_num, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            <?php endif; ?>
            
           
            
            
            <!-- エリア選択（placeが確定している場合に表示） -->
            <?php if ($place !== '未定'): ?>
            <div class="form-group">
                <label for="area">エリア <?php if ($area === '未定'): ?><span class="required">*</span><?php endif; ?></label>
                <select id="area" name="area" <?php if ($area === '未定'): ?>required<?php endif; ?>>
                    <?php if ($area === '未定'): ?>
                    <option value="">-- 選択してください --</option>
                    <?php else: ?>
                    <option value="<?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?> (現在)</option>
                    <?php endif; ?>
                    <?php
$area_stmt = $pdo->query("SELECT area_name FROM area WHERE area_name NOT IN ('未定', 'その他')");
while($area_row = $area_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($area_row['area_name'] !== $area) {
        echo '<option value="' . htmlspecialchars($area_row['area_name'], ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($area_row['area_name'], ENT_QUOTES, 'UTF-8');
        echo '</option>';
    }
}
?>
<option value="不明">不明</option>
                </select>
            </div>
            <?php endif; ?>
            
            
            <div class="form-group">
                <label for="hotel_comment">備考</label>
                <textarea id="hotel_comment" name="hotel_comment" rows="3"><?= htmlspecialchars($hotel_comment, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            
            <button type="submit" class="btn-submit">登録する</button>
        </form>
    </div>
    
<?php elseif ($done == 4): ?>
    <!-- done=4の場合: 管理者対応待ち -->
    <div class="admin-waiting">
        <h2>店舗確認中</h2>
        <p>店舗からご連絡いたしますので、しばらくお待ちください。</p>
        <?php if (!empty($place_comment)): ?>
            <p class="notice">ご記入内容: <?= htmlspecialchars($place_comment, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (!empty($area_comment)): ?>
            <p class="notice">場所: <?= htmlspecialchars($area_comment, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
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
    <!-- キャンセルボタン -->
<?php if ($can_cancel): ?>
<div class="cancel-section" style="margin: 30px 0; padding: 20px; background-color: #fff3cd; border-radius: 5px; border: 1px solid #ffc107;">
    <form method="POST" action="cancel_reserve.php" onsubmit="return confirm('本当にキャンセルしますか?\n<?= $reserve['point'] > 0 ? "使用した{$reserve['point']}ポイントは返還されます。" : "" ?>');">
        <input type="hidden" name="reserve_id" value="<?= $reserve_id ?>">
        <button type="submit" class="btn-cancel" style="background-color: #dc3545; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            <i class="bi bi-x-circle"></i> この予約をキャンセルする
        </button>
    </form>
</div>
<?php endif; ?>
<!-- 金額セクション -->
<?php if ($done == 1 || $done == 4): ?>
    <p style="color: red; font-weight: bold; margin-top: 20px;">
        ※ 表示の金額に加算される可能性があります。
    </p>
<?php endif; ?>
    
    <div class="payment-summary">
        <div class="payment-header">
            <div class="payment-total-line">
                <span class="label">お支払い金額</span>
                <span class="amount"><?= htmlspecialchars(number_format($total_cost), ENT_QUOTES, 'UTF-8') ?>円</span>
            </div>
            <button type="button" class="detail-toggle-btn" onclick="togglePaymentDetail()">
                <span id="toggle-text">詳細を表示</span>
                <span id="toggle-icon" class="arrow">▼</span>
            </button>
        </div>
        
        <div class="payment-detail" id="payment-detail">
            <table class="breakdown-table">
                <tbody>
                    <?php
                    $breakdown_items = json_decode($reserve['cost_uchiwake'], true);
                    
                    if (is_array($breakdown_items) && count($breakdown_items) > 0):
                        foreach ($breakdown_items as $item):
                            $name = $item['name'] ?? '';
                            $amount = $item['amount'] ?? 0;
                            
                            if ($amount == 0) continue;
                            
                            $is_total = ($name === '合計');
                            $row_class = $is_total ? 'total-row' : '';
                            $amount_class = $amount < 0 ? 'negative' : 'positive';
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="item-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="item-amount <?= $amount_class ?>">
                                <?= htmlspecialchars(number_format($amount), ENT_QUOTES, 'UTF-8') ?>円
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    else:
                        echo '<tr><td colspan="2" class="no-data">内訳情報がありません</td></tr>';
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>


<script>
function togglePaymentDetail() {
    const detail = document.getElementById('payment-detail');
    const icon = document.getElementById('toggle-icon');
    const text = document.getElementById('toggle-text');
    
    if (detail.classList.contains('open')) {
        // 閉じる
        detail.classList.remove('open');
        icon.classList.remove('open');
        text.textContent = '詳細を表示';
        detail.style.display = 'none'; // 追加
    } else {
        // 開く
        detail.style.display = 'block'; // 追加
        // 少し遅延させてアニメーションを有効にする
        setTimeout(() => {
            detail.classList.add('open');
            icon.classList.add('open');
            text.textContent = '詳細を非表示';
        }, 10);
    }
}
</script>
    
    <?php if ($done == 2): ?>
    <!-- done=2の場合: 下部にフォーム表示 -->
    <div class="hotel-form-bottom">
        <h2>ホテル情報を更新</h2>
        <form method="POST">
            <div class="form-group">
                <label for="hotel">ホテル名</label>
                <input type="text" id="hotel" name="hotel" value="">
            </div>
            <div class="form-group">
                <label for="hotel_num">部屋番号</label>
                <input type="text" id="hotel_num" name="hotel_num" value="">
            </div>
            
            <!-- エリア変更 -->
            <div class="form-group">
                <label for="area">エリア変更</label>
                <select id="area" name="area">
                    <option value="">変更しない</option>
                    <?php
$area_stmt = $pdo->query("SELECT area_name FROM area WHERE area_name NOT IN ('未定', 'その他')");
while($area_row = $area_stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<option value="' . htmlspecialchars($area_row['area_name'], ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars($area_row['area_name'], ENT_QUOTES, 'UTF-8');
    echo '</option>';
}
?>
<option value="不明">不明</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="hotel_comment">備考</label>
                <textarea id="hotel_comment" name="hotel_comment" rows="3"></textarea>
            </div>
            <button type="submit" class="btn-submit">更新する</button>
        </form>
    </div>
<?php endif; ?>
    
    <div class="back-link">
        <a href="reserve_list.php">予約リストに戻る</a>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const placeSelect = document.getElementById('place');
    const hotelFields = document.getElementById('hotel-fields');
    const jitakuFields = document.getElementById('jitaku-fields');
    const hotelInput = document.getElementById('hotel');
    const hotelNumInput = document.getElementById('hotel_num');
    const areaCommentInput = document.getElementById('area_comment');
    
    if (placeSelect) {
        placeSelect.addEventListener('change', function() {
            const selectedPlace = this.value;
            
            // 全ての入力欄を非表示・非必須に
            if (hotelFields) {
                hotelFields.style.display = 'none';
                if (hotelInput) hotelInput.required = false;
                if (hotelNumInput) hotelNumInput.required = false;
            }
            if (jitakuFields) {
                jitakuFields.style.display = 'none';
                if (areaCommentInput) areaCommentInput.required = false;
            }
            
            // 選択に応じて表示・必須設定
            if (selectedPlace === 'ビジネスホテル' || selectedPlace === 'ラブホテル') {
                if (hotelFields) {
                    hotelFields.style.display = 'block';
                    if (hotelInput) hotelInput.required = true;
                    if (hotelNumInput) hotelNumInput.required = true;
                }
            } else if (selectedPlace === 'ご自宅') {
                if (jitakuFields) {
                    jitakuFields.style.display = 'block';
                    if (areaCommentInput) areaCommentInput.required = true;
                }
            } else if (selectedPlace === 'その他') {
                alert('「その他」を選択された場合は、店舗からご連絡いたします。');
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
<link href="<?= $base ?>css/reserve_list_up.css" rel="stylesheet">