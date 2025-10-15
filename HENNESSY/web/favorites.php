<?php 
include 'header.php';
include 'base_date_functions.php'; // 基準日関数をインクルード

// ログインチェック
if (empty($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();
$tel = $_SESSION['USER']['tel'];

// 基準日を取得（明朝6時切り替え）
$baseDate = getBaseDate();

// お気に入りの女の子を取得
$sql = "SELECT g.g_login_id, g.name, g.img, g.head_comment
        FROM favorite_girls f 
        INNER JOIN girl g ON f.g_login_id = g.g_login_id 
        WHERE f.tel = :tel 
        ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
$stmt->execute();
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 各女の子の出勤情報を取得
foreach ($favorites as &$girl) {
    // まず今日のシフトを確認
    $sqlToday = "SELECT out_time, date FROM shift 
                 WHERE g_login_id = :g_login_id AND date = :base_date 
                 LIMIT 1";
    $stmtToday = $pdo->prepare($sqlToday);
    $stmtToday->bindValue(':g_login_id', $girl['g_login_id'], PDO::PARAM_STR);
    $stmtToday->bindValue(':base_date', $baseDate, PDO::PARAM_STR);
    $stmtToday->execute();
    $todayShift = $stmtToday->fetch(PDO::FETCH_ASSOC);
    
    if ($todayShift) {
        // 今日出勤がある場合
        $girl['out_time'] = $todayShift['out_time'];
        $girl['shift_date'] = null; // 今日なので日付表示不要
    } else {
        // 今日出勤がない場合、直近未来の出勤日を取得
        $sqlNext = "SELECT date FROM shift 
                    WHERE g_login_id = :g_login_id AND date > :base_date 
                    ORDER BY date ASC 
                    LIMIT 1";
        $stmtNext = $pdo->prepare($sqlNext);
        $stmtNext->bindValue(':g_login_id', $girl['g_login_id'], PDO::PARAM_STR);
        $stmtNext->bindValue(':base_date', $baseDate, PDO::PARAM_STR);
        $stmtNext->execute();
        $nextShift = $stmtNext->fetch(PDO::FETCH_ASSOC);
        
        if ($nextShift) {
            $girl['out_time'] = null;
            $girl['shift_date'] = $nextShift['date'];
        } else {
            $girl['out_time'] = null;
            $girl['shift_date'] = null;
        }
    }
}
unset($girl); // 参照を解除
?>

<link href="./css/reserve.css" rel="stylesheet">
<link href="./css/favorites.css" rel="stylesheet">

<div class="container">
    <h2 class="page-title">お気に入り</h2>
    
    <?php if (empty($favorites)): ?>
        <div class="empty-message">
            <p>お気に入りの女の子がいません</p>
            
        </div>
    <?php else: ?>
        <div class="favorites-grid">
            <?php foreach ($favorites as $girl): ?>
                <div class="gcard" data-g-login-id="<?= htmlspecialchars($girl['g_login_id']) ?>" 
                     onclick="goToDetail('<?= htmlspecialchars($girl['g_login_id']) ?>')">
                    <button class="favorite-btn active" 
                            onclick="event.stopPropagation(); toggleFavorite('<?= htmlspecialchars($girl['g_login_id']) ?>', this)">
                        ★
                    </button>
                    <img class="gcardimg" src="../img/<?= htmlspecialchars($girl['img'] ?: 'noimage.jpg') ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
                    <div class="gcard-textarea">
                        <div class="left-text">
                            <span class="name"><?= htmlspecialchars($girl['name']) ?></span>
                            <span class="headcomment"><?= htmlspecialchars($girl['head_comment']) ?></span>
                        </div>
                        <?php if (!empty($girl['out_time'])): ?>
                            <span class="out_time">～<?= htmlspecialchars(substr($girl['out_time'], 0, 5)) ?></span>
                        <?php elseif (!empty($girl['shift_date'])): ?>
                            <span class="out_time">
                                <?php 
                                $date = new DateTime($girl['shift_date']);
                                echo $date->format('n/j');
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFavorite(gLoginId, button) {
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'g_login_id=' + encodeURIComponent(gLoginId)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'removed') {
                const card = button.closest('.gcard');
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    
                    const grid = document.querySelector('.favorites-grid');
                    if (grid && grid.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } else {
            alert(data.message || 'エラーが発生しました');
        }
    })
    .catch(err => {
        console.error(err);
        alert('通信エラーが発生しました');
    });
}

function goToDetail(gLoginId) {
    window.location.href = 'girl_detail.php?g_login_id=' + encodeURIComponent(gLoginId);
}
</script>

<?php include 'footer.php'; ?>