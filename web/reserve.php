<?php 
include 'header.php';
// エラー表示とフォーム復元処理
$err = $_SESSION['RESERVE_ERRORS'] ?? [];
$old = $_SESSION['RESERVE_INPUT'] ?? [];

// 使ったら消す
unset($_SESSION['RESERVE_ERRORS'], $_SESSION['RESERVE_INPUT']);


$pdo = dbConnect();
$k_checkgirls = k_checkgirls($pdo);

// place料金データ取得
function getPlaceFees($pdo) {
    $stmt = $pdo->query("SELECT place_name, haken_fee, towel_fee FROM place_fees");
    $data = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        $data[$row['place_name']] = [
            'haken_fee' => (int)$row['haken_fee'],
            'towel_fee' => (int)$row['towel_fee']
        ];
    }
    return $data;
}

// 基準日を取得（明朝6時切り替え）
$baseDate = getBaseDate();

// 基準日出勤の女の子
$girls_today = girls_targetday($pdo, $baseDate);
$names_today = array_column($girls_today,'name');

// 女の子プルダウン作成
$sg_array = [];
foreach($k_checkgirls as $g){
    $label = $g['name'];
    if(in_array($g['name'], $names_today)){
        $label .= '（本日出勤）';
    }
    $sg_array[] = ['value'=>$g['name'], 'label'=>$label];
}

// コースプルダウン
$course_array = getDropdownArray($pdo,'course','c_name');

// 日付プルダウン（7日分）
$date_array = [];
$weekdays = ['日','月','火','水','木','金','土'];
for($i=0;$i<7;$i++){
    $d = new DateTime($baseDate);
    $d->modify("+$i day");
    $label = '';
    if($i===0) $label = '今日';
    elseif($i===1) $label = '明日';
    else $label = $d->format('n/j').'('.$weekdays[$d->format('w')].')';

    $date_array[] = [
        'value'=>$d->format('Y-m-d'),
        'label'=>$label
    ];
}

// 時間プルダウン作成
$time_array = [];


// area / place プルダウン
$area_array = getDropdownArray($pdo, 'area', 'area_name');
$place_array = getDropdownArray($pdo, 'place', 'place_name');

// ご利用可能ポイント初期化
$available_point = 0;
$point_err = '';

// ログインユーザーのtelを取得
if(!empty($_SESSION['USER']['tel'])){
    $tel = $_SESSION['USER']['tel'];

    try {
        $sql = "SELECT balance_after FROM point WHERE tel = :tel ORDER BY created DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
        $stmt->execute();
        $point = $stmt->fetchColumn();

        if($point !== false){
            $available_point = (int)$point;
        }
    } catch(PDOException $e){
        $point_err = 'ポイント情報の取得中にエラーが発生しました';
    }
} else {
    $point_err = 'ログイン情報が取得できません';
}

//コースデータ作成
function getCourseData($pdo) {
    $stmt = $pdo->query("SELECT c_name, cost, free_check FROM course");
    $data = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        $data[$row['c_name']] = $row;
    }
    return $data;
}

//オプションデータ作成
function getAllOptions($pdo) {
    $stmt = $pdo->query("SELECT option_name, option_cost FROM options");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//遷移データ
$preselected_g_login_id = $_GET['g_login_id'] ?? '';
$preselected_g_name = '';

if (!empty($preselected_g_login_id)) {
    $stmt = $pdo->prepare("SELECT name FROM girl WHERE g_login_id = :g_login_id LIMIT 1");
    $stmt->bindValue(':g_login_id', $preselected_g_login_id, PDO::PARAM_STR);
    $stmt->execute();
    $preselected_girl = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preselected_girl) {
        $preselected_g_name = $preselected_girl['name'];
    }
}

?>

<link href="./css/reserve.css" rel="stylesheet">

<?php if (!empty($err)): ?>
  <div class="alert alert-danger">
      <?php if (!empty($err['touroku'])): ?>
    <?php foreach ($err['touroku'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
    入力内容に誤りがあります。各項目をご確認ください。
  </div>
<?php endif; ?>

<!-- 女の子選択 -->
<div class="mb-3">
  <label for="girl-select">女の子を選択</label>
<select id="girl-select" class="form-select" name="girl_name">
  <option value="">-- 選択してください --</option>
  <?php foreach($sg_array as $g): ?>
    <option value="<?= htmlspecialchars($g['value']) ?>"
      <?= (isset($old['girl_name']) && $old['girl_name'] === $g['value']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($g['label']) ?>
    </option>
  <?php endforeach; ?>
</select>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['girl'])): ?>
    <?php foreach ($err['girl'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- 女の子カード -->
<div class="gcard" id="girl-card" style="display:none;">
  <img class="gcardimg" id="girl-img" src="../img/noimage.jpg" alt="">
  
  <div class="gcard-center">
    <span class="name" id="girl-name"></span>
    <span class="headcomment" id="girl-headcomment"></span>
  </div>
  
  <div class="gcard-right">
    <span class="status" id="girl-status"></span>
    <span class="time" id="girl-time"></span>
  </div>
</div>

<!-- コース選択 -->
<div class="mb-3">
  <label for="course-select">コースを選択</label>
<select id="course-select" class="form-select" name="course_name">
  <option value="">-- 選択してください --</option>
  <?php foreach($course_array as $v=>$l): ?>
    <option value="<?= htmlspecialchars($v) ?>"
      <?= (isset($old['course_name']) && $old['course_name'] === $v) ? 'selected' : '' ?>>
      <?= htmlspecialchars($l) ?>
    </option>
  <?php endforeach; ?>
</select>

  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['course'])): ?>
    <?php foreach ($err['course'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- 日付選択 -->
<div class="mb-3">
  <label for="date-select">日付を選択</label>
<select id="date-select" class="form-select" name="reserve_date">
  <option value="">-- 選択してください --</option>
  <?php foreach($date_array as $d): ?>
    <option value="<?= htmlspecialchars($d['value']) ?>"
      <?= (isset($old['reserve_date']) && $old['reserve_date'] === $d['value']) ? 'selected' : '' ?>>
      <?= htmlspecialchars($d['label']) ?>
    </option>
  <?php endforeach; ?>
</select>



  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['date'])): ?>
    <?php foreach ($err['date'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- 予約可能時間表 -->
<div class="table-wrapper">
  <table class="schedule-table" id="schedule-table">
    <thead><tr id="schedule-header"><th>時間</th></tr></thead>
    <tbody id="schedule-body"></tbody>
  </table>
</div>

<!-- 時間選択 -->
<div class="mb-3">
  <label for="time-select">時間を選択</label>
  <select id="time-select" class="form-select">
    <option value="">-- 選択してください --</option>
  </select>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['time'])): ?>
    <?php foreach ($err['time'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- place選択 -->
<div class="mb-3">
  <label for="place-select">形態を選択</label>
  <select id="place-select" class="form-select">
    <option value="">-- 選択してください --</option>
    <?php foreach($place_array as $value => $label): ?>
      <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
    <option value="その他">その他</option>
  </select>

  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['place'])): ?>
    <?php foreach ($err['place'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="mb-3" id="other-field" style="display:none;">
  <label for="other-textarea" class="form-label">その他記載欄</label>
  <textarea class="form-control" id="other-textarea" rows="3"></textarea>
</div>

<!-- area選択 -->
<div class="mb-3">
  <label for="area-select">エリアを選択</label>
  <select id="area-select" class="form-select">
    <option value="">-- 選択してください --</option>
    <?php foreach($area_array as $value => $label): ?>
      <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
    <?php endforeach; ?>
    <option value="その他">その他</option>
  </select>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['area'])): ?>
    <?php foreach ($err['area'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="mb-3" id="other-area-wrapper" style="display:none;">
  <label for="other-area-text" class="form-label">その他（エリア）</label>
      <div class="form-check">
  <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault">
  <label class="form-check-label" for="flexCheckDefault">
    郡山市外の場合はチェックを入れて下さい
  </label>
</div>
  <textarea class="form-control" id="other-area-text" rows="3">
  </textarea>
</div>

<!-- オプション -->
<div class="mb-3" id="options-wrapper" style="display:none;">
  <label class="form-label">オプション</label>
  <div id="options-list"></div>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['option'])): ?>
    <?php foreach ($err['option'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>



<!-- お支払方法 -->
<div class="mb-3">
  <label for="pay-select">お支払方法を選択</label>
  <select id="pay-select" class="form-select">
    <option value="1" selected>現金</option>
    <option value="2">クレジットカード</option>
  </select>
  <div id="pay-message" style="color:red; margin-top:5px; display:none;">
    未定、不確定な要素があるためお支払は現金のみとなります
  </div>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['pay'])): ?>
    <?php foreach ($err['pay'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- クーポン -->
<div class="mb-3">
  <label for="coupon_code" class="form-label">クーポン</label>
  <input type="text" class="form-control" id="coupon_code" placeholder="クーポンコード">
  <div id="coupon-result" style="margin-top:5px; color:blue;"></div>


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['coupon'])): ?>
    <?php foreach ($err['coupon'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ポイント -->
<div class="mb-3">
  <label for="use_point" class="form-label">ポイント利用</label>
  <div class="mb-1">
  <small>ご利用可能ポイント: <span id="available-point">読み込み中...</span></small>
  <div id="point-error" style="color:red; margin-top:2px;"></div>
</div>
  <input type="text" class="form-control" id="use_point" placeholder="ご利用ポイント入力">


  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['point'])): ?>
    <?php foreach ($err['point'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- お支払金額 -->
<div class = "total">
  <label>お支払金額</label>
</div>
<!-- お支払金額内訳 -->
<div class="accordion" id="accordionExample">
  <div class="accordion-item">
    <h2 class="accordion-header">
      
  <div class="accordion-item" >
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
        お支払金額内訳
      </button>
    </h2>
    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
      <div class="accordion-body">
        
      </div>
    </div>
  </div>
 </div>
</div>

<!-- tel -->
<div class="mb-3">
  <label for="use_tel" class="form-label">ご連絡先</label>
  <input type="text" class="form-control" id="use_tel" placeholder="ご連絡先"
         value="<?= htmlspecialchars($old['contact_tel'] ?? $_SESSION['USER']['tel'] ?? '') ?>">


  <label>※ご予約認証に使用いたしますので<br>お手元の端末の番号をご入力ください</label>
</div>

  <!-- エラーメッセージ表示 -->
  <?php if (!empty($err['tel'])): ?>
    <?php foreach ($err['tel'] as $msg): ?>
      <p class="error text-danger"><?= htmlspecialchars($msg) ?></p>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- 備考欄 -->
  <div class="mb-3" id="other-comment">
    <label for="other-comment-text" class="form-label">その他</label>

    <textarea class="form-control" id="other-comment-text" name="comment" rows="3"><?= htmlspecialchars($old['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

    <?php if (!empty($err['comment'])): ?>
      <?php foreach ($err['comment'] as $msg): ?>
        <p class="error text-danger"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<!-- 予約送信ボタン -->
<div class="mb-3 text-center">
  <button type="button" class="btn btn-primary btn-lg" onclick="submitReservation()">
    予約内容を確認する
  </button>
</div>

<script>
const courseData = <?= json_encode(getCourseData($pdo)) ?>; 
const optionsData = <?= json_encode(getAllOptions($pdo)) ?>;
const placeFeesData = <?= json_encode(getPlaceFees($pdo)) ?>; // ★ 追加

// PHPから復元値を渡す
const oldData = <?= json_encode($old) ?>;

document.addEventListener('DOMContentLoaded', () => {
    // 復元処理を先に行う
    // 女の子
    if(oldData.girl_name) document.getElementById('girl-select').value = oldData.girl_name;

    // コース
    if(oldData.course_name) document.getElementById('course-select').value = oldData.course_name;

    // 日付
    if(oldData.reserve_date) document.getElementById('date-select').value = oldData.reserve_date;

    // 時間（後で再設定するので、ここでは一旦保存のみ）
    const savedTime = oldData.reserve_time || null;

    // 形態
    if(oldData.place) document.getElementById('place-select').value = oldData.place;

    // placeその他
    if(oldData.place_other) document.getElementById('other-textarea').value = oldData.place_other;

    // エリア
    if(oldData.area) document.getElementById('area-select').value = oldData.area;

    // areaその他
    if(oldData.area_other) document.getElementById('other-area-text').value = oldData.area_other;

    // 郡山市外チェック
    if(oldData.area_outside && oldData.area_outside === '1') {
        document.getElementById('flexCheckDefault').checked = true;
    }

    // 支払方法
    if(oldData.payment_method) document.getElementById('pay-select').value = oldData.payment_method;

    // クーポン
    if(oldData.coupon_code) document.getElementById('coupon_code').value = oldData.coupon_code;

    // ポイント
    //if(oldData.use_point) document.getElementById('use_point').value = oldData.use_point;

    // 電話番号
    if(oldData.contact_tel) document.getElementById('use_tel').value = oldData.contact_tel;

    // その他表示非表示の制御
    if(oldData.place === 'その他') document.getElementById('other-field').style.display = 'block';
    if(oldData.area === 'その他') document.getElementById('other-area-wrapper').style.display = 'block';

    // ここから追加：復元後にイベントを手動発火させて表示を更新
    
    // コース選択の復元処理（コース→女の子の順序で処理）
    if(oldData.course_name) {
        const courseEvent = new Event('change');
        courseSelect.dispatchEvent(courseEvent);
        
        // コース処理完了後に女の子選択を処理
        setTimeout(() => {
            if(oldData.girl_name) {
                const girlEvent = new Event('change');
                girlSelect.dispatchEvent(girlEvent);
            }
            
            // スケジュール更新後に時間を復元
            setTimeout(() => {
                if(savedTime) {
                    // 時間プルダウンが生成されるのを待ってから値を設定
                    const checkTimeOptions = () => {
                        const timeOptions = timeSelect.querySelectorAll('option');
                        const timeExists = Array.from(timeOptions).some(opt => opt.value === savedTime);
                        
                        if(timeExists) {
                            timeSelect.value = savedTime;
                        } else if(timeOptions.length > 1) {
                            // まだオプションが生成されていない場合は少し待つ
                            setTimeout(checkTimeOptions, 100);
                        }
                    };
                    
                    checkTimeOptions();
                }
                
                // オプションのチェックボックス復元
                if(oldData.options && Array.isArray(oldData.options)) {
                    const restoreOptions = () => {
                        const optionCheckboxes = document.querySelectorAll('#options-list input[type="checkbox"]');
                        
                        if(optionCheckboxes.length > 0) {
                            // reserve_optionに復元値を設定
                            reserve_option = [...oldData.options];
                            
                            optionCheckboxes.forEach(checkbox => {
                                if(oldData.options.includes(checkbox.value)) {
                                    checkbox.checked = true;
                                }
                            });
                            
                            // オプション費用を更新
                            updateOptionsCost();
                        } else {
                            // オプションがまだ生成されていない場合は少し待つ
                            setTimeout(restoreOptions, 100);
                        }
                    };
                    
                    restoreOptions();
                }
            }, 500); // スケジュール更新を待つ
            
        }, 300); // コース処理を待つ
        
    } else if(oldData.girl_name) {
        // コースが選択されていない場合は女の子のみ
        const girlEvent = new Event('change');
        girlSelect.dispatchEvent(girlEvent);
        
        setTimeout(() => {
            if(savedTime) {
                const checkTimeOptions = () => {
                    const timeOptions = timeSelect.querySelectorAll('option');
                    const timeExists = Array.from(timeOptions).some(opt => opt.value === savedTime);
                    
                    if(timeExists) {
                        timeSelect.value = savedTime;
                    } else if(timeOptions.length > 1) {
                        setTimeout(checkTimeOptions, 100);
                    }
                };
                
                checkTimeOptions();
            }
            
            // オプションのチェックボックス復元（女の子のみの場合）
            if(oldData.options && Array.isArray(oldData.options)) {
                const restoreOptions = () => {
                    const optionCheckboxes = document.querySelectorAll('#options-list input[type="checkbox"]');
                    
                    if(optionCheckboxes.length > 0) {
                        // reserve_optionに復元値を設定
                        reserve_option = [...oldData.options];
                        
                        optionCheckboxes.forEach(checkbox => {
                            if(oldData.options.includes(checkbox.value)) {
                                checkbox.checked = true;
                            }
                        });
                        
                        // オプション費用を更新
                        updateOptionsCost();
                    } else {
                        // オプションがまだ生成されていない場合は少し待つ
                        setTimeout(restoreOptions, 100);
                    }
                };
                
                restoreOptions();
            }
        }, 500);
    }

    // その他の復元処理
    if(oldData.place) {
        const placeEvent = new Event('change');
        placeSelect.dispatchEvent(placeEvent);
    }
    
    if(oldData.area) {
        const areaEvent = new Event('change');
        areaSelect.dispatchEvent(areaEvent);
    }

    // クーポンがある場合は割引計算を実行
    if(oldData.coupon_code) {
        updateCouponDiscount();
    }


    // 支払い方法の制限チェック
    checkPaymentLock();
    
    // 総額計算
    updateTotalAmount();
});


const kCheckGirls = <?= json_encode($k_checkgirls) ?>;
let reserve_option = [];

// 総額計算用変数（修正版）
let courseCost = 0;       // コース料金
let nominationFee = 0;    // 指名料
let hakenFee = 0;         // 派遣料
let towelFee = 0;         // タオル代
let optionsCost = 0;      // 選択されたオプションの合計
let couponDiscount = 0;   // クーポン割引
let usePoint = 0;         // 入力されたポイント利用（エラーなければ）
let totalAmount = 0;      // 総額



// DOM
const girlSelect = document.getElementById('girl-select');
const girlCard = document.getElementById('girl-card');
const courseSelect = document.getElementById('course-select');
const dateSelect = document.getElementById('date-select');
const scheduleHeader = document.getElementById('schedule-header');
const scheduleBody = document.getElementById('schedule-body');
const placeSelect = document.getElementById('place-select');
const otherPlaceWrapper = document.getElementById('other-field');
const areaSelect = document.getElementById('area-select');
const otherAreaWrapper = document.getElementById('other-area-wrapper');
const optionsWrapper = document.getElementById('options-wrapper');
const optionsList = document.getElementById('options-list');
const paySelect = document.getElementById('pay-select');
const payMessage = document.getElementById('pay-message');
const otherAreaCheck = document.getElementById('flexCheckDefault'); // エリアチェックボックス
const couponInput = document.getElementById('coupon_code');
const couponResult = document.getElementById('coupon-result');
const availablePointEl = document.getElementById('available-point');
const pointErrorEl = document.getElementById('point-error');
const usePointInput = document.getElementById('use_point');
const totalAmountEl = document.querySelector('.total');
const timeSelect = document.getElementById('time-select');
const comment = document.getElementById('other-comment-text');

// 総額計算関数（修正版）
function updateTotalAmount() {
    let total = 0;

    // 各項目を加算（空白や未定なら0扱い）
    const course = courseCost || 0;
    const nomination = nominationFee || 0;
    const haken = hakenFee || 0;
    const towel = towelFee || 0;
    const options = optionsCost || 0;
    const coupon = couponDiscount || 0;
    const point = usePoint || 0;

    total += course + nomination + haken + towel + options;
    total -= coupon + point;

    total = Math.max(0, total);

    // 総額表示
    totalAmountEl.innerHTML = `
        <label>お支払金額: ${total.toLocaleString()}円</label>
        <div style="color:red; font-size:0.9em; margin-top:5px;">
            ${(!areaSelect.value || areaSelect.value==='未定' || areaSelect.value==='その他' && otherAreaCheck.checked ||
              !placeSelect.value || placeSelect.value==='未定' || placeSelect.value==='その他') 
              ? '※未確定の要素がある為、配送料やタオル代が加算される場合がございます' 
              : ''}
        </div>
    `;

    // 内訳テーブル生成（accordion-body内）
    const accordionBody = document.querySelector('#collapseTwo .accordion-body');
    accordionBody.innerHTML = `
        <table style="width:100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="border-bottom:1px solid #ccc; text-align:left;">項目</th>
              <th style="border-bottom:1px solid #ccc; text-align:right;">金額</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>コース料金</td><td style="text-align:right;">${course.toLocaleString()}円</td></tr>
            <tr><td>指名料</td><td style="text-align:right;">${nomination.toLocaleString()}円</td></tr>
            <tr><td>派遣料</td><td style="text-align:right;">${haken.toLocaleString()}円</td></tr>
            <tr><td>タオル代</td><td style="text-align:right;">${towel.toLocaleString()}円</td></tr>
            <tr><td>オプション合計</td><td style="text-align:right;">${options.toLocaleString()}円</td></tr>
            <tr><td>クーポン割引</td><td style="text-align:right;">-${coupon.toLocaleString()}円</td></tr>
            <tr><td>ポイント利用</td><td style="text-align:right;">-${point.toLocaleString()}円</td></tr>
            <tr><td><strong>合計</strong></td><td style="text-align:right;"><strong>${total.toLocaleString()}円</strong></td></tr>
          </tbody>
        </table>
    `;
}

// エリア選択時に料金更新（修正版）
areaSelect.addEventListener('change', () => {
    updateTotalAmount(); // 総額更新関数呼び出し
});

// コース選択時に料金更新（修正版）
courseSelect.addEventListener('change', () => {
    const selected = courseSelect.value;
    if(selected && courseData[selected]){
        courseCost = parseInt(courseData[selected].cost) || 0;
        nominationFee = courseData[selected].free_check === 0 ? 2000 : 0;
    } else {
        courseCost = 0;
        nominationFee = 0;
    }

    updateTotalAmount(); // 総額更新関数呼び出し
});


// place選択時に料金更新（修正版）
function updatePlaceFees() {
    const selectedPlace = placeSelect.value;
    hakenFee = 0;
    towelFee = 0;

    // DBから取得した料金データを使用
    if (placeFeesData[selectedPlace]) {
        hakenFee = placeFeesData[selectedPlace].haken_fee || 0;
        towelFee = placeFeesData[selectedPlace].towel_fee || 0;
    }

    updateTotalAmount();
}

// place選択時に実行
placeSelect.addEventListener('change', updatePlaceFees);

// オプション費用計算（修正版）
function updateOptionsCost() {
    if(reserve_option.length === 0) {
        optionsCost = 0; 
        updateTotalAmount();
        return;
    }

    // オプション費用を計算
    optionsCost = reserve_option.reduce((sum, optName) => {
        const opt = optionsData.find(o => o.option_name === optName);
        return sum + (opt ? parseInt(opt.option_cost, 10) : 0);
    }, 0);

    updateTotalAmount();
}

// クーポンチェック関数（修正版）
function updateCouponDiscount() {
    const code = couponInput.value.trim();
    if (!code) {
        couponDiscount = 0;
        couponResult.textContent = '';
        updateTotalAmount();
        return;
    }

    fetch(`check_coupon.php?coupon_code=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const couponName = Array.isArray(data.name) ? data.name[0] : data.name;
                couponResult.style.color = 'green';
                couponResult.textContent = `クーポン名: ${couponName} `;

                // nebiki を取得して couponDiscount にセット
                return fetch(`nebiki.php?coupon_code=${encodeURIComponent(code)}`)
                    .then(res => res.json())
                    .then(nebikiData => {
                        if (nebikiData.success) {
                            couponDiscount = parseInt(nebikiData.name, 10) || 0;
                            couponResult.textContent = `クーポン名: ${couponName} 割引額: ${couponDiscount}円`;
                        } else {
                            couponDiscount = 0;
                            couponResult.style.color = 'red';
                            couponResult.textContent = nebikiData.message;
                        }
                    });
            } else {
                couponDiscount = 0;
                couponResult.style.color = 'red';
                couponResult.textContent = data.message;
            }
        })
        .catch(err => {
            console.error(err);
            couponDiscount = 0;
            couponResult.style.color = 'red';
            couponResult.textContent = '通信エラーが発生しました';
        })
        .finally(() => {
            updateTotalAmount(); // 最後に1回だけ呼ぶ
        });
}

// ポイント入力処理（修正版）
usePointInput.addEventListener('input', () => {
    const entered = parseInt(usePointInput.value, 10) || 0;
    const available = parseInt(availablePointEl.textContent, 10) || 0;

    if (isNaN(entered) || entered <= 0) {
        usePoint = 0; 
        pointErrorEl.textContent = '';
    } else if (entered > available) {
        usePoint = 0; // 超過時は加算しない
        pointErrorEl.textContent = 'ご利用ポイントがご利用可能ポイントを超えています';
    } else {
        usePoint = entered;
        pointErrorEl.textContent = ''; // エラー解除
    }

    updateTotalAmount(); // 総額計算を更新
});

// リセット関数
function resetTime(){
    if(timeSelect) timeSelect.innerHTML = '<option value="">-- 選択してください --</option>';
}
function resetSchedule(){
    scheduleHeader.innerHTML='<th>時間</th>';
    scheduleBody.innerHTML='';
}
function resetGirlSelection(){
    girlSelect.value = '';
    girlCard.style.display='none';
    optionsWrapper.style.display='none';
    optionsList.innerHTML='';
    reserve_option=[];
}

// フリーコース用のオプション表示関数
function updateFreeOptions() {
    const dateVal = dateSelect.value;
    const timeVal = timeSelect.value;
    const courseVal = courseSelect.value;
    
    if (!dateVal || !timeVal || !courseVal) {
        return;
    }
    
    // コース情報を取得してフリーコースかチェック
    const courseInfo = courseData[courseVal];
    if (!courseInfo || parseInt(courseInfo.free_check) !== 1) {
        return; // フリーコースでない場合は何もしない
    }
    
    // 時間情報を構築（日跨ぎを考慮）
    let startTime;
    const [hours, minutes] = timeVal.split(':');
    const hour = parseInt(hours);
    
    if (hour < 6) {
        // 00:00-05:59は翌日扱い
        const nextDay = new Date(dateVal);
        nextDay.setDate(nextDay.getDate() + 1);
        startTime = `${nextDay.getFullYear()}-${String(nextDay.getMonth() + 1).padStart(2, '0')}-${String(nextDay.getDate()).padStart(2, '0')} ${timeVal}:00`;
    } else {
        // 06:00以降は当日扱い
        startTime = `${dateVal} ${timeVal}:00`;
    }
    
    const courseMinutes = parseInt(courseInfo.time) || 60;
    const endTime = new Date(new Date(startTime).getTime() + courseMinutes * 60000);
    const endTimeStr = endTime.toISOString().slice(0, 19).replace('T', ' ');
    
    // オプション取得
    fetch(`get_free_options.php?date=${encodeURIComponent(dateVal)}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTimeStr)}&course_time=${courseMinutes}`)
        .then(res => res.json())
        .then(options => {
            displayFreeOptions(options);
        })
        .catch(err => {
            console.error('Free options fetch error:', err);
            displayFreeOptions([]);
        });
}

// フリーコース用オプション表示
function displayFreeOptions(options) {
    optionsList.innerHTML = '';
    reserve_option = []; // リセット
    
    if (options.length === 0) {
        optionsWrapper.style.display = 'none';
        return;
    }
    
    optionsWrapper.style.display = 'block';
    
    options.forEach((option, index) => {
        const div = document.createElement('div');
        div.className = 'form-check';
        
        const input = document.createElement('input');
        input.className = 'form-check-input';
        input.type = 'checkbox';
        input.id = `free-opt-${index}`;
        input.value = option;
        input.addEventListener('change', (e) => {
            if (e.target.checked) {
                reserve_option.push(e.target.value);
            } else {
                reserve_option = reserve_option.filter(v => v !== e.target.value);
            }
            updateOptionsCost();
        });
        
        const label = document.createElement('label');
        label.className = 'form-check-label';
        label.htmlFor = `free-opt-${index}`;
        label.textContent = option;
        
        div.appendChild(input);
        div.appendChild(label);
        optionsList.appendChild(div);
    });
}

// スケジュール表示（修正版）
function updateSchedule(){
    const g_name = girlSelect.value;
    const date = dateSelect.value;
    const c_name = courseSelect.value;
    resetTime();
    
    if(!date || !c_name){
        resetSchedule();
        return;
    }

    // コースデータからfree_checkを確認
    const courseInfo = courseData[c_name];
    const isFreeCheck = courseInfo && parseInt(courseInfo.free_check) === 1;

    if (isFreeCheck) {
    fetch(`get_schedule_free.php?date=${encodeURIComponent(date)}&c_name=${encodeURIComponent(c_name)}`)
    .then(res => res.json())
    .then(data => {
        resetSchedule();
        
        // データがない場合の処理
        if (data.no_data === true) {
            scheduleHeader.innerHTML = '<th>メッセージ</th>';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.textContent = '出勤登録がされていません';
            td.style.color = 'red';
            td.style.textAlign = 'center';
            td.style.padding = '20px';
            tr.appendChild(td);
            scheduleBody.appendChild(tr);
            return;
        }
        
        const times = Object.keys(data);

        // ヘッダー行作成
        let header = '<th>時間</th>';
            times.forEach(t => header += '<th>' + t + '</th>');
            scheduleHeader.innerHTML = header;

            // データ行作成
            const tr = document.createElement('tr');
            const tdLabel = document.createElement('td');
            tdLabel.textContent = 'フリー';
            tr.appendChild(tdLabel);

            times.forEach(t => {
                const td = document.createElement('td');
                td.textContent = data[t];
                if(data[t] === '〇') {
                    td.style.cursor = 'pointer';
                    td.style.color = 'green';
                    const timeValue = t;
                    td.addEventListener('click', () => {
                       timeSelect.value = timeValue;
                       // 時間選択時にオプションを更新
                       updateFreeOptions();
                    });
                } else if(data[t] === '✖') {
                    td.style.color = 'red';
                }
                tr.appendChild(td);
            });

            scheduleBody.appendChild(tr);

            // 予約可能時間をプルダウンにセット
            const availableTimes = times.filter(t => data[t] === '〇');
            timeSelect.innerHTML = '<option value="">-- 選択してください --</option>';
            availableTimes.forEach(t => {
                const option = document.createElement('option');
                option.value = t;
                option.textContent = t;
                timeSelect.appendChild(option);
            });
        })
        .catch(err => {
            console.error('Free schedule fetch error:', err);
            resetSchedule();
        });
    } else {
        // 既存の処理（女の子指定あり）
        if (!g_name) {
            resetSchedule();
            return;
        }
        
        fetch(`get_schedule.php?g_name=${encodeURIComponent(g_name)}&date=${encodeURIComponent(date)}&c_name=${encodeURIComponent(c_name)}`)
        .then(res=>res.json())
        .then(data=>{
            resetSchedule();
            
            // データがない場合の処理
            if (data.no_data === true) {
                scheduleHeader.innerHTML = '<th>メッセージ</th>';
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.textContent = '出勤登録がされていません';
                td.style.color = 'red';
                td.style.textAlign = 'center';
                td.style.padding = '20px';
                tr.appendChild(td);
                scheduleBody.appendChild(tr);
                return;
            }
            
            const times = Object.keys(data);
            let header = '<th>時間</th>';
            times.forEach(t=>header+='<th>'+t+'</th>');
            scheduleHeader.innerHTML = header;

            const tr = document.createElement('tr');
            const tdLabel = document.createElement('td');
            tdLabel.textContent = g_name;
            tr.appendChild(tdLabel);

            times.forEach(t=>{
                const td = document.createElement('td');
                td.textContent = data[t] || 'tel';
                if(data[t] === '〇') {
                    td.style.cursor = 'pointer';
                    td.style.color = 'green';
                    td.addEventListener('click', ()=>{ 
                        timeSelect.value = t; 
                    });
                } else if(data[t] === '✖') {
                    td.style.color = 'red';
                }
                tr.appendChild(td);
            });
            scheduleBody.appendChild(tr);

            // 予約可能時間をプルダウンにセット
            const availableTimes = times.filter(t => data[t] === '〇');
            timeSelect.innerHTML = '<option value="">-- 選択してください --</option>';
            availableTimes.forEach(t => {
                const option = document.createElement('option');
                option.value = t;
                option.textContent = t;
                timeSelect.appendChild(option);
            });

        })
        .catch(err => {
            console.error('Regular schedule fetch error:', err);
            resetSchedule();
        });
    }
}

// 女の子オプション読み込み
function loadGirlOptions(gid){
    fetch('get_girl.php?gid='+encodeURIComponent(gid))
    .then(res=>res.json())
    .then(data=>{
        if(data && data.option){
            let options = JSON.parse(data.option);
            optionsList.innerHTML='';
            optionsWrapper.style.display='block';
            options.forEach((opt,i)=>{
                const div = document.createElement('div');
                div.className='form-check';
                const input = document.createElement('input');
                input.className='form-check-input';
                input.type='checkbox';
                input.id='opt-'+i;
                input.value=opt;
                input.addEventListener('change', (e)=>{
                    if(e.target.checked) reserve_option.push(e.target.value);
                    else reserve_option = reserve_option.filter(v=>v!==e.target.value);
                    updateOptionsCost();
                });
                const label = document.createElement('label');
                label.className='form-check-label';
                label.htmlFor='opt-'+i;
                label.textContent=opt;
                div.appendChild(input);
                div.appendChild(label);
                optionsList.appendChild(div);
            });
        } else {
            optionsWrapper.style.display='none';
            optionsList.innerHTML='';
            reserve_option=[];
            updateOptionsCost();
        }
    });
}

// 女の子選択
girlSelect.addEventListener('change', ()=>{
    reserve_option = [];
    const selectedName = girlSelect.value;
    const g = kCheckGirls.find(g=>g.name===selectedName);
    if(!g){
        resetGirlSelection();
        updateSchedule();
        return;
    }

    fetch('get_girl.php?gid='+encodeURIComponent(g.g_login_id))
    .then(res=>res.json())
    .then(data=>{
        if(data){
            document.getElementById('girl-img').src='../img/'+(data.img||'noimage.jpg');
            document.getElementById('girl-name').textContent=data.name||'';
            document.getElementById('girl-headcomment').textContent=data.head_comment||'';
            
            // ステータスと時間を表示（home.phpと同じ形式）
            const statusSpan = document.getElementById('girl-status');
            const timeSpan = document.getElementById('girl-time');
            
            // ステータスの設定
            if(data.status) {
                statusSpan.textContent = data.status;
                statusSpan.className = 'status ' + (data.status === '今すぐOK' ? 'status-now' : 'status-today');
                statusSpan.style.display = 'block';
            } else {
                statusSpan.style.display = 'none';
            }
            
            // 時間の設定
            // 時間の設定
if(data.time) {
    timeSpan.textContent = data.time;
    timeSpan.style.display = 'block';
} else {
    timeSpan.style.display = 'none';
}
            
            girlCard.style.display='flex';
        } else girlCard.style.display='none';
        loadGirlOptions(g.g_login_id);
        updateSchedule();
    });
});

// コース選択（修正版）
courseSelect.addEventListener('change', function(){
    const c_name = this.value;
    if(!c_name){
        girlSelect.disabled = false;
        updateSchedule();
        return;
    }

    fetch(`get_course.php?course=${encodeURIComponent(c_name)}`)
        .then(res=>res.json())
        .then(data=>{
            if(data && parseInt(data.free_check)===1){
                // 女の子選択無効化
                resetGirlSelection();
                resetTime();
                resetSchedule();
                girlSelect.disabled = true;
            } else {
                girlSelect.disabled = false;
            }
            // スケジュール更新（free_checkの状態に応じて処理される）
            updateSchedule();
        })
        .catch(err=>{
            console.error(err);
            girlSelect.disabled = false;
            updateSchedule();
        });
});

// 日付変更
dateSelect.addEventListener('change', updateSchedule);

// 時間選択のイベントリスナー追加
timeSelect.addEventListener('change', () => {
    const courseVal = courseSelect.value;
    if (courseVal) {
        const courseInfo = courseData[courseVal];
        if (courseInfo && parseInt(courseInfo.free_check) === 1) {
            updateFreeOptions();
        }
    }
});

// place「その他」
placeSelect.addEventListener('change', ()=> { 
    const placeVal = placeSelect.value;
    
    // その他の場合
    otherPlaceWrapper.style.display = placeVal === 'その他' ? 'block' : 'none';
    
    // ★ ご自宅の場合の処理
    if (placeVal === 'ご自宅') {
        // エリアを「その他」に設定
        areaSelect.value = 'その他';
        areaSelect.disabled = true;
        
        // その他エリアを表示
        otherAreaWrapper.style.display = 'block';
        
        // 赤文字メッセージを追加（既存がなければ）
        if (!document.getElementById('address-notice')) {
            const notice = document.createElement('div');
            notice.id = 'address-notice';
            notice.style.color = 'red';
            notice.style.marginTop = '5px';
            notice.style.marginBottom = '5px';
            notice.textContent = '住所を下記に記入してください';
            
            // その他（エリア）ラベルの後に挿入
            const label = otherAreaWrapper.querySelector('label');
            label.parentNode.insertBefore(notice, label.nextSibling);
        }
    } else {
        // ご自宅以外の場合は制限解除
        areaSelect.disabled = false;
        
        // 赤文字メッセージを削除
        const notice = document.getElementById('address-notice');
        if (notice) {
            notice.remove();
        }
        
        // エリアがその他でない場合は非表示
        if (areaSelect.value !== 'その他') {
            otherAreaWrapper.style.display = 'none';
        }
    }
    
    updatePlaceFees();
});

// area「その他」
areaSelect.addEventListener('change', ()=>{ otherAreaWrapper.style.display = areaSelect.value==='その他'?'block':'none'; });

// ページロード時
dateSelect.value='<?= $baseDate ?>';
updateSchedule();

//お支払選択
function checkPaymentLock(){
    const placeVal = placeSelect.value;
    const areaVal = areaSelect.value;
    const otherCheck = otherAreaCheck.checked;

    // 判定：placeが未定・空・その他 / areaが未定・空 / その他チェックON
    if(
        !placeVal || placeVal==='未定' || placeVal==='その他' ||
        !areaVal || areaVal==='未定' ||
        otherCheck
    ){
        paySelect.value = '1'; // 現金
        paySelect.disabled = true;
        payMessage.style.display = 'block';
    } else {
        paySelect.disabled = false;
        payMessage.style.display = 'none';
    }
}

// place / area / その他チェック変更時に呼ぶ
placeSelect.addEventListener('change', checkPaymentLock);
areaSelect.addEventListener('change', checkPaymentLock);
otherAreaCheck.addEventListener('change', () => {
    checkPaymentLock(); // 既存の処理はそのまま呼ぶ

    // ここから追加したいコード
    updateTotalAmount(); // 例えば総額表示の更新など
    // 他にも任意の処理を書けます
});

// ページロード時にもチェック
checkPaymentLock();

//couponチェック（修正版）
let couponTimer;
couponInput.addEventListener('input', ()=>{
    clearTimeout(couponTimer);
    couponResult.textContent = '';
    const code = couponInput.value.trim();
    
    if(!code) {
        updateTotalAmount();
        return;
    }

    // 入力停止 500ms で検索
    couponTimer = setTimeout(()=>{
        updateCouponDiscount(); // ここで割引計算も含めて実行
    }, 500);
});

//ポイント残高取得
fetch('get_point.php')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            availablePointEl.textContent = data.balance;
        } else {
            availablePointEl.textContent = '-';
            pointErrorEl.textContent = data.message;
        }
        
        // ポイント残高取得後にポイント復元処理を実行
        if(oldData.use_point) {
            usePointInput.value = oldData.use_point;
            const pointEvent = new Event('input');
            usePointInput.dispatchEvent(pointEvent);
        }
    })
    .catch(err => {
        availablePointEl.textContent = '-';
        pointErrorEl.textContent = '通信エラーでポイントを取得できません';
        console.error(err);
    });

// 初期表示時に総額を更新
document.addEventListener('DOMContentLoaded', function() {
    updateTotalAmount();
    updateSchedule();
});


function submitReservation() {
    // 予約時間が過去でないかチェック
    const reserveDate = dateSelect.value;
    const reserveTime = timeSelect.value;
    
    if (!reserveDate || !reserveTime) {
        alert('日付と時間を選択してください');
        return;
    }
    
    // 時間の結合と日跨ぎ処理
    const [hour, minute] = reserveTime.split(':');
    let checkDate = reserveDate;
    
    // 00:00～07:59は翌日扱い
    if (parseInt(hour) >= 0 && parseInt(hour) < 8) {
        const nextDay = new Date(reserveDate);
        nextDay.setDate(nextDay.getDate() + 1);
        checkDate = nextDay.toISOString().split('T')[0];
    }
    
    const reserveDatetime = new Date(checkDate + 'T' + reserveTime + ':00');
    const now = new Date();
    
    if (reserveDatetime < now) {
        alert('過去の時間は予約できません。別の時間を選択してください。');
        return;
    }
    
    // フォーム作成・送信
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_reserve.php';

    // データ収集・追加
    const formData = {
        girl_name: girlSelect.value,
        course_name: courseSelect.value,
        reserve_date: dateSelect.value,
        reserve_time: timeSelect.value,
        place: placeSelect.value,
        place_other: document.getElementById('other-textarea').value,
        area: areaSelect.value,
        area_other: document.getElementById('other-area-text').value,
        area_outside: document.getElementById('flexCheckDefault').checked ? '1' : '',
        payment_method: paySelect.value,
        coupon_code: couponInput.value,
        use_point: usePointInput.value,
        contact_tel: document.getElementById('use_tel').value,
        comment: document.getElementById('other-comment-text').value
    };

    // オプション追加
    reserve_option.forEach((option, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `options[${index}]`;
        input.value = option;
        form.appendChild(input);
    });

    // 他のデータ追加
    for (let [key, value] of Object.entries(formData)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value || '';
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}
</script>

<script>
// 遷移データ
const preselectedGirlName = '<?= htmlspecialchars($preselected_g_name, ENT_QUOTES, 'UTF-8') ?>';

// 既存のDOMContentLoadedが完了した後に実行するため、setTimeoutを使用
window.addEventListener('load', function() {
    setTimeout(function() {
        if (preselectedGirlName) {
            const girlSelect = document.getElementById('girl-select');
            if (girlSelect) {
                // value（名前）で直接設定
                girlSelect.value = preselectedGirlName;
                
                // changeイベントを発火
                const event = new Event('change', { bubbles: true });
                girlSelect.dispatchEvent(event);
                
                console.log('Girl preselected:', preselectedGirlName); // デバッグ用
            }
        }
    }, 500); // 既存の復元処理完了を待つ
});
</script>
<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
 integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
 crossorigin="anonymous"></script>