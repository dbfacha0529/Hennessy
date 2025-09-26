<?php

include 'header.php';


$pdo = dbConnect();


// 配列の中身を確認
echo '<pre>';
print_r($_SESSION["RESERVE_DATA"]);
echo '</pre>';

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

$orig_date = $_SESSION["RESERVE_DATA"]['reserve_date']; // DATE型の文字列

    // 時間の時だけを取得
    list($hour, $minute) = explode(':', $_SESSION["RESERVE_DATA"]['reserve_time']);
    $hour = (int)$hour;

    // DateTimeオブジェクト作成
    $dateObj = new DateTime($orig_date);

    // 00:00〜05:59なら前日にする
    if ($hour >= 0 && $hour <= 5) {
        $dateObj->modify('-1 day');
    }

    // $date に調整後の日付を格納
$date = $dateObj->format('Y-m-d');


    $date = $_SESSION["RESERVE_DATA"]['reserve_date'];   // 例: "2025-09-23"
    $time = $_SESSION["RESERVE_DATA"]['reserve_time'];   // 例: "14:30:00"

    // DATE と TIME を結合して DateTime オブジェクトを作成
$in_time = new DateTime($date . ' ' . $time);


$out_time = clone $in_time; // 元の日時を保持するためクローン
$out_time->add(new DateInterval('PT' . $course_time . 'M'));

$end_time = clone $in_time; // 元の日時を保持するためクローン
$end_time->add(new DateInterval('PT10M'));

$start_time = clone $in_time; // 元の日時を保持するためクローン
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
$user_name = $_SESSION["USER"]['user_name'];
$options = $_SESSION["RESERVE_DATA"]['options'];//これ配列なんだよね
$place = $_SESSION["RESERVE_DATA"]['place'];
$place_comment = $_SESSION["RESERVE_DATA"]['place_other'];
$area = $_SESSION["RESERVE_DATA"]['area'];
$area_comment = $_SESSION["RESERVE_DATA"]['area_other'];
$hotel = $_SESSION["RESERVE_DATA"][''];
$hotel_num = $_SESSION["RESERVE_DATA"][''];
$hotel_comment = $_SESSION["RESERVE_DATA"][''];

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
$cost = $_SESSION["RESERVE_DATA"]['total_amount']; 
$cost_uchiwake = $_SESSION["RESERVE_DATA"]['pricing']; 


$dt = new DateTime($orig_date);  // DATE型からDateTimeに変換
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

$orig_date_fmt = $dt->format('Y年n月j日（') 
                 . $weekdays[$dt->format('w')] 
                 . '）';
$in_time_fmt = $in_time->format('H時i分'); // 文字列に変換
$out_time_fmt = $out_time->format('H時i分'); // 文字列に変換

$pay_label = '';
    if ($pay == 1) {
        $pay_label = '現金';
    } elseif ($pay == 2) {
        $pay_label = 'クレジットカード';
    }
//<?= htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') ?>
  <!--オリジナルCSS-->
  <link href="./css/reserve_confirm.css" rel="stylesheet">


<table  cellspacing="0" cellpadding="8">
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
      <td><?= htmlspecialchars($orig_date_fmt, ENT_QUOTES, 'UTF-8') ?></td>
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
    <tr>
      <td>ご指名</td>
      <td><?= htmlspecialchars($g_name, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
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
      <td>エリア</td>
      <td><?= htmlspecialchars($area, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <?php if (!empty($area_comment)): ?>
    <tr>
      <td>備考欄</td>
      <td><?= htmlspecialchars($area_comment, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <?php endif; ?>
    <tr>
       <td>オプション</td>
       <td><?= nl2br(htmlspecialchars(implode("\n", $options), ENT_QUOTES, 'UTF-8')) ?></td>
    </tr>
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

<table  cellspacing="0" cellpadding="8">
  <thead>
    <tr>
      <th>要素名</th>
      <th>金額</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>コース</td>
      <td>3,000円</td>
    </tr>
    <tr>
      <td>指名料</td>
      <td>10,000円</td>
    </tr>
    <tr>
      <td>派遣料</td>
      <td>2,000円</td>
    </tr>
    <tr>
      <td>タオル代</td>
      <td>3,000円</td>
    </tr>
    <tr>
      <td>オプション</td>
      <td>3,000円</td>
    </tr>
    <tr>
      <td>クーポン</td>
      <td>3,000円</td>
    </tr>
    <tr>
      <td>ポイント</td>
      <td>3,000円</td>
    </tr>


    <tr>
      <td><strong>合計</strong></td>
      <td><strong>15,000円</strong></td>
    </tr>
  </tbody>
</table>
<?php include 'footer.php'; ?>
<script src="script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>