<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// キャスト情報取得
$sql = "SELECT name FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$cast_info = $stmt->fetch(PDO::FETCH_ASSOC);
$cast_name = $cast_info['name'] ?? 'キャスト';

require_once(dirname(__FILE__) . '/header.php');
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
        background: linear-gradient(135deg, #ffe4e9 0%, #ffd4e0 100%);
        min-height: 100vh;
    }

    /* コンテナ */
    .container {
        max-width: 50rem;
        margin: 0 auto;
        padding: 1rem;
    }

    /* ヘッダーカード */
    .shift-header-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 0.5rem 2rem rgba(255, 182, 193, 0.3);
    }

    .shift-header-card h1 {
        color: #ff69b4;
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        text-align: center;
    }

    /* シフト一覧に戻るボタン */
    .back-to-list-btn {
        display: block;
        width: 100%;
        background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
        color: #666;
        border: 0.125rem solid #ddd;
        padding: 0.875rem 1.25rem;
        border-radius: 0.75rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        text-decoration: none;
        margin-bottom: 1rem;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    }

    .back-to-list-btn:hover {
        background: linear-gradient(135deg, #e8e8e8 0%, #d8d8d8 100%);
        transform: translateY(-0.125rem);
        box-shadow: 0 0.375rem 1rem rgba(0, 0, 0, 0.15);
    }

    .back-to-list-btn i {
        margin-right: 0.5rem;
    }

    .week-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1rem;
    }

    .week-nav button {
        background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
        color: white;
        border: none;
        padding: 0.625rem 1.25rem;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.3s;
        box-shadow: 0 0.25rem 0.75rem rgba(255, 105, 180, 0.3);
    }

    .week-nav button:hover {
        background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
        transform: translateY(-0.125rem);
        box-shadow: 0 0.375rem 1rem rgba(255, 105, 180, 0.4);
    }

    .week-nav button:disabled {
        background: #e0e0e0;
        color: #999;
        cursor: not-allowed;
        box-shadow: none;
    }

    .week-display {
        flex: 1;
        text-align: center;
        font-weight: 600;
        color: #ff1493;
        font-size: 1.125rem;
    }

    /* アラート */
    .alert {
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
        font-size: 0.9375rem;
        font-weight: 500;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    }

    .alert-info {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        color: #1565c0;
        border: 0.125rem solid #90caf9;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        color: #e65100;
        border: 0.125rem solid #ffb74d;
    }

    .alert-danger {
        background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
        color: #c62828;
        border: 0.125rem solid #ef5350;
    }

    .alert-success {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        color: #2e7d32;
        border: 0.125rem solid #66bb6a;
    }

    /* シフトカード */
    .shift-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1rem;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 0.5rem 1.5rem rgba(255, 182, 193, 0.25);
        border: 0.125rem solid #ffb6c1;
        transition: all 0.3s;
    }

    .shift-card:hover {
        box-shadow: 0 0.75rem 2rem rgba(255, 105, 180, 0.35);
        border-color: #ff69b4;
    }

    .shift-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 0.125rem solid #ffe4e9;
    }

    .shift-date {
        font-size: 1.125rem;
        font-weight: 700;
        color: #ff1493;
    }

    .shift-day {
        font-size: 0.9375rem;
        color: #ff69b4;
        margin-left: 0.5rem;
        font-weight: 600;
    }

    .shift-day.sunday {
        color: #ff1493;
    }

    .shift-day.saturday {
        color: #ff69b4;
    }

    .status-badge {
        padding: 0.375rem 0.875rem;
        border-radius: 1rem;
        font-size: 0.8125rem;
        font-weight: 700;
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.15);
    }

    .status-pending {
        background: linear-gradient(135deg, #fff59d 0%, #fff176 100%);
        color: #f57f17;
    }

    .status-approved {
        background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
        color: #1b5e20;
    }

    .status-rejected {
        background: linear-gradient(135deg, #ef9a9a 0%, #e57373 100%);
        color: #b71c1c;
    }

    /* 出勤チェックボックス */
    .work-check {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding: 0.875rem;
        background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
        border-radius: 0.75rem;
        border: 0.125rem solid #ffb6c1;
    }

    .work-check input[type="checkbox"] {
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        accent-color: #ff69b4;
    }

    .work-check label {
        font-size: 1rem;
        font-weight: 600;
        color: #ff1493;
        cursor: pointer;
        user-select: none;
    }

    /* 時間選択 */
    .time-select-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .time-select-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .time-select-item label {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #ff69b4;
    }

    .time-select-item select {
        padding: 0.75rem;
        border: 0.125rem solid #ffb6c1;
        border-radius: 0.625rem;
        font-size: 1rem;
        background: white;
        cursor: pointer;
        transition: all 0.3s;
        color: #333;
        font-weight: 500;
    }

    .time-select-item select:focus {
        outline: none;
        border-color: #ff69b4;
        box-shadow: 0 0 0 0.1875rem rgba(255, 105, 180, 0.15);
    }

    .time-select-item select:disabled {
        background: #f5f5f5;
        cursor: not-allowed;
        color: #999;
    }

    /* LOチェックボックス */
    .lo-check {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem;
        background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
        border-radius: 0.75rem;
        border: 0.125rem solid #ce93d8;
    }

    .lo-check input[type="checkbox"] {
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        accent-color: #9c27b0;
    }

    .lo-check label {
        font-size: 0.9375rem;
        font-weight: 600;
        color: #6a1b9a;
        cursor: pointer;
        user-select: none;
    }

    .lo-help {
        font-size: 0.8125rem;
        color: #7b1fa2;
        margin-left: 2rem;
    }

    /* 却下理由 */
    .reject-reason {
        background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
        border: 0.125rem solid #ef5350;
        border-radius: 0.75rem;
        padding: 0.875rem;
        margin-top: 0.75rem;
        font-size: 0.875rem;
    }

    .reject-reason-label {
        font-weight: 700;
        color: #c62828;
        margin-bottom: 0.375rem;
    }

    .reject-reason-text {
        color: #d32f2f;
        line-height: 1.5;
    }

    /* ボタングループ */
    .button-group {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        position: sticky;
        bottom: 11vh;
        background: rgba(255, 255, 255, 0.98);
        padding: 1rem;
        border-radius: 1rem;
        box-shadow: 0 -0.25rem 1.5rem rgba(255, 105, 180, 0.2);
        border: 0.125rem solid #ffb6c1;
    }

    .btn {
        flex: 1;
        padding: 1rem;
        border: none;
        border-radius: 0.75rem;
        font-size: 1.0625rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #ff1493 0%, #c71585 100%);
        transform: translateY(-0.125rem);
        box-shadow: 0 0.375rem 1rem rgba(255, 105, 180, 0.4);
    }

    .btn-primary:disabled {
        background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
        cursor: not-allowed;
        box-shadow: none;
    }

    .btn-secondary {
        background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
        color: #666;
        border: 0.125rem solid #ccc;
    }

    .btn-secondary:hover {
        background: linear-gradient(135deg, #e0e0e0 0%, #d0d0d0 100%);
        transform: translateY(-0.125rem);
    }

    /* ローディング */
    .loading {
        text-align: center;
        padding: 3rem;
        color: #ff69b4;
    }

    .spinner {
        border: 0.25rem solid #ffe4e9;
        border-top: 0.25rem solid #ff69b4;
        border-radius: 50%;
        width: 3rem;
        height: 3rem;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* レスポンシブ */
    @media (max-width: 48rem) {
        .shift-header-card h1 {
            font-size: 1.5rem;
        }

        .week-display {
            font-size: 1rem;
        }

        .time-select-group {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 30rem) {
        .container {
            padding: 0.75rem;
        }

        .shift-header-card {
            padding: 1.25rem;
        }

        .shift-header-card h1 {
            font-size: 1.375rem;
        }

        .shift-card {
            padding: 1rem;
        }

        .button-group {
            flex-direction: column;
            gap: 0.75rem;
        }

        .week-nav button {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        .back-to-list-btn {
            font-size: 0.9375rem;
            padding: 0.75rem 1rem;
        }
    }
</style>

<div class="container">
    <div class="shift-header-card">
        <h1>🌸 シフト提出 🌸</h1>
        <a href="cast_shift_list.php" class="back-to-list-btn">
            <i class="bi bi-arrow-left"></i>シフト一覧に戻る
        </a>
        <div class="week-nav">
            <button onclick="changeWeek(-1)">← 前週</button>
            <div class="week-display" id="weekDisplay">読み込み中...</div>
            <button onclick="changeWeek(1)">次週 →</button>
        </div>
    </div>

    <div id="alertContainer"></div>
    
    <div id="shiftContainer">
        <div class="loading">
            <div class="spinner"></div>
            <p>読み込み中...</p>
        </div>
    </div>

    <div class="button-group">
        <button class="btn btn-secondary" onclick="clearForm()">クリア</button>
        <button class="btn btn-primary" id="submitBtn" onclick="submitShifts()">提出する</button>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
let currentWeekStart = null;
let shiftsData = {};

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    // 今週の月曜日を取得
    const today = new Date();
    const dayOfWeek = today.getDay();
    const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
    currentWeekStart = new Date(today);
    currentWeekStart.setDate(today.getDate() + diff);
    
    loadWeekShifts();
});

// 週を変更
function changeWeek(direction) {
    currentWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
    loadWeekShifts();
}

// 週のシフトを読み込み
async function loadWeekShifts() {
    const startDate = formatDate(currentWeekStart);
    
    try {
        const response = await fetch(`cast_shift_api.php?action=get_week_shifts&start_date=${startDate}`);
        const data = await response.json();
        
        if (data.success) {
            shiftsData = data.shifts;
            renderShifts();
        } else {
            showAlert('error', data.error || 'データの取得に失敗しました');
        }
    } catch (error) {
        console.error('シフト取得エラー:', error);
        showAlert('error', 'エラーが発生しました');
    }
}

// シフトフォームをレンダリング
function renderShifts() {
    const container = document.getElementById('shiftContainer');
    const weekDisplay = document.getElementById('weekDisplay');
    
    // 週表示を更新
    const endDate = new Date(currentWeekStart);
    endDate.setDate(endDate.getDate() + 6);
    weekDisplay.textContent = `${formatDateDisplay(currentWeekStart)} 〜 ${formatDateDisplay(endDate)}`;
    
    let html = '';
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    
    Object.keys(shiftsData).forEach((dateStr, index) => {
        const date = new Date(dateStr);
        const dayOfWeek = date.getDay();
        const dayName = dayNames[dayOfWeek];
        const dayClass = dayOfWeek === 0 ? 'sunday' : (dayOfWeek === 6 ? 'saturday' : '');
        
        const shift = shiftsData[dateStr];
        const approved = shift.approved;
        const request = shift.request;
        
        // 状態判定
        let statusBadge = '';
        let alertMessage = '';
        let rejectReason = '';
        let defaultInHour = '';
        let defaultOutHour = '';
        let defaultLO = true;
        let isWorking = false;
        
        if (request) {
            // 申請中または却下
            if (request.status === 'pending') {
                statusBadge = '<span class="status-badge status-pending">承認待ち</span>';
                alertMessage = '<div class="alert alert-warning">承認待ちです。編集して再提出できます。</div>';
            } else if (request.status === 'rejected') {
                statusBadge = '<span class="status-badge status-rejected">却下</span>';
                alertMessage = '<div class="alert alert-danger">却下されました。編集して再提出できます。</div>';
                rejectReason = request.reject_reason || '理由なし';
            }
            
            defaultInHour = datetimeToHourString(request.in_time);
            defaultOutHour = datetimeToHourString(request.out_time);
            defaultLO = request.LO == 1;
            isWorking = true;
            
        } else if (approved) {
            // 承認済み
            statusBadge = '<span class="status-badge status-approved">承認済み</span>';
            alertMessage = '<div class="alert alert-info">承認済みシフトです。変更するには新しく申請してください。</div>';
            
            defaultInHour = datetimeToHourString(approved.in_time);
            defaultOutHour = datetimeToHourString(approved.out_time);
            defaultLO = approved.LO == 1;
            isWorking = true;
        }
        
        html += `
            <div class="shift-card">
                <div class="shift-card-header">
                    <div>
                        <span class="shift-date">${formatDateDisplay(date)}</span>
                        <span class="shift-day ${dayClass}">(${dayName})</span>
                    </div>
                    ${statusBadge}
                </div>
                
                ${alertMessage}
                
                <div class="work-check">
                    <input type="checkbox" id="work_${dateStr}" onchange="toggleShift('${dateStr}')" ${isWorking ? 'checked' : ''}>
                    <label for="work_${dateStr}">✨ この日は出勤する</label>
                </div>
                
                <div id="shift_content_${dateStr}" style="${isWorking ? '' : 'display: none;'}">
                    <div class="time-select-group">
                        <div class="time-select-item">
                            <label>出勤時間</label>
                            <select id="in_${dateStr}">
                                ${generateTimeOptions(defaultInHour)}
                            </select>
                        </div>
                        <div class="time-select-item">
                            <label>退勤時間</label>
                            <select id="out_${dateStr}">
                                ${generateTimeOptions(defaultOutHour)}
                            </select>
                        </div>
                    </div>
                    
                    <div class="lo-check">
                        <input type="checkbox" id="lo_${dateStr}" ${defaultLO ? 'checked' : ''}>
                        <label for="lo_${dateStr}">🌙 ラストオーダー可</label>
                    </div>
                    <div class="lo-help">※ シフト終了時刻を過ぎての予約受付が可能です</div>
                    
                    ${rejectReason ? `
                        <div class="reject-reason">
                            <div class="reject-reason-label">却下理由:</div>
                            <div class="reject-reason-text">${escapeHtml(rejectReason)}</div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// 17:00〜27:00の選択肢を生成
function generateTimeOptions(selectedValue = '') {
    let options = '<option value="">選択してください</option>';
    
    for (let h = 17; h <= 27; h++) {
        for (let m = 0; m < 60; m += 30) {
            const timeStr = `${h}:${String(m).padStart(2, '0')}`;
            const selected = timeStr === selectedValue ? 'selected' : '';
            options += `<option value="${timeStr}" ${selected}>${timeStr}</option>`;
        }
    }
    
    return options;
}

// 出勤チェックボックスの切り替え
function toggleShift(dateStr) {
    const checkbox = document.getElementById(`work_${dateStr}`);
    const content = document.getElementById(`shift_content_${dateStr}`);
    
    if (checkbox.checked) {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}

// シフトを提出
async function submitShifts() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = '提出中...';
    
    const shiftsToSubmit = [];
    
    Object.keys(shiftsData).forEach(dateStr => {
        const workCheck = document.getElementById(`work_${dateStr}`);
        const isWorking = workCheck.checked;
        
        if (isWorking) {
            const inHour = document.getElementById(`in_${dateStr}`).value;
            const outHour = document.getElementById(`out_${dateStr}`).value;
            const lo = document.getElementById(`lo_${dateStr}`).checked ? 1 : 0;
            
            shiftsToSubmit.push({
                date: dateStr,
                in_hour: inHour,
                out_hour: outHour,
                lo: lo
            });
        } else {
            // チェックOFFの場合もサーバー側で判定させるため送信
            shiftsToSubmit.push({
                date: dateStr,
                in_hour: null,
                out_hour: null,
                lo: 0
            });
        }
    });
    
    try {
        const response = await fetch('cast_shift_api.php?action=submit_shifts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(shiftsToSubmit)
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.count === 0) {
                showAlert('info', data.message || '変更がないため、提出するデータがありません。');
            } else {
                showAlert('success', data.message || `シフトを提出しました(${data.count}件)!承認をお待ちください。`);
            }
            // 再読み込み
            await loadWeekShifts();
        } else {
            showAlert('error', data.error || 'シフトの提出に失敗しました');
        }
    } catch (error) {
        console.error('シフト提出エラー:', error);
        showAlert('error', 'エラーが発生しました');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = '提出する';
    }
}

// フォームをクリア
function clearForm() {
    if (!confirm('入力内容をすべてクリアしますか?')) {
        return;
    }
    
    Object.keys(shiftsData).forEach(dateStr => {
        document.getElementById(`work_${dateStr}`).checked = false;
        toggleShift(dateStr);
        document.getElementById(`in_${dateStr}`).value = '';
        document.getElementById(`out_${dateStr}`).value = '';
        document.getElementById(`lo_${dateStr}`).checked = false;
    });
}

// アラート表示
function showAlert(type, message) {
    const container = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
    
    // 3秒後に自動で消す
    setTimeout(() => {
        container.innerHTML = '';
    }, 3000);
    
    // トップにスクロール
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 日付フォーマット: YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 日付フォーマット: M/D
function formatDateDisplay(date) {
    return `${date.getMonth() + 1}/${date.getDate()}`;
}

// DATETIME型を時刻文字列(17:00〜27:00形式)に変換
function datetimeToHourString(datetime) {
    if (!datetime) return '';
    
    const date = new Date(datetime);
    let hour = date.getHours();
    const minute = String(date.getMinutes()).padStart(2, '0');
    
    // 0〜6時の場合は24を加算(翌日扱い)
    if (hour < 7) {
        hour += 24;
    }
    
    return `${hour}:${minute}`;
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>