<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: cast_login.php');
    exit;
}

$g_login_id = $_SESSION['CAST']['g_login_id'];

// DB接続
$pdo = dbConnect();

// 選択された日付を取得（デフォルトは基準日）
$base_date = getBaseDate();
$selected_date = isset($_GET['date']) ? $_GET['date'] : $base_date;

// ユーザー検索パラメータを取得
$search_user = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';

// 予約リストを取得
if (!empty($search_user)) {
    // ユーザー名検索時：全期間から該当ユーザーの予約を取得
    $sql = "SELECT * FROM reserve 
            WHERE g_login_id = :g_login_id 
            AND user_name = :user_name
            AND done != 5
            ORDER BY date DESC, in_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmt->bindValue(':user_name', $search_user, PDO::PARAM_STR);
    $stmt->execute();
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 通常時：選択された日付のみ
    $sql = "SELECT * FROM reserve 
            WHERE g_login_id = :g_login_id 
            AND date = :selected_date
            AND done != 5
            ORDER BY in_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
    $stmt->bindValue(':selected_date', $selected_date, PDO::PARAM_STR);
    $stmt->execute();
    $reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 全ユーザー名リストを取得（オートコンプリート用）
$sql_users = "SELECT DISTINCT user_name FROM reserve 
              WHERE g_login_id = :g_login_id 
              AND done != 5
              ORDER BY user_name ASC";
$stmt_users = $pdo->prepare($sql_users);
$stmt_users->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
$stmt_users->execute();
$all_users = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

// 過去7日と未来7日の選択肢を生成
$date_options = [];
for ($i = -7; $i <= 7; $i++) {
    $date = new DateTime($base_date);
    $date->modify("$i day");
    $date_value = $date->format('Y-m-d');
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    $date_label = $date->format('n月j日') . '（' . $weekdays[$date->format('w')] . '）';
    
    // 今日の基準日かどうか
    $is_today = ($date_value === $base_date);
    if ($is_today) {
        $date_label = '今日 ' . $date_label;
    }
    
    $date_options[] = [
        'value' => $date_value,
        'label' => $date_label,
        'is_today' => $is_today
    ];
}

include 'header.php';
?>

<link href="./css/cast_reserve_list.css" rel="stylesheet">

<div class="reserve-list-container">
    <h1>📅 予約一覧</h1>
    
    <!-- 日付選択プルダウン -->
    <div class="month-selector">
        <form method="GET" id="dateForm">
            <label for="date">表示日:</label>
            <select name="date" id="date" onchange="document.getElementById('dateForm').submit();" <?= !empty($search_user) ? 'disabled' : '' ?>>
                <?php foreach ($date_options as $option): ?>
                    <option value="<?= $option['value'] ?>" 
                            <?= $option['value'] === $selected_date ? 'selected' : '' ?>
                            <?= $option['is_today'] ? 'style="font-weight: bold; color: #ff69b4;"' : '' ?>>
                        <?= $option['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
<!-- ユーザー検索バー -->
<div class="search-bar">
    <div class="search-input-wrapper">
        <input 
            type="text" 
            class="search-input" 
            id="searchInput" 
            placeholder="ユーザー名で検索..."
            value="<?= htmlspecialchars($search_user) ?>"
            autocomplete="off"
        >
    </div>
    <div class="autocomplete-list" id="autocompleteList"></div>
</div>

<?php if (!empty($search_user)): ?>
    <div class="search-info">
        <span>🔍 「<?= htmlspecialchars($search_user) ?>」さんの予約履歴（全期間）</span>
        <button class="clear-button-inline" onclick="clearSearch()">
            <i class="bi bi-x-circle-fill"></i>
        </button>
    </div>
<?php endif; ?>
    
    <?php if (empty($reserves)): ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <p>
                <?php if (!empty($search_user)): ?>
                    「<?= htmlspecialchars($search_user) ?>」さんの予約がありません
                <?php else: ?>
                    <?php
                    $display_date = new DateTime($selected_date);
                    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                    echo $display_date->format('Y年n月j日') . '（' . $weekdays[$display_date->format('w')] . '）';
                    ?>
                    の予約がありません
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="reserve-list">
            <?php foreach ($reserves as $reserve): ?>
                <?php
                // フリーコースかどうかを判定
                $stmt = $pdo->prepare("SELECT free_check FROM course WHERE c_name = :c_name");
                $stmt->execute([':c_name' => $reserve['c_name']]);
                $courseData = $stmt->fetch(PDO::FETCH_ASSOC);
                $isFreeCheck = $courseData ? (int)$courseData['free_check'] : 0;
                
                // 時間のフォーマット
                $in_time = new DateTime($reserve['in_time']);
                $out_time = new DateTime($reserve['out_time']);
                $start_time = new DateTime($reserve['start_time']);
                $end_time = new DateTime($reserve['end_time']);
                
                // 日付のフォーマット
                $date = new DateTime($reserve['date']);
                $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                $date_fmt = $date->format('Y年n月j日') . '（' . $weekdays[$date->format('w')] . '）';
                
                // オプションをデコード
                $options = json_decode($reserve['options'], true);
                $options_display = !empty($options) ? implode(', ', $options) : 'なし';
                
                // ステータスの判定
                $status_class = '';
                $status_text = '';
                switch ($reserve['done']) {
                    case 1:
                        $status_class = 'status-pending';
                        $status_text = '追加情報待ち';
                        break;
                    case 2:
                        $status_class = 'status-confirmed';
                        $status_text = '予約確定';
                        break;
                    case 3:
                        $status_class = 'status-completed';
                        $status_text = '完了';
                        break;
                    case 4:
                        $status_class = 'status-checking';
                        $status_text = '店舗確認中';
                        break;
                }
                ?>
                <a href="cast_reserve_detail.php?id=<?= htmlspecialchars($reserve['id']) ?>" class="reserve-card-link">
                    <div class="reserve-card">
                        <div class="reserve-card-header">
                            <div class="reserve-date">
                                <span class="date-main"><?= $date_fmt ?></span>
                            </div>
                            <div class="reserve-status">
                                <span class="<?= $status_class ?>"><?= $status_text ?></span>
                            </div>
                        </div>
                        
                        <div class="reserve-card-body">
                            <div class="reserve-info-row">
                                <span class="info-label">🕐 出勤時間</span>
                                <span class="info-value"><?= $in_time->format('H:i') ?> ~ <?= $out_time->format('H:i') ?></span>
                            </div>
                            
                            <div class="reserve-info-row">
                                <span class="info-label">⏰ コース時間</span>
                                <span class="info-value"><?= $start_time->format('H:i') ?> ~ <?= $end_time->format('H:i') ?> (<?= htmlspecialchars($reserve['course_time']) ?>分)</span>
                            </div>
                            
                            <div class="reserve-info-row">
                                <span class="info-label">👤 お客様</span>
                                <span class="info-value"><?= htmlspecialchars($reserve['user_name']) ?></span>
                            </div>
                            
                            <div class="reserve-info-row">
                                <span class="info-label">📝 コース</span>
                                <span class="info-value"><?= htmlspecialchars($reserve['c_name']) ?></span>
                            </div>
                            
                            <?php if (!empty($reserve['place'])): ?>
                            <div class="reserve-info-row">
                                <span class="info-label">📍 場所</span>
                                <span class="info-value"><?= htmlspecialchars($reserve['place']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($reserve['area'])): ?>
                            <div class="reserve-info-row">
                                <span class="info-label">🗺️ エリア</span>
                                <span class="info-value"><?= htmlspecialchars($reserve['area']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($reserve['hotel'])): ?>
                            <div class="reserve-info-row">
                                <span class="info-label">🏨 ホテル</span>
                                <span class="info-value">
                                    <?= htmlspecialchars($reserve['hotel']) ?>
                                    <?php if (!empty($reserve['hotel_num'])): ?>
                                        <span class="hotel-room">部屋: <?= htmlspecialchars($reserve['hotel_num']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="reserve-info-row">
                                <span class="info-label">✨ オプション</span>
                                <span class="info-value"><?= htmlspecialchars($options_display) ?></span>
                            </div>
                            
                            <div class="reserve-info-row reserve-cost">
                                <span class="info-label">💰 料金</span>
                                <span class="info-value cost-value"><?= number_format($reserve['cost']) ?>円</span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// 全ユーザー名リスト
const allUsers = <?= json_encode($all_users) ?>;
let selectedIndex = -1;

// 検索入力時の処理
document.getElementById('searchInput').addEventListener('input', function(e) {
    const keyword = e.target.value.trim().toLowerCase();
    const list = document.getElementById('autocompleteList');
    
    if (keyword === '') {
        list.innerHTML = '';
        list.style.display = 'none';
        selectedIndex = -1;
        return;
    }
    
    // 候補を絞り込み
    const filtered = allUsers.filter(user => 
        user.toLowerCase().includes(keyword)
    );
    
    if (filtered.length === 0) {
        list.innerHTML = '<div class="autocomplete-item no-result">該当するユーザーが見つかりません</div>';
        list.style.display = 'block';
        return;
    }
    
    // 候補リストを生成（最大10件）
    const items = filtered.slice(0, 10).map((user, index) => {
        const highlightedUser = highlightMatch(user, keyword);
        return `<div class="autocomplete-item" data-index="${index}" onclick="selectUser('${escapeHtml(user)}')">${highlightedUser}</div>`;
    }).join('');
    
    list.innerHTML = items;
    list.style.display = 'block';
    selectedIndex = -1;
});

// キーボード操作
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    const list = document.getElementById('autocompleteList');
    const items = list.querySelectorAll('.autocomplete-item:not(.no-result)');
    
    if (items.length === 0) return;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = (selectedIndex + 1) % items.length;
        updateSelection(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
        updateSelection(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && selectedIndex < items.length) {
            items[selectedIndex].click();
        }
    } else if (e.key === 'Escape') {
        list.style.display = 'none';
        selectedIndex = -1;
    }
});

// 選択状態の更新
function updateSelection(items) {
    items.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });
}

// ユーザー選択
function selectUser(userName) {
    window.location.href = `?search_user=${encodeURIComponent(userName)}`;
}

// 検索クリア
function clearSearch() {
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date') || '<?= $selected_date ?>';
    window.location.href = `?date=${date}`;
}

// マッチ部分をハイライト
function highlightMatch(text, keyword) {
    const index = text.toLowerCase().indexOf(keyword);
    if (index === -1) return escapeHtml(text);
    
    const before = escapeHtml(text.substring(0, index));
    const match = escapeHtml(text.substring(index, index + keyword.length));
    const after = escapeHtml(text.substring(index + keyword.length));
    
    return `${before}<strong>${match}</strong>${after}`;
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 外側クリックで候補リストを閉じる
document.addEventListener('click', function(e) {
    const searchBar = document.querySelector('.search-bar');
    if (!searchBar.contains(e.target)) {
        document.getElementById('autocompleteList').style.display = 'none';
        selectedIndex = -1;
    }
});
</script>
<?php include 'footer.php'; ?>