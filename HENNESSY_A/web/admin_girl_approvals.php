<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

try {
  $pdo = dbConnect();
  
  // 承認待ちデータ取得
  $sql = "SELECT gp.*, 
          g.name as current_name,
          g.age as current_age,
          g.high as current_high,
          g.b as current_b,
          g.w as current_w,
          g.h as current_h,
          g.head_comment as current_head_comment,
          g.comment as current_comment,
          g.img as current_img
          FROM girl_pending gp
          LEFT JOIN girl g ON gp.g_login_id = g.g_login_id
          WHERE gp.status = 'pending'
          ORDER BY gp.requested_at ASC";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $pending_girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 未読チャット数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND sender_type = 'user'");
  $unread_chats = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
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
  <title>承認管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <link href="./css/admin_girl_approvals.css" rel="stylesheet">
</head>

<body>
  <!-- サイドバー -->
  <div class="sidebar">
    <div class="logo-section">
      <img src="../../img/logo/adminlogo.png" alt="Logo">
      <h4>HENNESSY</h4>
      <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
    </div>
    
    <nav class="nav-menu">
      <a href="admin_home.php" class="nav-item">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="admin_girl_approvals.php" class="nav-item active">
        <i class="bi bi-check-circle"></i>
        <span>承認管理</span>
        <?php if (count($pending_girls) > 0): ?>
          <span class="badge"><?= count($pending_girls) ?></span>
        <?php endif; ?>
      </a>
      <a href="admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
        <?php if ($unread_chats > 0): ?>
          <span class="badge"><?= $unread_chats ?></span>
        <?php endif; ?>
      </a>
      <a href="admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="admin_settings.php" class="nav-item">
        <i class="bi bi-gear"></i>
        <span>設定</span>
      </a>
    </nav>
    
    <div class="logout-section">
      <a href="admin_logout.php" class="nav-item logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>ログアウト</span>
      </a>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="main-content">
    <!-- ヘッダー -->
    <header class="top-header">
      <h1><i class="bi bi-check-circle"></i> 承認管理</h1>
      <div class="header-info">
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- 承認待ちカウント -->
    <div class="pending-count">
      <i class="bi bi-hourglass-split"></i>
      承認待ち: <strong><?= count($pending_girls) ?></strong>件
    </div>

    <!-- 承認待ちリスト -->
    <div class="approval-list">
      <?php if (empty($pending_girls)): ?>
        <div class="no-data">
          <i class="bi bi-check-circle"></i>
          <p>承認待ちのデータはありません</p>
        </div>
      <?php else: ?>
        <?php foreach ($pending_girls as $pending): ?>
          <div class="approval-card">
            <!-- アイコンと名前をカード上部に配置 -->
            <div class="card-top-info">
              <div class="girl-icon-name">
                <div class="girl-icon">
                  <?php 
                  $displayImg = $pending['request_type'] === 'edit' ? $pending['current_img'] : $pending['img'];
                  if ($displayImg): 
                  ?>
                    <img src="../../img/icon/<?= htmlspecialchars($displayImg) ?>" alt="<?= htmlspecialchars($pending['name']) ?>">
                  <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                  <?php endif; ?>
                </div>
                <div class="girl-name">
                  <?php 
                  $displayName = $pending['request_type'] === 'edit' ? $pending['current_name'] : $pending['name'];
                  echo htmlspecialchars($displayName);
                  ?>
                </div>
              </div>
              <div class="request-info">
                <span class="request-type <?= $pending['request_type'] ?>">
                  <?= $pending['request_type'] === 'new' ? '新規登録' : 'プロフィール編集' ?>
                </span>
                <span class="request-date">
                  <i class="bi bi-clock"></i>
                  <?= date('Y/m/d H:i', strtotime($pending['requested_at'])) ?>
                </span>
              </div>
            </div>

            <div class="approval-body">
              <!-- 編集の場合は比較表示 -->
              <?php if ($pending['request_type'] === 'edit'): ?>
                <?php
                  // 変更箇所を検出
                  $changes = [];
                  
                  if ($pending['name'] !== $pending['current_name']) {
                    $changes['name'] = ['old' => $pending['current_name'], 'new' => $pending['name']];
                  }
                  if ($pending['age'] !== $pending['current_age']) {
                    $changes['age'] = ['old' => $pending['current_age'], 'new' => $pending['age']];
                  }
                  if ($pending['high'] !== $pending['current_high']) {
                    $changes['high'] = ['old' => $pending['current_high'], 'new' => $pending['high']];
                  }
                  if ($pending['b'] !== $pending['current_b']) {
                    $changes['b'] = ['old' => $pending['current_b'], 'new' => $pending['b']];
                  }
                  if ($pending['w'] !== $pending['current_w']) {
                    $changes['w'] = ['old' => $pending['current_w'], 'new' => $pending['w']];
                  }
                  if ($pending['h'] !== $pending['current_h']) {
                    $changes['h'] = ['old' => $pending['current_h'], 'new' => $pending['h']];
                  }
                  if ($pending['head_comment'] !== $pending['current_head_comment']) {
                    $changes['head_comment'] = ['old' => $pending['current_head_comment'], 'new' => $pending['head_comment']];
                  }
                  if ($pending['comment'] !== $pending['current_comment']) {
                    $changes['comment'] = ['old' => $pending['current_comment'], 'new' => $pending['comment']];
                  }
                  if ($pending['img'] !== $pending['current_img']) {
                    $changes['img'] = ['old' => $pending['current_img'], 'new' => $pending['img']];
                  }
                ?>
                
                <?php if (empty($changes)): ?>
                  <div class="no-changes">
                    <i class="bi bi-info-circle"></i>
                    <p>変更はありません</p>
                  </div>
                <?php else: ?>
                  <div class="comparison-view">
                    <!-- 現在のデータ（変更項目のみ） -->
                    <div class="data-column current">
                      <h3><i class="bi bi-check-circle-fill"></i> 現在掲載中</h3>
                      <div class="profile-preview">
                        <?php if (isset($changes['img'])): ?>
                          <div class="profile-icon">
                            <?php if ($changes['img']['old']): ?>
                              <img src="../../img/icon/<?= htmlspecialchars($changes['img']['old']) ?>" alt="現在">
                            <?php else: ?>
                              <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                        <div class="profile-details">
                          <?php if (isset($changes['name'])): ?>
                            <p><strong>名前:</strong> <?= htmlspecialchars($changes['name']['old']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['age'])): ?>
                            <p><strong>年齢:</strong> <?= htmlspecialchars($changes['age']['old']) ?>歳</p>
                          <?php endif; ?>
                          <?php if (isset($changes['high'])): ?>
                            <p><strong>身長:</strong> <?= htmlspecialchars($changes['high']['old']) ?>cm</p>
                          <?php endif; ?>
                          <?php if (isset($changes['b']) || isset($changes['w']) || isset($changes['h'])): ?>
                            <p><strong>サイズ:</strong> B<?= htmlspecialchars($changes['b']['old'] ?? $pending['current_b']) ?> W<?= htmlspecialchars($changes['w']['old'] ?? $pending['current_w']) ?> H<?= htmlspecialchars($changes['h']['old'] ?? $pending['current_h']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['head_comment'])): ?>
                            <p><strong>ヘッドコメント:</strong> <?= htmlspecialchars($changes['head_comment']['old']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['comment'])): ?>
                            <p><strong>自己紹介:</strong> <?= nl2br(htmlspecialchars($changes['comment']['old'])) ?></p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <div class="arrow-divider">
                      <i class="bi bi-arrow-right"></i>
                    </div>

                    <!-- 編集後のデータ（変更項目のみ） -->
                    <div class="data-column pending">
                      <h3><i class="bi bi-pencil-fill"></i> 編集後</h3>
                      <div class="profile-preview">
                        <?php if (isset($changes['img'])): ?>
                          <div class="profile-icon">
                            <?php if ($changes['img']['new']): ?>
                              <img src="../../img/icon/<?= htmlspecialchars($changes['img']['new']) ?>" alt="編集後">
                            <?php else: ?>
                              <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                        <div class="profile-details">
                          <?php if (isset($changes['name'])): ?>
                            <p><strong>名前:</strong> <?= htmlspecialchars($changes['name']['new']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['age'])): ?>
                            <p><strong>年齢:</strong> <?= htmlspecialchars($changes['age']['new']) ?>歳</p>
                          <?php endif; ?>
                          <?php if (isset($changes['high'])): ?>
                            <p><strong>身長:</strong> <?= htmlspecialchars($changes['high']['new']) ?>cm</p>
                          <?php endif; ?>
                          <?php if (isset($changes['b']) || isset($changes['w']) || isset($changes['h'])): ?>
                            <p><strong>サイズ:</strong> B<?= htmlspecialchars($changes['b']['new'] ?? $pending['b']) ?> W<?= htmlspecialchars($changes['w']['new'] ?? $pending['w']) ?> H<?= htmlspecialchars($changes['h']['new'] ?? $pending['h']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['head_comment'])): ?>
                            <p><strong>ヘッドコメント:</strong> <?= htmlspecialchars($changes['head_comment']['new']) ?></p>
                          <?php endif; ?>
                          <?php if (isset($changes['comment'])): ?>
                            <p><strong>自己紹介:</strong> <?= nl2br(htmlspecialchars($changes['comment']['new'])) ?></p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <!-- 新規登録の場合 - 変更内容なし表示 -->
                <div class="new-registration-info">
                  <p><i class="bi bi-info-circle"></i> 新規キャスト登録申請</p>
                </div>
              <?php endif; ?>
            </div>

            <div class="approval-actions">
              <button class="btn-reject" onclick="rejectApproval(<?= $pending['id'] ?>, <?= $pending['g_login_id'] ?>)">
                <i class="bi bi-x-circle"></i>
                却下
              </button>
              <button class="btn-approve" onclick="approveGirl(<?= $pending['id'] ?>, <?= $pending['g_login_id'] ?>, '<?= $pending['request_type'] ?>')">
                <i class="bi bi-check-circle"></i>
                承認
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- 承認確認モーダル -->
  <div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">承認確認</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>このキャストを承認しますか？</p>
          <div class="mb-3">
            <label class="form-label">コメント（任意）</label>
            <textarea class="form-control" id="approveComment" rows="3" placeholder="承認理由やメッセージを入力..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-success" onclick="confirmApprove()">承認する</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 却下確認モーダル -->
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">却下確認</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>このキャストを却下しますか？</p>
          <div class="mb-3">
            <label class="form-label">却下理由（必須）</label>
            <textarea class="form-control" id="rejectComment" rows="3" placeholder="却下理由を入力してください" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-danger" onclick="confirmReject()">却下する</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    let currentPendingId = null;
    let currentGirlId = null;
    let currentRequestType = null;
    let approveModal = null;
    let rejectModal = null;

    // ページ読み込み完了後にモーダルを初期化
    window.addEventListener('load', function() {
      approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
      rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
      
      console.log('Modals initialized successfully');
    });

    // 承認ボタンクリック
    function approveGirl(pendingId, girlId, requestType) {
      console.log('approveGirl called:', pendingId, girlId, requestType);
      
      currentPendingId = pendingId;
      currentGirlId = girlId;
      currentRequestType = requestType;
      
      if (approveModal) {
        approveModal.show();
      } else {
        console.error('Modal not initialized yet');
        // モーダルが初期化されていない場合は直接初期化して表示
        approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
        approveModal.show();
      }
    }

    // 承認実行
    function confirmApprove() {
      const comment = document.getElementById('approveComment').value;
      
      console.log('Sending approval request...');

      fetch('./api/approve_girl.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          pending_id: currentPendingId,
          g_login_id: currentGirlId,
          request_type: currentRequestType,
          action: 'approved',
          comment: comment
        })
      })
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        if (data.success) {
          alert('承認しました');
          location.reload();
        } else {
          alert('エラー: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        alert('エラーが発生しました: ' + error.message);
      });
    }

    // 却下ボタンクリック
    function rejectApproval(pendingId, girlId) {
      console.log('rejectApproval called:', pendingId, girlId);
      
      currentPendingId = pendingId;
      currentGirlId = girlId;
      
      if (rejectModal) {
        rejectModal.show();
      } else {
        console.error('Modal not initialized yet');
        // モーダルが初期化されていない場合は直接初期化して表示
        rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
        rejectModal.show();
      }
    }

    // 却下実行
    function confirmReject() {
      const comment = document.getElementById('rejectComment').value;

      if (!comment.trim()) {
        alert('却下理由を入力してください');
        return;
      }
      
      console.log('Sending reject request...');

      fetch('./api/approve_girl.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          pending_id: currentPendingId,
          g_login_id: currentGirlId,
          action: 'rejected',
          comment: comment
        })
      })
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        if (data.success) {
          alert('却下しました');
          location.reload();
        } else {
          alert('エラー: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        alert('エラーが発生しました: ' + error.message);
      });
    }
  </script>
</body>

</html>