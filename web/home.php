<?php 

include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');




  // DB接続
  $pdo = dbConnect(); // 共通関数で接続

  //Today's girlを取得


// 取得したい日付
$target_date = "2025-09-25";

// SQL準備
$sql = "SELECT id, date, in_time, out_time, g_login_id 
        FROM shift 
        WHERE date = :date";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':date', $target_date, PDO::PARAM_STR);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($results);//何件あったか


$counter = 1;
foreach ($results as $row) {
    // data1, data2, data3... という変数を自動で作成
    ${"data".$counter} = $row;
    $counter++;
}

$girls_today = []; // 今日出勤の子の girl データを格納する配列

for ($i = 1; $i <= $count; $i++) {
    $target_g = ${"data".$i}['g_login_id'] ?? null;

    if ($target_g) {
        $sql_g = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
        $stmt_g = $pdo->prepare($sql_g);
        $stmt_g->bindValue(':g_login_id', $target_g, PDO::PARAM_STR);
        $stmt_g->execute();
        $results_g = $stmt_g->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($results_g)) {
            $girls_today[] = $results_g[0]; // 1件目だけ入れる場合
        }
    }
}

// 確認用表示
echo "<pre>";
print_r($girls_today);
echo "</pre>";





?>
  <!--オリジナルCSS-->
  <link href="./css/home.css" rel="stylesheet">

  <h1>トップページ</h1>
  <p>ここにコンテンツ</p>
<div class="gcard"><!--女の子カード-->
  <img class="gcardimg" src="../img/こい１a.jpg">
  <div class="gcard-textarea">
    <div class="left-text">
      <span class="name"><?= htmlspecialchars($girls_today[0]['name']) ?></span>
      <span class="headcomment"><?= htmlspecialchars($girls_today[0]['head_comment']) ?></span>
    </div>
    <span class="out_time"><?= htmlspecialchars($results[0]['out_time']) ?></span>
  </div>
</div>



<?php include 'footer.php'; ?>
<script src="script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
