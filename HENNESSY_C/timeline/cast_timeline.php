<?php
session_start();
require_once(dirname(__FILE__) . '/../functions.php');

// キャストログインチェック
if (!isset($_SESSION['CAST']['g_login_id'])) {
    header('Location: ../cast_login.php');
    exit;
}

$pdo = dbConnect();
$g_login_id = $_SESSION['CAST']['g_login_id'];

// キャスト情報取得
$sql = "SELECT name, img FROM girl WHERE g_login_id = :g_login_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':g_login_id' => $g_login_id]);
$cast = $stmt->fetch(PDO::FETCH_ASSOC);

require_once(dirname(__FILE__) . '/../web/header.php');
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: linear-gradient(135deg, #ffe4e9 0%, #ffd4e0 100%);
        min-height: 100vh;
    }

    /* Pull-to-Refresh インジケーター */
    .refresh-indicator {
        position: fixed;
        top: 8vh;
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
        z-index: 999;
    }

    .refresh-indicator i {
        margin-right: 0.3125rem;
    }

    /* 検索バー */
    .search-bar {
        position: sticky;
    top: 8vh;
    background: white;
    padding: 0.625rem 1rem;
    box-shadow: 0 0.125rem 0.5rem rgba(255, 105, 180, 0.2);
    z-index: 997;
    max-width: 37.5rem;
    margin: 0 auto;
    }

    .search-container {
        position: relative;
    }

    .search-input {
        width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2rem;
    border: 0.125rem solid #f0f0f0;
    border-radius: 1.25rem;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s;
    background: #f8f8f8;
    }

    .search-input:focus {
        border-color: #ff69b4;
        background: white;
    }

    .search-icon {
        position: absolute;
    left: 0.625rem;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    font-size: 0.875rem;
    }

    .clear-search {
        position: absolute;
        right: 0.875rem;
        top: 50%;
        transform: translateY(-50%);
        background: #ddd;
        border: none;
        border-radius: 50%;
        width: 1.25rem;
        height: 1.25rem;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        font-size: 0.75rem;
    }

    .clear-search.show {
        display: flex;
    }

    /* 検索結果ドロップダウン */
    .search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.15);
        max-height: 15rem;
        overflow-y: auto;
        margin-top: 0.5rem;
        display: none;
        z-index: 10;
    }

    .search-dropdown.show {
        display: block;
    }

    .search-dropdown-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 0.0625rem solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: background 0.2s;
    }

    .search-dropdown-item:last-child {
        border-bottom: none;
    }

    .search-dropdown-item:hover {
        background: #f8f8f8;
    }

    .search-dropdown-item.all-posts {
        color: #ff69b4;
        font-weight: 600;
    }

    .dropdown-avatar {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .dropdown-name {
        flex: 1;
        font-size: 0.9375rem;
    }

    .no-results {
        padding: 1rem;
        text-align: center;
        color: #999;
        font-size: 0.875rem;
    }

    /* 選択中の表示 */
    .selected-filter {
        display: none;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding: 0.5rem 0.75rem;
        background: #fff5f7;
        border-radius: 0.5rem;
        border: 0.0625rem solid #ffb6c1;
    }

    .selected-filter.show {
        display: flex;
    }

    .selected-filter-text {
        flex: 1;
        font-size: 0.875rem;
        color: #ff69b4;
        font-weight: 600;
    }

    .remove-filter {
        background: none;
        border: none;
        color: #ff69b4;
        cursor: pointer;
        font-size: 1rem;
    }

    /* コンテナ */
    .timeline-container {
        max-width: 37.5rem;
    margin: 0 auto;
    padding: 0 1rem calc(11vh + 8rem) 1rem;
    position: relative;
    }

    /* 投稿作成エリア（下部固定） */
    .post-composer {
        position: fixed;
    bottom: 11vh;
    left: 0;
    right: 0;
    background: white;
    padding: 0.75rem 1rem;
    box-shadow: 0 -0.125rem 0.5rem rgba(255, 105, 180, 0.3);
    z-index: 998;
    max-width: 37.5rem;
    margin: 0 auto;
    }

    .composer-header {
        display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    }

    .composer-avatar {
        width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    }

    .composer-input-area {
        flex: 1;
    }

    .composer-textarea {
        width: 100%;
    border: 0.0625rem solid #f0f0f0;
    border-radius: 0.5rem;
    resize: none;
    font-size: 0.875rem;
    min-height: 2.5rem;
    max-height: 6rem;
    padding: 0.5rem 0.75rem;
    outline: none;
    font-family: inherit;
    }

    .composer-textarea::placeholder {
        color: #999;
    }

    .composer-textarea:focus {
        border-color: #ff69b4;
    }

    /* メディアプレビュー */
    .media-preview-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .media-preview-item {
        position: relative;
        padding-top: 100%;
        border-radius: 0.5rem;
        overflow: hidden;
        background: #f0f0f0;
    }

    .media-preview-item img,
    .media-preview-item video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .media-preview-remove {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        background: rgba(0, 0, 0, 0.6);
        color: white;
        border: none;
        border-radius: 50%;
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.875rem;
        z-index: 1;
    }

    /* 投稿ボタンエリア */
    .composer-actions {
        display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.5rem;
    border-top: 0.0625rem solid #f0f0f0;
    margin-top: 0.5rem;
    }

    .media-upload-btn {
        background: none;
    border: none;
    color: #ff69b4;
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: color 0.2s;
    }

    .media-upload-btn:hover {
        color: #ff1493;
    }

    .media-count {
        font-size: 0.75rem;
        color: #666;
    }

    .post-submit-btn {
        background: linear-gradient(135deg, #ff69b4 0%, #ff1493 100%);
    color: white;
    border: none;
    border-radius: 1.25rem;
    padding: 0.4375rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    }

    .post-submit-btn:hover:not(:disabled) {
        transform: translateY(-0.0625rem);
        box-shadow: 0 0.25rem 0.75rem rgba(255, 105, 180, 0.3);
    }

    .post-submit-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* 投稿カード */
    .post-card {
        background: white;
        border-radius: 1rem;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 0.125rem 0.5rem rgba(255, 105, 180, 0.2);
    }

    .post-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.75rem;
    }

    .post-user-info {
        display: flex;
        gap: 0.75rem;
    }

    .post-avatar {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .post-details {
        flex: 1;
    }

    .post-name {
        font-weight: 600;
        font-size: 0.9375rem;
        margin-bottom: 0.125rem;
        color: #ff69b4;
    }

    .post-time {
        font-size: 0.8125rem;
        color: #666;
    }

    .post-actions-menu {
        position: relative;
    }

    .post-menu-btn {
        background: none;
        border: none;
        color: #666;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .post-menu-btn:hover {
        background: #f0f0f0;
    }

    .post-menu-dropdown {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.15);
        min-width: 8rem;
        display: none;
        z-index: 10;
    }

    .post-menu-dropdown.show {
        display: block;
    }

    .post-menu-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 0.0625rem solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: background 0.2s;
    }

    .post-menu-item:last-child {
        border-bottom: none;
    }

    .post-menu-item:hover {
        background: #f8f8f8;
    }

    .post-menu-item.delete {
        color: #dc3545;
    }

    .post-content {
        margin-bottom: 0.75rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .post-media {
        margin-bottom: 0.75rem;
    }

    .post-media-grid {
        display: grid;
        gap: 0.5rem;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .post-media-grid.single {
        grid-template-columns: 1fr;
    }

    .post-media-grid.double {
        grid-template-columns: repeat(2, 1fr);
    }

    .post-media-grid.triple {
        grid-template-columns: repeat(3, 1fr);
    }

    .post-media-item {
        position: relative;
    background: #f0f0f0;
    border-radius: 0.5rem;
    overflow: hidden;
}

.post-media-item img,
.post-media-item video {
    width: 100%;
    height: auto;
    display: block;
    object-fit: contain;
    }

    .post-stats {
        display: flex;
        gap: 1.5rem;
        padding-top: 0.75rem;
        border-top: 0.0625rem solid #f0f0f0;
        color: #666;
        font-size: 0.875rem;
    }

    .post-stat {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .post-stat i {
        color: #ff69b4;
    }

    /* ローディング */
    .loading {
        text-align: center;
        padding: 2rem;
        color: #999;
    }

    .spinner {
        border: 0.25rem solid #f3f3f3;
        border-top: 0.25rem solid #ff69b4;
        border-radius: 50%;
        width: 2.5rem;
        height: 2.5rem;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: #999;
    }

    .empty-state i {
        font-size: 4rem;
        color: #ffb6c1;
        margin-bottom: 1rem;
    }

    /* モーダル（編集用） */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-overlay.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 1rem;
        width: 100%;
        max-width: 31.25rem;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 1rem;
        border-bottom: 0.0625rem solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        font-size: 1.125rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #666;
        cursor: pointer;
    }

    .modal-body {
        padding: 1rem;
    }

    /* レスポンシブ */
    @media (max-width: 37.5rem) {
        .post-composer {
            left: 0;
            right: 0;
            max-width: 100%;
        }

        .search-bar {
            max-width: 100%;
        }
    }

    @media (max-width: 30rem) {
        .search-bar {
        padding: 0.5rem 0.75rem;
    }
    
    .search-input {
        padding: 0.4375rem 0.625rem 0.4375rem 1.75rem;
        font-size: 0.8125rem;
    }
    
    .timeline-container {
        padding: 0 0.75rem calc(11vh + 7rem) 0.75rem;
    }

    .post-card {
        padding: 0.875rem;
    }

    .composer-avatar,
    .post-avatar,
    .dropdown-avatar {
        width: 2rem;
        height: 2rem;
    }

    .composer-textarea {
        font-size: 0.8125rem;
        min-height: 2rem;
        padding: 0.4375rem 0.625rem;
    }
    
    .post-submit-btn {
        padding: 0.375rem 1rem;
        font-size: 0.8125rem;
    }
    }

    /* 非表示 */
    .hidden {
        display: none;
    }
</style>

<!-- Pull-to-Refresh インジケーター -->
<div id="refresh-indicator" class="refresh-indicator">
    <i class="bi bi-arrow-down-circle"></i> 引っ張って更新
</div>

<!-- 検索バー -->
<div class="search-bar">
    <div class="search-container">
        <i class="bi bi-search search-icon"></i>
        <input type="text" 
               id="searchInput" 
               class="search-input" 
               placeholder="源氏名で検索..."
               autocomplete="off">
        <button id="clearSearch" class="clear-search">
            <i class="bi bi-x"></i>
        </button>
        
        <!-- 検索ドロップダウン -->
        <div id="searchDropdown" class="search-dropdown"></div>
    </div>
    
    <!-- 選択中のフィルター -->
    <div id="selectedFilter" class="selected-filter">
        <span class="selected-filter-text"></span>
        <button id="removeFilter" class="remove-filter">
            <i class="bi bi-x-circle-fill"></i>
        </button>
    </div>
</div>

<div class="timeline-container">
    <!-- 投稿一覧 -->
    <div id="postsContainer"></div>

    <!-- ローディング -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
        <p>読み込み中...</p>
    </div>

    <!-- 無限スクロール用トリガー -->
    <div id="loadMoreTrigger" style="height: 0.0625rem;"></div>
</div>

<!-- 投稿作成エリア（下部固定） -->
<div class="post-composer">
    <div class="composer-header">
        <img src="../../img/icon/<?= htmlspecialchars($cast['img']) ?>" 
             alt="<?= htmlspecialchars($cast['name']) ?>" 
             class="composer-avatar">
        <div class="composer-input-area">
            <textarea id="postContent" 
                      class="composer-textarea" 
                      placeholder="今何してる？"
                      maxlength="500"></textarea>
        </div>
    </div>

    <!-- メディアプレビュー -->
    <div id="mediaPreview" class="media-preview-container hidden"></div>

    <!-- アクション -->
    <div class="composer-actions">
        <div>
            <input type="file" 
                   id="mediaInput" 
                   accept="image/jpeg,image/jpg,image/png,video/mp4" 
                   multiple 
                   class="hidden">
            <button class="media-upload-btn" onclick="document.getElementById('mediaInput').click()">
                <i class="bi bi-image"></i>
                <span class="media-count" id="mediaCount"></span>
            </button>
        </div>
        <button id="postSubmitBtn" class="post-submit-btn" disabled>投稿する</button>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>投稿を編集</h3>
            <button class="modal-close" onclick="castTimeline.closeEditModal()">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <div class="modal-body">
            <textarea id="editContent" 
                      class="composer-textarea" 
                      style="min-height: 8rem;"
                      maxlength="500"></textarea>
            <div class="composer-actions" style="margin-top: 1rem; border-top: none; padding-top: 0;">
                <button class="post-submit-btn" 
                        style="background: #6c757d;"
                        onclick="castTimeline.closeEditModal()">
                    キャンセル
                </button>
                <button id="editSubmitBtn" class="post-submit-btn">
                    更新する
                </button>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="cast_timeline.js"></script>

<?php require_once(dirname(__FILE__) . '/../web/footer.php'); ?>