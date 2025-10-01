class Timeline {
    constructor() {
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.filter = 'all';
        this.gLoginId = null;
        
        // Pull-to-Refresh用
        this.startY = 0;
        this.currentY = 0;
        this.isPulling = false;
        this.refreshThreshold = 80;
        
        this.init();
    }
    
    init() {
        // フィルター変更イベント
        document.getElementById('filterType').addEventListener('change', (e) => {
            this.filter = e.target.value;
            
            if (this.filter === 'girl') {
                document.getElementById('girlSelectWrapper').style.display = 'block';
            } else {
                document.getElementById('girlSelectWrapper').style.display = 'none';
                this.resetAndLoad();
            }
        });
        
        document.getElementById('girlSelect').addEventListener('change', (e) => {
            this.gLoginId = e.target.value;
            if (this.gLoginId) {
                this.resetAndLoad();
            }
        });
        
        // 無限スクロール
        this.setupInfiniteScroll();
        
        // Pull-to-Refresh
        this.setupPullToRefresh();
        
        // 初回読み込み
        this.loadPosts();
    }
    
    setupInfiniteScroll() {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                this.loadPosts();
            }
        }, { threshold: 0.1 });
        
        observer.observe(document.getElementById('loadMoreTrigger'));
    }
    
    setupPullToRefresh() {
        const container = document.querySelector('.timeline-container');
        
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
        
        if (this.filter === 'girl' && this.gLoginId) {
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
        
        // アイコン画像（クリック可能）
        const avatarImg = post.girl_img 
            ? `<img src="../img/${this.escapeHtml(post.girl_img)}" class="post-avatar clickable" alt="${this.escapeHtml(post.girl_name)}" data-g-login-id="${this.escapeHtml(post.g_login_id)}">`
            : `<div class="post-avatar clickable" style="background: #ddd; display: flex; align-items: center; justify-content: center;" data-g-login-id="${this.escapeHtml(post.g_login_id)}">👤</div>`;
        
        let mediaHtml = '';
        if (post.media && post.media.length > 0) {
            const gridClass = post.media.length === 1 ? 'single' : 'multiple';
            mediaHtml = `<div class="post-media"><div class="media-grid ${gridClass}">`;
            
            post.media.forEach(media => {
                if (media.media_type === 'image') {
                    mediaHtml += `
                        <div class="media-item">
                            <img src="${this.escapeHtml(media.file_path)}" alt="投稿画像">
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
                <button class="like-btn ${likedClass}" onclick="timeline.toggleLike(${post.id}, this)">
                    <i class="bi ${likeIcon}"></i>
                    <span class="like-count">${post.like_count}</span>
                </button>
            </div>
        `;
        
        // クリックイベントを追加
        const clickableElements = postEl.querySelectorAll('.clickable');
        clickableElements.forEach(el => {
            el.addEventListener('click', () => {
                const gLoginId = el.dataset.gLoginId;
                if (gLoginId) {
                    window.location.href = `../web/girl_detail.php?g_login_id=${encodeURIComponent(gLoginId)}`;
                }
            });
        });
        
        container.appendChild(postEl);
    }
    
    async toggleLike(postId, button) {
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
                
                if (data.liked) {
                    button.classList.add('liked');
                    icon.className = 'bi bi-heart-fill';
                } else {
                    button.classList.remove('liked');
                    icon.className = 'bi bi-heart';
                }
                
                count.textContent = data.like_count;
            } else {
                console.error('いいねエラー:', data.error);
            }
        } catch (error) {
            console.error('いいねエラー:', error);
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

// 初期化
const timeline = new Timeline();