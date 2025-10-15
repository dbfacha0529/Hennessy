<?php
require_once(dirname(__FILE__) . '/../functions.php');

session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
  header('Location: admin_login.php');
  exit;
}

$admin = $_SESSION['ADMIN'];

// g_login_idチェック
if (empty($_GET['id'])) {
  header('Location: admin_girls.php');
  exit;
}

$pdo = dbConnect();
$g_login_id = $_GET['id'];

try {
  // 女の子の情報を取得
  $sql = "SELECT * FROM girl WHERE g_login_id = :g_login_id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
  $stmt->execute();
  $girl = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$girl) {
    header('Location: admin_girls.php');
    exit;
  }
  
  // 最終出勤日を取得
  $sqlLastShift = "SELECT MAX(date) as last_date FROM shift WHERE g_login_id = :g_login_id";
  $stmtLastShift = $pdo->prepare($sqlLastShift);
  $stmtLastShift->bindValue(':g_login_id', $g_login_id, PDO::PARAM_STR);
  $stmtLastShift->execute();
  $lastShift = $stmtLastShift->fetch(PDO::FETCH_ASSOC);
  
  // 属性を取得
  $sqlAttr = "SELECT attribute_value FROM girl_attributes WHERE g_login_id = :g_login_id ORDER BY created_at ASC";
  $stmtAttr = $pdo->prepare($sqlAttr);
  $stmtAttr->execute([':g_login_id' => $g_login_id]);
  $attributes = $stmtAttr->fetchAll(PDO::FETCH_COLUMN);
  
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

// 画像配列をデコード
$images = json_decode($girl['imgs'], true) ?: [];

// 一問一答をデコード
$ichimon = json_decode($girl['ichimon'], true) ?: [];

// オプションをデコード
$options = json_decode($girl['option'], true) ?: [];
?>

<!doctype html>
<html lang="ja">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($girl['name']) ?> - 詳細 | Hennessy Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="./css/admin_home.css" rel="stylesheet">
  <link href="./css/admin_girl_detail.css" rel="stylesheet">
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
      <div class="header-left">
        <a href="admin_girls.php" class="back-link">
          <i class="bi bi-arrow-left"></i> キャスト一覧へ
        </a>
        <h1><i class="bi bi-person-circle"></i> <?= htmlspecialchars($girl['name']) ?></h1>
      </div>
      <div class="header-info">
        <span class="admin-name">
          <i class="bi bi-person-circle"></i>
          <?= htmlspecialchars($admin['admin_name']) ?>
        </span>
      </div>
    </header>

    <!-- キャスト情報カード -->
    <div class="girl-info-card">
      <div class="card-left">
        <div class="girl-image">
          <?php if ($girl['img']): ?>
            <img src="../../img/icon/<?= htmlspecialchars($girl['img']) ?>" alt="<?= htmlspecialchars($girl['name']) ?>">
          <?php else: ?>
            <div class="no-image">
              <i class="bi bi-person-circle"></i>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="card-right">
        <div class="info-grid">
          <div class="info-item editable" data-field="name" data-type="text">
            <label>源氏名</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['name']) ?></span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item">
            <label>ログインID</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['g_login_id']) ?></span>
            </div>
          </div>
          
          <div class="info-item editable" data-field="age" data-type="number">
            <label>年齢</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['age']) ?>歳</span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item editable" data-field="high" data-type="number">
            <label>身長</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['high']) ?>cm</span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item editable" data-field="b" data-type="text">
            <label>B</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['b']) ?></span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item editable" data-field="w" data-type="text">
            <label>W</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['w']) ?></span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item editable" data-field="h" data-type="text">
            <label>H</label>
            <div class="value-display">
              <span class="value"><?= htmlspecialchars($girl['h']) ?></span>
              <i class="bi bi-pencil edit-icon"></i>
            </div>
          </div>
          
          <div class="info-item">
            <label>最終出勤日</label>
            <div class="value-display">
              <span class="value"><?= $lastShift['last_date'] ? date('Y年m月d日', strtotime($lastShift['last_date'])) : '未出勤' ?></span>
            </div>
          </div>
          
          <div class="info-item">
            <label>掲載ステータス</label>
            <div class="value-display">
              <span class="status-badge <?= $girl['k_check'] === 'y' ? 'published' : 'unpublished' ?>">
                <?= $girl['k_check'] === 'y' ? '掲載中' : '非掲載' ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ヘッドコメント -->
    <div class="section-card">
      <h3 class="section-title">ヘッドコメント</h3>
      <div class="editable-content" data-field="head_comment" data-type="textarea">
        <div class="content-display">
          <p><?= nl2br(htmlspecialchars($girl['head_comment'])) ?></p>
          <i class="bi bi-pencil edit-icon"></i>
        </div>
      </div>
    </div>

    <!-- プロフィール -->
    <div class="section-card">
      <h3 class="section-title">
        プロフィール
        <?php if (empty($girl['comment'])): ?>
          <span class="badge-warning">未登録</span>
        <?php endif; ?>
      </h3>
      <div class="editable-content" data-field="comment" data-type="textarea">
        <div class="content-display">
          <p><?= !empty($girl['comment']) ? nl2br(htmlspecialchars($girl['comment'])) : '<span class="text-muted">未登録</span>' ?></p>
          <i class="bi bi-pencil edit-icon"></i>
        </div>
      </div>
    </div>

    <!-- 店舗コメント -->
    <div class="section-card">
      <h3 class="section-title">
        店舗コメント
        <?php if (empty($girl['store_comment'])): ?>
          <span class="badge-warning">未登録</span>
        <?php endif; ?>
      </h3>
      <div class="editable-content" data-field="store_comment" data-type="textarea">
        <div class="content-display">
          <p><?= !empty($girl['store_comment']) ? nl2br(htmlspecialchars($girl['store_comment'])) : '<span class="text-muted">未登録</span>' ?></p>
          <i class="bi bi-pencil edit-icon"></i>
        </div>
      </div>
    </div>

    <!-- 属性・特徴 -->
    <?php if (!empty($attributes)): ?>
    <div class="section-card">
      <h3 class="section-title">属性・特徴</h3>
      <div class="attributes-list">
        <?php foreach ($attributes as $attr): ?>
          <span class="attribute-tag"><?= htmlspecialchars($attr) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 可能オプション -->
    <?php if (!empty($options)): ?>
    <div class="section-card">
      <h3 class="section-title">可能オプション</h3>
      <div class="options-list">
        <?php foreach ($options as $option): ?>
          <span class="option-tag"><?= htmlspecialchars($option) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- 一問一答 -->
    <?php if (!empty($ichimon)): ?>
    <div class="section-card">
      <h3 class="section-title">一問一答</h3>
      <div class="qa-list">
        <?php foreach ($ichimon as $qa): ?>
          <div class="qa-item">
            <div class="question">Q. <?= htmlspecialchars($qa['q']) ?></div>
            <div class="answer">A. <?= htmlspecialchars($qa['a']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- パネル画像 -->
    <?php if (!empty($images)): ?>
    <div class="section-card">
      <h3 class="section-title">パネル画像</h3>
      <div class="images-grid">
        <?php foreach ($images as $img): ?>
          <div class="image-item">
            <img src="../../img/panels/<?= htmlspecialchars($img) ?>" alt="パネル画像">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- 編集モーダル -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">編集</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" id="editLabel"></label>
            <input type="text" class="form-control" id="editInput" style="display: none;">
            <input type="number" class="form-control" id="editInputNumber" style="display: none;">
            <textarea class="form-control" id="editTextarea" rows="5" style="display: none;"></textarea>
          </div>
          <div id="nameWarning" class="alert alert-warning" style="display: none;">
            <i class="bi bi-exclamation-triangle"></i> 源氏名は重複チェックが行われます
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="button" class="btn btn-primary" id="saveBtn">保存</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const girlId = '<?= htmlspecialchars($g_login_id, ENT_QUOTES) ?>';
    let currentField = '';
    let currentType = '';
    let currentElement = null;
    
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    
    // 編集可能な要素にイベントを追加
    document.querySelectorAll('.editable, .editable-content').forEach(element => {
      element.addEventListener('click', function(e) {
        if (e.target.classList.contains('edit-icon') || e.target.closest('.edit-icon')) {
          const field = this.dataset.field;
          const type = this.dataset.type;
          openEditModal(field, type, this);
        }
      });
    });
    
    function openEditModal(field, type, element) {
      currentField = field;
      currentType = type;
      currentElement = element;
      
      const valueElement = element.querySelector('.value, .content-display p');
      let currentValue = '';
      
      if (valueElement) {
        if (type === 'textarea') {
          currentValue = valueElement.textContent.trim();
          if (currentValue === '未登録') currentValue = '';
        } else {
          currentValue = valueElement.textContent.trim();
          // 単位を除去
          currentValue = currentValue.replace(/[歳cm]/g, '');
        }
      }
      
      // モーダルのタイトルとラベルを設定
      const labels = {
        'name': '源氏名',
        'age': '年齢',
        'high': '身長',
        'b': 'B',
        'w': 'W',
        'h': 'H',
        'head_comment': 'ヘッドコメント',
        'comment': 'プロフィール',
        'store_comment': '店舗コメント'
      };
      
      document.getElementById('editLabel').textContent = labels[field] || field;
      
      // 入力フィールドの表示切替
      document.getElementById('editInput').style.display = 'none';
      document.getElementById('editInputNumber').style.display = 'none';
      document.getElementById('editTextarea').style.display = 'none';
      document.getElementById('nameWarning').style.display = 'none';
      
      if (type === 'textarea') {
        document.getElementById('editTextarea').style.display = 'block';
        document.getElementById('editTextarea').value = currentValue;
      } else if (type === 'number') {
        document.getElementById('editInputNumber').style.display = 'block';
        document.getElementById('editInputNumber').value = currentValue;
      } else {
        document.getElementById('editInput').style.display = 'block';
        document.getElementById('editInput').value = currentValue;
        
        if (field === 'name') {
          document.getElementById('nameWarning').style.display = 'block';
        }
      }
      
      editModal.show();
    }
    
    // 保存ボタン
    document.getElementById('saveBtn').addEventListener('click', function() {
      let newValue = '';
      
      if (currentType === 'textarea') {
        newValue = document.getElementById('editTextarea').value;
      } else if (currentType === 'number') {
        newValue = document.getElementById('editInputNumber').value;
      } else {
        newValue = document.getElementById('editInput').value;
      }
      
      if (currentField === 'name' && !newValue.trim()) {
        alert('源氏名を入力してください');
        return;
      }
      
      // 保存処理
      this.disabled = true;
      this.textContent = '保存中...';
      
      fetch('api/update_girl_field.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          girl_id: girlId,
          field: currentField,
          value: newValue
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // 表示を更新
          const valueElement = currentElement.querySelector('.value, .content-display p');
          
          if (currentType === 'textarea') {
            if (newValue.trim()) {
              valueElement.innerHTML = newValue.replace(/\n/g, '<br>');
              valueElement.classList.remove('text-muted');
            } else {
              valueElement.innerHTML = '<span class="text-muted">未登録</span>';
            }
          } else {
            let displayValue = newValue;
            if (currentField === 'age') displayValue += '歳';
            if (currentField === 'high') displayValue += 'cm';
            valueElement.textContent = displayValue;
          }
          
          editModal.hide();
          
          // 成功メッセージ
          const alert = document.createElement('div');
          alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
          alert.style.top = '20px';
          alert.style.right = '20px';
          alert.style.zIndex = '9999';
          alert.innerHTML = `
            <i class="bi bi-check-circle"></i> 更新しました
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          `;
          document.body.appendChild(alert);
          
          setTimeout(() => {
            alert.remove();
          }, 3000);
        } else {
          alert(data.message || '更新に失敗しました');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('エラーが発生しました');
      })
      .finally(() => {
        document.getElementById('saveBtn').disabled = false;
        document.getElementById('saveBtn').textContent = '保存';
      });
    });
  </script>
</body>

</html>