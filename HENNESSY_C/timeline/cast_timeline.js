class CastTimeline {
    constructor() {
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.selectedFiles = [];
        this.editingPostId = null;
        this.filterGLoginId = null;
        this.filterName = null;
        
        // Pull-to-Refresh用
        this.startY = 0;
        this.currentY = 0;
        this.isPulling = false;
        this.refreshThreshold = 80;
        
        // 検索用
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.setupEventListeners();
                this.setupInfiniteScroll();
                this.setupPullToRefresh();
                this.loadPosts();
            });
        } else {
            this.setupEventListeners();
            this.setupInfiniteScroll();
            this.setupPullToRefresh();
            this.loadPosts();
        }
    }
    
    setupEventListeners() {
        // 投稿内容の入力監視
        const postContent = document.getElementById('postContent');
        if (postContent) {
            postContent.addEventListener('input', () => this.updateSubmitButton());
        }
        
        // メディア選択
        const mediaInput = document.getElementById('mediaInput');
        if (mediaInput) {
            mediaInput.addEventListener('change', (e) => this.handleMediaSelect(e));
        }
        
        // 投稿ボタン
        const postSubmitBtn = document.getElementById('postSubmitBtn');
        if (postSubmitBtn) {
            postSubmitBtn.addEventListener('click', () => this.submitPost());
        }
        
        // 編集モーダルの投稿ボタン
        const editSubmitBtn = document.getElementById('editSubmitBtn');
        if (editSubmitBtn) {
            editSubmitBtn.addEventListener('click', () => this.submitEdit());
        }
        
        // モーダル外クリックで閉じる
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('click', (e) => {
                if (e.target === editModal) {
                    this.closeEditModal();
                }
            });
        }
        
        // 検索入力
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearchInput(e));
            searchInput.addEventListener('focus', () => {
                if (searchInput.value) {
                    this.searchGirls(searchInput.value);
                }
            });
        }
        
        // クリアボタン
        const clearSearch = document.getElementById('clearSearch');
        if (clearSearch) {
            clearSearch.addEventListener('click', () => this.clearSearch());
        }
        
        // フィルター削除ボタン
        const removeFilter = document.getElementById('removeFilter');
        if (removeFilter) {
            removeFilter.addEventListener('click', () => this.removeFilter());
        }
        
        // ドロップダウン外クリックで閉じる
        document.addEventListener('click', (e) => {
            const searchContainer = document.querySelector('.search-container');
            const dropdown = document.getElementById('searchDropdown');
            if (dropdown && !searchContainer.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }
    
    setupInfiniteScroll() {
        const trigger = document.getElementById('loadMoreTrigger');
        if (!trigger) return;
        
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                this.loadPosts();
            }
        }, { threshold: 0.1 });
        
        observer.observe(trigger);
    }
    
    setupPullToRefresh() {
        const container = document.querySelector('.timeline-container');
        if (!container) return;
        
        container.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                this.startY = e.touches[0].pageY;
                this.isPulling = true;
            }
        }, { passive: true });
        
        container.addEventListener('touchmove', (e) => {
            if (!this.isPulling) return;
            
            this.currentY = e.touches[0].pageY;
            const pullDistance = this.currentY - this.startY;
            
            if (pullDistance > 0 && window.scrollY === 0) {
                const indicator = document.getElementById('refresh-indicator');
                if (indicator) {
                    indicator.style.height = Math.min(pullDistance, this.refreshThreshold) + 'px';
                    indicator.style.opacity = Math.min(pullDistance / this.refreshThreshold, 1);
                    
                    if (pullDistance >= this.refreshThreshold) {
                        indicator.innerHTML = '<i class="bi bi-arrow-down-circle-fill"></i> 離すと更新';
                    } else {
                        indicator.innerHTML = '<i class="bi bi-arrow-down-circle"></i> 引っ張って更新';
                    }
                }
            }
        }, { passive: true });
        
        container.addEventListener('touchend', () => {
            if (!this.isPulling) return;
            
            const pullDistance = this.currentY - this.startY;
            const indicator = document.getElementById('refresh-indicator');
            
            if (pullDistance >= this.refreshThreshold) {
                this.refresh();
            } else if (indicator) {
                indicator.style.height = '0';
                indicator.style.opacity = '0';
            }
            
            this.isPulling = false;
            this.startY = 0;
            this.currentY = 0;
        }, { passive: true });
    }
    
    async refresh() {
        const indicator = document.getElementById('refresh-indicator');
        if (indicator) {
            indicator.innerHTML = '<div class="spinner-border spinner-border-sm"></div> 更新中...';
        }
        
        this.offset = 0;
        this.hasMore = true;
        document.getElementById('postsContainer').innerHTML = '';
        
        await this.loadPosts();
        
        setTimeout(() => {
            if (indicator) {
                indicator.style.height = '0';
                indicator.style.opacity = '0';
            }
        }, 300);
    }
    
    handleSearchInput(e) {
        const query = e.target.value;
        const clearBtn = document.getElementById('clearSearch');
        
        // クリアボタンの表示/非表示
        if (query) {
            clearBtn.classList.add('show');
        } else {
            clearBtn.classList.remove('show');
            document.getElementById('searchDropdown').classList.remove('show');
        }
        
        // デバウンス処理
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            if (query) {
                this.searchGirls(query);
            }
        }, 300);
    }
    
    async searchGirls(query) {
        try {
            const response = await fetch(`cast_timeline_api.php?action=search_girls&query=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderSearchDropdown(data.girls);
            }
        } catch (error) {
            console.error('検索エラー:', error);
        }
    }
    
    renderSearchDropdown(girls) {
        const dropdown = document.getElementById('searchDropdown');
        if (!dropdown) return;
        
        dropdown.innerHTML = '';
        
        if (girls.length === 0) {
            dropdown.innerHTML = '<div class="no-results">該当する源氏名が見つかりません</div>';
            dropdown.classList.add('show');
            return;
        }
        
        // 「すべて表示」オプション
        const allItem = document.createElement('div');
        allItem.className = 'search-dropdown-item all-posts';
        allItem.innerHTML = '<i class="bi bi-grid-3x3"></i> <div class="dropdown-name">すべての投稿</div>';
        allItem.onclick = () => this.selectGirl(null, 'すべての投稿');
        dropdown.appendChild(allItem);
        
        // キャストリスト
        girls.forEach(girl => {
            const item = document.createElement('div');
            item.className = 'search-dropdown-item';
            
            const avatarImg = girl.img 
                ? `<img src="../../img/icon/${this.escapeHtml(girl.img)}" class="dropdown-avatar" alt="${this.escapeHtml(girl.name)}">`
                : '<div class="dropdown-avatar" style="background: #ddd;"></div>';
            
            item.innerHTML = `
                ${avatarImg}
                <div class="dropdown-name">${this.escapeHtml(girl.name)}</div>
            `;
            item.onclick = () => this.selectGirl(girl.g_login_id, girl.name);
            dropdown.appendChild(item);
        });
        
        dropdown.classList.add('show');
    }
    
    selectGirl(gLoginId, name) {
        this.filterGLoginId = gLoginId;
        this.filterName = name;
        
        // 検索バーをクリア
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // クリアボタンを隠す
        document.getElementById('clearSearch').classList.remove('show');
        
        // ドロップダウンを閉じる
        document.getElementById('searchDropdown').classList.remove('show');
        
        // フィルター表示
        const selectedFilter = document.getElementById('selectedFilter');
        const selectedFilterText = selectedFilter.querySelector('.selected-filter-text');
        if (selectedFilter && selectedFilterText) {
            if (gLoginId) {
                selectedFilterText.textContent = `絞り込み: ${name}`;
                selectedFilter.classList.add('show');
            } else {
                selectedFilter.classList.remove('show');
            }
        }
        
        // 投稿をリロード
        this.offset = 0;
        this.hasMore = true;
        document.getElementById('postsContainer').innerHTML = '';
        this.loadPosts();
    }
    
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        
        document.getElementById('clearSearch').classList.remove('show');
        document.getElementById('searchDropdown').classList.remove('show');
    }
    
    removeFilter() {
        this.filterGLoginId = null;
        this.filterName = null;
        
        document.getElementById('selectedFilter').classList.remove('show');
        
        // 投稿をリロード
        this.offset = 0;
        this.hasMore = true;
        document.getElementById('postsContainer').innerHTML = '';
        this.loadPosts();
    }
    
    updateSubmitButton() {
        const postContent = document.getElementById('postContent');
        const postSubmitBtn = document.getElementById('postSubmitBtn');
        
        if (postContent && postSubmitBtn) {
            const hasContent = postContent.value.trim().length > 0;
            const hasMedia = this.selectedFiles.length > 0;
            postSubmitBtn.disabled = !(hasContent || hasMedia);
        }
    }
    
    handleMediaSelect(e) {
        const files = Array.from(e.target.files);
        const maxFiles = 3;
        
        // 最大3枚まで
        if (this.selectedFiles.length + files.length > maxFiles) {
            alert(`画像は最大${maxFiles}枚までです`);
            return;
        }
        
        // ファイルサイズとタイプをチェック
        for (const file of files) {
            // 10MB制限
            if (file.size > 10 * 1024 * 1024) {
                alert('ファイルサイズは10MB以下にしてください');
                continue;
            }
            
            // 画像・動画のみ
            if (!file.type.match(/^(image\/(jpeg|jpg|png)|video\/mp4)$/)) {
                alert('JPG、PNG、MP4形式のファイルのみアップロード可能です');
                continue;
            }
            
            this.selectedFiles.push(file);
        }
        
        this.updateMediaPreview();
        this.updateSubmitButton();
        
        // inputをリセット
        e.target.value = '';
    }
    
    updateMediaPreview() {
        const previewContainer = document.getElementById('mediaPreview');
        const mediaCount = document.getElementById('mediaCount');
        
     if (!previewContainer) return;
        
        // プレビューをクリア
        previewContainer.innerHTML = '';
        
        if (this.selectedFiles.length === 0) {
            previewContainer.classList.add('hidden');
            if (mediaCount) mediaCount.textContent = '';
            return;
        }
        
        previewContainer.classList.remove('hidden');
        if (mediaCount) {
            mediaCount.textContent = `${this.selectedFiles.length}/3`;
        }
        
        this.selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'media-preview-item';
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'media-preview-remove';
            removeBtn.innerHTML = '<i class="bi bi-x"></i>';
            removeBtn.onclick = () => this.removeMedia(index);
            
            const reader = new FileReader();
            reader.onload = (e) => {
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    item.appendChild(img);
                } else if (file.type.startsWith('video/')) {
                    const video = document.createElement('video');
                    video.src = e.target.result;
                    item.appendChild(video);
                }
            };
            reader.readAsDataURL(file);
            
            item.appendChild(removeBtn);
            previewContainer.appendChild(item);
        });
    }
    
    removeMedia(index) {
        this.selectedFiles.splice(index, 1);
        this.updateMediaPreview();
        this.updateSubmitButton();
    }
    
    async submitPost() {
        const postContent = document.getElementById('postContent');
        const postSubmitBtn = document.getElementById('postSubmitBtn');
        
        if (!postContent) return;
        
        const content = postContent.value.trim();
        
        if (!content && this.selectedFiles.length === 0) {
            alert('内容を入力するか、画像を選択してください');
            return;
        }
        
        // ボタンを無効化
        postSubmitBtn.disabled = true;
        postSubmitBtn.textContent = '投稿中...';
        
        const formData = new FormData();
        formData.append('action', 'create_post');
        formData.append('content', content);
        
        // メディアファイルを追加
        this.selectedFiles.forEach((file) => {
            formData.append('media[]', file);
        });
        
        try {
            const response = await fetch('cast_timeline_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // フォームをリセット
                postContent.value = '';
                this.selectedFiles = [];
                this.updateMediaPreview();
                
                // 投稿一覧を再読み込み
                this.offset = 0;
                this.hasMore = true;
                document.getElementById('postsContainer').innerHTML = '';
                await this.loadPosts();
                
                // スクロールを一番上に
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                alert(data.error || '投稿に失敗しました');
                console.error('投稿エラー:', data);
            }
        } catch (error) {
            console.error('投稿エラー:', error);
            alert('投稿に失敗しました');
        } finally {
            postSubmitBtn.disabled = false;
            postSubmitBtn.textContent = '投稿する';
            this.updateSubmitButton();
        }
    }
    
    async loadPosts() {
        if (this.loading) return;
        
        this.loading = true;
        const loadingEl = document.getElementById('loading');
        if (loadingEl) loadingEl.style.display = 'block';
        
        const params = new URLSearchParams({
            action: 'get_posts',
            offset: this.offset
        });
        
        // フィルター適用
        if (this.filterGLoginId) {
            params.append('filter_g_login_id', this.filterGLoginId);
        }
        
        try {
            const response = await fetch(`cast_timeline_api.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.posts.length === 0 && this.offset === 0) {
                    this.showNoPosts();
                } else {
                    data.posts.forEach(post => this.renderPost(post));
                    this.offset += data.posts.length;
                    this.hasMore = data.has_more;
                }
            } else {
                console.error('投稿読み込みエラー:', data);
            }
        } catch (error) {
            console.error('投稿読み込みエラー:', error);
        } finally {
            this.loading = false;
            if (loadingEl) loadingEl.style.display = 'none';
        }
    }
    
    renderPost(post) {
        const container = document.getElementById('postsContainer');
        if (!container) return;
        
        const postEl = document.createElement('div');
        postEl.className = 'post-card';
        postEl.dataset.postId = post.id;
        
        // メディア表示
        let mediaHtml = '';
        if (post.media && post.media.length > 0) {
            const gridClass = post.media.length === 1 ? 'single' : 
                            post.media.length === 2 ? 'double' : 'triple';
            
            mediaHtml = `<div class="post-media"><div class="post-media-grid ${gridClass}">`;
            
            post.media.forEach(media => {
                const mediaPath = `../../img/timeline_uploads/${this.escapeHtml(media.file_path)}`;
                
                if (media.media_type === 'image') {
                    mediaHtml += `
                        <div class="post-media-item">
                            <img src="${mediaPath}" alt="投稿画像">
                        </div>
                    `;
                } else if (media.media_type === 'video') {
                    mediaHtml += `
                        <div class="post-media-item">
                            <video controls>
                                <source src="${mediaPath}" type="video/mp4">
                            </video>
                        </div>
                    `;
                }
            });
            
            mediaHtml += `</div></div>`;
        }
        
        // 自分の投稿の場合のみ編集・削除ボタンを表示
        let menuHtml = '';
        if (post.is_own_post) {
            menuHtml = `
                <div class="post-actions-menu">
                    <button class="post-menu-btn" onclick="castTimeline.toggleMenu(${post.id})">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <div class="post-menu-dropdown" id="menu-${post.id}">
                        <div class="post-menu-item" onclick="castTimeline.openEditModal(${post.id}, \`${this.escapeJs(post.content)}\`)">
                            <i class="bi bi-pencil"></i> 編集
                        </div>
                        <div class="post-menu-item delete" onclick="castTimeline.deletePost(${post.id})">
                            <i class="bi bi-trash"></i> 削除
                        </div>
                    </div>
                </div>
            `;
        }
        
        // アバター画像
        const avatarImg = post.girl_img 
            ? `<img src="../../img/icon/${this.escapeHtml(post.girl_img)}" class="post-avatar" alt="${this.escapeHtml(post.girl_name)}">`
            : '<div class="post-avatar" style="background: #ddd;"></div>';
        
        postEl.innerHTML = `
            <div class="post-header">
                <div class="post-user-info">
                    ${avatarImg}
                    <div class="post-details">
                        <div class="post-name">${this.escapeHtml(post.girl_name)}</div>
                        <div class="post-time">${this.escapeHtml(post.time_ago)}</div>
                    </div>
                </div>
                ${menuHtml}
            </div>
            <div class="post-content">${this.escapeHtml(post.content)}</div>
            ${mediaHtml}
            <div class="post-stats">
                <div class="post-stat">
                    <i class="bi bi-heart-fill"></i>
                    <span>${post.like_count}</span>
                </div>
            </div>
        `;
        
        container.appendChild(postEl);
    }
    
    toggleMenu(postId) {
        // 他のメニューを閉じる
        document.querySelectorAll('.post-menu-dropdown').forEach(menu => {
            if (menu.id !== `menu-${postId}`) {
                menu.classList.remove('show');
            }
        });
        
        // 対象のメニューをトグル
        const menu = document.getElementById(`menu-${postId}`);
        if (menu) {
            menu.classList.toggle('show');
        }
        
        // メニュー外クリックで閉じる
        if (menu && menu.classList.contains('show')) {
            const closeMenu = (e) => {
                if (!e.target.closest('.post-actions-menu')) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            };
            
            setTimeout(() => {
                document.addEventListener('click', closeMenu);
            }, 0);
        }
    }
    
    openEditModal(postId, content) {
        this.editingPostId = postId;
        
        const editContent = document.getElementById('editContent');
        const editModal = document.getElementById('editModal');
        
        if (editContent) {
            editContent.value = content;
        }
        
        if (editModal) {
            editModal.classList.add('show');
        }
        
        // メニューを閉じる
        const menu = document.getElementById(`menu-${postId}`);
        if (menu) {
            menu.classList.remove('show');
        }
    }
    
    closeEditModal() {
        const editModal = document.getElementById('editModal');
        const editContent = document.getElementById('editContent');
        
        if (editModal) {
            editModal.classList.remove('show');
        }
        
        if (editContent) {
            editContent.value = '';
        }
        
        this.editingPostId = null;
    }
    
    async submitEdit() {
        const editContent = document.getElementById('editContent');
        const editSubmitBtn = document.getElementById('editSubmitBtn');
        
        if (!editContent || !this.editingPostId) return;
        
        const content = editContent.value.trim();
        
        if (!content) {
            alert('内容を入力してください');
            return;
        }
        
        editSubmitBtn.disabled = true;
        editSubmitBtn.textContent = '更新中...';
        
        const formData = new FormData();
        formData.append('action', 'update_post');
        formData.append('post_id', this.editingPostId);
        formData.append('content', content);
        
        try {
            const response = await fetch('cast_timeline_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // 投稿を再読み込み
                this.offset = 0;
                this.hasMore = true;
                document.getElementById('postsContainer').innerHTML = '';
                await this.loadPosts();
                
                this.closeEditModal();
            } else {
                alert(data.error || '更新に失敗しました');
            }
        } catch (error) {
            console.error('更新エラー:', error);
            alert('更新に失敗しました');
        } finally {
            editSubmitBtn.disabled = false;
            editSubmitBtn.textContent = '更新する';
        }
    }
    
    async deletePost(postId) {
        if (!confirm('この投稿を削除してもよろしいですか？')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', postId);
        
        try {
            const response = await fetch('cast_timeline_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // 投稿要素を削除
                const postEl = document.querySelector(`[data-post-id="${postId}"]`);
                if (postEl) {
                    postEl.remove();
                }
                
                // 投稿がなくなった場合
                const container = document.getElementById('postsContainer');
                if (container && container.children.length === 0) {
                    this.showNoPosts();
                }
            } else {
                alert(data.error || '削除に失敗しました');
            }
        } catch (error) {
            console.error('削除エラー:', error);
            alert('削除に失敗しました');
        }
    }
    
    showNoPosts() {
        const container = document.getElementById('postsContainer');
        if (!container) return;
        
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-chat-quote"></i>
                <p>まだ投稿がありません</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">最初の投稿をしてみましょう！</p>
            </div>
        `;
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    escapeJs(text) {
        if (!text) return '';
        return text.replace(/`/g, '\\`').replace(/\$/g, '\\$').replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
    }
}

// インスタンス作成
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.castTimeline = new CastTimeline();
    });
} else {
    window.castTimeline = new CastTimeline();
}