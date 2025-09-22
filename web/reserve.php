<?php 
include 'header.php';

$pdo = dbConnect();
$k_checkgirls = k_checkgirls($pdo);

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
$h_array = [];
for ($h = 17; $h <= 23; $h++) $h_array[] = $h;
for ($h = 0; $h <= 4; $h++) $h_array[] = $h;

// 分プルダウン
$m_array = array_map(fn($m) => str_pad($m, 2, '0', STR_PAD_LEFT), range(0, 50, 10));

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


?>

<link href="./css/reserve.css" rel="stylesheet">

<!-- 女の子選択 -->
<div class="mb-3">
  <label for="girl-select">女の子を選択</label>
  <select id="girl-select" class="form-select">
    <option value="">-- 選択してください --</option>
    <?php foreach($sg_array as $g): ?>
      <option value="<?= htmlspecialchars($g['value']) ?>"><?= htmlspecialchars($g['label']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- 女の子カード -->
<div class="gcard" id="girl-card" style="display:none;">
  <img class="gcardimg" id="girl-img" src="../img/noimage.jpg">
  <div class="gcard-textarea">
    <div class="left-text">
      <span class="name" id="girl-name"></span>
      <span class="headcomment" id="girl-headcomment"></span>
    </div>
    <span class="out_time" id="girl-outtime"></span>
  </div>
</div>

<!-- コース選択 -->
<div class="mb-3">
  <label for="course-select">コースを選択</label>
  <select id="course-select" class="form-select">
    <option value="">-- 選択してください --</option>
    <?php foreach($course_array as $v=>$l): ?>
      <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($l) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- 日付選択 -->
<div class="mb-3">
  <label for="date-select">日付を選択</label>
  <select id="date-select" class="form-select">
    <option value="">-- 選択してください --</option>
    <?php foreach($date_array as $d): ?>
      <option value="<?= htmlspecialchars($d['value']) ?>"><?= htmlspecialchars($d['label']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- 予約可能時間表 -->
<div class="table-wrapper">
  <table class="schedule-table" id="schedule-table">
    <thead><tr id="schedule-header"><th>時間</th></tr></thead>
    <tbody id="schedule-body"></tbody>
  </table>
</div>

<!-- 時間・分選択 -->
<div class="mb-3">
  <label for="time-select">時間を選択</label>
  <div class="d-flex">
    <select id="hour-select" class="form-select me-2">
      <option value="">-- 時 --</option>
      <?php foreach ($h_array as $h): ?>
        <option value="<?= str_pad($h,2,'0',STR_PAD_LEFT) ?>"><?= str_pad($h,2,'0',STR_PAD_LEFT) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="minute-select" class="form-select">
      <option value="">-- 分 --</option>
      <?php foreach ($m_array as $m): ?>
        <option value="<?= $m ?>"><?= $m ?></option>
      <?php endforeach; ?>
    </select>
  </div>
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
</div>

<!-- クーポン -->
<div class="mb-3">
  <label for="coupon_code" class="form-label">クーポン</label>
  <input type="text" class="form-control" id="coupon_code" placeholder="クーポンコード">
  <div id="coupon-result" style="margin-top:5px; color:blue;"></div>
</div>

<!-- ポイント -->
<div class="mb-3">
  <label for="use_point" class="form-label">ポイント利用</label>
  <div class="mb-1">
  <small>ご利用可能ポイント: <span id="available-point">読み込み中...</span></small>
  <div id="point-error" style="color:red; margin-top:2px;"></div>
</div>
  <input type="text" class="form-control" id="use_point" placeholder="ご利用ポイント入力">
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
         value="<?= htmlspecialchars($_SESSION['USER']['tel'] ?? '') ?>">
  <label>※ご予約認証に使用いたしますので<br>お手元の端末の番号をご入力ください</label>
</div>

<script>
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

const courseData = <?= json_encode(getCourseData($pdo)) ?>; 
const optionsData = <?= json_encode(getAllOptions($pdo)) ?>;

// DOM
const girlSelect = document.getElementById('girl-select');
const girlCard = document.getElementById('girl-card');
const courseSelect = document.getElementById('course-select');
const dateSelect = document.getElementById('date-select');
const scheduleHeader = document.getElementById('schedule-header');
const scheduleBody = document.getElementById('schedule-body');
const hourSelect = document.getElementById('hour-select');
const minuteSelect = document.getElementById('minute-select');
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
const totalBreakdownEl = document.getElementById('total-breakdown');

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

    if(selectedPlace === 'ご自宅') {
        hakenFee = 1000;
        towelFee = 1000;
    } else if(selectedPlace === 'ビジネスホテル') {
        hakenFee = 1000;
        towelFee = 0;
    } else if(selectedPlace === 'ラブホテル') {
        hakenFee = 0;
        towelFee = 0;
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
    hourSelect.value = '';
    minuteSelect.value = '';
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

// スケジュール表示
function updateSchedule(){
    const g_name = girlSelect.value;
    const date = dateSelect.value;
    const c_name = courseSelect.value;
    resetTime();
    if(!g_name || !date || !c_name){
        resetSchedule();
        return;
    }
    fetch(`get_schedule.php?g_name=${encodeURIComponent(g_name)}&date=${encodeURIComponent(date)}&c_name=${encodeURIComponent(c_name)}`)
    .then(res=>res.json())
    .then(data=>{
        resetSchedule();
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
                td.addEventListener('click', ()=>{ hourSelect.value = t.split(':')[0]; minuteSelect.value = t.split(':')[1]; });
            } else if(data[t] === '✖') td.style.color = 'red';
            tr.appendChild(td);
        });
        scheduleBody.appendChild(tr);
    });
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
                    updateOptionsCost(); // ← ここで総額更新
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
            updateOptionsCost(); // オプションリセット時も総額更新
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
            document.getElementById('girl-outtime').textContent=data.out_time||'';
            girlCard.style.display='flex';
        } else girlCard.style.display='none';
        loadGirlOptions(g.g_login_id);
        updateSchedule();
    });
});

// コース選択
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

// place「その他」
placeSelect.addEventListener('change', ()=>{ otherPlaceWrapper.style.display = placeSelect.value==='その他'?'block':'none'; });

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
    })
    .catch(err => {
        availablePointEl.textContent = '-';
        pointErrorEl.textContent = '通信エラーでポイントを取得できません';
        console.error(err);
    });

// 初期表示時に総額を更新
document.addEventListener('DOMContentLoaded', function() {
    updateTotalAmount();
});

</script>

<?php include 'footer.php'; ?>
<script src="script.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
 integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
 crossorigin="anonymous"></script>