<?php
require_once(dirname(__FILE__) . '/../../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: ../admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

try {
  $pdo = dbConnect();
  
  // 配信履歴を取得（collation修正版）
  $stmt = $pdo->query("
    SELECT 
      pb.*,
      au.admin_name
    FROM promotion_broadcasts pb
    LEFT JOIN admin_users au ON pb.admin_id COLLATE utf8mb4_general_ci = au.admin_id COLLATE utf8mb4_general_ci
    ORDER BY pb.created_at DESC
    LIMIT 100
  ");
  $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
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
  <title>配信履歴 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="../css/admin_home.css" rel="stylesheet">
  <link href="./admin_promotion.css" rel="stylesheet">
  <style>
    .history-container {
      max-width: 1200px;
      margin: 20px auto;
    }
    
    .history-card {
      background: #2a2a2a;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 15px;
      border: 1px solid #444;
      transition: all 0.2s;
      cursor: pointer;
    }
    
    .history-card:hover {
      border-color: #d4af37;
      background: #333;
    }
    
    .history-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .history-title {
      color: #d4af37;
      font-size: 18px;
      font-weight: bold;
      margin: 0;
    }
    
    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
    }
    
    .status-sent {
      background: #28a745;
      color: white;
    }
    
    .status-sending {
      background: #ffc107;
      color: #000;
    }
    
    .status-scheduled {
      background: #17a2b8;
      color: white;
    }
    
    .status-cancelled {
      background: #dc3545;
      color: white;
    }
    
    .history-meta {
      display: flex;
      gap: 20px;
      color: #aaa;
      font-size: 14px;
      margin-bottom: 10px;
    }
    
    .history-meta span {
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .history-content {
      color: #ddd;
      margin-bottom: 15px;
      line-height: 1.6;
    }
    
    .history-stats {
      display: flex;
      gap: 30px;
      padding: 15px;
      background: #1a1a1a;
      border-radius: 6px;
    }
    
    .stat-item {
      text-align: center;
    }
    
    .stat-value {
      font-size: 24px;
      font-weight: bold;
      color: #d4af37;
    }
    
    .stat-label {
      font-size: 12px;
      color: #aaa;
      margin-top: 5px;
    }
    
    .open-rate {
      font-size: 28px;
      font-weight: bold;
      color: #28a745;
    }
    
    .filter-tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }
    
    .filter-tab {
      padding: 10px 20px;
      background: #2a2a2a;
      color: #aaa;
      border: 1px solid #444;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .filter-tab:hover {
      border-color: #d4af37;
      color: #fff;
    }
    
    .filter-tab.active {
      background: #d4af37;
      color: #1a1a1a;
      border-color: #d4af37;
    }
    
    .detail-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.9);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    
    .detail-modal.show {
      display: flex;
    }
    
    .detail-content {
      background: #2a2a2a;
      padding: 30px;
      border-radius: 8px;
      max-width: 800px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
    }
  </style>
</head>

<body>
  <!-- サイドバー -->
  <div class="sidebar">
    <div class="logo-section">
      <img src="../../../img/logo/adminlogo.png" alt="Logo">
      <h4>HENNESSY</h4>
      <p class="admin-role"><?= htmlspecialchars($admin['role']) ?></p>
    </div>
    
    <nav class="nav-menu">
      <a href="../admin_home.php" class="nav-item">
        <i class="bi bi-speedometer2"></i>
        <span>ダッシュボード</span>
      </a>
      <a href="../admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="../admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="../admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="../admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
      </a>
      <a href="admin_promotion_chat.php" class="nav-item">
        <i class="bi bi-megaphone"></i>
        <span>販促チャット</span>
      </a>
      <a href="admin_broadcast.php" class="nav-item">
        <i class="bi bi-send"></i>
        <span>一斉配信</span>
      </a>
      <a href="admin_broadcast_history.php" class="nav-item active">
        <i class="bi bi-clock-history"></i>
        <span>配信履歴</span>
      </a>
      <a href="../admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="../admin_settings.php" class="nav-item">
        <i class="bi bi-gear"></i>
        <span>設定</span>
      </a>
    </nav>
    
    <div class="logout-section">
      <a href="../admin_logout.php" class="nav-item logout">
        <i class="bi bi-box-arrow-right"></i>
        <span>ログアウト</span>
      </a>
    </div>
  </div>

  <!-- メインコンテンツ -->
  <div class="main-content">
    <!-- ヘッダー -->
    <header class="top-header">
      <h1><i class="bi bi-clock-history"></i> 配信履歴</h1>
      <div class="header-info">
        <a href="admin_broadcast.php" class="btn btn-sm" style="background: #d4af37; color: #1a1a1a;">
          <i class="bi bi-plus-circle"></i> 新規配信
        </a>
        <span class="admin-name ms-3">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- フィルター -->
    <div class="history-container">
      <div class="filter-tabs">
        <div class="filter-tab active" data-status="all">すべて</div>
        <div class="filter-tab" data-status="sent">配信完了</div>
        <div class="filter-tab" data-status="scheduled">予約中</div>
        <div class="filter-tab" data-status="sending">配信中</div>
      </div>

      <!-- 配信履歴リスト -->
      <div id="history-list">
        <?php if (empty($broadcasts)): ?>
          <div class="text-center p-5 text-muted">
            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
            <p>配信履歴がありません</p>
            <a href="admin_broadcast.php" class="btn btn-primary mt-3">
              <i class="bi bi-send"></i> 初めての配信を作成
            </a>
          </div>
        <?php else: ?>
          <?php foreach ($broadcasts as $broadcast): ?>
            <div class="history-card" data-status="<?= htmlspecialchars($broadcast['status']) ?>" data-id="<?= $broadcast['id'] ?>">
              <div class="history-header">
                <h3 class="history-title"><?= htmlspecialchars($broadcast['title']) ?></h3>
                <span class="status-badge status-<?= htmlspecialchars($broadcast['status']) ?>">
                  <?php
                  $statusText = [
                    'sent' => '配信完了',
                    'sending' => '配信中',
                    'scheduled' => '予約中',
                    'cancelled' => 'キャンセル',
                    'draft' => '下書き'
                  ];
                  echo $statusText[$broadcast['status']] ?? $broadcast['status'];
                  ?>
                </span>
              </div>
              
              <div class="history-meta">
                <span>
                  <i class="bi bi-person"></i>
                  <?= htmlspecialchars($broadcast['admin_name'] ?? $broadcast['admin_id']) ?>
                </span>
                <span>
                  <i class="bi bi-calendar"></i>
                  <?= date('Y/m/d H:i', strtotime($broadcast['created_at'])) ?>
                </span>
                <?php if ($broadcast['sent_at']): ?>
                <span>
                  <i class="bi bi-check-circle"></i>
                  配信: <?= date('Y/m/d H:i', strtotime($broadcast['sent_at'])) ?>
                </span>
                <?php endif; ?>
                <span>
                  <i class="bi bi-funnel"></i>
                  <?php
                  $targetTypes = [
                    'all' => '全員',
                    'rank' => 'ランク別',
                    'usage_count' => '利用回数別',
                    'days_since_visit' => '最終来店日別',
                    'favorite_girl' => 'お気に入りキャスト別',
                    'preference' => 'プリファレンス別',
                    'girl_attribute_match' => 'キャスト属性マッチ'
                  ];
                  echo $targetTypes[$broadcast['target_type']] ?? $broadcast['target_type'];
                  ?>
                </span>
              </div>
              
              <div class="history-content">
                <?= nl2br(htmlspecialchars(mb_substr($broadcast['content'], 0, 100))) ?>
                <?php if (mb_strlen($broadcast['content']) > 100): ?>...<?php endif; ?>
              </div>
              
              <?php if ($broadcast['status'] === 'sent'): ?>
              <div class="history-stats">
                <div class="stat-item">
                  <div class="stat-value"><?= number_format($broadcast['total_target']) ?></div>
                  <div class="stat-label">対象</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value"><?= number_format($broadcast['total_sent']) ?></div>
                  <div class="stat-label">配信</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value"><?= number_format($broadcast['total_read']) ?></div>
                  <div class="stat-label">既読</div>
                </div>
                <div class="stat-item">
                  <div class="open-rate"><?= number_format($broadcast['open_rate'], 1) ?>%</div>
                  <div class="stat-label">開封率</div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 詳細モーダル -->
  <div class="detail-modal" id="detail-modal">
    <div class="detail-content" id="detail-content">
      <!-- JSで動的に生成 -->
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // フィルタータブ
    document.querySelectorAll('.filter-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        // アクティブ切り替え
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const status = this.dataset.status;
        
        // カードをフィルター
        document.querySelectorAll('.history-card').forEach(card => {
          if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
    
    // カードクリックで詳細表示
    document.querySelectorAll('.history-card').forEach(card => {
      card.addEventListener('click', function() {
        const broadcastId = this.dataset.id;
        showBroadcastDetail(broadcastId);
      });
    });
    
    // 詳細表示
    function showBroadcastDetail(broadcastId) {
      fetch(`/HENNESSY/HENNESSY/chat/chat_api.php?action=admin_get_broadcast_detail&broadcast_id=${broadcastId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayBroadcastDetail(data.broadcast);
          } else {
            alert('詳細の取得に失敗しました');
          }
        })
        .catch(error => {
          console.error('エラー:', error);
          alert('通信エラーが発生しました');
        });
    }
    
    // 詳細を表示
    function displayBroadcastDetail(broadcast) {
      const modal = document.getElementById('detail-modal');
      const content = document.getElementById('detail-content');
      
      const statusText = {
        'sent': '配信完了',
        'sending': '配信中',
        'scheduled': '予約中',
        'cancelled': 'キャンセル',
        'draft': '下書き'
      };
      
      let html = `
        <h3 style="color: #d4af37; margin-bottom: 20px;">${escapeHtml(broadcast.title)}</h3>
        
        <div style="background: #1a1a1a; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
          <p><strong>ステータス:</strong> ${statusText[broadcast.status] || broadcast.status}</p>
          <p><strong>作成日時:</strong> ${new Date(broadcast.created_at).toLocaleString('ja-JP')}</p>
          ${broadcast.sent_at ? `<p><strong>配信日時:</strong> ${new Date(broadcast.sent_at).toLocaleString('ja-JP')}</p>` : ''}
          <p><strong>配信対象:</strong> ${getTargetTypeText(broadcast.target_type)}</p>
        </div>
        
        <div style="background: #1a1a1a; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
          <h5 style="color: #d4af37; margin-bottom: 10px;">メッセージ内容</h5>
          <p style="white-space: pre-wrap;">${escapeHtml(broadcast.content)}</p>
      `;
      
      if (broadcast.file_path) {
        if (broadcast.message_type === 'image') {
          html += `<img src="/HENNESSY/${broadcast.file_path}" style="max-width: 100%; border-radius: 8px; margin-top: 10px;">`;
        } else if (broadcast.message_type === 'video') {
          html += `<video src="/HENNESSY/${broadcast.file_path}" controls style="max-width: 100%; border-radius: 8px; margin-top: 10px;"></video>`;
        }
      }
      
      if (broadcast.link_url) {
        html += `
          <div style="margin-top: 10px;">
            <a href="${escapeHtml(broadcast.link_url)}" target="_blank" 
               style="display: inline-block; padding: 8px 16px; background: #d4af37; color: #1a1a1a; 
                      text-decoration: none; border-radius: 6px; font-weight: bold;">
              ${escapeHtml(broadcast.link_text || 'リンクを開く')}
            </a>
          </div>
        `;
      }
      
      html += `</div>`;
      
      if (broadcast.status === 'sent') {
        html += `
          <div style="background: #1a1a1a; padding: 15px; border-radius: 6px;">
            <h5 style="color: #d4af37; margin-bottom: 15px;">配信統計</h5>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; text-align: center;">
              <div>
                <div style="font-size: 24px; font-weight: bold; color: #d4af37;">${broadcast.total_target || 0}</div>
                <div style="font-size: 12px; color: #aaa;">対象者数</div>
              </div>
              <div>
                <div style="font-size: 24px; font-weight: bold; color: #d4af37;">${broadcast.total_sent || 0}</div>
                <div style="font-size: 12px; color: #aaa;">配信成功</div>
              </div>
              <div>
                <div style="font-size: 24px; font-weight: bold; color: #d4af37;">${broadcast.total_read || 0}</div>
                <div style="font-size: 12px; color: #aaa;">既読</div>
              </div>
              <div>
                <div style="font-size: 28px; font-weight: bold; color: #28a745;">${parseFloat(broadcast.open_rate || 0).toFixed(1)}%</div>
                <div style="font-size: 12px; color: #aaa;">開封率</div>
              </div>
            </div>
          </div>
        `;
      }
      
      html += `
        <button class="btn btn-secondary mt-3" onclick="document.getElementById('detail-modal').classList.remove('show')">
          閉じる
        </button>
      `;
      
      content.innerHTML = html;
      modal.classList.add('show');
    }
    
    // モーダル外クリックで閉じる
    document.getElementById('detail-modal').addEventListener('click', function(e) {
      if (e.target === this) {
        this.classList.remove('show');
      }
    });
    
    function getTargetTypeText(type) {
      const types = {
        'all': '全員',
        'rank': 'ランク別',
        'usage_count': '利用回数別',
        'days_since_visit': '最終来店日別',
        'favorite_girl': 'お気に入りキャスト別',
        'preference': 'プリファレンス別',
        'girl_attribute_match': 'キャスト属性マッチ'
      };
      return types[type] || type;
    }
    
    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  </script>
</body>

</html>