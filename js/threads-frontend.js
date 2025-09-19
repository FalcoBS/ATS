/* assets/js/threads-frontend.js */

(function(){
  "use strict";

  // Utilità base
  const qs  = (s, r=document) => r.querySelector(s);
  const qsa = (s, r=document) => Array.from(r.querySelectorAll(s));
  const ce  = (t, props={}) => Object.assign(document.createElement(t), props);

  const state = {
    inflight: new Set(), // per evitare doppi click
    paging: {            // gestisce infinite scroll
      threads: { busy:false, done:false, page:2 },
      replies: {}        // mappa postId -> {busy, done, page}
    }
  };

  // Fetch con nonce e gestione errori
  async function postForm(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    fd.append('_ajax_nonce', ATS.nonce);

    let res;
    try {
      res = await fetch(ATS.ajaxUrl, { method:'POST', credentials:'same-origin', body:fd });
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
      const msg = json && json.data && json.data.message ? json.data.message : 'error';
      const code = json && json.data && json.data.code ? json.data.code : 'error';
      const err  = new Error(msg);
      err.code = code;
      throw err;
    }
    return json.data || {};
  }

  // Toast minimale
  function toast(msg) {
    let box = qs('.ats-toast');
    if (!box) {
      box = ce('div', { className:'ats-toast' });
      document.body.appendChild(box);
    }
    box.textContent = msg;
    box.classList.add('show');
    setTimeout(()=> box.classList.remove('show'), 1800);
  }

  // Guard login
  function requireLogin() {
    if (ATS.isLogged) return true;
    toast(ATS.texts.loginRequired);
    return false;
  }

  // Disabilita pulsante durante richiesta
  function lockButton(btn) {
    if (!btn) return () => {};
    const prev = { disabled: btn.disabled, text: btn.textContent };
    btn.disabled = true;
    btn.dataset.prevText = prev.text;
    btn.textContent = ATS.texts.loading;
    return () => {
      btn.disabled = prev.disabled;
      btn.textContent = prev.text;
      delete btn.dataset.prevText;
    };
  }

  // Voti thread
  async function handleThreadVote(e) {
    const btn = e.currentTarget;
    const postId = parseInt(btn.dataset.id,10);
    const delta  = parseInt(btn.dataset.delta,10);

    if (!requireLogin()) return;
    const key = `vote-thread-${postId}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);

    // UI ottimistica
    const scoreEl = qs('.ats-thread-actions .ats-score') || qs('.ats-score');
    let prevScore = scoreEl ? parseInt(scoreEl.textContent,10) || 0 : 0;
    if (scoreEl) scoreEl.textContent = String(prevScore + delta);

    try {
      const data = await postForm({
        action:'ats_vote',
        target:'post',
        post_id: postId,
        delta: delta
      });
      if (scoreEl && typeof data.score === 'number') scoreEl.textContent = String(data.score);
      toast(ATS.texts.saved);
    } catch(err) {
      // rollback
      if (scoreEl) scoreEl.textContent = String(prevScore);
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Voti commenti
  async function handleCommentVote(e) {
    const btn = e.currentTarget;
    const cid = parseInt(btn.dataset.cid,10);
    const delta = parseInt(btn.dataset.delta,10);
    if (!requireLogin()) return;

    const key = `vote-comment-${cid}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const wrap = btn.closest('.ft-comment');
    const scoreEl = wrap ? wrap.querySelector('.ft-score') : null;
    const unlock = lockButton(btn);

    let prevScore = scoreEl ? parseInt(scoreEl.textContent,10) || 0 : 0;
    if (scoreEl) scoreEl.textContent = String(prevScore + delta);

    try {
      const data = await postForm({
        action:'ats_vote',
        target:'comment',
        comment_id: cid,
        delta: delta
      });
      if (scoreEl && typeof data.score === 'number') scoreEl.textContent = String(data.score);
      toast(ATS.texts.saved);
    } catch(err) {
      if (scoreEl) scoreEl.textContent = String(prevScore);
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Follow utente
  async function handleFollowUser(e) {
    const btn = e.currentTarget;
    if (!requireLogin()) return;

    const uid = parseInt(btn.dataset.id,10);
    const following = btn.classList.contains('is-following');
    const actionName = following ? 'unfollow' : 'follow';

    const key = `follow-user-${uid}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);

    try {
      const data = await postForm({
        action:'ats_follow_user',
        user_id: uid,
        op: actionName
      });
      btn.classList.toggle('is-following', data.following === true);
      btn.textContent = data.following ? 'Segui già' : 'Segui';
      toast(ATS.texts.saved);
    } catch(err) {
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Follow thread
  async function handleFollowThread(e) {
    const btn = e.currentTarget;
    if (!requireLogin()) return;

    const pid = parseInt(btn.dataset.id,10);
    const following = btn.classList.contains('is-following');
    const actionName = following ? 'unfollow' : 'follow';

    const key = `follow-thread-${pid}`;
    if (state.inflight.has(key)) return;
    state.inflight.add(key);

    const unlock = lockButton(btn);

    try {
      const data = await postForm({
        action:'ats_follow_thread',
        post_id: pid,
        op: actionName
      });
      btn.classList.toggle('is-following', data.following === true);
      btn.textContent = data.following ? 'Segui già' : 'Segui';
      toast(ATS.texts.saved);
    } catch(err) {
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      unlock();
      state.inflight.delete(key);
    }
  }

  // Invio nuova risposta inline
  async function handleReplySubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    if (!requireLogin()) return;

    const btn = form.querySelector('button[type=submit]');
    const unlock = lockButton(btn);

    const postId = parseInt(form.dataset.postId,10);
    const parentId = parseInt(form.dataset.parentId || '0',10);
    const content = (form.querySelector('textarea[name=content]')?.value || '').trim();

    if (!content) {
      unlock();
      return;
    }

    try {
      const data = await postForm({
        action:'ats_new_reply',
        post_id: postId,
        parent_id: parentId,
        content: content
      });

      // Inserisci la risposta renderizzata dal server se presente, altrimenti crea markup base
      const list = qs('.comment-list') || qs('#comments') || qs('.ats-replies');
      if (data.html && list) {
        const tmp = ce('div');
        tmp.innerHTML = data.html;
        const node = tmp.firstElementChild;
        list.appendChild(node);
      } else if (list) {
        const item = ce('div', { className:'ft-comment' });
        const now = new Date().toLocaleString();
        item.innerHTML =
          `<div class="ft-comment__meta"><strong>Tu</strong> : <time>${now}</time></div>`+
          `<div class="ft-comment__body"></div>`+
          `<div class="ft-comment__actions">`+
            `<button class="ft-vote" data-cid="${data.comment_id}" data-delta="1">▲</button>`+
            `<span class="ft-score">0</span>`+
            `<button class="ft-vote" data-cid="${data.comment_id}" data-delta="-1">▼</button>`+
          `</div>`;
        item.querySelector('.ft-comment__body').textContent = content;
        list.appendChild(item);
      }

      // Pulisci form
      const ta = form.querySelector('textarea[name=content]');
      if (ta) ta.value = '';
      toast(ATS.texts.replyPosted);
    } catch(err) {
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      unlock();
    }
  }

  // Ordinamento e ricerca in archivio
  function handleArchiveControls() {
    const form = qs('.ats-filters');
    if (!form) return;

    form.addEventListener('submit', function(e){
      // lascia eseguire submit standard per query string pulita
    });

    // opzionale: submit automatico al cambio sort
    const sort = form.querySelector('select[name=sort]');
    if (sort) {
      sort.addEventListener('change', ()=> form.submit());
    }
  }

  // Infinite scroll per archivio threads
  async function handleInfiniteScroll() {
    const container = qs('.ats-thread-list');
    if (!container) return;

    const sentinel = ce('div', { className:'ats-sentinel' });
    container.after(sentinel);

    const observer = new IntersectionObserver(async entries => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        await loadMoreThreads(container);
      }
    }, { rootMargin:'400px 0px' });

    observer.observe(sentinel);
  }

  async function loadMoreThreads(container) {
    const meta = state.paging.threads;
    if (meta.busy || meta.done) return;

    meta.busy = true;

    try {
      const data = await postForm({
        action:'ats_load_more_threads',
        page: meta.page
      });

      if (!data || !data.html || !data.html.trim()) {
        state.paging.threads.done = true;
        return;
      }
      const tmp = ce('div'); tmp.innerHTML = data.html;
      tmp.childNodes.forEach(n => container.appendChild(n));
      meta.page++;
    } catch(err) {
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      meta.busy = false;
    }
  }

  // Carica altre risposte per un thread
  async function handleLoadMoreReplies(e) {
    const btn = e.currentTarget;
    const postId = parseInt(btn.dataset.postId,10);
    const list  = qs('.comment-list') || qs('#comments') || qs('.ats-replies');
    if (!postId || !list) return;

    const meta = state.paging.replies[postId] || { busy:false, done:false, page:2 };
    if (meta.busy || meta.done) return;
    state.paging.replies[postId] = meta;

    const unlock = lockButton(btn);
    meta.busy = true;

    try {
      const data = await postForm({
        action:'ats_load_more_replies',
        post_id: postId,
        page: meta.page
      });
      if (!data || !data.html || !data.html.trim()) {
        meta.done = true;
        btn.remove();
        return;
      }
      const tmp = ce('div'); tmp.innerHTML = data.html;
      tmp.childNodes.forEach(n => list.appendChild(n));
      meta.page++;
    } catch(err) {
      toast(err.code === 'network' ? ATS.texts.networkError : ATS.texts.error);
    } finally {
      meta.busy = false;
      unlock();
    }
  }

  // Deleghe eventi
  function bindEvents() {
    // Voti thread
    qsa('.ats-vote-thread').forEach(btn => {
      btn.addEventListener('click', handleThreadVote);
    });

    // Voti commenti
    document.addEventListener('click', function(e){
      const t = e.target.closest('.ft-vote');
      if (t) handleCommentVote({ currentTarget: t, target: t, preventDefault: ()=>{} });
    });

    // Follow utente
    qsa('.ats-follow-user').forEach(btn => {
      btn.addEventListener('click', handleFollowUser);
    });

    // Follow thread
    qsa('.ats-follow-thread').forEach(btn => {
      btn.addEventListener('click', handleFollowThread);
    });

    // Invio risposta
    qsa('.ats-reply-form').forEach(form => {
      form.addEventListener('submit', handleReplySubmit);
    });

    // Load more replies
    qsa('.ats-load-more-replies').forEach(btn => {
      btn.addEventListener('click', handleLoadMoreReplies);
    });
  }

  // Stili minimi per toast se non presenti
  function injectToastStyle() {
    if (qs('#ats-toast-style')) return;
    const style = ce('style', { id:'ats-toast-style' });
    style.textContent = `
      .ats-toast {
        position: fixed;
        left: 50%;
        bottom: 24px;
        transform: translateX(-50%) translateY(20px);
        background: var(--ats-bg, #1c1c1c);
        color: var(--ats-text, #fff);
        border: 1px solid var(--ats-border, #333);
        border-radius: 8px;
        padding: 10px 14px;
        opacity: 0;
        pointer-events: none;
        transition: all .25s ease;
        z-index: 9999;
      }
      .ats-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
        pointer-events: auto;
      }
    `;
    document.head.appendChild(style);
  }

  // Avvio
  function init() {
    injectToastStyle();
    bindEvents();
    handleArchiveControls();
    handleInfiniteScroll(); // se presente l’archivio
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
