<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];
$err = [];
$success_message = '';

try {
  $pdo = dbConnect();
  
  // テーブルが存在しない場合は作成
  $pdo->exec("CREATE TABLE IF NOT EXISTS `extra_fee_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `item_name` varchar(100) NOT NULL,
    `default_amount` int(11) NOT NULL DEFAULT 0,
    `sort_order` int(11) NOT NULL DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  
  // 削除処理
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM extra_fee_items WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $success_message = '項目を削除しました';
    }
  }
  
  // 追加処理
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $item_name = trim($_POST['item_name'] ?? '');
    $default_amount = intval($_POST['default_amount'] ?? 0);
    
    if (empty($item_name)) {
      $err['item_name'] = '項目名を入力してください';
    }
    if ($default_amount < 0) {
      $err['default_amount'] = '金額は0以上で入力してください';
    }
    
    if (empty($err)) {
      // 最大のsort_orderを取得
      $stmt = $pdo->query("SELECT MAX(sort_order) as max_order FROM extra_fee_items");
      $max_order = $stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;
      
      $stmt = $pdo->prepare("INSERT INTO extra_fee_items (item_name, default_amount, sort_order) 
                            VALUES (:item_name, :default_amount, :sort_order)");
      $stmt->execute([
        ':item_name' => $item_name,
        ':default_amount' => $default_amount,
        ':sort_order' => $max_order + 1
      ]);
      $success_message = '項目を追加しました';
    }
  }
  
  // 更新処理
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $item_name = trim($_POST['item_name'] ?? '');
    $default_amount = intval($_POST['default_amount'] ?? 0);
    
    if ($id > 0 && !empty($item_name)) {
      $stmt = $pdo->prepare("UPDATE extra_fee_items 
                            SET item_name = :item_name, default_amount = :default_amount 
                            WHERE id = :id");
      $stmt->execute([
        ':item_name' => $item_name,
        ':default_amount' => $default_amount,
        ':id' => $id
      ]);
      $success_message = '項目を更新しました';
    }
  }
  
  // 並び順更新処理
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $orders = json_decode($_POST['orders'], true);
    if (is_array($orders)) {
      foreach ($orders as $order) {
        $stmt = $pdo->prepare("UPDATE extra_fee_items SET sort_order = :sort_order WHERE id = :id");
        $stmt->execute([
          ':sort_order' => $order['sort_order'],
          ':id' => $order['id']
        ]);
      }
      echo json_encode(['success' => true]);
      exit;
    }
  }
  
  // 項目一覧取得
  $stmt = $pdo->query("SELECT * FROM extra_fee_items ORDER BY sort_order ASC, id ASC");
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // 初期データがない場合はデフォルトを追加
  if (empty($items)) {
    $default_items = [
      ['item_name' => '特別派遣料金', 'default_amount' => 5000, 'sort_order' => 1],
      ['item_name' => 'タオル代', 'default_amount' => 1000, 'sort_order' => 2],
      ['item_name' => '延長料金', 'default_amount' => 10000, 'sort_order' => 3],
      ['item_name' => '交通費', 'default_amount' => 2000, 'sort_order' => 4],
      ['item_name' => '指名料', 'default_amount' => 3000, 'sort_order' => 5],
      ['item_name' => '深夜料金', 'default_amount' => 5000, 'sort_order' => 6]
    ];
    
    foreach ($default_items as $item) {
      $stmt = $pdo->prepare("INSERT INTO extra_fee_items (item_name, default_amount, sort_order) 
                            VALUES (:item_name, :default_amount, :sort_order)");
      $stmt->execute($item);
    }
    
    // 再取得
    $stmt = $pdo->query("SELECT * FROM extra_fee_items ORDER BY sort_order ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  
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
  <title>よく使う追加項目管理 - Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <style>
    .manage-container {
      padding: 20px;
      max-width: 1000px;
      margin: 0 auto;
    }
    
    .page-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 30px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      color: white;
    }
    
    .page-header h2 {
      margin: 0;
      font-weight: bold;
      font-size: 28px;
    }
    
    .page-header p {
      margin: 10px 0 0 0;
      opacity: 0.9;
    }
    
    .manage-card {
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border: 1px solid #dee2e6;
      margin-bottom: 30px;
    }
    
    .card-title {
      font-size: 20px;
      font-weight: bold;
      color: #212529;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 3px solid #667eea;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .item-row {
      display: flex;
      gap: 15px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
      margin-bottom: 10px;
      align-items: center;
      transition: all 0.2s;
      border: 2px solid transparent;
    }
    
    .item-row:hover {
      background: #e9ecef;
      border-color: #667eea;
    }
    
    .item-row.dragging {
      opacity: 0.5;
    }
    
    .drag-handle {
      cursor: grab;
      color: #6c757d;
      font-size: 20px;
      padding: 5px;
    }
    
    .drag-handle:active {
      cursor: grabbing;
    }
    
    .item-name {
      flex: 1;
      font-weight: 600;
      color: #212529;
      display: flex;
      align-items: center;
    }
    
    .item-amount {
      width: 150px;
      font-weight: bold;
      color: #667eea;
      text-align: right;
    }
    
    .item-actions {
      display: flex;
      gap: 5px;
    }
    
    .btn-icon {
      padding: 8px 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 14px;
    }
    
    .btn-edit {
      background: #2196f3;
      color: white;
    }
    
    .btn-edit:hover {
      background: #1976d2;
    }
    
    .btn-delete {
      background: #dc3545;
      color: white;
    }
    
    .btn-delete:hover {
      background: #c82333;
    }
    
    .add-form {
      display: flex;
      gap: 15px;
      align-items: end;
    }
    
    .form-group {
      flex: 1;
    }
    
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
      display: block;
    }
    
    .form-control {
      border-radius: 8px;
      border: 2px solid #dee2e6;
      padding: 12px 15px;
    }
    
    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-add {
      padding: 12px 30px;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }
    
    .btn-add:hover {
      background: #5568d3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .alert-success {
      background: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .edit-row {
      display: flex;
      gap: 10px;
      padding: 15px;
      background: #fff8e1;
      border: 2px solid #ffc107;
      border-radius: 8px;
      margin-bottom: 10px;
    }
    
    .edit-row input {
      flex: 1;
    }
    
    .edit-row .form-control {
      padding: 8px 12px;
    }
  </style>
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
      <a href="admin_schedule.php" class="nav-item">
        <i class="bi bi-calendar3"></i>
        <span>スケジュール</span>
      </a>
      <a href="admin_reservations.php" class="nav-item">
        <i class="bi bi-calendar-check"></i>
        <span>予約管理</span>
      </a>
      <a href="admin_girls.php" class="nav-item">
        <i class="bi bi-people"></i>
        <span>キャスト管理</span>
      </a>
      <a href="admin_customers.php" class="nav-item">
        <i class="bi bi-person-badge"></i>
        <span>顧客管理</span>
      </a>
      <a href="admin_chat.php" class="nav-item">
        <i class="bi bi-chat-dots"></i>
        <span>チャット管理</span>
      </a>
      <a href="admin_sales.php" class="nav-item">
        <i class="bi bi-graph-up"></i>
        <span>売上レポート</span>
      </a>
      <a href="admin_settings.php" class="nav-item active">
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
    <div class="manage-container">
      <div class="page-header">
        <h2><i class="bi bi-tags"></i> よく使う追加項目管理</h2>
        <p>予約編集時に使用する追加料金項目を管理します</p>
      </div>

      <?php if ($success_message): ?>
        <div class="alert-success">
          <i class="bi bi-check-circle-fill" style="font-size: 24px;"></i>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
      <?php endif; ?>

      <!-- 新規追加フォーム -->
      <div class="manage-card">
        <h3 class="card-title">
          <i class="bi bi-plus-circle"></i> 新しい項目を追加
        </h3>
        
        <form method="post">
          <input type="hidden" name="action" value="add">
          <div class="add-form">
            <div class="form-group">
              <label class="form-label">項目名</label>
              <input type="text" class="form-control <?= isset($err['item_name']) ? 'is-invalid' : '' ?>" 
                     name="item_name" placeholder="例：特別派遣料金" required>
              <?php if (isset($err['item_name'])): ?>
                <div class="invalid-feedback"><?= $err['item_name'] ?></div>
              <?php endif; ?>
            </div>
            
            <div class="form-group" style="flex: 0 0 200px;">
              <label class="form-label">デフォルト金額</label>
              <input type="number" class="form-control <?= isset($err['default_amount']) ? 'is-invalid' : '' ?>" 
                     name="default_amount" placeholder="5000" min="0" step="100" required>
              <?php if (isset($err['default_amount'])): ?>
                <div class="invalid-feedback"><?= $err['default_amount'] ?></div>
              <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-add">
              <i class="bi bi-plus-lg"></i> 追加
            </button>
          </div>
        </form>
      </div>

      <!-- 項目一覧 -->
      <div class="manage-card">
        <h3 class="card-title">
          <i class="bi bi-list-ul"></i> 登録済み項目
        </h3>
        
        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
          <i class="bi bi-info-circle"></i> 
          <strong>ドラッグ&ドロップで並び替えができます</strong>
        </div>
        
        <div id="items-list">
          <?php if (empty($items)): ?>
            <p style="text-align: center; color: #6c757d; padding: 40px;">
              登録されている項目がありません
            </p>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <div class="item-row" data-id="<?= $item['id'] ?>" draggable="true">
                <div class="drag-handle">
                  <i class="bi bi-grip-vertical"></i>
                </div>
                <div class="item-name">
                  <?= htmlspecialchars($item['item_name']) ?>
                </div>
                <div class="item-amount">
                  ¥<?= number_format($item['default_amount']) ?>
                </div>
                <div class="item-actions">
                  <button class="btn-icon btn-edit" onclick="editItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['default_amount'] ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn-icon btn-delete" onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>')">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- 編集モーダル -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-pencil-square"></i> 項目を編集
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" id="edit-form">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="edit-id">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">項目名</label>
              <input type="text" class="form-control" name="item_name" id="edit-item-name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">デフォルト金額</label>
              <input type="number" class="form-control" name="default_amount" id="edit-default-amount" min="0" step="100" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check"></i> 更新
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 削除確認モーダル -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-exclamation-triangle text-danger"></i> 削除確認
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post" id="delete-form">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="delete-id">
          <div class="modal-body">
            <p>「<strong id="delete-item-name"></strong>」を削除してもよろしいですか？</p>
            <div style="background: #fff8e1; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
              <strong style="color: #f57c00;">注意：</strong>
              削除すると元に戻せません。
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
            <button type="submit" class="btn btn-danger">
              <i class="bi bi-trash"></i> 削除する
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // 編集モーダル表示
    function editItem(id, name, amount) {
      document.getElementById('edit-id').value = id;
      document.getElementById('edit-item-name').value = name;
      document.getElementById('edit-default-amount').value = amount;
      const modal = new bootstrap.Modal(document.getElementById('editModal'));
      modal.show();
    }
    
    // 削除モーダル表示
    function deleteItem(id, name) {
      document.getElementById('delete-id').value = id;
      document.getElementById('delete-item-name').textContent = name;
      const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
      modal.show();
    }
    
    // ドラッグ&ドロップ機能
    let draggedElement = null;
    
    document.querySelectorAll('.item-row').forEach(item => {
      item.addEventListener('dragstart', function(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      
      item.addEventListener('dragend', function(e) {
        this.classList.remove('dragging');
        draggedElement = null;
      });
      
      item.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        if (draggedElement && draggedElement !== this) {
          const list = this.parentNode;
          const items = [...list.querySelectorAll('.item-row')];
          const draggedIndex = items.indexOf(draggedElement);
          const targetIndex = items.indexOf(this);
          
          if (draggedIndex < targetIndex) {
            this.parentNode.insertBefore(draggedElement, this.nextSibling);
          } else {
            this.parentNode.insertBefore(draggedElement, this);
          }
        }
      });
      
      item.addEventListener('drop', function(e) {
        e.preventDefault();
        saveOrder();
      });
    });
    
    // 並び順を保存
    function saveOrder() {
      const items = document.querySelectorAll('.item-row');
      const orders = [];
      
      items.forEach((item, index) => {
        orders.push({
          id: parseInt(item.dataset.id),
          sort_order: index + 1
        });
      });
      
      // Ajax送信
      fetch('admin_extra_items.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=reorder&orders=' + encodeURIComponent(JSON.stringify(orders))
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          console.log('並び順を保存しました');
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    }
  </script>
</body>

</html>