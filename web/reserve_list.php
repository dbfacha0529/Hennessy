<?php

include 'header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER'])) {
    header('Location: index.php');
    exit();
}

$tel = $_SESSION['USER']['tel'];

// DB接続
$pdo = dbConnect();

// 予約リストを取得（最新順）
$sql = "SELECT * FROM reserve WHERE tel = :tel ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
$stmt->execute();
$reserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="reserve-list-container">
    <h1>予約リスト</h1>
    
    <?php if (empty($reserves)): ?>
        <p>予約がありません</p>
    <?php else: ?>
        <?php foreach ($reserves as $reserve): ?>
            <a href="reserve_list_up.php?id=<?= htmlspecialchars($reserve['id']) ?>" class="reserve-card-link">
                <div class="reserve-card">
                    <div class="reserve-card-left">
                        <div class="reserve-date">
                            <?php
                            $date = new DateTime($reserve['date']);
                            echo $date->format('Y年m月d日 (D)');
                            ?>
                        </div>
                        <div class="reserve-time">
                            <?= htmlspecialchars($reserve['in_time']) ?> ~ <?= htmlspecialchars($reserve['out_time']) ?>
                        </div>
                        <div class="reserve-course">
                            <?= htmlspecialchars($reserve['c_name']) ?> / <?= htmlspecialchars($reserve['course_time']) ?>分
                        </div>
                        <?php if (!empty($reserve['g_name'])): ?>
                            <div class="reserve-girl">
                                ご指名: <?= htmlspecialchars($reserve['g_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($reserve['hotel'])): ?>
                            <div class="reserve-hotel">
                                ホテル: <?= htmlspecialchars($reserve['hotel']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($reserve['hotel_num'])): ?>
                            <div class="reserve-room">
                                部屋番号: <?= htmlspecialchars($reserve['hotel_num']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reserve-card-right">
                        <div class="reserve-status">
                            <?php
                            if ($reserve['done'] == 3) {
                                echo '<span class="status-completed">完了済み</span>';
                            } elseif ($reserve['done'] == 2) {
                                echo '<span class="status-confirmed">ご予約完了</span>';
                            } elseif ($reserve['done'] == 1) {
                                echo '<span class="status-pending">追加情報を登録してください</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>