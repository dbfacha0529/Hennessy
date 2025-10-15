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
        padding-bottom: 12vh;
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
        transform: none;
    }

    .week-display {
        flex: 1;
        text-align: center;
        font-weight: 600;
        color: #ff1493;
        font-size: 1.125rem;
    }

    /* アクションボタン */
    .action-buttons {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .btn-submit-shift {
        flex: 1;
        background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
        color: #1b5e20;
        border: none;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.9375rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 0.25rem 0.75rem rgba(129, 199, 132, 0.3);
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-submit-shift:hover {
        background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%);
        transform: translateY(-0.125rem);
        box-shadow: 0 0.375rem 1rem rgba(129, 199, 132, 0.4);
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
        transform: translateY(-0.125rem);
    }

    .shift-card.has-shift {
        border-color: #ff69b4;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 245, 247, 0.98) 100%);
    }

    .shift-card.has-reserves {
        border-color: #ff1493;
        box-shadow: 0 0.5rem 1.5rem rgba(255, 20, 147, 0.3);
    }

    .shift-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 0.125rem solid #ffe4e9;
    }

    .shift-date-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .shift-date {
        font-size: 1.125rem;
        font-weight: 700;
        color: #ff1493;
    }

    .shift-day {
        font-size: 0.9375rem;
        color: #ff69b4;
        font-weight: 600;
    }

    .shift-day.sunday {
        color: #ff1493;
    }

    .shift-day.saturday {
        color: #ff69b4;
    }

    .reserve-badge {
        background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
        color: white;
        padding: 0.375rem 0.875rem;
        border-radius: 1rem;
        font-size: 0.8125rem;
        font-weight: 700;
        box-shadow: 0 0.125rem 0.5rem rgba(255, 20, 147, 0.3);
    }

    /* シフト内容 */
    .shift-content {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .shift-time-item {
        background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
        padding: 0.875rem;
        border-radius: 0.75rem;
        border: 0.125rem solid #ffb6c1;
    }

    .shift-time-label {
        font-size: 0.8125rem;
        color: #ff69b4;
        font-weight: 600;
        margin-bottom: 0.375rem;
    }

    .shift-time-value {
        font-size: 1.25rem;
        color: #ff1493;
        font-weight: 700;
    }

    .shift-lo-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
        color: #6a1b9a;
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        border: 0.125rem solid #ce93d8;
    }

    /* 休みカード */
    .no-shift {
        text-align: center;
        padding: 2rem 1rem;
        color: #999;
    }

    .no-shift-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }

    .no-shift-text {
        font-size: 0.9375rem;
        color: #666;
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

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1rem;
        box-shadow: 0 0.5rem 2rem rgba(255, 182, 193, 0.3);
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state-title {
        font-size: 1.25rem;
        color: #ff69b4;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .empty-state-text {
        font-size: 0.9375rem;
        color: #666;
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    /* レスポンシブ */
    @media (max-width: 48rem) {
        .shift-header-card h1 {
            font-size: 1.5rem;
        }

        .week-display {
            font-size: 1rem;
        }

        .shift-content {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 30rem) {
        .container {
            padding: 0.75rem;
            padding-bottom: 12vh;
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

        .week-nav button {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        .shift-date {
            font-size: 1rem;
        }

        .shift-time-value {
            font-size: 1.125rem;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <div class="shift-header-card">
        <h1>🌸 シフト一覧 🌸</h1>
        <div class="week-nav">
            <button onclick="changeWeek(-1)">← 前週</button>
            <div class="week-display" id="weekDisplay">読み込み中...</div>
            <button onclick="changeWeek(1)">次週 →</button>
        </div>
        <div class="action-buttons">
            <a href="cast_shift.php" class="btn-submit-shift">
                ✏️ シフトを提出する
            </a>
        </div>
    </div>
    
    <div id="shiftContainer">
        <div class="loading">
            <div class="spinner"></div>
            <p>読み込み中...</p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
let currentWeekStart = null;

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
        const response = await fetch(`cast_shift_list_api.php?action=get_week_shifts&start_date=${startDate}`);
        const data = await response.json();
        
        if (data.success) {
            renderShifts(data.shifts);
        } else {
            showError(data.error || 'データの取得に失敗しました');
        }
    } catch (error) {
        console.error('シフト取得エラー:', error);
        showError('エラーが発生しました');
    }
}


// シフトをレンダリング（修正版）
function renderShifts(shifts) {
    const container = document.getElementById('shiftContainer');
    const weekDisplay = document.getElementById('weekDisplay');
    
    // 週表示を更新
    const endDate = new Date(currentWeekStart);
    endDate.setDate(endDate.getDate() + 6);
    weekDisplay.textContent = `${formatDateDisplay(currentWeekStart)} 〜 ${formatDateDisplay(endDate)}`;
    
    // シフトが1件もない場合
    const hasAnyShift = Object.values(shifts).some(shift => shift !== null);
    
    if (!hasAnyShift) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">📅</div>
                <div class="empty-state-title">この週のシフトはありません</div>
                <div class="empty-state-text">
                    シフトを提出すると、承認後にここに表示されます。<br>
                    「シフトを提出する」ボタンから提出してください。
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    
    Object.keys(shifts).forEach((dateStr, index) => {
        const date = new Date(dateStr);
        const dayOfWeek = date.getDay();
        const dayName = dayNames[dayOfWeek];
        const dayClass = dayOfWeek === 0 ? 'sunday' : (dayOfWeek === 6 ? 'saturday' : '');
        
        const shift = shifts[dateStr];
        
        if (shift) {
            // シフトあり（in_time と out_time を使用）
            const startTime = formatTimeDisplay(shift.in_time);
            const endTime = formatTimeDisplay(shift.out_time);
            const reserveCount = parseInt(shift.reserve_count) || 0;
            const hasReserves = reserveCount > 0;
            const loEnabled = shift.LO == 1;
            
            html += `
                <div class="shift-card has-shift ${hasReserves ? 'has-reserves' : ''}">
                    <div class="shift-card-header">
                        <div class="shift-date-info">
                            <span class="shift-date">${formatDateDisplay(date)}</span>
                            <span class="shift-day ${dayClass}">(${dayName})</span>
                        </div>
                        ${hasReserves ? `<span class="reserve-badge">📋 予約${reserveCount}件</span>` : ''}
                    </div>
                    
                    <div class="shift-content">
                        <div class="shift-time-item">
                            <div class="shift-time-label">🕐 出勤時刻</div>
                            <div class="shift-time-value">${startTime}</div>
                        </div>
                        <div class="shift-time-item">
                            <div class="shift-time-label">🕐 退勤時刻</div>
                            <div class="shift-time-value">${endTime}</div>
                        </div>
                    </div>
                    
                    ${loEnabled ? `
                        <div class="shift-lo-badge">
                            🌙 ラストオーダー可
                        </div>
                    ` : ''}
                </div>
            `;
        } else {
            // シフトなし（休み）
            html += `
                <div class="shift-card">
                    <div class="shift-card-header">
                        <div class="shift-date-info">
                            <span class="shift-date">${formatDateDisplay(date)}</span>
                            <span class="shift-day ${dayClass}">(${dayName})</span>
                        </div>
                    </div>
                    
                    <div class="no-shift">
                        <div class="no-shift-icon">💤</div>
                        <div class="no-shift-text">お休み</div>
                    </div>
                </div>
            `;
        }
    });
    
    container.innerHTML = html;
}

// エラー表示
function showError(message) {
    const container = document.getElementById('shiftContainer');
    container.innerHTML = `
        <div class="empty-state">
            <div class="empty-state-icon">⚠️</div>
            <div class="empty-state-title">エラー</div>
            <div class="empty-state-text">${escapeHtml(message)}</div>
        </div>
    `;
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

// 時刻フォーマット: HH:MM
function formatTimeDisplay(datetime) {
    if (!datetime) return '--:--';
    
    const date = new Date(datetime);
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');
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