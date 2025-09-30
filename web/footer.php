<?php
// 現在のディレクトリに応じてベースパスを設定
$current_dir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$base = ($current_dir === 'chat') ? '../web/' : './';
$chat_base = ($current_dir === 'chat') ? './' : '../chat/';

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
  <div class="footer-icons">
    <a href="<?= $base ?>home.php">
      <i class="bi bi-house"></i>
      <span class="icon-label">ホーム</span>
    </a>
    <a href="#">
      <i class="bi bi-card-list"></i>
      <span class="icon-label">タイムライン</span>
    </a>
    <a href="<?= $base ?>reserve.php">
      <i class="bi bi-heart"></i>
      <span class="icon-label">ご予約</span>
    </a>
    <a href="<?= $chat_base ?>chat_list.php" style="position: relative;">
      <i class="bi bi-chat-dots"></i>
      <span class="icon-label">チャット</span>
      <span id="chat-badge" style="position: absolute; top: -5px; right: -5px; background-color: #ff3b30; color: white; border-radius: 12px; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center; display: none;"></span>
    </a>
    <a href="<?= $base ?>reserve_list.php" style="position: relative;">
      <i class="bi bi-list-ul"></i>
      <span class="icon-label">予約リスト</span>
      <?php if ($show_reserve_badge): ?>
      <span class="reserve-badge" style="position: absolute; top: -5px; right: -5px; background-color: #ff3b30; color: white; border-radius: 50%; width: 12px; height: 12px;"></span>
      <?php endif; ?>
    </a>
  </div>
</footer>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="<?= $base ?>css/footer.css" rel="stylesheet">

<script src="<?= $chat_base ?>chat.js"></script>