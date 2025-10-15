<?php
// 現在のディレクトリに応じてベースパスを設定
$current_dir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
if ($current_dir === 'chat' || $current_dir === 'timeline') {
    $base = '../web/';
    $chat_base = ($current_dir === 'chat') ? './' : '../chat/';
    $timeline_base = ($current_dir === 'timeline') ? './' : '../timeline/';
} else {
    $base = './';
    $chat_base = '../chat/';
    $timeline_base = '../timeline/';
}

// done=1の予約があるかチェック
$show_reserve_badge = false;
if (isset($_SESSION['USER']['tel'])) {
    $user_tel = $_SESSION['USER']['tel'];
    $pdo = dbConnect();
    $badge_sql = "SELECT COUNT(*) as count FROM reserve WHERE tel = :tel AND done = 1";
    $badge_stmt = $pdo->prepare($badge_sql);
    $badge_stmt->bindValue(':tel', $user_tel, PDO::PARAM_STR);
    $badge_stmt->execute();
    $badge_result = $badge_stmt->fetch(PDO::FETCH_ASSOC);
    if ($badge_result['count'] > 0) {
        $show_reserve_badge = true;
    }
}
?>

<footer class="site-footer">
  <a href="<?= $base ?>home.php">
    <i class="bi bi-house-heart"></i>
    <span class="icon-label">ホーム</span>
  </a>
  <a href="<?= $timeline_base ?>timeline.php">
   <i class="bi bi-file-earmark-text"></i>
    <span class="icon-label">タイムライン</span>
  </a>
  <a href="<?= $base ?>reserve.php">
    <i class="bi bi-calendar-heart"></i>
    <span class="icon-label">ご予約</span>
  </a>
  <a href="<?= $chat_base ?>chat_list.php">
    <i class="bi bi-chat-text"></i>
    <span class="icon-label">チャット</span>
    <span id="chat-badge" class="chat-badge"></span>
</a>
<a href="<?= $base ?>reserve_list.php">
    <i class="bi bi-calendar2-heart"></i>
    <span class="icon-label">予約リスト</span>
    <?php if ($show_reserve_badge): ?>
    <span class="reserve-badge"></span>
    <?php endif; ?>
</a>
</footer>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="<?= $base ?>css/footer.css" rel="stylesheet">

<script src="<?= $chat_base ?>chat.js"></script>
</body>
</html>