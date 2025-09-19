/* Advanced Threads System - Frontend JavaScript */

(function(){
  "use strict";

  // Utility functions
  const qs  = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => Array.from(r.querySelectorAll(s));
  const ce  = (t, props={}) => Object.assign(document.createElement(t), props);

  // Check if atsConfig is available (localized from PHP)
  if (typeof atsConfig === 'undefined') {
    console.warn('ATS: atsConfig not found. Make sure wp_localize_script is called.');
    return;
  }

  const config = atsConfig;
  const state = {
    inflight: new Set(), // prevent double clicks
    paging: {            // manage infinite scroll
      threads: { busy:false, done:false, page:2 },
      replies: {}        // map postId -> {busy, done, page}
    }
  };

  // Fetch with nonce and error handling
  async function postForm(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    fd.append('_ajax_nonce', config.nonce);

    let res;
    try {
      res = await fetch(config.ajax_url, { 
        method: 'POST', 
        credentials: 'same-origin', 
        body: fd 
      });
    } catch(e) {
      throw new Error('network');
    }

    let json;
    try {
      json = await res.json();
    } catch(e) {
      throw new Error('parse');
    }

    if (!json || json.success !== true) {
      const msg = json && json.data ? json.data : config.strings.error_occurred;
      const err = new Error(msg);
      err.code = 'error';
      throw err;
    }
    return json.data || {};
  }

  // Toast notifications
  function toast(msg, type = 'info') {
    let container = qs('.ats-toast-container');
    if (!container) {
      container = ce('div', { className: 'ats-toast-container' });
      document.body.appendChild(container);
    }

    const toast = ce('div', { 
      className: `ats-toast ats-toast-${type}`,
      textContent: msg
    });
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto remove
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Check if user is logged in
  function requireLogin() {
    if (config.is_logged_in) return true;
    toast(config.strings.login_required, 'warning');
    return false;
  }

  // Lock button during request
  function lockButton(btn) {
    if (!btn) return () => {};
    
    const spinner = btn.querySelector('.loading-spinner');
    const prevDisabled = btn.disabled;
    
    btn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    
    return () => {
      btn.disabled = prevDisabled;
      if (spinner) spinner.style.display = 'none';
    };
  }

  // Thread voting
  async function handleThreadVote(e) {
    const btn = e.currentTarget;
    const threadId = parseInt(btn.closest('[data-thread-id]').dataset.threadId, 10);
    const voteType = btn.dataset.vote; // 'up' or 'down'

    if (!requireLogin()) return;
    
    const key = `vote-thread-${threadId}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);
    const votingContainer = btn.closest('.voting-buttons');
    const scoreEl = votingContainer.querySelector('.vote-score');
    
    // Optimistic UI update
    const prevScore = parseInt(scoreEl?.textContent || '0', 10);
    const isActive = btn.classList.contains('active');
    const delta = isActive ? 0 : (voteType === 'up' ? 1 : -1);
    
    if (scoreEl) scoreEl.textContent = String(prevScore + delta);

    try {
      const data = await postForm({
        action: 'ats_vote_thread',
        thread_id: threadId,
        vote_type: voteType
      });

      // Update UI with server response
      if (data.score !== undefined && scoreEl) {
        scoreEl.textContent = String(data.score);
      }
      
      // Update vote buttons state
      votingContainer.querySelectorAll('.vote-btn').forEach(b => {
        b.classList.remove('active');
      });
      
      if (data.user_vote) {
        const activeBtn = votingContainer.querySelector(`[data-vote="${data.user_vote}"]`);
        if (activeBtn) activeBtn.classList.add('active');
      }

      toast(config.strings.vote_recorded);
    } catch(err) {
      // Rollback UI
      if (scoreEl) scoreEl.textContent = String(prevScore);
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Reply voting
  async function handleReplyVote(e) {
    const btn = e.currentTarget;
    const replyId = parseInt(btn.closest('[data-reply-id]').dataset.replyId, 10);
    const voteType = btn.dataset.vote;

    if (!requireLogin()) return;
    
    const key = `vote-reply-${replyId}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);
    const votingContainer = btn.closest('.voting-buttons');
    const scoreEl = votingContainer.querySelector('.vote-score');
    
    const prevScore = parseInt(scoreEl?.textContent || '0', 10);
    const isActive = btn.classList.contains('active');
    const delta = isActive ? 0 : (voteType === 'up' ? 1 : -1);
    
    if (scoreEl) scoreEl.textContent = String(prevScore + delta);

    try {
      const data = await postForm({
        action: 'ats_vote_reply',
        reply_id: replyId,
        vote_type: voteType
      });

      if (data.score !== undefined && scoreEl) {
        scoreEl.textContent = String(data.score);
      }
      
      votingContainer.querySelectorAll('.vote-btn').forEach(b => {
        b.classList.remove('active');
      });
      
      if (data.user_vote) {
        const activeBtn = votingContainer.querySelector(`[data-vote="${data.user_vote}"]`);
        if (activeBtn) activeBtn.classList.add('active');
      }

      toast(config.strings.vote_recorded);
    } catch(err) {
      if (scoreEl) scoreEl.textContent = String(prevScore);
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Follow thread
  async function handleFollowThread(e) {
    const btn = e.currentTarget;
    const threadId = parseInt(btn.dataset.threadId, 10);
    
    if (!requireLogin()) return;
    
    const key = `follow-thread-${threadId}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);
    const isFollowing = btn.classList.contains('active');

    try {
      const data = await postForm({
        action: 'ats_follow_thread',
        thread_id: threadId,
        follow: !isFollowing
      });

      btn.classList.toggle('active', data.following);
      const span = btn.querySelector('span');
      if (span) {
        span.textContent = data.following ? config.strings.following : config.strings.follow;
      }

      toast(data.following ? config.strings.following : config.strings.unfollow);
    } catch(err) {
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Bookmark thread
  async function handleBookmarkThread(e) {
    const btn = e.currentTarget;
    const threadId = parseInt(btn.dataset.threadId, 10);
    
    if (!requireLogin()) return;
    
    const key = `bookmark-thread-${threadId}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);

    try {
      const data = await postForm({
        action: 'ats_bookmark_thread',
        thread_id: threadId
      });

      btn.classList.toggle('active', data.bookmarked);
      toast(data.bookmarked ? 'Thread bookmarked' : 'Bookmark removed');
    } catch(err) {
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Submit new reply
  async function handleReplySubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    
    if (!requireLogin()) return;

    const submitBtn = form.querySelector('button[type=submit]');
    const unlock = lockButton(submitBtn);

    const threadId = parseInt(form.dataset.threadId, 10);
    const contentField = form.querySelector('#reply-content');
    const content = contentField?.value?.trim() || '';

    if (!content) {
      unlock();
      toast('Please enter your reply', 'warning');
      return;
    }

    if (content.length > config.settings.max_content_length) {
      unlock();
      toast(`Reply is too long. Maximum ${config.settings.max_content_length} characters allowed.`, 'warning');
      return;
    }

    try {
      const data = await postForm({
        action: 'ats_add_reply',
        thread_id: threadId,
        content: content
      });

      // Add new reply to the list
      const repliesList = qs('#replies-list');
      if (data.html && repliesList) {
        const temp = ce('div');
        temp.innerHTML = data.html;
        const newReply = temp.firstElementChild;
        repliesList.appendChild(newReply);
      }

      // Clear form
      if (contentField) contentField.value = '';
      
      // Update reply count
      const replyCountEls = qsa('.stat-item.replies .stat-value');
      replyCountEls.forEach(el => {
        const count = parseInt(el.textContent, 10) || 0;
        el.textContent = String(count + 1);
      });

      toast(config.strings.reply_posted);
    } catch(err) {
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
    }
  }

  // Load more replies
  async function handleLoadMoreReplies(e) {
    const btn = e.currentTarget;
    const threadId = parseInt(btn.dataset.threadId, 10);
    const page = parseInt(btn.dataset.page, 10);
    
    const unlock = lockButton(btn);

    try {
      const data = await postForm({
        action: 'ats_load_more_replies',
        thread_id: threadId,
        page: page
      });

      if (data.html) {
        const repliesList = qs('#replies-list');
        if (repliesList) {
          const temp = ce('div');
          temp.innerHTML = data.html;
          Array.from(temp.children).forEach(child => {
            repliesList.appendChild(child);
          });
        }

        // Update button page number or remove if no more
        if (data.has_more) {
          btn.dataset.page = String(page + 1);
        } else {
          btn.remove();
        }
      } else {
        btn.remove();
      }
    } catch(err) {
      toast(err.message || config.strings.error_occurred, 'error');
    } finally {
      unlock();
    }
  }

  // Character counter for text areas
  function updateCharacterCounter(textarea) {
    const counter = qs('#char-count');
    if (!counter) return;
    
    const current = textarea.value.length;
    const max = config.settings.max_content_length;
    
    counter.textContent = String(current);
    counter.parentElement.classList.toggle('over-limit', current > max);
  }

  // Preview functionality
  function handlePreview(e) {
    const btn = e.currentTarget;
    const form = btn.closest('form');
    const previewDiv = qs('#reply-preview');
    const contentField = form.querySelector('#reply-content');
    
    if (!previewDiv || !contentField) return;

    const content = contentField.value.trim();
    if (!content) return;

    // Simple markdown-like conversion for preview
    let html = content
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/`(.*?)`/g, '<code>$1</code>')
      .replace(/\n/g, '<br>');

    previewDiv.querySelector('.preview-content').innerHTML = html;
    previewDiv.style.display = 'block';
    form.style.display = 'none';
  }

  function handleEditReply(e) {
    const btn = e.currentTarget;
    const previewDiv = qs('#reply-preview');
    const form = qs('#reply-form');
    
    if (previewDiv) previewDiv.style.display = 'none';
    if (form) form.style.display = 'block';
  }

  // Sorting replies
  function handleRepliesSort(e) {
    const select = e.currentTarget;
    const threadId = select.dataset.threadId;
    const sortOrder = select.value;
    
    // Reload replies with new sort order
    const repliesList = qs('#replies-list');
    if (!repliesList) return;

    const unlock = lockButton(select);

    postForm({
      action: 'ats_load_replies',
      thread_id: threadId,
      sort: sortOrder,
      page: 1
    }).then(data => {
      if (data.html) {
        repliesList.innerHTML = data.html;
      }
    }).catch(err => {
      toast(err.message || config.strings.error_occurred, 'error');
    }).finally(() => {
      unlock();
    });
  }

  // Share thread
  function handleShareThread(e) {
    const btn = e.currentTarget;
    const url = btn.dataset.url || window.location.href;
    const title = btn.dataset.title || document.title;

    if (navigator.share) {
      navigator.share({
        title: title,
        url: url
      }).catch(() => {
        // Fallback to clipboard
        copyToClipboard(url);
      });
    } else {
      copyToClipboard(url);
    }
  }

  function copyToClipboard(text) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        toast('Link copied to clipboard');
      });
    } else {
      // Fallback
      const textArea = ce('textarea', { value: text });
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      textArea.remove();
      toast('Link copied to clipboard');
    }
  }

  // Event delegation and binding
  function bindEvents() {
    // Thread voting
    document.addEventListener('click', function(e) {
      const voteBtn = e.target.closest('.vote-btn[data-vote]');
      if (voteBtn) {
        if (voteBtn.closest('.thread-voting')) {
          handleThreadVote({ currentTarget: voteBtn });
        } else if (voteBtn.closest('.reply-voting')) {
          handleReplyVote({ currentTarget: voteBtn });
        }
      }
    });

    // Follow thread
    document.addEventListener('click', function(e) {
      const followBtn = e.target.closest('.follow-thread');
      if (followBtn) handleFollowThread({ currentTarget: followBtn });
    });

    // Bookmark thread
    document.addEventListener('click', function(e) {
      const bookmarkBtn = e.target.closest('.bookmark-thread');
      if (bookmarkBtn) handleBookmarkThread({ currentTarget: bookmarkBtn });
    });

    // Share thread
    document.addEventListener('click', function(e) {
      const shareBtn = e.target.closest('.share-thread, .share-thread-btn');
      if (shareBtn) handleShareThread({ currentTarget: shareBtn });
    });

    // Load more replies
    document.addEventListener('click', function(e) {
      const loadMoreBtn = e.target.closest('.load-more-replies');
      if (loadMoreBtn) handleLoadMoreReplies({ currentTarget: loadMoreBtn });
    });

    // Preview and edit reply
    document.addEventListener('click', function(e) {
      if (e.target.matches('#preview-reply-btn')) {
        handlePreview({ currentTarget: e.target });
      } else if (e.target.matches('#edit-reply-btn')) {
        handleEditReply({ currentTarget: e.target });
      }
    });

    // Reply form submission
    const replyForm = qs('#reply-form');
    if (replyForm) {
      replyForm.addEventListener('submit', handleReplySubmit);
    }

    // Character counter
    const replyTextarea = qs('#reply-content');
    if (replyTextarea) {
      replyTextarea.addEventListener('input', function() {
        updateCharacterCounter(this);
      });
    }

    // Replies sorting
    const sortSelect = qs('#replies-sort-order');
    if (sortSelect) {
      sortSelect.addEventListener('change', handleRepliesSort);
    }

    // Formatting help toggle
    document.addEventListener('click', function(e) {
      const helpToggle = e.target.closest('.help-toggle');
      if (helpToggle) {
        const helpContent = helpToggle.parentElement.querySelector('.help-content');
        if (helpContent) {
          const isVisible = helpContent.style.display === 'block';
          helpContent.style.display = isVisible ? 'none' : 'block';
          helpToggle.setAttribute('aria-expanded', !isVisible);
        }
      }
    });

    // Close help when clicking outside
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.formatting-help')) {
        qsa('.help-content').forEach(el => {
          el.style.display = 'none';
          const toggle = el.parentElement.querySelector('.help-toggle');
          if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
      }
    });
  }

  // Inject toast styles
  function injectToastStyles() {
    if (qs('#ats-toast-styles')) return;
    
    const style = ce('style', { id: 'ats-toast-styles' });
    style.textContent = `
      .ats-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        pointer-events: none;
      }
      .ats-toast {
        background: #333;
        color: white;
        padding: 12px 16px;
        border-radius: 4px;
        margin-bottom: 8px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        pointer-events: auto;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      }
      .ats-toast.show {
        opacity: 1;
        transform: translateX(0);
      }
      .ats-toast-warning {
        background: #f39c12;
      }
      .ats-toast-error {
        background: #e74c3c;
      }
      .form-help .over-limit {
        color: #e74c3c;
      }
    `;
    document.head.appendChild(style);
  }

  // Initialize
  function init() {
    injectToastStyles();
    bindEvents();
    
    // Initialize character counter if textarea exists
    const textarea = qs('#reply-content');
    if (textarea) {
      updateCharacterCounter(textarea);
    }
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();