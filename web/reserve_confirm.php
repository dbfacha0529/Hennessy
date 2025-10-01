<?php

include 'header.php';


$pdo = dbConnect();




//配列の中身を分かりやすい変数へ

$c_name = $_SESSION["RESERVE_DATA"]['course_name'];
    $course_time = null;

    if ($c_name) {
        try {
            $stmt = $pdo->prepare("SELECT time FROM course WHERE c_name = :c_name LIMIT 1");
            $stmt->bindValue(':c_name', $c_name, PDO::PARAM_STR);
            $stmt->execute();
$course_time = $stmt->fetchColumn(); // fetchColumn() で time の値だけ取得
        } catch (PDOException $e) {
            echo "エラー: " . $e->getMessage();
            $course_time = null;
        }
    }

$date = $_SESSION["RESERVE_DATA"]['reserve_date']; // DATE型の文字列



    // DATE と TIME を結合して DateTime オブジェクトを作成
$in_times = new DateTime($_SESSION["RESERVE_DATA"]['reserve_times']);
$in_time  =$_SESSION["RESERVE_DATA"]['reserve_times'];


  $out_time = clone $in_times; // 元の日時を保持するためクローン
$out_time->add(new DateInterval('PT' . $course_time . 'M'));

$end_time = clone $out_time; // 終了時刻をベースにする
$end_time->add(new DateInterval('PT10M')); // 後処理10分を追加

$start_time = clone $in_times; // 元の日時を保持するためクローン
$start_time->sub(new DateInterval('PT10M')); // 10分引く

$g_name = $_SESSION["RESERVE_DATA"]['girl_name'];

    if ($g_name) {
    try {
        $stmt = $pdo->prepare("SELECT g_login_id FROM girl WHERE name = :g_name LIMIT 1");
        $stmt->bindValue(':g_name', $g_name, PDO::PARAM_STR);
        $stmt->execute();
$g_login_id = $stmt->fetchColumn(); // 該当がなければ false が返る
    } catch (PDOException $e) {
        echo "エラー: " . $e->getMessage();
        $g_login_id = null;
    }
    }


$login_id = $_SESSION["USER"]['login_id'];
$tel = $_SESSION["RESERVE_DATA"]['contact_tel'];
$user_tel = $_SESSION['USER']['tel'];
$user_name = $_SESSION["USER"]['user_name'];
$options = $_SESSION["RESERVE_DATA"]['options'];//これ配列なんだよね
$place = $_SESSION["RESERVE_DATA"]['place'];
$place_comment = $_SESSION["RESERVE_DATA"]['place_other'];
$area = $_SESSION["RESERVE_DATA"]['area'];
$area_comment = $_SESSION["RESERVE_DATA"]['area_other'];
$hotel = $_SESSION["RESERVE_DATA"][''];
$hotel_num = $_SESSION["RESERVE_DATA"][''];
$hotel_comment = $_SESSION["RESERVE_DATA"][''];
$comment = $_SESSION["RESERVE_DATA"]['comment'];
    $coupon_code = $_SESSION["RESERVE_DATA"]['coupon_code'];
    if ($coupon_code) {try {
        $stmt = $pdo->prepare("SELECT coupon_name FROM coupon WHERE coupon_code = :coupon_code LIMIT 1");
        $stmt->bindValue(':coupon_code', $coupon_code, PDO::PARAM_STR);
        $stmt->execute();
$coupon = $stmt->fetchColumn(); // 該当がなければ false が返る
    } catch (PDOException $e) {
        echo "エラー: " . $e->getMessage();
        $coupon = null;
    }}

$point = $_SESSION["RESERVE_DATA"]['use_point'];
$pay = $_SESSION["RESERVE_DATA"]['payment_method'];
$pay_done = $_SESSION["RESERVE_DATA"][''];
$done = $_SESSION["RESERVE_DATA"]['']; 

$cost = $_SESSION["RESERVE_DATA"]['pricing']; 


$dt = clone $start_time;  

    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];


$date_fmt = $dt->format('Y年n月j日（') 
                 . $weekdays[$dt->format('w')] 
                 . '）';
$in_time_fmt = $in_times->format('H時i分'); // 文字列に変換
$out_time_fmt = $out_time->format('H時i分'); // 文字列に変換
$total_cost = $cost['total_amount'];
$pay_label = '';
    if ($pay == 1) {
        $pay_label = '現金';
    } elseif ($pay == 2) {
        $pay_label = 'クレジットカード';
    }

$options_cost = [];

    if (!empty($options)) {
        foreach ($options as $opt) {
            try {
                $stmt = $pdo->prepare("SELECT option_cost FROM options WHERE option_name = :option_name LIMIT 1");
                $stmt->bindValue(':option_name', $opt, PDO::PARAM_STR);
                $stmt->execute();
                $opt_cost = $stmt->fetchColumn();

                // 見つかった場合はそのまま、なければ null を入れる
                $options_cost[] = $opt_cost !== false ? (int)$opt_cost : null;
            } catch (PDOException $e) {
                // エラー時は null を入れて続行
                $options_cost[] = null;
            }
        }
    }
 ?>
  <!--オリジナルCSS-->
  <link href="./css/reserve_confirm.css" rel="stylesheet">

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
<table cellspacing="0" cellpadding="8">
  <thead>
    <tr>
      <th>要素名</th>
      <th>金額</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if (is_array($cost) && count($cost) > 0):
        foreach ($cost as $item):
            $name = $item['name'] ?? '';
            $amount = $item['amount'] ?? 0;
            
            if ($amount == 0) continue;
            
            $is_total = ($name === '合計');
            $style = $is_total ? 'font-weight: bold;' : '';
            $amount_color = $amount < 0 ? 'color: red;' : '';
    ?>
        <tr style="<?= $style ?>">
            <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
            <td style="<?= $amount_color ?>">
                <?= htmlspecialchars(number_format($amount), ENT_QUOTES, 'UTF-8') ?>円
            </td>
        </tr>
    <?php
        endforeach;
    endif;
    ?>
  </tbody>
</table>

<!-- 予約送信フォーム -->
<form id="reserveForm" method="post">
    <!-- 必要なデータを hidden で送る -->
    <input type="hidden" name="pay" value="<?= htmlspecialchars($pay) ?>">
    <input type="hidden" name="c_name" value="<?= htmlspecialchars($c_name) ?>">
    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
    <input type="hidden" name="in_time" value="<?= htmlspecialchars($in_times->format('Y-m-d H:i:s')) ?>">
    <input type="hidden" name="out_time" value="<?= htmlspecialchars($out_time->format('Y-m-d H:i:s')) ?>">
    <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time->format('Y-m-d H:i:s')) ?>">
    <input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time->format('Y-m-d H:i:s')) ?>">
    <input type="hidden" name="g_name" value="<?= htmlspecialchars($g_name) ?>">
    <input type="hidden" name="contact_tel" value="<?= htmlspecialchars($tel) ?>">
    <input type="hidden" name="tel" value="<?= htmlspecialchars($user_tel) ?>">
    <input type="hidden" name="options" value="<?= htmlspecialchars(json_encode($options, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="place" value="<?= htmlspecialchars($place) ?>">
    <input type="hidden" name="place_comment" value="<?= htmlspecialchars($place_comment) ?>">
    <input type="hidden" name="area" value="<?= htmlspecialchars($area) ?>">
    <input type="hidden" name="area_comment" value="<?= htmlspecialchars($area_comment) ?>">
    <input type="hidden" name="comment" value="<?= htmlspecialchars($comment) ?>">
    <input type="hidden" name="cost_uchiwake" value="<?= htmlspecialchars(json_encode($cost, JSON_UNESCAPED_UNICODE)) ?>">
    <input type="hidden" name="g_login_id" value="<?= htmlspecialchars($g_login_id) ?>">
    <input type="hidden" name="login_id" value="<?= htmlspecialchars($login_id) ?>">
    <input type="hidden" name="user_name" value="<?= htmlspecialchars($user_name) ?>">
    <input type="hidden" name="coupon" value="<?= htmlspecialchars($coupon) ?>">
    <input type="hidden" name="point" value="<?= htmlspecialchars($point) ?>">
    <input type="hidden" name="course_time" value="<?= htmlspecialchars($course_time) ?>">
<?php
$total_amount = 0;
foreach ($cost as $item) {
    if ($item['name'] === '合計') {
        $total_amount = $item['amount'];
        break;
    }
}
?>
<input type="hidden" name="cost" value="<?= htmlspecialchars($total_amount) ?>">


    <div class="mb-3 text-center">
        <button type="button" class="btn btn-primary btn-lg" onclick="submitReservation()">
            SMS認証へ
        </button>
    </div>
</form>
    <!-- 戻る -->
    <a type="button" class="btn btn-back" href="reserve.php">戻る</a>

<?php include 'footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>

<script>
const payMethod = <?= json_encode($pay) ?>;

function submitReservation() {
    const form = document.getElementById('reserveForm');

    
    form.action = 'reserve_sms.php'; // 現金
    

    form.submit();
}
</script>