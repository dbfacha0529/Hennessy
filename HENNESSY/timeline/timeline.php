<?php
include '../web/header.php';
require_once(dirname(__FILE__) . '/../functions.php');

// ログインチェック
if (!isset($_SESSION['USER']['tel'])) {
    header('Location: login.php');
    exit;
}

$pdo = dbConnect();

// k_check='y'の女の子リスト取得(検索用)
$girlsStmt = $pdo->query("SELECT g_login_id, name FROM girl WHERE k_check = 'y' ORDER BY name");
$girls = $girlsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

    <style>
        
/* ===============================
   タイムラインスタイル
   =============================== */

.timeline-container {
    max-width: 62.5rem;
    margin: 1.25rem auto;
    padding-bottom: 11vh;
    position: relative;
    padding-top: 0;
}

/* ===============================
   フィルター&検索バー
   =============================== */
.filter-bar {
    background: white;
    padding: 0.9375rem;
    border-radius: 0.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
}

.filter-search-row {
    display: flex;
    gap: 0.625rem;
    align-items: flex-start;
}

.filter-section {
    flex: 1;
}

.search-section {
    flex: 1;
    position: relative;
}

.search-input-wrapper {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    border: 0.0625rem solid #ddd;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    height: 2.375rem;
}

.search-input:focus {
    outline: none;
    border-color: #003018;
}

.filter-section .form-select {
    height: 2.375rem;
    font-size: 0.875rem;
}

.search-clear-btn {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 0.25rem;
    display: none;
}

.search-input:not(:placeholder-shown) ~ .search-clear-btn {
    display: block;
}

.search-clear-btn:hover {
    color: #333;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 0.0625rem solid #ddd;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    max-height: 18.75rem;
    overflow-y: auto;
    display: none;
    z-index: 100;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
}

.search-results.show {
    display: block;
}

.search-result-item {
    padding: 0.625rem 0.75rem;
    cursor: pointer;
    border-bottom: 0.0625rem solid #f0f0f0;
    font-size: 0.875rem;
    transition: background 0.2s;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: #f5f5f5;
}

.search-result-item.active {
    background: #e8f5e9;
    font-weight: bold;
}

.no-results {
    padding: 0.625rem 0.75rem;
    color: #999;
    font-size: 0.875rem;
    text-align: center;
}

.selected-girl-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    background: #e8f5e9;
    color: #003018;
    padding: 0.375rem 0.625rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.selected-girl-tag button {
    background: none;
    border: none;
    color: #003018;
    cursor: pointer;
    padding: 0;
    font-size: 1rem;
    line-height: 1;
}

.selected-girl-tag button:hover {
    color: #ff3b30;
}

/* ===============================
   投稿カード
   =============================== */
.post-card {
    background: white;
    border-radius: 0.5rem;
    padding: 1.25rem;
    margin-bottom: 0.9375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
}

.post-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.9375rem;
}

.post-avatar {
    width: 3.125rem;
    height: 3.125rem;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 0.75rem;
}

.post-info {
    flex: 1;
}

.post-name {
    font-weight: bold;
    font-size: 1rem;
    margin-bottom: 0.125rem;
}

.post-time {
    font-size: 0.8125rem;
    color: #666;
}

.post-content {
    margin-bottom: 0.9375rem;
    line-height: 1.6;
    white-space: pre-wrap;
    font-size: 0.9375rem;
}

/* ===============================
   メディア表示
   =============================== */
.post-media {
    margin-bottom: 0.9375rem;
}

.media-grid {
    display: grid;
    gap: 0.625rem;
}

.media-grid.single {
    grid-template-columns: 1fr;
}

.media-grid.multiple {
    grid-template-columns: repeat(2, 1fr);
}

.media-item img {
    width: 100%;
    border-radius: 0.5rem;
    cursor: pointer;
}

.media-item video {
    width: 100%;
    border-radius: 0.5rem;
}

/* ===============================
   投稿アクション
   =============================== */
.post-actions {
    display: flex;
    gap: 1.25rem;
    border-top: 0.0625rem solid #eee;
    padding-top: 0.75rem;
}

.like-btn {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    background: none;
    border: none;
    cursor: pointer;
    color: #666;
    font-size: 0.875rem;
    transition: color 0.2s;
}

.like-btn:hover {
    color: #ff3b30;
}

.like-btn.liked {
    color: #ff3b30;
}

.like-btn i {
    font-size: 1.125rem;
}

/* ===============================
   ローディング・メッセージ
   =============================== */
.loading {
    text-align: center;
    padding: 2.5rem;
    color: #999;
}

.no-posts {
    text-align: center;
    padding: 3.75rem 1.25rem;
    color: #999;
    font-size: 1rem;
}

#loadMoreTrigger {
    height: 0.0625rem;
}

/* ===============================
   クリック可能要素
   =============================== */
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

/* ===============================
   Pull-to-Refresh
   =============================== */
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
    font-size: 0.875rem;
    color: #666;
    transition: height 0.3s, opacity 0.3s;
    opacity: 0;
    overflow: hidden;
    z-index: 10;
}

.refresh-indicator i {
    margin-right: 0.3125rem;
}

/* ===============================
   レスポンシブ: タブレット (768px以下)
   =============================== */
@media (max-width: 48rem) {
    .timeline-container {
        margin: 1rem auto;
        padding-bottom: 10vh;
    }

    .filter-bar {
        padding: 0.875rem;
        margin-bottom: 1rem;
    }

    .post-card {
        padding: 1.125rem;
        margin-bottom: 0.875rem;
    }

    .post-avatar {
        width: 2.75rem;
        height: 2.75rem;
    }

    .post-name {
        font-size: 0.9375rem;
    }

    .post-time {
        font-size: 0.75rem;
    }

    .post-content {
        font-size: 0.875rem;
    }

    .like-btn {
        font-size: 0.8125rem;
    }

    .like-btn i {
        font-size: 1rem;
    }
}

/* ===============================
   レスポンシブ: スマホ (480px以下)
   文字サイズを大きく調整
   =============================== */
@media (max-width: 30rem) {
    .timeline-container {
        margin: 0.625rem auto;
        padding-bottom: 9vh;
    }

    .filter-bar {
        padding: 0.875rem;
        margin-bottom: 1rem;
        border-radius: 0.5rem;
    }

    .search-input {
        font-size: 1.0625rem;
        padding: 0.75rem 2.75rem 0.75rem 0.875rem;
        height: 2.875rem;
    }

    .filter-section .form-select {
        height: 2.875rem;
        font-size: 1.0625rem;
        padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    }

    .search-result-item {
        padding: 0.875rem;
        font-size: 1.0625rem;
    }

    .selected-girl-tag {
        font-size: 1.0625rem;
        padding: 0.5rem 0.875rem;
    }

    .selected-girl-tag button {
        font-size: 1.125rem;
    }

    .no-results {
        font-size: 1rem;
        padding: 0.875rem;
    }

    .post-card {
        padding: 1.125rem;
        margin-bottom: 0.875rem;
        border-radius: 0.5rem;
    }

    .post-header {
        margin-bottom: 1rem;
    }

    .post-avatar {
        width: 3rem;
        height: 3rem;
        margin-right: 0.75rem;
    }

    .post-name {
        font-size: 1.125rem;
        margin-bottom: 0.1875rem;
    }

    .post-time {
        font-size: 0.9375rem;
    }

    .post-content {
        font-size: 1.0625rem;
        margin-bottom: 1rem;
        line-height: 1.7;
    }

    .post-media {
        margin-bottom: 1rem;
    }

    .media-grid {
        gap: 0.625rem;
    }

    .media-item img,
    .media-item video {
        border-radius: 0.5rem;
    }

    .post-actions {
        gap: 1.25rem;
        padding-top: 0.75rem;
    }

    .like-btn {
        font-size: 1.0625rem;
        gap: 0.5rem;
    }

    .like-btn i {
        font-size: 1.25rem;
    }

    .loading {
        padding: 2.5rem;
        font-size: 1rem;
    }

    .no-posts {
        padding: 3.5rem 1.25rem;
        font-size: 1.125rem;
    }

    .refresh-indicator {
        font-size: 1rem;
    }

    .refresh-indicator i {
        margin-right: 0.5rem;
        font-size: 1.125rem;
    }
}

    </style>
</head>
<body>
    <div class="timeline-container">
       <!-- Pull-to-Refresh インジケーター -->
        <div id="refresh-indicator" class="refresh-indicator">
            <i class="bi bi-arrow-down-circle"></i> 引っ張って更新
        </div>
        
        <!-- フィルター&検索バー -->
        <div class="filter-bar">
            <div class="filter-search-row">
                <!-- 左側: フィルター -->
                <div class="filter-section">
                    <select id="filterType" class="form-select">
                        <option value="all">全て</option>
                        <option value="favorite">お気に入り</option>
                        <option value="used">ご利用済み</option>
                    </select>
                </div>
                
                <!-- 右側: 検索 -->
                <div class="search-section">
                    <div class="search-input-wrapper">
                        <input 
                            type="text" 
                            id="girlSearch" 
                            class="search-input" 
                            placeholder="女の子を検索..."
                            autocomplete="off"
                        >
                        <button class="search-clear-btn" id="searchClearBtn">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                    <div id="searchResults" class="search-results"></div>
                    <div id="selectedGirlTag"></div>
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
    
    <script>
        // 女の子リストをJavaScriptに渡す
        window.girlsList = <?php echo json_encode($girls); ?>;
    </script>
    <script src="timeline.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php include '../web/footer.php'; ?>