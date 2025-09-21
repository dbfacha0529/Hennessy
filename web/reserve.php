<?php 
include 'header.php';
include '../functions.php';

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

// 日付プルダウン（7日分）を基準日から作成
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

<script>
const kCheckGirls = <?= json_encode($k_checkgirls) ?>;
const girlSelect = document.getElementById('girl-select');
const girlCard = document.getElementById('girl-card');
const courseSelect = document.getElementById('course-select');
const dateSelect = document.getElementById('date-select');
const scheduleHeader = document.getElementById('schedule-header');
const scheduleBody = document.getElementById('schedule-body');

function updateSchedule(){
    const g_name = girlSelect.value;
    const date = dateSelect.value;
    const c_name = courseSelect.value;
    if(!g_name || !date || !c_name){
        scheduleHeader.innerHTML='<th>時間</th>';
        scheduleBody.innerHTML='';
        return;
    }

    fetch(`get_schedule.php?g_name=${encodeURIComponent(g_name)}&date=${encodeURIComponent(date)}&c_name=${encodeURIComponent(c_name)}`)
    .then(res=>res.json())
    .then(data=>{
        scheduleBody.innerHTML='';
        const times = Object.keys(data);
        // ヘッダー
        let header = '<th>時間</th>';
        times.forEach(t=>header+='<th>'+t+'</th>');
        scheduleHeader.innerHTML = header;
        // データ行
        const tr = document.createElement('tr');
        const tdLabel = document.createElement('td');
        tdLabel.textContent = g_name;
        tr.appendChild(tdLabel);
        times.forEach(t=>{
            const td = document.createElement('td');
            td.textContent = data[t] || 'tel';
            tr.appendChild(td);
        });
        scheduleBody.appendChild(tr);
    });
}

// 女の子選択
girlSelect.addEventListener('change', ()=>{
    const selected = girlSelect.value;
    if(!selected){ girlCard.style.display='none'; updateSchedule(); return; }
    const g = kCheckGirls.find(g=>g.name===selected);
    if(!g){ girlCard.style.display='none'; updateSchedule(); return; }

    fetch('get_girl.php?gid='+encodeURIComponent(g.g_login_id))
    .then(res=>res.json())
    .then(data=>{
        if(data){
            document.getElementById('girl-img').src='../img/'+(data.img||'noimage.jpg');
            document.getElementById('girl-name').textContent=data.name||'';
            document.getElementById('girl-headcomment').textContent=data.head_comment||'';
            document.getElementById('girl-outtime').textContent=data.out_time||'';
            girlCard.style.display='flex';
        } else {
            girlCard.style.display='none';
        }
        updateSchedule();
    });
});

// コース・日付変更
courseSelect.addEventListener('change', updateSchedule);
dateSelect.addEventListener('change', updateSchedule);

// ページロード時に基準日を選択
dateSelect.value='<?= $baseDate ?>';
updateSchedule();
</script>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
 integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
 crossorigin="anonymous"></script>
