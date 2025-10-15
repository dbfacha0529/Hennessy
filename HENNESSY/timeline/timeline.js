class Timeline {
    constructor() {
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.filter = 'all';
        this.gLoginId = null;
        this.selectedGirlName = null;
        
        // Pull-to-Refresh用
        this.startY = 0;
        this.currentY = 0;
        this.isPulling = false;
        this.refreshThreshold = 80;
        
        // 検索用
        this.girlsList = window.girlsList || [];
        
        this.init();
    }
    
    init() {
        
        // URLパラメータを取得
        const urlParams = new URLSearchParams(window.location.search);
        const urlFilter = urlParams.get('filter');
        const urlGLoginId = urlParams.get('g_login_id');
        
        // URLパラメータがある場合は優先的に設定
        if (urlFilter && urlGLoginId) {
            this.filter = urlFilter;
            this.gLoginId = urlGLoginId;
            
            // 女の子の名前を取得
            const girl = this.girlsList.find(g => g.g_login_id === urlGLoginId);
            if (girl) {
                this.selectedGirlName = girl.name;
            }
        }
        
        // DOMが完全に読み込まれてから実行
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.setupEventListeners();
                this.setupUI();
                this.setupInfiniteScroll();
                this.setupPullToRefresh();
                this.loadPosts();
            });
        } else {
            this.setupEventListeners();
            this.setupUI();
            this.setupInfiniteScroll();
            this.setupPullToRefresh();
            this.loadPosts();
        }
    }
    
    setupEventListeners() {
        const filterType = document.getElementById('filterType');
        const searchInput = document.getElementById('girlSearch');
        const searchClearBtn = document.getElementById('searchClearBtn');
        const searchResults = document.getElementById('searchResults');
        
        // フィルター変更
        if (filterType) {
            filterType.addEventListener('change', (e) => {
                this.filter = e.target.value;
                
                // 検索が選択されていない場合のみリロード
                if (!this.gLoginId) {
                    this.resetAndLoad();
                } else {
                    // 検索が選択されている場合はフィルターと併用
                    this.resetAndLoad();
                }
            });
        }
        
        // 検索入力
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                this.filterGirls(query);
            });
            
            // フォーカス時に結果を表示
            searchInput.addEventListener('focus', (e) => {
                const query = e.target.value.trim();
                if (query) {
                    this.filterGirls(query);
                }
            });
        }
        
        // 検索クリアボタン
        if (searchClearBtn) {
            searchClearBtn.addEventListener('click', () => {
                this.clearSearch();
            });
        }
        
        // 検索結果外をクリックしたら閉じる
        document.addEventListener('click', (e) => {
            if (searchResults && !searchResults.contains(e.target) && 
                searchInput && !searchInput.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
    }
    
    setupUI() {
        const filterSelect = document.getElementById('filterType');
        const searchInput = document.getElementById('girlSearch');
        
        if (!filterSelect || !searchInput) return;
        
        // URLパラメータがある場合はUIに反映
        if (this.filter) {
            filterSelect.value = this.filter;
        }
        
        if (this.gLoginId && this.selectedGirlName) {
            this.showSelectedGirlTag(this.selectedGirlName);
        }
    }
    
    filterGirls(query) {
        const searchResults = document.getElementById('searchResults');
        if (!searchResults) return;
        
        if (!query) {
            searchResults.classList.remove('show');
            searchResults.innerHTML = '';
            return;
        }
        
        // 検索クエリに一致する女の子をフィルタリング
        const filtered = this.girlsList.filter(girl => 
            girl.name.toLowerCase().includes(query.toLowerCase())
        );
        
        if (filtered.length === 0) {
            searchResults.innerHTML = '<div class="no-results">該当する女の子が見つかりません</div>';
            searchResults.classList.add('show');
            return;
        }
        
        // 検索結果を表示
        searchResults.innerHTML = filtered.map(girl => {
            const isActive = girl.g_login_id === this.gLoginId ? 'active' : '';
            return `
                <div class="search-result-item ${isActive}" data-g-login-id="${this.escapeHtml(girl.g_login_id)}" data-name="${this.escapeHtml(girl.name)}">
                    ${this.escapeHtml(girl.name)}
                </div>
            `;
        }).join('');
        
        searchResults.classList.add('show');
        
        // 検索結果アイテムのクリックイベント
        searchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                const gLoginId = item.dataset.gLoginId;
                const name = item.dataset.name;
                this.selectGirl(gLoginId, name);
            });
        });
    }
    
    selectGirl(gLoginId, name) {
        this.gLoginId = gLoginId;
        this.selectedGirlName = name;
        
        // 検索結果を非表示
        const searchResults = document.getElementById('searchResults');
        if (searchResults) {
            searchResults.classList.remove('show');
        }
        
        // 検索入力をクリア
        const searchInput = document.getElementById('girlSearch');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // 選択タグを表示
        this.showSelectedGirlTag(name);
        
        // 投稿を再読み込み
        this.resetAndLoad();
    }
    
    showSelectedGirlTag(name) {
        const tagContainer = document.getElementById('selectedGirlTag');
        if (!tagContainer) return;
        
        tagContainer.innerHTML = `
            <div class="selected-girl-tag">
                <span>${this.escapeHtml(name)}</span>
                <button onclick="window.timeline.clearSearch()" title="クリア">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    }
    
    clearSearch() {
        this.gLoginId = null;
        this.selectedGirlName = null;
        
        // 検索入力をクリア
        const searchInput = document.getElementById('girlSearch');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // 検索結果を非表示
        const searchResults = document.getElementById('searchResults');
        if (searchResults) {
            searchResults.classList.remove('show');
            searchResults.innerHTML = '';
        }
        
        // 選択タグを削除
        const tagContainer = document.getElementById('selectedGirlTag');
        if (tagContainer) {
            tagContainer.innerHTML = '';
        }
        
        // 投稿を再読み込み
        this.resetAndLoad();
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
        });
        
        container.addEventListener('touchmove', (e) => {
            if (!this.isPulling) return;
            
            this.currentY = e.touches[0].pageY;
            const pullDistance = this.currentY - this.startY;
            
            if (pullDistance > 0 && window.scrollY === 0) {
                e.preventDefault();
                
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
        });
        
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
        });
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
    
    resetAndLoad() {
        this.offset = 0;
        this.hasMore = true;
        document.getElementById('postsContainer').innerHTML = '';
        this.loadPosts();
    }
    
    async loadPosts() {
        
        if (this.loading) return;
        
        this.loading = true;
        document.getElementById('loading').style.display = 'block';
        
        const params = new URLSearchParams({
            action: 'get_posts',
            offset: this.offset,
            filter: this.filter
        });
        
        // 女の子が選択されている場合
        if (this.gLoginId) {
            params.append('g_login_id', this.gLoginId);
        }
        
        try {
            const response = await fetch(`timeline_api.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.posts.length === 0 && this.offset === 0) {
                    this.showNoPosts();
                } else {
                    data.posts.forEach(post => this.renderPost(post));
                    this.offset += data.posts.length;
                    this.hasMore = data.has_more;
                }
            }
        } catch (error) {
            console.error('投稿読み込みエラー:', error);
        } finally {
            this.loading = false;
            document.getElementById('loading').style.display = 'none';
        }
    }
    
    renderPost(post) {
        const container = document.getElementById('postsContainer');
        const postEl = document.createElement('div');
        postEl.className = 'post-card';
        postEl.dataset.postId = post.id;
        
        const avatarImg = post.girl_img 
            ? `<img src="../../img/icon/${this.escapeHtml(post.girl_img)}" class="post-avatar clickable" alt="${this.escapeHtml(post.girl_name)}" data-g-login-id="${this.escapeHtml(post.g_login_id)}">`
            : `<div class="post-avatar clickable" style="background: #ddd; display: flex; align-items: center; justify-content: center;" data-g-login-id="${this.escapeHtml(post.g_login_id)}">👤</div>`;
        
        let mediaHtml = '';
        if (post.media && post.media.length > 0) {
            const gridClass = post.media.length === 1 ? 'single' : 'multiple';
            mediaHtml = `<div class="post-media"><div class="media-grid ${gridClass}">`;
            
            post.media.forEach(media => {
                if (media.media_type === 'image') {
                    mediaHtml += `
                        <div class="media-item">
                            <img src="../../img/timeline_uploads/${this.escapeHtml(media.file_path)}" alt="投稿画像">
                        </div>
                    `;
                } else if (media.media_type === 'video') {
                    mediaHtml += `
                        <div class="media-item">
                            <video controls>
                                <source src="${this.escapeHtml(media.file_path)}" type="video/mp4">
                            </video>
                        </div>
                    `;
                }
            });
            
            mediaHtml += `</div></div>`;
        }
        
        const likedClass = post.user_liked ? 'liked' : '';
        const likeIcon = post.user_liked ? 'bi-heart-fill' : 'bi-heart';
        
        postEl.innerHTML = `
            <div class="post-header">
                ${avatarImg}
                <div class="post-info">
                    <div class="post-name clickable" data-g-login-id="${this.escapeHtml(post.g_login_id)}">${this.escapeHtml(post.girl_name)}</div>
                    <div class="post-time">${this.escapeHtml(post.time_ago)}</div>
                </div>
            </div>
            <div class="post-content">${this.escapeHtml(post.content)}</div>
            ${mediaHtml}
            <div class="post-actions">
                <button class="like-btn ${likedClass}" data-post-id="${post.id}">
                    <i class="bi ${likeIcon}"></i>
                    <span class="like-count">${post.like_count}</span>
                </button>
            </div>
        `;
        
        // クリック可能な要素のイベントリスナー設定
        const clickableElements = postEl.querySelectorAll('.clickable');
        clickableElements.forEach(el => {
            el.addEventListener('click', () => {
                const gLoginId = el.dataset.gLoginId;
                if (gLoginId) {
                    window.location.href = `../web/girl_detail.php?g_login_id=${encodeURIComponent(gLoginId)}`;
                }
            });
        });
        
        // いいねボタンのイベントリスナー設定
        const likeBtn = postEl.querySelector('.like-btn');
        if (likeBtn) {
            likeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleLike(post.id, likeBtn);
            });
        }
        
        container.appendChild(postEl);
    }
    
    async toggleLike(postId, button) {
        // 連続クリック防止
        if (button.disabled) return;
        button.disabled = true;
        
        const formData = new FormData();
        formData.append('post_id', postId);
        
        try {
            const response = await fetch('./timeline_api.php?action=toggle_like', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const icon = button.querySelector('i');
                const count = button.querySelector('.like-count');
                
                // アニメーション用のクラスを追加
                button.classList.add('like-animating');
                
                if (data.liked) {
                    button.classList.add('liked');
                    icon.className = 'bi bi-heart-fill';
                } else {
                    button.classList.remove('liked');
                    icon.className = 'bi bi-heart';
                }
                
                count.textContent = data.like_count;
                
                // アニメーション終了後にクラスを削除
                setTimeout(() => {
                    button.classList.remove('like-animating');
                }, 300);
            }
        } catch (error) {
            console.error('いいねエラー:', error);
        } finally {
            // ボタンを再度有効化
            button.disabled = false;
        }
    }
    
    showNoPosts() {
        const container = document.getElementById('postsContainer');
        container.innerHTML = `
            <div class="no-posts">
                <p>投稿がまだありません</p>
            </div>
        `;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.timeline = new Timeline();
    });
} else {
    window.timeline = new Timeline();
}