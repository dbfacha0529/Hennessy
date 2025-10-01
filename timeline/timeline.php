<?php
include '../web/header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();

// 女の子リスト取得(絞り込み用)
$girlsStmt = $pdo->query("SELECT g_login_id, name FROM girl ORDER BY name");
$girls = k_checkgirls($pdo);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タイムライン</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding-bottom: 11vh;
            margin: 0;
        }
        
        .timeline-container {
            max-width: 600px;
            margin: 20px auto;
            padding-bottom: 11vh;
        }
        
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .post-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .post-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
        }
        
        .post-info {
            flex: 1;
        }
        
        .post-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .post-time {
            font-size: 13px;
            color: #666;
        }
        
        .post-content {
            margin-bottom: 15px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .post-media {
            margin-bottom: 15px;
        }
        
        .media-grid {
            display: grid;
            gap: 10px;
        }
        
        .media-grid.single {
            grid-template-columns: 1fr;
        }
        
        .media-grid.multiple {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .media-item img {
            width: 100%;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .media-item video {
            width: 100%;
            border-radius: 8px;
        }
        
        .post-actions {
            display: flex;
            gap: 20px;
            border-top: 1px solid #eee;
            padding-top: 12px;
        }
        
        .like-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .like-btn:hover {
            color: #ff3b30;
        }
        
        .like-btn.liked {
            color: #ff3b30;
        }
        
        .like-btn i {
            font-size: 18px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-posts {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        #loadMoreTrigger {
            height: 1px;
        }
        /* クリック可能要素のスタイル */
.clickable {
    cursor: pointer;
    transition: opacity 0.2s;
}

.clickable:hover {
    opacity: 0.7;
}

.post-avatar.clickable {
    cursor: pointer;
}

.post-name.clickable {
    cursor: pointer;
}

.post-name.clickable:hover {
    color: #003018;
    text-decoration: underline;
}

/* クリック可能要素のスタイル */
.clickable {
    cursor: pointer;
    transition: opacity 0.2s;
}

.clickable:hover {
    opacity: 0.7;
}

.post-avatar.clickable {
    cursor: pointer;
}

.post-name.clickable {
    cursor: pointer;
}

.post-name.clickable:hover {
    color: #003018;
    text-decoration: underline;
}

/* Pull-to-Refresh */
.refresh-indicator {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 0;
    background: linear-gradient(to bottom, #f5f5f5, white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: #666;
    transition: height 0.3s, opacity 0.3s;
    opacity: 0;
    overflow: hidden;
    z-index: 10;
}

.refresh-indicator i {
    margin-right: 5px;
}

.timeline-container {
    position: relative;
    padding-top: 0;
}
      
    </style>
</head>
<body>
    <div class="timeline-container">
       <!-- Pull-to-Refresh インジケーター -->
    <div id="refresh-indicator" class="refresh-indicator">
        <i class="bi bi-arrow-down-circle"></i> 引っ張って更新
    </div>
        <!-- フィルターバー -->
        <div class="filter-bar">
            <div class="row g-2">
                <div class="col">
                    <select id="filterType" class="form-select">
                        <option value="all">全て</option>
                        <option value="favorite">お気に入り</option>
                        <option value="girl">女の子で絞り込み</option>
                    </select>
                </div>
                <div class="col" id="girlSelectWrapper" style="display: none;">
                    <select id="girlSelect" class="form-select">
                        <option value="">女の子を選択</option>
                        <?php foreach ($girls as $girl): ?>
                            <option value="<?= htmlspecialchars($girl['g_login_id']) ?>">
                                <?= htmlspecialchars($girl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- 投稿一覧 -->
        <div id="postsContainer"></div>
        
        <!-- 読み込み中 -->
        <div id="loading" class="loading" style="display: none;">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">読み込み中...</span>
            </div>
        </div>
        
        <!-- 無限スクロール用トリガー -->
        <div id="loadMoreTrigger"></div>
    </div>
    
    <script src="timeline.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../web/footer.php'; ?>