<?php
require_once(dirname(__FILE__) . '/../functions.php');
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];
$message = '';
$error = '';

try {
  $pdo = dbConnect();
  
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 一括承認
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] == 'bulk_approve') {
      $pdo->beginTransaction();
      try {
        $stmt = $pdo->query("SELECT sr.*, g.name as girl_name FROM shift_requests sr LEFT JOIN girl g ON sr.g_login_id = g.g_login_id WHERE sr.status = 'pending'");
        $bulk_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $approved_count = 0;
        
        foreach ($bulk_requests as $request) {
          if ($request['in_time'] === null || $request['out_time'] === null) {
            $stmt = $pdo->prepare("DELETE FROM shift WHERE g_login_id = :g_login_id AND date = :date");
            $stmt->execute([':g_login_id' => $request['g_login_id'], ':date' => $request['date']]);
            $notification_message = "【シフト削除承認】\n\nシフト削除申請が承認されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n\n承認者: " . htmlspecialchars($admin['admin_name']);
          } else {
            $stmt = $pdo->prepare("DELETE FROM shift WHERE g_login_id = :g_login_id AND date = :date");
            $stmt->execute([':g_login_id' => $request['g_login_id'], ':date' => $request['date']]);
            $stmt = $pdo->prepare("INSERT INTO shift (date, in_time, out_time, g_login_id, LO) VALUES (:date, :in_time, :out_time, :g_login_id, :lo)");
            $stmt->execute([':date' => $request['date'], ':in_time' => $request['in_time'], ':out_time' => $request['out_time'], ':g_login_id' => $request['g_login_id'], ':lo' => $request['LO']]);
            $notification_message = "【シフト承認完了】\n\nシフト申請が承認されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n時間: " . date('H:i', strtotime($request['in_time'])) . " 〜 " . date('H:i', strtotime($request['out_time'])) . "\n\n承認者: " . htmlspecialchars($admin['admin_name']);
          }
          
          $stmt = $pdo->prepare("DELETE FROM shift_requests WHERE id = :id");
          $stmt->execute([':id' => $request['id']]);
          
          $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE user_tel = '00000000000' AND g_login_id = :g_login_id");
          $stmt->execute([':g_login_id' => $request['g_login_id']]);
          $room = $stmt->fetch(PDO::FETCH_ASSOC);
          
          if (!$room) {
            $stmt = $pdo->prepare("INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at) VALUES ('00000000000', :g_login_id, NOW(), NOW())");
            $stmt->execute([':g_login_id' => $request['g_login_id']]);
            $room_id = $pdo->lastInsertId();
          } else {
            $room_id = $room['id'];
          }
          
          $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_type, message_type, content, is_read, created_at) VALUES (:room_id, 'user', 'text', :content, 0, NOW())");
          $stmt->execute([':room_id' => $room_id, ':content' => $notification_message]);
          $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id");
          $stmt->execute([':room_id' => $room_id]);
          
          $approved_count++;
        }
        
        $pdo->commit();
        $message = "シフト申請 {$approved_count}件 を一括承認しました。";
      } catch (Exception $e) {
        $pdo->rollBack();
        $error = "一括承認処理中にエラーが発生しました: " . $e->getMessage();
      }
    }
    
    // 一括却下
    elseif (isset($_POST['bulk_action']) && $_POST['bulk_action'] == 'bulk_reject') {
      $reject_reason = $_POST['bulk_reject_reason'] ?? '';
      $pdo->beginTransaction();
      try {
        $stmt = $pdo->query("SELECT sr.*, g.name as girl_name FROM shift_requests sr LEFT JOIN girl g ON sr.g_login_id = g.g_login_id WHERE sr.status = 'pending'");
        $bulk_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rejected_count = 0;
        
        foreach ($bulk_requests as $request) {
          $notification_message = "【シフト申請却下】\n\nシフト申請が却下されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n";
          if ($request['in_time'] && $request['out_time']) {
            $notification_message .= "時間: " . date('H:i', strtotime($request['in_time'])) . " 〜 " . date('H:i', strtotime($request['out_time'])) . "\n\n";
          }
          if ($reject_reason) {
            $notification_message .= "却下理由:\n" . $reject_reason . "\n\n";
          }
          $notification_message .= "詳細は管理者にお問い合わせください。";
          
          $stmt = $pdo->prepare("DELETE FROM shift_requests WHERE id = :id");
          $stmt->execute([':id' => $request['id']]);
          
          $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE user_tel = '00000000000' AND g_login_id = :g_login_id");
          $stmt->execute([':g_login_id' => $request['g_login_id']]);
          $room = $stmt->fetch(PDO::FETCH_ASSOC);
          
          if (!$room) {
            $stmt = $pdo->prepare("INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at) VALUES ('00000000000', :g_login_id, NOW(), NOW())");
            $stmt->execute([':g_login_id' => $request['g_login_id']]);
            $room_id = $pdo->lastInsertId();
          } else {
            $room_id = $room['id'];
          }
          
          $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_type, message_type, content, is_read, created_at) VALUES (:room_id, 'user', 'text', :content, 0, NOW())");
          $stmt->execute([':room_id' => $room_id, ':content' => $notification_message]);
          $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id");
          $stmt->execute([':room_id' => $room_id]);
          
          $rejected_count++;
        }
        
        $pdo->commit();
        $message = "シフト申請 {$rejected_count}件 を一括却下しました。";
      } catch (Exception $e) {
        $pdo->rollBack();
        $error = "一括却下処理中にエラーが発生しました: " . $e->getMessage();
      }
    }
    
    // 個別承認/却下
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
      $request_id = $_POST['request_id'];
      $action = $_POST['action'];
      
      $stmt = $pdo->prepare("SELECT sr.*, g.name as girl_name FROM shift_requests sr LEFT JOIN girl g ON sr.g_login_id = g.g_login_id WHERE sr.id = :id AND sr.status = 'pending'");
      $stmt->execute([':id' => $request_id]);
      $request = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($request) {
        
        if ($action == 'approve') {
          $pdo->beginTransaction();
          try {
            if ($request['in_time'] === null || $request['out_time'] === null) {
              $stmt = $pdo->prepare("DELETE FROM shift WHERE g_login_id = :g_login_id AND date = :date");
              $stmt->execute([':g_login_id' => $request['g_login_id'], ':date' => $request['date']]);
              $notification_message = "【シフト削除承認】\n\nシフト削除申請が承認されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n\n承認者: " . htmlspecialchars($admin['admin_name']);
            } else {
              $stmt = $pdo->prepare("DELETE FROM shift WHERE g_login_id = :g_login_id AND date = :date");
              $stmt->execute([':g_login_id' => $request['g_login_id'], ':date' => $request['date']]);
              $stmt = $pdo->prepare("INSERT INTO shift (date, in_time, out_time, g_login_id, LO) VALUES (:date, :in_time, :out_time, :g_login_id, :lo)");
              $stmt->execute([':date' => $request['date'], ':in_time' => $request['in_time'], ':out_time' => $request['out_time'], ':g_login_id' => $request['g_login_id'], ':lo' => $request['LO']]);
              $notification_message = "【シフト承認完了】\n\nシフト申請が承認されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n時間: " . date('H:i', strtotime($request['in_time'])) . " 〜 " . date('H:i', strtotime($request['out_time'])) . "\n\n承認者: " . htmlspecialchars($admin['admin_name']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM shift_requests WHERE id = :id");
            $stmt->execute([':id' => $request_id]);
            
            $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE user_tel = '00000000000' AND g_login_id = :g_login_id");
            $stmt->execute([':g_login_id' => $request['g_login_id']]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
              $stmt = $pdo->prepare("INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at) VALUES ('00000000000', :g_login_id, NOW(), NOW())");
              $stmt->execute([':g_login_id' => $request['g_login_id']]);
              $room_id = $pdo->lastInsertId();
            } else {
              $room_id = $room['id'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_type, message_type, content, is_read, created_at) VALUES (:room_id, 'user', 'text', :content, 0, NOW())");
            $stmt->execute([':room_id' => $room_id, ':content' => $notification_message]);
            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id");
            $stmt->execute([':room_id' => $room_id]);
            
            $pdo->commit();
            $message = "シフト申請を承認しました。";
          } catch (Exception $e) {
            $pdo->rollBack();
            $error = "承認処理中にエラーが発生しました: " . $e->getMessage();
          }
        }
        
        elseif ($action == 'reject') {
          $reject_reason = $_POST['reject_reason'] ?? '';
          $pdo->beginTransaction();
          try {
            $notification_message = "【シフト申請却下】\n\nシフト申請が却下されました。\n\n日付: " . date('Y年m月d日', strtotime($request['date'])) . "\n";
            if ($request['in_time'] && $request['out_time']) {
              $notification_message .= "時間: " . date('H:i', strtotime($request['in_time'])) . " 〜 " . date('H:i', strtotime($request['out_time'])) . "\n\n";
            }
            if ($reject_reason) {
              $notification_message .= "却下理由:\n" . $reject_reason . "\n\n";
            }
            $notification_message .= "詳細は管理者にお問い合わせください。";
            
            $stmt = $pdo->prepare("DELETE FROM shift_requests WHERE id = :id");
            $stmt->execute([':id' => $request_id]);
            
            $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE user_tel = '00000000000' AND g_login_id = :g_login_id");
            $stmt->execute([':g_login_id' => $request['g_login_id']]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
              $stmt = $pdo->prepare("INSERT INTO chat_rooms (user_tel, g_login_id, created_at, last_message_at) VALUES ('00000000000', :g_login_id, NOW(), NOW())");
              $stmt->execute([':g_login_id' => $request['g_login_id']]);
              $room_id = $pdo->lastInsertId();
            } else {
              $room_id = $room['id'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_type, message_type, content, is_read, created_at) VALUES (:room_id, 'user', 'text', :content, 0, NOW())");
            $stmt->execute([':room_id' => $room_id, ':content' => $notification_message]);
            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = :room_id");
            $stmt->execute([':room_id' => $room_id]);
            
            $pdo->commit();
            $message = "シフト申請を却下しました。";
          } catch (Exception $e) {
            $pdo->rollBack();
            $error = "却下処理中にエラーが発生しました: " . $e->getMessage();
          }
        }
      }
    }
  }
  
  $stmt = $pdo->query("
    SELECT sr.*, g.name as girl_name, g.tel as girl_tel,
           s.id as existing_shift_id, s.in_time as existing_in_time,
           s.out_time as existing_out_time, s.LO as existing_LO
    FROM shift_requests sr
    LEFT JOIN girl g ON sr.g_login_id = g.g_login_id
    LEFT JOIN shift s ON sr.g_login_id = s.g_login_id AND sr.date = s.date
    WHERE sr.status = 'pending'
    ORDER BY sr.date ASC, sr.in_time ASC
  ");
  $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>シフト承認管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
.request-card { border: 2px solid #e0e0e0; border-radius: 12px; padding: 25px; margin-bottom: 20px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
.girl-info h5 { margin: 0; font-size: 1.4rem; color: #333; font-weight: 600; }
.girl-info small { color: #888; font-size: 0.9rem; }
.comparison-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
.shift-box { border: 2px solid #ddd; border-radius: 10px; padding: 20px; background: #fafafa; position: relative; }
.shift-box.existing { border-color: #6c757d; background: #f8f9fa; }
.shift-box.new { border-color: #d4af37; background: #fffef7; }
.shift-box.delete-request { border-color: #dc3545; background: #fff5f5; }
.shift-box.no-shift { border-style: dashed; border-color: #ccc; background: #f9f9f9; text-align: center; color: #999; padding: 40px 20px; }
.shift-label { position: absolute; top: -12px; left: 15px; background: white; padding: 2px 12px; font-weight: 600; font-size: 0.85rem; border-radius: 4px; }
.shift-label.existing { color: #6c757d; border: 2px solid #6c757d; }
.shift-label.new { color: #d4af37; border: 2px solid #d4af37; }
.shift-label.delete { color: #dc3545; border: 2px solid #dc3545; }
.shift-info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
.shift-info-row:last-child { border-bottom: none; }
.info-label { font-size: 0.9rem; color: #666; font-weight: 500; }
.info-value { font-size: 1.1rem; font-weight: 600; color: #333; }
.shift-info-row.changed { background: #fff9e6; margin: 0 -15px; padding: 12px 15px; border-radius: 6px; position: relative; }
.shift-info-row.changed::before { content: '◉'; position: absolute; left: 0; color: #ffa500; font-size: 0.8rem; }
.shift-info-row.changed .info-value { color: #ff8c00; font-weight: 700; }
.warning-box { background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.warning-box i { font-size: 1.5rem; color: #ff9800; }
.info-box { background: #d1ecf1; border: 2px solid #17a2b8; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.info-box i { font-size: 1.5rem; color: #17a2b8; }
.danger-box { background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.danger-box i { font-size: 1.5rem; color: #dc3545; }
.request-actions { display: flex; gap: 15px; justify-content: flex-end; }
.request-actions .btn { padding: 10px 30px; font-weight: 600; font-size: 1rem; }
.status-badge { padding: 8px 20px; border-radius: 25px; font-size: 0.9rem; font-weight: 600; }
.status-pending { background: #fff3cd; color: #856404; border: 2px solid #ffc107; }
.empty-state { text-align: center; padding: 80px 20px; color: #999; }
.empty-state i { font-size: 5rem; margin-bottom: 25px; opacity: 0.5; }
.bulk-actions { position: fixed; bottom: 30px; right: 30px; display: flex; gap: 15px; z-index: 1000; }
.bulk-btn { padding: 15px 30px; font-size: 1.1rem; font-weight: 600; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s; }
.bulk-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
.bulk-btn i { margin-right: 8px; }
@media (max-width: 768px) { .comparison-container { grid-template-columns: 1fr; } }
</style>

</head>
<body>
  <div class="sidebar">
    <div class="logo-section">
      <img src="../../img/logo/adminlogo.png" alt="Logo">
      <h4>HENNESSY</h4>
      <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
    </div>
    <nav class="nav-menu">
      <a href="admin_home.php" class="nav-item"><i class="bi bi-speedometer2"></i><span>ダッシュボード</span></a>
      <a href="admin_reservations.php" class="nav-item"><i class="bi bi-calendar-check"></i><span>予約管理</span></a>
      <a href="admin_girls.php" class="nav-item"><i class="bi bi-people"></i><span>キャスト管理</span></a>
      <a href="admin_shift_requests.php" class="nav-item active"><i class="bi bi-calendar2-check"></i><span>シフト承認</span><?php if (count($pending_requests) > 0): ?><span class="badge"><?= count($pending_requests) ?></span><?php endif; ?></a>
      <a href="admin_customers.php" class="nav-item"><i class="bi bi-person-badge"></i><span>顧客管理</span></a>
      <a href="admin_chat.php" class="nav-item"><i class="bi bi-chat-dots"></i><span>チャット管理</span></a>
      <a href="admin_sales.php" class="nav-item"><i class="bi bi-graph-up"></i><span>売上レポート</span></a>
      <a href="admin_settings.php" class="nav-item"><i class="bi bi-gear"></i><span>設定</span></a>
    </nav>
    <div class="logout-section">
      <a href="admin_logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i><span>ログアウト</span></a>
    </div>
  </div>
  
  <div class="main-content">
    <header class="top-header">
      <h1><i class="bi bi-calendar2-check"></i> シフト承認管理</h1>
      <div class="header-info">
        <span class="admin-name"><i class="bi bi-person-circle"></i><?= htmlspecialchars($admin['admin_name']) ?></span>
      </div>
    </header>
    
    <?php if ($message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <?php if (empty($pending_requests)): ?>
      <div class="empty-state">
        <i class="bi bi-calendar-check"></i>
        <h4>承認待ちのシフト申請はありません</h4>
        <p>新しい申請があるとここに表示されます</p>
      </div>
    <?php else: ?>
      <?php foreach ($pending_requests as $req): ?>
        <?php
        $has_existing = !empty($req['existing_shift_id']);
        $is_delete_request = ($req['in_time'] === null || $req['out_time'] === null);
        $time_changed = false;
        $lo_changed = false;
        if ($has_existing && !$is_delete_request) {
          $time_changed = ($req['in_time'] != $req['existing_in_time']) || ($req['out_time'] != $req['existing_out_time']);
          $lo_changed = ($req['LO'] != $req['existing_LO']);
        }
        ?>
        
        <div class="request-card">
          <div class="request-header">
            <div class="girl-info">
              <h5><i class="bi bi-person-circle"></i> <?= htmlspecialchars($req['girl_name']) ?></h5>
              <small>申請日時: <?= date('Y年m月d日 H:i', strtotime($req['created_at'])) ?></small>
            </div>
            <span class="status-badge status-pending"><i class="bi bi-clock"></i> 承認待ち</span>
          </div>
          
          <?php if ($is_delete_request): ?>
            <div class="danger-box"><i class="bi bi-trash-fill"></i><div><strong>シフト削除リクエスト</strong><br>承認すると既存シフトが削除されます</div></div>
          <?php elseif ($has_existing): ?>
            <div class="warning-box"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>既存シフトが存在します</strong><br>承認すると既存シフトは削除され、新しい申請内容に置き換わります</div></div>
          <?php else: ?>
            <div class="info-box"><i class="bi bi-info-circle-fill"></i><div><strong>新規シフト登録</strong><br>この日はまだシフトが登録されていません</div></div>
          <?php endif; ?>
          
          <div class="comparison-container">
            <div class="shift-box <?= $has_existing ? 'existing' : 'no-shift' ?>">
              <?php if ($has_existing): ?>
                <span class="shift-label existing">既存シフト</span>
                <div class="shift-info-row"><span class="info-label"><i class="bi bi-calendar3"></i> 日付</span><span class="info-value"><?= date('Y年m月d日 (D)', strtotime($req['date'])) ?></span></div>
                <div class="shift-info-row <?= $time_changed ? 'changed' : '' ?>"><span class="info-label"><i class="bi bi-clock"></i> 勤務時間</span><span class="info-value"><?= date('H:i', strtotime($req['existing_in_time'])) ?> 〜 <?= date('H:i', strtotime($req['existing_out_time'])) ?></span></div>
                <div class="shift-info-row <?= $lo_changed ? 'changed' : '' ?>"><span class="info-label"><i class="bi bi-bookmark"></i> ラストオーダー</span><span class="info-value"><?= $req['existing_LO'] == 1 ? '受付可能' : '受付不可' ?></span></div>
              <?php else: ?>
                <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.3;"></i>
                <p style="margin-top: 15px; font-size: 1.1rem;">シフト未登録</p>
              <?php endif; ?>
            </div>
            
            <div class="shift-box <?= $is_delete_request ? 'delete-request' : 'new' ?>">
              <?php if ($is_delete_request): ?>
                <span class="shift-label delete">削除リクエスト</span>
                <i class="bi bi-trash" style="font-size: 3rem; opacity: 0.5; color: #dc3545;"></i>
                <p style="margin-top: 15px; font-size: 1.1rem; color: #dc3545;"><?= date('Y年m月d日 (D)', strtotime($req['date'])) ?><br>シフト削除を希望</p>
              <?php else: ?>
                <span class="shift-label new">新規申請</span>
                <div class="shift-info-row"><span class="info-label"><i class="bi bi-calendar3"></i> 日付</span><span class="info-value"><?= date('Y年m月d日 (D)', strtotime($req['date'])) ?></span></div>
                <div class="shift-info-row <?= $time_changed ? 'changed' : '' ?>"><span class="info-label"><i class="bi bi-clock"></i> 勤務時間</span><span class="info-value"><?= date('H:i', strtotime($req['in_time'])) ?> 〜 <?= date('H:i', strtotime($req['out_time'])) ?></span></div>
                <div class="shift-info-row <?= $lo_changed ? 'changed' : '' ?>"><span class="info-label"><i class="bi bi-bookmark"></i> ラストオーダー</span><span class="info-value"><?= $req['LO'] == 1 ? '受付可能' : '受付不可' ?></span></div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="request-actions">
            <button type="button" class="btn btn-danger btn-lg" onclick="showRejectModal(<?= $req['id'] ?>, '<?= htmlspecialchars($req['girl_name'], ENT_QUOTES) ?>')"><i class="bi bi-x-circle"></i> 却下</button>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('<?= $is_delete_request ? 'シフトを削除します。' : ($has_existing ? '既存シフトを削除して' : '') ?>この申請を承認しますか?')"><i class="bi bi-check-circle"></i> 承認</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  
  <?php if (!empty($pending_requests)): ?>
    <div class="bulk-actions">
      <button type="button" class="btn btn-danger bulk-btn" onclick="bulkReject()"><i class="bi bi-x-circle-fill"></i> 一括却下</button>
      <button type="button" class="btn btn-success bulk-btn" onclick="bulkApprove()"><i class="bi bi-check-circle-fill"></i> 一括承認</button>
    </div>
    <form id="bulk_approve_form" method="POST" style="display: none;"><input type="hidden" name="bulk_action" value="bulk_approve"></form>
    <form id="bulk_reject_form" method="POST" style="display: none;"><input type="hidden" name="bulk_action" value="bulk_reject"><input type="hidden" name="bulk_reject_reason" id="bulk_reject_reason"></form>
  <?php endif; ?>
  
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-x-circle text-danger"></i> シフト申請を却下</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="request_id" id="reject_request_id">
            <input type="hidden" name="action" value="reject">
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i><strong id="reject_girl_name"></strong>さんのシフト申請を却下します。</div>
            <div class="mb-3">
              <label for="reject_reason" class="form-label"><i class="bi bi-chat-left-text"></i> 却下理由 <small class="text-muted">(任意)</small></label>
              <textarea class="form-control" id="reject_reason" name="reject_reason" rows="4" placeholder="却下理由を入力してください(キャストに通知されます)"></textarea>
              <small class="text-muted">※ 理由を記入すると、キャストにチャットで通知されます</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i> キャンセル</button>
            <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> 却下する</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showRejectModal(requestId, girlName) {
  document.getElementById('reject_request_id').value = requestId;
  document.getElementById('reject_girl_name').textContent = girlName;
  document.getElementById('reject_reason').value = '';
  new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

setTimeout(function() {
  document.querySelectorAll('.alert').forEach(function(alert) {
    new bootstrap.Alert(alert).close();
  });
}, 5000);

function bulkApprove() {
  const count = <?= count($pending_requests) ?>;
  if (count === 0) { alert('承認待ちの申請がありません'); return; }
  if (confirm(`承認待ちの申請 ${count}件 をすべて承認しますか?\n\n※既存シフトがある場合は削除されます`)) {
    document.getElementById('bulk_approve_form').submit();
  }
}

function bulkReject() {
  const count = <?= count($pending_requests) ?>;
  if (count === 0) { alert('承認待ちの申請がありません'); return; }
  const reason = prompt(`承認待ちの申請 ${count}件 をすべて却下します。\n\n却下理由を入力してください(任意):`);
  if (reason !== null) {
    document.getElementById('bulk_reject_reason').value = reason;
    document.getElementById('bulk_reject_form').submit();
  }
}
</script>
</body>
</html>