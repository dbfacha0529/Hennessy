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
  
  // 絞り込み条件
  $search_name = $_GET['search_name'] ?? '';
  $filter_status = $_GET['filter_status'] ?? 'all';
  
  // SQL作成（commentとstore_commentも取得）
  $sql = "SELECT g.*, 
          (SELECT MAX(date) FROM shift WHERE g_login_id = g.g_login_id) as last_shift_date
          FROM girl g
          WHERE 1=1";
  
  $params = [];
  
  // サポートアカウントを除外
  $sql .= " AND g.name != 'サポート'";
  
  // 名前検索
  if ($search_name) {
    $sql .= " AND g.name LIKE :search_name";
    $params[':search_name'] = '%' . $search_name . '%';
  }
  
  // 掲載ステータス絞り込み
  if ($filter_status === 'published') {
    $sql .= " AND g.k_check = 'y'";
  } elseif ($filter_status === 'unpublished') {
    $sql .= " AND g.k_check = 'n'";
  }
  
  $sql .= " ORDER BY g.k_check DESC, g.g_login_id DESC";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $girls = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 統計情報（サポートアカウント除外）
  $total_girls = count($girls);
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM girl WHERE k_check = 'y' AND name != 'サポート'");
  $published_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  // 承認待ち件数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM girl_pending WHERE status = 'pending'");
  $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

  // 未読チャット数
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND sender_type = 'user'");
  $unread_chats = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
  
  unset($pdo);
} catch (Exception $e) {
  echo "DBエラー: " . $e->getMessage();
  exit;
}

// バッジ判定関数
function getBadgeColor($girl) {
  // commentが空またはnullの場合は赤
  if (empty($girl['comment']) || trim($girl['comment']) === '') {
    return 'red';
  }
  // commentはあるがstore_commentが空またはnullの場合は黄色
  if (empty($girl['store_comment']) || trim($girl['store_comment']) === '') {
    return 'yellow';
  }
  // 両方ある場合はバッジなし
  return null;
}
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>キャスト管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <link href="./css/admin_girls.css" rel="stylesheet">
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
      <a href="admin_girls.php" class="nav-item active">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="admin_girl_approvals.php" class="nav-item">
        <i class="bi bi-check-circle"></i>
        <span>承認管理</span>
        <?php if ($pending_count > 0): ?>
          <span class="badge"><?= $pending_count ?></span>
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
      <div style="display: flex; align-items: center; gap: 20px;">
        <h1 style="margin: 0;"><i class="bi bi-people"></i> キャスト管理</h1>
        <div style="display: flex; gap: 15px; font-size: 0.85rem; color: #999;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 12px; height: 12px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 50%;"></span>
            <span>プロフィール未登録</span>
          </div>
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 12px; height: 12px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 50%;"></span>
            <span>店舗コメント未登録</span>
          </div>
        </div>
      </div>
      <div class="header-info">
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- 統計情報 -->
    <div class="stats-row">
      <div class="stat-item">
        <i class="bi bi-people-fill"></i>
        <div>
          <span class="stat-number"><?= $total_girls ?></span>
          <span class="stat-label">総キャスト数</span>
        </div>
      </div>
      <div class="stat-item">
        <i class="bi bi-check-circle-fill"></i>
        <div>
          <span class="stat-number"><?= $published_count ?></span>
          <span class="stat-label">掲載中</span>
        </div>
      </div>
    </div>

    <!-- 検索・絞り込みエリア -->
    <div class="filter-section">
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="キャスト名で検索..." value="<?= htmlspecialchars($search_name) ?>">
      </div>
      
      <div class="filter-buttons">
        <button class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>" data-filter="all">
          すべて
        </button>
        <button class="filter-btn <?= $filter_status === 'published' ? 'active' : '' ?>" data-filter="published">
          掲載中
        </button>
        <button class="filter-btn <?= $filter_status === 'unpublished' ? 'active' : '' ?>" data-filter="unpublished">
          非掲載
        </button>
      </div>
      
      <button class="btn-add-girl" onclick="location.href='admin_girl_new.php'">
        <i class="bi bi-plus-circle"></i>
        新規登録
      </button>
    </div>

    <!-- キャストカード一覧 -->
    <div class="girls-grid">
      <?php if (empty($girls)): ?>
        <div class="no-data">
          <i class="bi bi-inbox"></i>
          <p>キャストが見つかりませんでした</p>
        </div>
      <?php else: ?>
        <?php foreach ($girls as $girl): ?>
          <?php $badgeColor = getBadgeColor($girl); ?>
          <div class="girl-card" data-girl-id="<?= $girl['g_login_id'] ?>">
            <!-- 情報不足バッジ -->
            <?php if ($badgeColor): ?>
              <div class="info-badge <?= $badgeColor ?>" 
                   title="<?= $badgeColor === 'red' ? 'コメント未入力' : '店舗コメント未入力' ?>">
              </div>
            <?php endif; ?>
            
            <div class="card-header">
              <button class="status-toggle <?= $girl['k_check'] === 'y' ? 'published' : 'unpublished' ?>" 
                      data-girl-id="<?= $girl['g_login_id'] ?>"
                      data-status="<?= $girl['k_check'] ?>">
                <?= $girl['k_check'] === 'y' ? '掲載中' : '非掲載' ?>
              </button>
            </div>
            
            <div class="card-body" onclick="location.href='admin_girl_detail.php?id=<?= $girl['g_login_id'] ?>'">
              <div class="girl-icon">
                <?php if ($girl['img']): ?>
                  <img src="../../img/icon/<?= htmlspecialchars($girl['img']) ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
                <?php else: ?>
                  <div class="no-image">
                    <i class="bi bi-person-circle"></i>
                  </div>
                <?php endif; ?>
              </div>
              
              <h3 class="girl-name"><?= htmlspecialchars($girl['name']) ?></h3>
              
              <div class="girl-info">
                <div class="info-item">
                  <i class="bi bi-calendar-event"></i>
                  <span>最終出勤: <?= $girl['last_shift_date'] ? date('Y/m/d', strtotime($girl['last_shift_date'])) : '未出勤' ?></span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // JavaScript側でのフィルタリング（フォーカスを保持）
    const searchInput = document.getElementById('searchInput');
    const allCards = document.querySelectorAll('.girl-card');
    
    // 検索機能（ページ遷移なし）
    searchInput.addEventListener('input', function() {
      const searchValue = this.value.toLowerCase();
      
      allCards.forEach(card => {
        const girlName = card.querySelector('.girl-name').textContent.toLowerCase();
        if (girlName.includes(searchValue)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });

    // 絞り込みボタン
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const filter = this.dataset.filter;
        const searchName = document.getElementById('searchInput').value;
        window.location.href = `admin_girls.php?search_name=${searchName}&filter_status=${filter}`;
      });
    });

    // 掲載/非掲載切り替え
    document.querySelectorAll('.status-toggle').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        const girlId = this.dataset.girlId;
        const currentStatus = this.dataset.status;
        const newStatus = currentStatus === 'y' ? 'n' : 'y';
        
        // APIリクエスト
        fetch('api/toggle_girl_status.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            girl_id: girlId,
            status: newStatus
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // ボタンの表示を更新
            this.dataset.status = newStatus;
            if (newStatus === 'y') {
              this.classList.remove('unpublished');
              this.classList.add('published');
              this.textContent = '掲載中';
            } else {
              this.classList.remove('published');
              this.classList.add('unpublished');
              this.textContent = '非掲載';
            }
          } else {
            alert('ステータス変更に失敗しました');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('エラーが発生しました');
        });
      });
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>