(() => {
  const VF_BASE = (document.querySelector('meta[name="vf-base"]')?.getAttribute('content') || '').replace(/\/+$/, '');
  const vfUrl = (path) => {
    if (!path || !path.startsWith('/') || path.startsWith('//')) return path;
    if (VF_BASE && (path === VF_BASE || path.startsWith(VF_BASE + '/'))) return path;
    return VF_BASE + path;
  };

  // Natychmiastowy pasek postępu ładowania przy przejściach i wysyłaniu formularzy
  const navLoader = document.querySelector('[data-vf-nav-loader]');
  const startNavLoader = () => {
    if (!navLoader) return;
    navLoader.classList.add('is-loading');
  };
  const stopNavLoader = () => {
    if (!navLoader) return;
    navLoader.classList.remove('is-loading');
  };
  window.addEventListener('pageshow', stopNavLoader);
  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    const anchor = e.target?.closest?.('a[href]');
    if (!anchor) return;
    if (anchor.hasAttribute('data-vf-tab-target') || anchor.hasAttribute('data-vf-tab-jump')) return;
    if (anchor.target && anchor.target !== '_self') return;
    if (anchor.hasAttribute('download')) return;
    const href = anchor.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
    try {
      const targetUrl = new URL(anchor.href, window.location.origin);
      if (targetUrl.origin !== window.location.origin) return;
      if (targetUrl.pathname + targetUrl.search === window.location.pathname + window.location.search && targetUrl.hash) return;
      startNavLoader();
    } catch {}
  });
  document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const f = e.target;
    if (!f || f.hasAttribute('data-vf-react-form') || f.hasAttribute('data-vf-pm-reply-form')) return;
    startNavLoader();
  });

  // Graceful fallback dla uszkodzonych avatarów na inicjały użytkownika
  document.addEventListener(
    'error',
    (e) => {
      const target = e.target;
      if (!(target instanceof HTMLImageElement)) return;
      const initials = target.getAttribute('data-vf-initials');
      if (!initials || !target.parentElement) return;
      const fallback = document.createTextNode(initials);
      target.replaceWith(fallback);
    },
    true
  );

  // Przełącznik motywu jasny / ciemny (localStorage + wszystkie przyciski w tym w szufladzie mobilnej)
  const themeBtns = Array.from(document.querySelectorAll('[data-vf-theme-toggle]'));
  const moonSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
  const sunSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
  const applyThemeIcon = () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    themeBtns.forEach((btn) => {
      btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      if (btn.classList.contains('vf-mobile-sheet-action')) {
        btn.textContent = isDark ? 'Tryb jasny' : 'Tryb ciemny';
      } else {
        btn.innerHTML = isDark ? sunSvg : moonSvg;
      }
    });
  };
  applyThemeIcon();
  themeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('vf-theme', 'light'); } catch {}
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        try { localStorage.setItem('vf-theme', 'dark'); } catch {}
      }
      applyThemeIcon();
    });
  });

  // Mobilne menu nawigacji (hamburger) + wysuwana szuflada "Więcej" z dolnego paska
  const mobileToggle = document.querySelector('[data-vf-mobile-toggle]');
  const mainNav = document.querySelector('[data-vf-nav]');
  if (mobileToggle && mainNav) {
    mobileToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = !mainNav.classList.contains('is-open');
      mainNav.classList.toggle('is-open', willOpen);
      mobileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    document.addEventListener('click', (e) => {
      if (!mainNav.contains(e.target) && !mobileToggle.contains(e.target)) {
        mainNav.classList.remove('is-open');
        mobileToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  const sheetBackdrop = document.querySelector('[data-vf-mobile-sheet-backdrop]');
  const sheetToggles = document.querySelectorAll('[data-vf-mobile-sheet-toggle]');
  const sheetClosers = document.querySelectorAll('[data-vf-mobile-sheet-close]');
  const closeMobileSheet = () => {
    if (!sheetBackdrop) return;
    sheetBackdrop.hidden = true;
    sheetToggles.forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
  };
  if (sheetBackdrop) {
    sheetToggles.forEach((btn) => {
      btn.addEventListener('click', () => {
        const willOpen = sheetBackdrop.hidden;
        sheetBackdrop.hidden = !willOpen;
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });
    sheetClosers.forEach((btn) => btn.addEventListener('click', closeMobileSheet));
    sheetBackdrop.addEventListener('click', (e) => {
      if (e.target === sheetBackdrop) closeMobileSheet();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !sheetBackdrop.hidden) closeMobileSheet();
    });
  }

  // Popup / Dropdown Powiadomień w nagłówku
  const notifTrigger = document.querySelector('[data-vf-notif-trigger]');
  const notifPopover = document.querySelector('[data-vf-notif-popover]');
  if (notifTrigger && notifPopover) {
    notifTrigger.addEventListener('click', async (e) => {
      e.preventDefault();
      const isOpen = notifPopover.style.display === 'block';
      if (isOpen) {
        notifPopover.style.display = 'none';
        return;
      }
      notifPopover.style.display = 'block';
      const listEl = notifPopover.querySelector('[data-vf-notif-list]');
      if (!listEl) return;
      listEl.innerHTML = '<div style="padding:14px;color:var(--muted);font-size:0.8125rem;">Ładowanie...</div>';
      try {
        const res = await fetch(vfUrl('/api/notifications'), { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.items || data.items.length === 0) {
          listEl.innerHTML = '<div style="padding:14px;color:var(--muted);font-size:0.8125rem;">Brak powiadomień.</div>';
          return;
        }
        listEl.innerHTML = '';
        data.items.slice(0, 8).forEach((item) => {
          const row = document.createElement('a');
          row.href = item.topic_id ? vfUrl(`/topic/${item.topic_id}#post-${item.post_id || ''}`) : vfUrl('/notifications');
          row.style.cssText =
            'display:block;padding:10px 14px;border-bottom:1px solid var(--border);color:var(--ink);font-size:0.8125rem;background:' +
            (Number(item.is_read) === 0 ? 'var(--accent-soft)' : 'var(--surface)');
          row.textContent = item.message;
          listEl.appendChild(row);
        });
      } catch {
        window.location.href = vfUrl('/notifications');
      }
    });

    document.addEventListener('click', (e) => {
      if (!notifPopover.contains(e.target) && !notifTrigger.contains(e.target)) {
        notifPopover.style.display = 'none';
      }
    });
  }

  // Paleta szybkiego przejścia (Ctrl+K / ⌘K)
  const quickModal = document.querySelector('[data-vf-quick-modal]');
  const quickInput = document.querySelector('[data-vf-quick-input]');
  const quickResults = document.querySelector('[data-vf-quick-results]');
  const quickTriggers = document.querySelectorAll('[data-vf-quick-jump]');
  const quickClose = document.querySelector('[data-vf-quick-close]');
  let quickTimer = null;

  const esc = (s) =>
    String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  async function loadQuickResults(query) {
    if (!quickResults) return;
    try {
      const res = await fetch(vfUrl(`/api/quick-search?q=${encodeURIComponent(query)}`), {
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      const topics = data.topics || [];
      const forums = data.forums || [];
      const members = data.members || [];
      if (topics.length === 0 && forums.length === 0 && members.length === 0) {
        quickResults.innerHTML = '<div class="vf-muted" style="padding:18px;text-align:center;">Brak pasujących wyników.</div>';
        return;
      }
      let html = '';
      if (topics.length > 0) {
        html += '<div class="vf-quick-group"><div class="vf-quick-group-title">Tematy</div>';
        topics.forEach((t) => {
          html += `<a class="vf-quick-item" href="${vfUrl(`/topic/${Number(t.id)}-${esc(t.slug)}`)}"><span><strong>${esc(t.title)}</strong><br><span class="vf-muted">${esc(t.forum_name)} · ${esc(t.author_name)}</span></span><span class="vf-muted">${Math.max(0, Number(t.posts_count) - 1)} odp.</span></a>`;
        });
        html += '</div>';
      }
      if (forums.length > 0) {
        html += '<div class="vf-quick-group"><div class="vf-quick-group-title">Działy</div>';
        forums.forEach((f) => {
          html += `<a class="vf-quick-item" href="${vfUrl(`/forum/${Number(f.id)}-${esc(f.slug)}`)}"><span>${esc(f.name)}</span><span class="vf-muted">${Number(f.topics_count)} tematów</span></a>`;
        });
        html += '</div>';
      }
      if (members.length > 0) {
        html += '<div class="vf-quick-group"><div class="vf-quick-group-title">Użytkownicy</div>';
        members.forEach((m) => {
          html += `<a class="vf-quick-item" href="${vfUrl(`/member/${Number(m.id)}-${esc(m.slug)}`)}"><span><strong>${esc(m.username)}</strong></span><span class="vf-muted">${esc(m.group_name)}</span></a>`;
        });
        html += '</div>';
      }
      quickResults.innerHTML = html;
    } catch {
      quickResults.innerHTML = '<div class="vf-muted" style="padding:18px;text-align:center;">Nie udało się pobrać wyników.</div>';
    }
  }

  function openQuickModal() {
    if (!quickModal) return;
    quickModal.hidden = false;
    if (quickInput) {
      quickInput.value = '';
      setTimeout(() => quickInput.focus(), 20);
    }
    loadQuickResults('');
  }
  function closeQuickModal() {
    if (quickModal) quickModal.hidden = true;
  }

  if (quickModal) {
    quickTriggers.forEach((btn) => btn.addEventListener('click', openQuickModal));
    if (quickClose) quickClose.addEventListener('click', closeQuickModal);
    quickModal.addEventListener('click', (e) => {
      if (e.target === quickModal) closeQuickModal();
    });
    if (quickInput) {
      quickInput.addEventListener('input', () => {
        clearTimeout(quickTimer);
        quickTimer = setTimeout(() => loadQuickResults(quickInput.value.trim()), 180);
      });
    }
    window.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        quickModal.hidden ? openQuickModal() : closeQuickModal();
      } else if (e.key === 'Escape' && !quickModal.hidden) {
        closeQuickModal();
      }
    });
  }

  // Pasek postępu czytania wątku oraz przycisk "Do góry" na każdej długiej stronie
  const progressWrap = document.querySelector('[data-vf-read-progress]');
  const progressBar = progressWrap ? progressWrap.querySelector('.vf-read-progress-bar') : null;
  const scrollTopBtn = document.querySelector('[data-vf-scroll-top]');
  const hasThreadPosts = document.querySelectorAll('.vf-post').length > 0;
  if (hasThreadPosts && progressWrap) {
    progressWrap.classList.add('is-active');
  }
  if (scrollTopBtn || (hasThreadPosts && progressWrap)) {
    const updateScrollUi = () => {
      const doc = document.documentElement;
      const y = window.scrollY || doc.scrollTop || 0;
      if (hasThreadPosts && progressBar) {
        const scrollable = Math.max(1, doc.scrollHeight - window.innerHeight);
        const ratio = Math.min(1, Math.max(0, y / scrollable));
        progressBar.style.transform = `scaleX(${ratio.toFixed(4)})`;
      }
      if (scrollTopBtn) {
        scrollTopBtn.hidden = y <= 320;
      }
    };
    window.addEventListener('scroll', updateScrollUi, { passive: true });
    updateScrollUi();
    if (scrollTopBtn) {
      scrollTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
  }

  // Reakcje w stylu Facebooka: jeden przycisk Emoji + wyskakujący pasek wyboru opcji (bez toastów reputacji)
  function closeAllReactionPickers(exceptWidget) {
    document.querySelectorAll('[data-vf-react-widget]').forEach((widget) => {
      if (widget === exceptWidget) return;
      widget.classList.remove('is-open');
      const trig = widget.querySelector('[data-vf-react-trigger]');
      if (trig) trig.setAttribute('aria-expanded', 'false');
    });
  }

  document.querySelectorAll('[data-vf-react-widget]').forEach((widget) => {
    const trigger = widget.querySelector('[data-vf-react-trigger]');
    const popover = widget.querySelector('[data-vf-react-popover]');
    if (!trigger || !popover) return;

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const willOpen = !widget.classList.contains('is-open');
      closeAllReactionPickers(widget);
      widget.classList.toggle('is-open', willOpen);
      trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    widget.querySelectorAll('form[data-vf-react-form]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
          const res = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new FormData(form),
          });
          if (!res.ok) {
            form.submit();
            return;
          }
          const data = await res.json();
          const userReaction = data.user_reaction ? Number(data.user_reaction) : null;
          const counts = data.counts || {};

          const options = Array.from(widget.querySelectorAll('[data-reaction-id]'));
          let activeBtn = null;
          let total = 0;
          const usedEmojis = [];

          options.forEach((opt) => {
            const rid = Number(opt.getAttribute('data-reaction-id'));
            const cnt = Number(counts[rid] || counts[String(rid)] || 0);
            total += cnt;
            if (cnt > 0) {
              usedEmojis.push(opt.getAttribute('data-reaction-emoji') || '👍');
            }
            const isMine = userReaction === rid;
            opt.classList.toggle('is-selected', isMine);
            if (isMine) activeBtn = opt;
          });

          const emojiSpan = trigger.querySelector('[data-vf-react-emoji]');
          const labelSpan = trigger.querySelector('[data-vf-react-label]');
          if (activeBtn) {
            trigger.classList.add('is-active');
            if (emojiSpan) emojiSpan.textContent = activeBtn.getAttribute('data-reaction-emoji') || '👍';
            if (labelSpan) labelSpan.textContent = activeBtn.getAttribute('data-reaction-label') || 'Lubię to';
          } else {
            trigger.classList.remove('is-active');
            if (emojiSpan) emojiSpan.textContent = '👍';
            if (labelSpan) labelSpan.textContent = 'Lubię to';
          }

          const summary = widget.querySelector('[data-vf-react-summary]');
          const summaryIcons = widget.querySelector('[data-vf-react-summary-icons]');
          const summaryCount = widget.querySelector('[data-vf-react-summary-count]');
          if (summary && summaryIcons && summaryCount) {
            if (total > 0) {
              summary.style.display = 'inline-flex';
              summaryIcons.textContent = usedEmojis.slice(0, 3).join(' ');
              summaryCount.textContent = String(total);
            } else {
              summary.style.display = 'none';
            }
          }

          widget.classList.remove('is-open');
          trigger.setAttribute('aria-expanded', 'false');
        } catch {
          form.submit();
        }
      });
    });
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-vf-react-widget]')) {
      closeAllReactionPickers(null);
    }
    if (!e.target.closest('[data-vf-react-summary]') && !e.target.closest('[data-vf-reactors-popover]')) {
      document.querySelectorAll('[data-vf-reactors-popover].is-open').forEach((el) => el.classList.remove('is-open'));
    }
  });

  // Podgląd "Kto zareagował" po kliknięciu licznika reakcji
  const emojiByCode = { like: '👍', thanks: '❤️', helpful: '🙌', insightful: '💡' };
  document.querySelectorAll('[data-vf-react-summary]').forEach((summaryBtn) => {
    summaryBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const pid = summaryBtn.getAttribute('data-post-id');
      const pop = document.querySelector(`[data-vf-reactors-popover="${pid}"]`);
      if (!pid || !pop) return;
      const willOpen = !pop.classList.contains('is-open');
      document.querySelectorAll('[data-vf-reactors-popover].is-open').forEach((el) => el.classList.remove('is-open'));
      if (!willOpen) return;
      pop.classList.add('is-open');
      pop.innerHTML = '<div style="padding:10px 12px;font-size:0.75rem;color:var(--muted);">Ładowanie...</div>';
      try {
        const res = await fetch(vfUrl(`/post/${pid}/reactors`), { headers: { Accept: 'application/json' } });
        const data = await res.json();
        const items = data.items || [];
        if (items.length === 0) {
          pop.innerHTML = '<div style="padding:10px 12px;font-size:0.75rem;color:var(--muted);">Brak reakcji.</div>';
          return;
        }
        pop.innerHTML = '';
        items.forEach((item) => {
          const row = document.createElement('a');
          row.href = vfUrl(`/member/${item.member_id}-${encodeURIComponent(item.member_slug || 'profil')}`);
          row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid var(--border);font-size:0.8125rem;color:var(--ink);';
          const nameSpan = document.createElement('span');
          nameSpan.textContent = item.username;
          nameSpan.style.fontWeight = '700';
          if (/^#[0-9a-fA-F]{3,8}$/.test(String(item.group_color || ''))) {
            nameSpan.style.color = item.group_color;
          }
          if (item.group_name) {
            nameSpan.title = `Grupa: ${item.group_name}`;
          }
          const emSpan = document.createElement('span');
          emSpan.textContent = emojiByCode[item.code] || '👍';
          emSpan.title = item.label || '';
          row.appendChild(nameSpan);
          row.appendChild(emSpan);
          pop.appendChild(row);
        });
      } catch {
        pop.innerHTML = '<div style="padding:10px 12px;font-size:0.75rem;color:#dc2626;">Nie udało się pobrać listy.</div>';
      }
    });
  });

  // Przycisk "Kopiuj" na blokach kodu <pre> w postach
  document.querySelectorAll('.vf-post-content pre').forEach((pre) => {
    if (!pre.parentElement || pre.parentElement.classList.contains('vf-code-wrap')) return;
    const wrap = document.createElement('div');
    wrap.className = 'vf-code-wrap';
    pre.parentNode.insertBefore(wrap, pre);
    wrap.appendChild(pre);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'vf-code-copy';
    btn.textContent = 'Kopiuj';
    btn.addEventListener('click', () => {
      navigator.clipboard?.writeText(pre.textContent || '').catch(() => {});
      btn.textContent = 'Skopiowano';
      showToast('Skopiowano fragment kodu.');
      setTimeout(() => { btn.textContent = 'Kopiuj'; }, 1600);
    });
    wrap.appendChild(btn);
  });

  // Nowoczesny edytor blokowy WYSIWYG + Slash Commands (/) + Wzmianki (@) + Link/Emoji/Klipy/Podgląd + Autozapis szkicu
  const slashCommands = [
    { label: 'Nagłówek sekcji', hint: 'H3', cmd: 'formatBlock', val: 'h3' },
    { label: 'Lista punktowana', hint: 'UL', cmd: 'insertUnorderedList', val: null },
    { label: 'Lista numerowana', hint: 'OL', cmd: 'insertOrderedList', val: null },
    { label: 'Cytat blokowy', hint: 'Quote', cmd: 'formatBlock', val: 'blockquote' },
    { label: 'Blok kodu', hint: 'Pre', cmd: 'insertHTML', val: '<pre><code>// kod tutaj</code></pre><p><br></p>' },
    { label: 'Wyróżnienie (Callout)', hint: 'Info', cmd: 'insertHTML', val: '<div class="vf-callout">Ważna wskazówka dla społeczności...</div><p><br></p>' },
    { label: 'Spoiler / Sekcja rozwijana', hint: 'Details', cmd: 'insertHTML', val: '<details><summary>Ukryta treść (Spoiler)</summary><p>Wpisz szczegóły...</p></details><p><br></p>' },
    { label: 'Klipy GIF / Klip wideo', hint: 'Klipy.com', cmd: 'klipy', val: null },
    { label: 'Linia podziału', hint: 'HR', cmd: 'insertHorizontalRule', val: null },
  ];

  const quickEmojis = ['👍', '❤️', '😂', '🔥', '🙌', '🎉', '💡', '👀', '🤔', '👏', '🚀', '✅', '⚡', '🎮', '🛠️', '😎'];

  const buildKlipyFigureHtml = (rawUrl, captionText) => {
    let url;
    try {
      url = new URL(rawUrl.trim());
    } catch {
      return null;
    }
    if (!['https:', 'http:'].includes(url.protocol)) return null;
    const host = url.hostname.toLowerCase();
    const isKlipyHost = host === 'klipy.com' || host.endsWith('.klipy.com');
    if (!isKlipyHost) return null;

    const cleanUrl = url.toString();
    const path = url.pathname.toLowerCase();
    const title = esc((captionText || 'Klip z Klipy.com').slice(0, 80));

    if (/\.(mp4|webm)$/i.test(path)) {
      return `<figure class="vf-klipy-embed"><video autoplay loop muted playsinline controls src="${esc(cleanUrl)}"></video><figcaption class="vf-klipy-caption"><span>${title}</span><span>Klipy.com</span></figcaption></figure><p><br></p>`;
    }
    if (/\.(gif|webp|png|jpe?g)$/i.test(path) || host.startsWith('static')) {
      return `<figure class="vf-klipy-embed"><img src="${esc(cleanUrl)}" alt="${title}" loading="lazy" /><figcaption class="vf-klipy-caption"><span>${title}</span><span>Klipy.com</span></figcaption></figure><p><br></p>`;
    }
    const embedSrc = path.startsWith('/embed/')
      ? cleanUrl
      : `https://klipy.com/embed${url.pathname.startsWith('/') ? url.pathname : '/' + url.pathname}`;
    return `<figure class="vf-klipy-embed"><iframe src="${esc(embedSrc)}" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin"></iframe><figcaption class="vf-klipy-caption"><span>${title}</span><a href="${esc(cleanUrl)}" target="_blank" rel="noopener noreferrer">Klipy.com</a></figcaption></figure><p><br></p>`;
  };

  const edSvg = (pathD, w = 15) =>
    `<svg width="${w}" height="${w}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${pathD}</svg>`;

  const editorIcons = {
    h3: edSvg('<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17.5 10.5c1.7-1 3.5 0 3.5 1.5a2 2 0 0 1-2 2"/><path d="M17 17.5c2 1.5 4 .3 4-1.5a2 2 0 0 0-2-2"/>'),
    bold: edSvg('<path d="M14 12a4 4 0 0 0 0-8H6v8"/><path d="M15 20a4 4 0 0 0 0-8H6v8Z"/>'),
    italic: edSvg('<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'),
    underline: edSvg('<path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" x2="20" y1="20" y2="20"/>'),
    strike: edSvg('<path d="M16 4H9a3 3 0 0 0-2.83 4"/><path d="M14 12a4 4 0 0 1 0 8H6"/><line x1="4" x2="20" y1="12" y2="12"/>'),
    ul: edSvg('<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>'),
    ol: edSvg('<line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'),
    quote: edSvg('<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>'),
    code: edSvg('<path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/>'),
    callout: edSvg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/>'),
    spoiler: edSvg('<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>'),
    hr: edSvg('<path d="M5 12h14"/>'),
    link: edSvg('<path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/>'),
    emoji: edSvg('<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/>'),
    klipy: edSvg('<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h18"/><path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 16.5h4"/>'),
    mention: edSvg('<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>'),
    blocks: edSvg('<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>', 13),
    clearFmt: edSvg('<path d="M4 7V4h16v3"/><path d="M5 20h6"/><path d="M13 4 8 20"/><path d="m15 15 5 5"/><path d="m20 15-5 5"/>'),
    pen: edSvg('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>', 12),
    eye: edSvg('<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>', 12),
  };

  const editors = new Map();

  document.querySelectorAll('textarea[data-vf-wysiwyg]').forEach((textarea) => {
    const form = textarea.closest('form');
    const draftKey =
      'vf-php-draft-' +
      window.location.pathname +
      '-' +
      (textarea.hasAttribute('data-vf-reply-editor') ? 'reply' : form?.getAttribute('action') || 'post');

    const wrap = document.createElement('div');
    wrap.className = 'vf-editor';

    const toolbar = document.createElement('div');
    toolbar.className = 'vf-editor-toolbar';

    const toolbarTools = document.createElement('div');
    toolbarTools.className = 'vf-editor-tools';

    const toolbarGroups = [
      [
        { icon: editorIcons.h3, title: 'Nagłówek sekcji (H3)', cmd: 'formatBlock', val: 'h3' },
        { icon: editorIcons.bold, title: 'Pogrubienie (Ctrl+B)', cmd: 'bold', val: null },
        { icon: editorIcons.italic, title: 'Kursywa (Ctrl+I)', cmd: 'italic', val: null },
        { icon: editorIcons.underline, title: 'Podkreślenie (Ctrl+U)', cmd: 'underline', val: null },
        { icon: editorIcons.strike, title: 'Przekreślenie', cmd: 'strikeThrough', val: null },
      ],
      [
        { icon: editorIcons.ul, title: 'Lista punktowana', cmd: 'insertUnorderedList', val: null },
        { icon: editorIcons.ol, title: 'Lista numerowana', cmd: 'insertOrderedList', val: null },
        { icon: editorIcons.quote, title: 'Cytat blokowy', cmd: 'formatBlock', val: 'blockquote' },
        { icon: editorIcons.code, title: 'Blok kodu (PHP / SQL)', cmd: 'insertHTML', val: '<pre><code>// Wklej kod PHP lub zapytanie SQL...</code></pre><p><br></p>' },
      ],
      [
        { icon: editorIcons.callout, title: 'Wyróżnienie / Notatka', cmd: 'insertHTML', val: '<div class="vf-callout"><strong>Wskazówka:</strong> Ważna informacja dla czytelników wątku.</div><p><br></p>' },
        { icon: editorIcons.spoiler, title: 'Spoiler / Sekcja rozwijana', cmd: 'insertHTML', val: '<details><summary>Ukryta treść (kliknij, aby rozwinąć)</summary><p>Wpisz ukryte szczegóły lub logi...</p></details><p><br></p>' },
        { icon: editorIcons.hr, title: 'Linia podziału', cmd: 'insertHorizontalRule', val: null },
        { icon: editorIcons.link, title: 'Wstaw odnośnik', cmd: 'link', val: null },
        { icon: editorIcons.emoji, title: 'Wstaw emoji', cmd: 'emoji', val: null },
        { icon: editorIcons.klipy, text: 'Klipy', title: 'Wstaw GIF lub klip z Klipy.com', cmd: 'klipy', val: null, tone: 'klipy' },
        { icon: editorIcons.mention, text: 'Wzmianka', title: 'Wspomnij użytkownika (@)', cmd: 'mention', val: null, tone: 'mention' },
        { icon: editorIcons.blocks, text: 'Bloki /', title: 'Menu bloków (lub wpisz /)', cmd: 'slash', val: null, tone: 'pill' },
        { icon: editorIcons.clearFmt, title: 'Wyczyść formatowanie', cmd: 'removeFormat', val: null },
      ],
    ];

    const modeSwitch = document.createElement('div');
    modeSwitch.className = 'vf-editor-mode-switch';
    const visualBtn = document.createElement('button');
    visualBtn.type = 'button';
    visualBtn.className = 'vf-editor-mode-btn is-active';
    visualBtn.innerHTML = `${editorIcons.pen}<span>Edytor</span>`;
    const previewBtn = document.createElement('button');
    previewBtn.type = 'button';
    previewBtn.className = 'vf-editor-mode-btn';
    previewBtn.innerHTML = `${editorIcons.eye}<span>Podgląd</span>`;
    modeSwitch.appendChild(visualBtn);
    modeSwitch.appendChild(previewBtn);

    const surface = document.createElement('div');
    surface.className = 'vf-editor-surface';
    surface.contentEditable = 'true';
    surface.setAttribute('data-placeholder', textarea.getAttribute('placeholder') || 'Napisz wiadomość... Wpisz / dla bloków lub @ aby wspomnieć użytkownika.');

    const previewPane = document.createElement('div');
    previewPane.className = 'vf-editor-preview vf-post-content';
    previewPane.hidden = true;

    let initialHtml = textarea.value.trim();
    if (!initialHtml) {
      try {
        const savedDraft = localStorage.getItem(draftKey);
        if (savedDraft) initialHtml = savedDraft;
      } catch {}
    }
    surface.innerHTML = initialHtml || '<p><br></p>';

    const footer = document.createElement('div');
    footer.className = 'vf-editor-footer';
    const leftFooter = document.createElement('span');
    leftFooter.className = 'vf-editor-footer-hints';
    const draftSpan = document.createElement('span');
    const clearDraftBtn = document.createElement('button');
    clearDraftBtn.type = 'button';
    clearDraftBtn.className = 'vf-editor-clear-btn';
    clearDraftBtn.textContent = 'Wyczyść';
    clearDraftBtn.hidden = true;
    leftFooter.appendChild(draftSpan);
    leftFooter.appendChild(clearDraftBtn);

    const countsSpan = document.createElement('span');
    countsSpan.className = 'vf-editor-counts';
    footer.appendChild(leftFooter);
    footer.appendChild(countsSpan);

    const mentionMenu = document.createElement('div');
    mentionMenu.className = 'vf-editor-menu';
    mentionMenu.style.display = 'none';

    const slashMenu = document.createElement('div');
    slashMenu.className = 'vf-editor-menu';
    slashMenu.style.display = 'none';

    const popover = document.createElement('div');
    popover.className = 'vf-editor-popover';
    popover.hidden = true;

    let savedRange = null;
    const saveSelection = () => {
      const sel = window.getSelection();
      if (sel && sel.rangeCount > 0 && surface.contains(sel.anchorNode)) {
        savedRange = sel.getRangeAt(0).cloneRange();
      }
    };
    const restoreSelection = () => {
      surface.focus();
      if (!savedRange) return;
      const sel = window.getSelection();
      if (sel) {
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }
    };

    const hasRichMedia = () => Boolean(surface.querySelector('img, video, iframe, figure, pre, blockquote, details, hr'));

    const updateFooterAndDraft = (plain, hasMedia) => {
      const words = plain ? plain.split(/\s+/).length : 0;
      const chars = plain.length;
      countsSpan.textContent = `${words} słów · ${chars} zn.`;
      try {
        if (plain || hasMedia) {
          localStorage.setItem(draftKey, surface.innerHTML);
          draftSpan.textContent = 'Szkic zapisany lokalnie · Ctrl+Enter wysyła';
          clearDraftBtn.hidden = false;
        } else {
          localStorage.removeItem(draftKey);
          draftSpan.textContent = 'Wpisz / aby wstawić blok · @ aby wspomnieć · Ctrl+Enter wysyła';
          clearDraftBtn.hidden = true;
        }
      } catch {
        draftSpan.textContent = 'Wpisz / aby wstawić blok · @ aby wspomnieć · Ctrl+Enter wysyła';
        clearDraftBtn.hidden = true;
      }
    };

    const syncTextarea = () => {
      const textOnly = (surface.textContent || '').trim();
      const media = hasRichMedia();
      textarea.value = textOnly === '' && !media ? '' : surface.innerHTML;
      updateFooterAndDraft(textOnly, media);
    };

    clearDraftBtn.addEventListener('click', () => {
      surface.innerHTML = '<p><br></p>';
      try { localStorage.removeItem(draftKey); } catch {}
      syncTextarea();
      surface.focus();
      showToast('Wyczyszczono szkic wiadomości.');
    });

    const closeEditorPopover = () => {
      popover.hidden = true;
      popover.innerHTML = '';
    };

    const setEditorMode = (preview) => {
      if (preview) {
        closeEditorPopover();
        mentionMenu.style.display = 'none';
        slashMenu.style.display = 'none';
        previewPane.innerHTML = surface.innerHTML;
        surface.hidden = true;
        previewPane.hidden = false;
        visualBtn.classList.remove('is-active');
        previewBtn.classList.add('is-active');
      } else {
        previewPane.hidden = true;
        surface.hidden = false;
        previewBtn.classList.remove('is-active');
        visualBtn.classList.add('is-active');
        surface.focus();
      }
    };

    visualBtn.addEventListener('click', () => setEditorMode(false));
    previewBtn.addEventListener('click', () => setEditorMode(true));

    const openLinkPopover = () => {
      saveSelection();
      popover.hidden = false;
      popover.innerHTML = `
        <div style="display:grid;gap:8px;">
          <strong style="font-size:0.8125rem;">Wstaw odnośnik</strong>
          <input type="url" class="vf-input" data-vf-link-url placeholder="https://example.com" />
          <input type="text" class="vf-input" data-vf-link-text placeholder="Tekst linku (opcjonalnie)" />
          <div style="display:flex;justify-content:flex-end;gap:8px;">
            <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-pop-close>Anuluj</button>
            <button type="button" class="vf-btn vf-btn-sm" data-vf-link-insert>Wstaw</button>
          </div>
        </div>`;
      const urlInput = popover.querySelector('[data-vf-link-url]');
      const textInput = popover.querySelector('[data-vf-link-text]');
      popover.querySelector('[data-vf-pop-close]')?.addEventListener('click', closeEditorPopover);
      popover.querySelector('[data-vf-link-insert]')?.addEventListener('click', () => {
        const raw = (urlInput?.value || '').trim();
        if (!raw) return;
        const safeHref = /^(https?:\/\/|mailto:|\/|#)/i.test(raw) ? raw : `https://${raw}`;
        const label = esc((textInput?.value || '').trim() || safeHref);
        restoreSelection();
        document.execCommand('insertHTML', false, `<a href="${esc(safeHref)}" target="_blank" rel="noopener noreferrer">${label}</a>&nbsp;`);
        closeEditorPopover();
        syncTextarea();
      });
      setTimeout(() => urlInput?.focus(), 20);
    };

    const openEmojiPopover = () => {
      saveSelection();
      popover.hidden = false;
      popover.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <strong style="font-size:0.8125rem;">Szybkie emoji</strong>
          <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-pop-close>Zamknij</button>
        </div>
        <div class="vf-emoji-grid"></div>`;
      const grid = popover.querySelector('.vf-emoji-grid');
      quickEmojis.forEach((em) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'vf-emoji-opt';
        b.textContent = em;
        b.addEventListener('click', () => {
          restoreSelection();
          document.execCommand('insertText', false, em + ' ');
          closeEditorPopover();
          syncTextarea();
        });
        grid.appendChild(b);
      });
      popover.querySelector('[data-vf-pop-close]')?.addEventListener('click', closeEditorPopover);
    };

    const openKlipyPopover = () => {
      saveSelection();
      popover.hidden = false;
      popover.innerHTML = `
        <div style="display:grid;gap:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:0.8125rem;">Klipy.com — GIF i klipy wideo</strong>
            <button type="button" class="vf-btn vf-btn-sm vf-btn-secondary" data-vf-pop-close>Zamknij</button>
          </div>
          <div style="display:flex;gap:6px;">
            <input type="text" class="vf-input" data-vf-klipy-query placeholder="Szukaj GIF w Klipy lub wpisz hasło..." />
            <button type="button" class="vf-btn vf-btn-sm" data-vf-klipy-search>Szukaj</button>
          </div>
          <div data-vf-klipy-results style="font-size:0.75rem;color:var(--muted);"></div>
          <div style="border-top:1px solid var(--border);padding-top:8px;display:grid;gap:6px;">
            <span style="font-size:0.75rem;color:var(--muted);">Lub wklej bezpośredni link z <strong>klipy.com</strong>:</span>
            <div style="display:flex;gap:6px;">
              <input type="url" class="vf-input" data-vf-klipy-url placeholder="https://klipy.com/gifs/... lub static.klipy.com/..." />
              <button type="button" class="vf-btn vf-btn-sm" data-vf-klipy-insert>Wstaw</button>
            </div>
          </div>
        </div>`;
      popover.querySelector('[data-vf-pop-close]')?.addEventListener('click', closeEditorPopover);
      const qInput = popover.querySelector('[data-vf-klipy-query]');
      const sBtn = popover.querySelector('[data-vf-klipy-search]');
      const resBox = popover.querySelector('[data-vf-klipy-results]');
      const uInput = popover.querySelector('[data-vf-klipy-url]');
      const iBtn = popover.querySelector('[data-vf-klipy-insert]');

      const runKlipySearch = async () => {
        if (!resBox) return;
        const q = (qInput?.value || '').trim();
        resBox.innerHTML = 'Ładowanie z Klipy.com...';
        try {
          const res = await fetch(vfUrl(`/api/klipy?q=${encodeURIComponent(q)}&type=gifs`), {
            headers: { Accept: 'application/json' },
          });
          const data = await res.json();
          if (!data.configured) {
            resBox.innerHTML = 'Klucz API Klipy nie jest skonfigurowany w ACP (Ustawienia i SEO) — użyj pola linku poniżej.';
            return;
          }
          const items = data.items || [];
          if (items.length === 0) {
            resBox.innerHTML = 'Brak wyników dla podanego zapytania.';
            return;
          }
          const grid = document.createElement('div');
          grid.className = 'vf-klipy-grid';
          items.forEach((item) => {
            const previewSrc = item.previewUrl || item.preview_url || item.mediaUrl || item.media_url;
            const media = item.mediaUrl || item.media_url || previewSrc;
            if (!media) return;
            const btnItem = document.createElement('button');
            btnItem.type = 'button';
            btnItem.className = 'vf-klipy-item';
            btnItem.title = item.title || 'Wstaw z Klipy.com';
            btnItem.innerHTML = `<img src="${esc(previewSrc || media)}" alt="${esc(item.title || 'Klipy')}" loading="lazy" />`;
            btnItem.addEventListener('click', () => {
              const html = buildKlipyFigureHtml(media, item.title || 'Klipy.com');
              if (!html) return;
              restoreSelection();
              document.execCommand('insertHTML', false, html);
              closeEditorPopover();
              syncTextarea();
            });
            grid.appendChild(btnItem);
          });
          resBox.innerHTML = '';
          resBox.appendChild(grid);
        } catch {
          resBox.innerHTML = 'Nie udało się połączyć z proxy Klipy. Wklej link bezpośrednio poniżej.';
        }
      };

      sBtn?.addEventListener('click', runKlipySearch);
      qInput?.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          runKlipySearch();
        }
      });
      iBtn?.addEventListener('click', () => {
        const html = buildKlipyFigureHtml(uInput?.value || '', 'Klipy.com');
        if (!html) {
          showToast('Podaj poprawny adres URL z domeny klipy.com.');
          return;
        }
        restoreSelection();
        document.execCommand('insertHTML', false, html);
        closeEditorPopover();
        syncTextarea();
      });
      setTimeout(() => qInput?.focus(), 20);
    };

    toolbarGroups.forEach((grp, gIdx) => {
      if (gIdx > 0) {
        const sep = document.createElement('div');
        sep.className = 'vf-editor-sep';
        sep.setAttribute('aria-hidden', 'true');
        toolbarTools.appendChild(sep);
      }
      grp.forEach((b) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vf-editor-btn' + (b.text ? ' has-label' : '') + (b.tone ? ` is-${b.tone}` : '');
        btn.title = b.title;
        btn.setAttribute('aria-label', b.title);
        btn.innerHTML = b.text ? `${b.icon}<span>${esc(b.text)}</span>` : b.icon;
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => {
          if (!previewPane.hidden) {
            setEditorMode(false);
          }
          if (b.cmd === 'link') {
            openLinkPopover();
            return;
          }
          if (b.cmd === 'emoji') {
            openEmojiPopover();
            return;
          }
          if (b.cmd === 'klipy') {
            openKlipyPopover();
            return;
          }
          if (b.cmd === 'slash') {
            if (slashMenu.style.display === 'block') {
              slashMenu.style.display = 'none';
            } else {
              surface.focus();
              openSlashMenu('');
            }
            return;
          }
          surface.focus();
          if (b.cmd === 'mention') {
            document.execCommand('insertText', false, '@');
            openMentionMenu('');
            return;
          }
          document.execCommand(b.cmd, false, b.val);
          syncTextarea();
        });
        toolbarTools.appendChild(btn);
      });
    });

    toolbar.appendChild(toolbarTools);
    toolbar.appendChild(modeSwitch);

    let mentionItems = [];
    let mentionIndex = 0;

    const updateMentionSelection = () => {
      const opts = mentionMenu.querySelectorAll('.vf-editor-menu-item');
      opts.forEach((el, idx) => el.classList.toggle('is-selected', idx === mentionIndex));
    };

    const safeHexColor = (val) => (/^#[0-9a-fA-F]{6}$/.test(String(val || '').trim()) ? String(val).trim() : '');

    async function openMentionMenu(query) {
      slashMenu.style.display = 'none';
      try {
        const res = await fetch(vfUrl(`/api/members/search?q=${encodeURIComponent(query)}`), {
          headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        const items = data.items || [];
        mentionItems = items;
        mentionIndex = 0;
        if (items.length === 0) {
          mentionMenu.style.display = 'none';
          return;
        }
        mentionMenu.innerHTML = '<div class="vf-editor-menu-head"><span>Wspomnij użytkownika</span><span>↑↓ · Enter</span></div>';
        items.forEach((member, idx) => {
          const opt = document.createElement('button');
          opt.type = 'button';
          opt.className = 'vf-editor-menu-item' + (idx === 0 ? ' is-selected' : '');
          const mName = String(member.username || 'Użytkownik');
          const mInit = mName.slice(0, 2).toUpperCase();
          const mColor = safeHexColor(member.group_color);
          const avatarHtml = member.avatar_url
            ? `<img src="${esc(member.avatar_url)}" alt="" />`
            : esc(mInit);
          opt.innerHTML = `<span class="vf-mention-opt-left"><span class="vf-mention-opt-avatar">${avatarHtml}</span><strong${mColor ? ` style="color:${esc(mColor)}"` : ''}>@${esc(mName)}</strong></span><span>${esc(member.group_name || 'Użytkownik')}</span>`;
          opt.addEventListener('mousedown', (e) => e.preventDefault());
          opt.addEventListener('mouseenter', () => {
            mentionIndex = idx;
            updateMentionSelection();
          });
          opt.addEventListener('click', () => {
            insertMentionChip(member);
            mentionMenu.style.display = 'none';
          });
          mentionMenu.appendChild(opt);
        });
        mentionMenu.style.display = 'block';
      } catch {
        mentionItems = [];
        mentionMenu.style.display = 'none';
      }
    }

    function insertMentionChip(member) {
      const sel = window.getSelection();
      if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        const node = range.startContainer;
        if (node.nodeType === Node.TEXT_NODE) {
          const text = node.textContent || '';
          const before = text.slice(0, range.startOffset);
          const match = before.match(/@([A-Za-z0-9_.\-ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]*)$/u);
          if (match) {
            range.setStart(node, range.startOffset - match[0].length);
            range.deleteContents();
          }
        }
      }
      const mColor = safeHexColor(member.group_color);
      const colorAttr = mColor ? ` style="color:${esc(mColor)};"` : '';
      document.execCommand(
        'insertHTML',
        false,
        `<a href="${vfUrl(`/member/${Number(member.id)}-${esc(member.slug || 'profil')}`)}" class="vf-mention" data-mention-id="${Number(member.id)}"${colorAttr}>@${esc(member.username)}</a>&nbsp;`
      );
      syncTextarea();
    }

    function openSlashMenu(filter) {
      mentionMenu.style.display = 'none';
      const filtered = slashCommands.filter((c) =>
        c.label.toLowerCase().includes(filter.toLowerCase()) ||
        c.hint.toLowerCase().includes(filter.toLowerCase())
      );
      if (filtered.length === 0) {
        slashMenu.style.display = 'none';
        return;
      }
      slashMenu.innerHTML = '<div class="vf-editor-menu-head"><span>Wstaw blok</span><span>Enter / Kliknij</span></div>';
      filtered.forEach((cmd) => {
        const opt = document.createElement('button');
        opt.type = 'button';
        opt.className = 'vf-editor-menu-item';
        opt.innerHTML = `<strong>${esc(cmd.label)}</strong><span>${esc(cmd.hint)}</span>`;
        opt.addEventListener('mousedown', (e) => e.preventDefault());
        opt.addEventListener('click', () => {
          const sel = window.getSelection();
          if (sel && sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const node = range.startContainer;
            if (node.nodeType === Node.TEXT_NODE) {
              const before = (node.textContent || '').slice(0, range.startOffset);
              const m = before.match(/\/([a-zA-Ząćęłńóśźż]*)$/u);
              if (m) {
                range.setStart(node, range.startOffset - m[0].length);
                range.deleteContents();
              }
            }
          }
          if (cmd.cmd === 'klipy') {
            slashMenu.style.display = 'none';
            openKlipyPopover();
            return;
          }
          document.execCommand(cmd.cmd, false, cmd.val);
          slashMenu.style.display = 'none';
          syncTextarea();
        });
        slashMenu.appendChild(opt);
      });
      slashMenu.style.display = 'block';
    }

    surface.addEventListener('input', () => {
      syncTextarea();
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return;
      const range = sel.getRangeAt(0);
      const node = range.startContainer;
      if (node.nodeType !== Node.TEXT_NODE) {
        mentionMenu.style.display = 'none';
        slashMenu.style.display = 'none';
        return;
      }
      const before = (node.textContent || '').slice(0, range.startOffset);
      const mentionMatch = before.match(/(?:^|[\s\u00A0])@([A-Za-z0-9_.\-ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]{0,24})$/u);
      if (mentionMatch) {
        openMentionMenu(mentionMatch[1]);
        return;
      }
      const slashMatch = before.match(/(?:^|[\s\u00A0])\/([a-zA-Ząćęłńóśźż]{0,16})$/u);
      if (slashMatch) {
        openSlashMenu(slashMatch[1]);
        return;
      }
      mentionMenu.style.display = 'none';
      slashMenu.style.display = 'none';
    });

    surface.addEventListener('keydown', (e) => {
      if (mentionMenu.style.display === 'block' && mentionItems.length > 0) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          mentionIndex = (mentionIndex + 1) % mentionItems.length;
          updateMentionSelection();
          return;
        }
        if (e.key === 'ArrowUp') {
          e.preventDefault();
          mentionIndex = (mentionIndex - 1 + mentionItems.length) % mentionItems.length;
          updateMentionSelection();
          return;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
          e.preventDefault();
          const chosen = mentionItems[mentionIndex] || mentionItems[0];
          if (chosen) insertMentionChip(chosen);
          mentionMenu.style.display = 'none';
          return;
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          mentionMenu.style.display = 'none';
          return;
        }
      }
      if (slashMenu.style.display === 'block' && e.key === 'Escape') {
        e.preventDefault();
        slashMenu.style.display = 'none';
        return;
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && form) {
        e.preventDefault();
        syncTextarea();
        try { localStorage.removeItem(draftKey); } catch {}
        form.requestSubmit ? form.requestSubmit() : form.submit();
      }
    });

    if (form) {
      form.addEventListener('submit', () => {
        syncTextarea();
        try { localStorage.removeItem(draftKey); } catch {}
      });
    }

    textarea.style.display = 'none';
    textarea.removeAttribute('required');
    wrap.appendChild(toolbar);
    wrap.appendChild(surface);
    wrap.appendChild(previewPane);
    wrap.appendChild(footer);
    wrap.appendChild(mentionMenu);
    wrap.appendChild(slashMenu);
    wrap.appendChild(popover);
    textarea.parentNode.insertBefore(wrap, textarea.nextSibling);

    editors.set(textarea, { surface, syncTextarea });
    syncTextarea();
  });

  // Przycisk "Cytuj" przy postach + "Cytuj zaznaczenie" (Select-to-Quote)
  const insertQuoteIntoReply = (author, excerpt) => {
    const replyTextarea = document.querySelector('textarea[data-vf-reply-editor]');
    if (!replyTextarea) return;
    const quoteHtml = `<blockquote><p><strong>${esc(author)} napisał(a):</strong> ${esc(excerpt)}</p></blockquote><p><br></p>`;
    const ed = editors.get(replyTextarea);
    if (ed) {
      ed.surface.focus();
      ed.surface.innerHTML += quoteHtml;
      ed.syncTextarea();
    } else {
      replyTextarea.value += `> ${author}: ${excerpt}\n\n`;
    }
    const replyBox = document.getElementById('reply-box');
    if (replyBox) replyBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  document.querySelectorAll('[data-vf-quote-btn]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const author = btn.getAttribute('data-quote-author') || 'Użytkownik';
      const pid = btn.getAttribute('data-quote-post-id');
      const contentEl = document.querySelector(`[data-vf-post-content="${pid}"]`);
      if (!contentEl) return;
      const excerpt = (contentEl.textContent || '').trim().slice(0, 240);
      insertQuoteIntoReply(author, excerpt);
      showToast('Dodano cytat do odpowiedzi.');
    });
  });

  // Szybkie oznaczanie autora posta przyciskiem "@ Oznacz"
  document.querySelectorAll('[data-vf-mention-btn]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const replyTextarea = document.querySelector('textarea[data-vf-reply-editor]');
      if (!replyTextarea) return;
      const mid = Number(btn.getAttribute('data-mention-id') || '0');
      const username = btn.getAttribute('data-mention-username') || 'Użytkownik';
      const slug = btn.getAttribute('data-mention-slug') || 'profil';
      const rawColor = (btn.getAttribute('data-mention-color') || '').trim();
      const colorStyle = /^#[0-9a-fA-F]{6}$/.test(rawColor) ? ` style="color:${esc(rawColor)};"` : '';
      const chipHtml = mid > 0
        ? `<a href="${vfUrl(`/member/${mid}-${esc(slug)}`)}" class="vf-mention" data-mention-id="${mid}"${colorStyle}>@${esc(username)}</a>&nbsp;`
        : `@${esc(username)} `;

      const ed = editors.get(replyTextarea);
      if (ed) {
        ed.surface.focus();
        const sel = window.getSelection();
        if (sel && (!sel.rangeCount || !ed.surface.contains(sel.anchorNode))) {
          const r = document.createRange();
          r.selectNodeContents(ed.surface);
          r.collapse(false);
          sel.removeAllRanges();
          sel.addRange(r);
        }
        if (!document.execCommand('insertHTML', false, chipHtml)) {
          ed.surface.innerHTML += `<p>${chipHtml}</p>`;
        }
        ed.syncTextarea();
      } else {
        replyTextarea.value += `@${username} `;
        replyTextarea.focus();
      }
      const replyBox = document.getElementById('reply-box');
      if (replyBox) replyBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      showToast(`Oznaczono @${username} w odpowiedzi.`);
    });
  });

  const replyTextareaEl = document.querySelector('textarea[data-vf-reply-editor]');
  if (replyTextareaEl) {
    let floatQuoteBtn = null;
    const removeFloatQuote = () => {
      if (floatQuoteBtn) {
        floatQuoteBtn.remove();
        floatQuoteBtn = null;
      }
    };
    document.addEventListener('mouseup', () => {
      const sel = window.getSelection();
      const text = sel ? sel.toString().trim() : '';
      if (text.length < 4 || !sel || sel.rangeCount === 0) {
        removeFloatQuote();
        return;
      }
      const anchorEl = sel.anchorNode?.nodeType === Node.ELEMENT_NODE ? sel.anchorNode : sel.anchorNode?.parentElement;
      const postContent = anchorEl?.closest?.('[data-vf-post-content]');
      if (!postContent) {
        removeFloatQuote();
        return;
      }
      const pid = postContent.getAttribute('data-vf-post-content');
      const quoteBtn = document.querySelector(`[data-vf-quote-btn][data-quote-post-id="${pid}"]`);
      const author = quoteBtn?.getAttribute('data-quote-author') || 'Użytkownik';
      const rect = sel.getRangeAt(0).getBoundingClientRect();

      removeFloatQuote();
      floatQuoteBtn = document.createElement('button');
      floatQuoteBtn.type = 'button';
      floatQuoteBtn.className = 'vf-select-quote-bar';
      floatQuoteBtn.textContent = 'Cytuj zaznaczenie';
      floatQuoteBtn.style.left = `${Math.max(12, Math.min(window.innerWidth - 160, rect.left))}px`;
      floatQuoteBtn.style.top = `${Math.max(64, rect.top - 38)}px`;
      floatQuoteBtn.addEventListener('mousedown', (e) => e.preventDefault());
      floatQuoteBtn.addEventListener('click', () => {
        insertQuoteIntoReply(author, text.slice(0, 260));
        removeFloatQuote();
        sel.removeAllRanges();
      });
      document.body.appendChild(floatQuoteBtn);
    });
  }

  // Dwustopniowe bezpieczne potwierdzanie usunięcia (bez window.confirm, zachowuje SVG w ikonach)
  document.querySelectorAll('[data-vf-confirm-delete]').forEach((form) => {
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    const origTip = btn.getAttribute('data-tooltip');
    let armed = false;
    let timer = null;
    form.addEventListener('submit', (e) => {
      if (armed) return;
      e.preventDefault();
      armed = true;
      const confirmTxt = btn.getAttribute('data-confirm-label') || 'Potwierdź usunięcie';
      if (btn.classList.contains('vf-post-ico-btn')) {
        btn.style.width = 'auto';
        btn.style.padding = '0 9px';
        btn.textContent = confirmTxt;
        if (origTip) btn.setAttribute('data-tooltip', confirmTxt);
      } else {
        btn.textContent = confirmTxt;
        btn.classList.remove('vf-btn-danger');
        btn.classList.add('vf-btn-danger-solid');
      }
      clearTimeout(timer);
      timer = setTimeout(() => {
        armed = false;
        btn.innerHTML = origHtml;
        btn.style.width = '';
        btn.style.padding = '';
        if (origTip) btn.setAttribute('data-tooltip', origTip);
        btn.classList.remove('vf-btn-danger-solid');
        if (!btn.classList.contains('vf-post-ico-btn')) {
          btn.classList.add('vf-btn-danger');
        }
      }, 4000);
    });
  });

  // Podgląd snippetu Google SEO na żywo w ACP
  const seoNameInput = document.querySelector('[data-vf-seo-name]');
  const seoDescInput = document.querySelector('[data-vf-seo-desc]');
  const seoTitleOut = document.querySelector('[data-vf-seo-title-preview]');
  const seoDescOut = document.querySelector('[data-vf-seo-desc-preview]');
  if (seoNameInput && seoDescInput && seoTitleOut && seoDescOut) {
    const syncSeoPreview = () => {
      const name = (seoNameInput.value || 'VeloForum').trim();
      const desc = (seoDescInput.value || 'Nowoczesna społeczność dyskusyjna.').trim();
      seoTitleOut.textContent = `${name} — Nowoczesne forum dyskusyjne`;
      seoDescOut.textContent = desc.length > 160 ? `${desc.slice(0, 157)}…` : desc;
    };
    seoNameInput.addEventListener('input', syncSeoPreview);
    seoDescInput.addEventListener('input', syncSeoPreview);
    syncSeoPreview();
  }

  // Pływające powiadomienia Toast
  const toastStack = document.querySelector('[data-vf-toast-stack]');
  function showToast(message) {
    if (!toastStack || !message) return;
    const toast = document.createElement('div');
    toast.className = 'vf-toast';
    toast.textContent = message;
    toastStack.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(4px)';
      toast.style.transition = 'opacity 0.18s ease, transform 0.18s ease';
      setTimeout(() => toast.remove(), 190);
    }, 2500);
  }

  // Błyskawiczne zakładki (0 ms bez przeładowania strony, np. w profilu użytkownika)
  const activateTab = (scopeEl, target, updateUrl) => {
    if (!scopeEl || !target) return false;
    const tabLinks = Array.from(scopeEl.querySelectorAll('[data-vf-tab-target]'));
    const panels = Array.from(scopeEl.querySelectorAll('[data-vf-tab-panel]'));
    if (tabLinks.length === 0 || panels.length === 0) return false;
    let matchedLink = null;
    tabLinks.forEach((lnk) => {
      const isTarget = lnk.getAttribute('data-vf-tab-target') === target;
      lnk.classList.toggle('is-active', isTarget);
      lnk.setAttribute('aria-selected', isTarget ? 'true' : 'false');
      if (isTarget) matchedLink = lnk;
    });
    panels.forEach((pnl) => {
      const isTarget = pnl.getAttribute('data-vf-tab-panel') === target;
      pnl.hidden = !isTarget;
    });
    if (updateUrl && matchedLink && matchedLink.href && window.history?.replaceState) {
      try {
        window.history.replaceState(null, '', matchedLink.href);
      } catch {}
    }
    return true;
  };

  document.querySelectorAll('[data-vf-tabs]').forEach((scopeEl) => {
    scopeEl.querySelectorAll('[data-vf-tab-target]').forEach((tabLink) => {
      tabLink.addEventListener('click', (e) => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        const target = tabLink.getAttribute('data-vf-tab-target');
        if (!target) return;
        e.preventDefault();
        activateTab(scopeEl, target, true);
      });
    });
  });

  document.querySelectorAll('[data-vf-tab-jump]').forEach((jumpBtn) => {
    jumpBtn.addEventListener('click', (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      const target = jumpBtn.getAttribute('data-vf-tab-jump');
      const scopeName = jumpBtn.getAttribute('data-vf-tabs-scope');
      const scopeEl = scopeName ? document.querySelector(`[data-vf-tabs="${scopeName}"]`) : document.querySelector('[data-vf-tabs]');
      if (!scopeEl || !target) return;
      e.preventDefault();
      if (activateTab(scopeEl, target, true)) {
        const editForm = document.getElementById('vf-edit-profile-form');
        const targetNode = editForm || scopeEl;
        targetNode.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const firstInput = editForm?.querySelector('input:not([type="hidden"]), textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 220);
      }
    });
  });

  // Rozwijane panele edycji / zgłoszeń bez przeładowania
  document.querySelectorAll('[data-vf-toggle-target]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-vf-toggle-target');
      if (!targetId) return;
      const panel = document.getElementById(targetId);
      if (!panel) return;
      const willOpen = panel.hidden;
      panel.hidden = !willOpen;
      document.querySelectorAll('[data-vf-toggle-target]').forEach((ctrl) => {
        if (ctrl.getAttribute('data-vf-toggle-target') === targetId && ctrl.hasAttribute('aria-expanded')) {
          ctrl.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
      });
      if (willOpen) {
        const focusEl = panel.querySelector('.vf-editor-surface, input:not([type="hidden"]), textarea:not([style*="display: none"]), select');
        if (focusEl && typeof focusEl.focus === 'function') {
          setTimeout(() => focusEl.focus(), 20);
        }
      }
    });
  });

  // Kopiowanie bezpośredniego linku do posta
  document.querySelectorAll('[data-vf-copy-link]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const hash = btn.getAttribute('data-vf-copy-link') || '';
      const fullUrl = `${window.location.origin}${window.location.pathname}${window.location.search}${hash}`;
      const done = () => showToast('Skopiowano bezpośredni link do posta.');
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(fullUrl).then(done).catch(() => {
          const tmp = document.createElement('textarea');
          tmp.value = fullUrl;
          tmp.style.position = 'fixed';
          tmp.style.opacity = '0';
          document.body.appendChild(tmp);
          tmp.select();
          try { document.execCommand('copy'); } catch {}
          tmp.remove();
          done();
        });
      } else {
        const tmp = document.createElement('textarea');
        tmp.value = fullUrl;
        tmp.style.position = 'fixed';
        tmp.style.opacity = '0';
        document.body.appendChild(tmp);
        tmp.select();
        try { document.execCommand('copy'); } catch {}
        tmp.remove();
        done();
      }
    });
  });

  // Natychmiastowe filtrowanie list w ACP i ModCP
  document.querySelectorAll('[data-vf-filter-input]').forEach((input) => {
    const listKey = input.getAttribute('data-vf-filter-input');
    const listEl = listKey ? document.querySelector(`[data-vf-filter-list="${listKey}"]`) : null;
    if (!listEl) return;
    const items = Array.from(listEl.querySelectorAll('[data-vf-filter-item]'));
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      items.forEach((item) => {
        const hay = (item.getAttribute('data-vf-filter-item') || item.textContent || '').toLowerCase();
        item.hidden = q !== '' && !hay.includes(q);
      });
    });
  });

  // Bezpieczny prefetch podstron read-only po najechaniu myszą
  const conn = navigator.connection;
  const allowPrefetch = !(conn && (conn.saveData || conn.effectiveType === '2g'));
  if (allowPrefetch) {
    const prefetched = new Set();
    let prefetchTimer = null;
    const canPrefetchUrl = (anchor) => {
      if (!anchor || anchor.hasAttribute('data-vf-tab-target') || anchor.hasAttribute('data-vf-tab-jump')) return false;
      const href = anchor.getAttribute('href') || '';
      if (!href || href.startsWith('#')) return false;
      let url;
      try {
        url = new URL(anchor.href, window.location.origin);
      } catch {
        return false;
      }
      if (url.origin !== window.location.origin) return false;
      if (url.pathname + url.search === window.location.pathname + window.location.search) return false;
      const relPath = VF_BASE && url.pathname.startsWith(VF_BASE) ? (url.pathname.slice(VF_BASE.length) || '/') : url.pathname;
      const safePrefixes = ['/', '/activity', '/members', '/bookmarks', '/forum/', '/topic/', '/member/', '/tag/'];
      return relPath === '/' || safePrefixes.some((p) => p !== '/' && relPath.startsWith(p));
    };

    const schedulePrefetch = (anchor) => {
      if (prefetched.size >= 16 || !canPrefetchUrl(anchor)) return;
      const targetHref = anchor.href;
      if (prefetched.has(targetHref)) return;
      clearTimeout(prefetchTimer);
      prefetchTimer = setTimeout(() => {
        if (prefetched.has(targetHref) || prefetched.size >= 16) return;
        prefetched.add(targetHref);
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.as = 'document';
        link.href = targetHref;
        document.head.appendChild(link);
      }, 35);
    };

    document.addEventListener('pointerover', (e) => {
      const anchor = e.target?.closest?.('a[href]');
      if (anchor) schedulePrefetch(anchor);
    });
    document.addEventListener('focusin', (e) => {
      const anchor = e.target?.closest?.('a[href]');
      if (anchor) schedulePrefetch(anchor);
    });
  }

  // Zapamiętywanie zwiniętych kategorii na stronie głównej
  const catButtons = Array.from(document.querySelectorAll('[data-vf-cat-toggle]'));
  if (catButtons.length > 0) {
    let collapsedIds = [];
    try {
      const savedCats = JSON.parse(localStorage.getItem('vf-collapsed-cats') || '[]');
      if (Array.isArray(savedCats)) collapsedIds = savedCats.map(Number);
    } catch {}

    catButtons.forEach((btn) => {
      const catId = Number(btn.getAttribute('data-vf-cat-toggle') || '0');
      const card = btn.closest('.vf-card');
      if (!card || !catId) return;
      if (collapsedIds.includes(catId)) {
        card.classList.add('is-collapsed');
        btn.setAttribute('aria-expanded', 'false');
      }
      btn.addEventListener('click', () => {
        const isCollapsed = card.classList.toggle('is-collapsed');
        btn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        if (isCollapsed) {
          if (!collapsedIds.includes(catId)) collapsedIds.push(catId);
        } else {
          collapsedIds = collapsedIds.filter((id) => id !== catId);
        }
        try {
          localStorage.setItem('vf-collapsed-cats', JSON.stringify(collapsedIds));
        } catch {}
      });
    });
  }

  // Podgląd wybranego avatara przed zapisaniem profilu
  const avatarInput = document.querySelector('[data-vf-avatar-input]');
  if (avatarInput) {
    const avatarPreview = document.querySelector('[data-vf-avatar-preview]');
    const avatarFilename = document.querySelector('[data-vf-avatar-filename]');
    avatarInput.addEventListener('change', () => {
      const file = avatarInput.files && avatarInput.files[0];
      if (!file) return;
      const sizeKb = Math.max(1, Math.round(file.size / 1024));
      const tooBig = file.size > 2 * 1024 * 1024;
      if (avatarFilename) {
        avatarFilename.textContent = tooBig
          ? `${file.name} (${sizeKb} KB) — przekracza limit 2 MB!`
          : `${file.name} (${sizeKb} KB)`;
        avatarFilename.style.color = tooBig ? '#dc2626' : '';
      }
      if (avatarPreview && file.type.startsWith('image/') && !tooBig) {
        const objectUrl = URL.createObjectURL(file);
        avatarPreview.innerHTML = '';
        const img = document.createElement('img');
        img.src = objectUrl;
        img.alt = 'Podgląd nowego avatara';
        img.onload = () => URL.revokeObjectURL(objectUrl);
        avatarPreview.appendChild(img);
      }
    });
  }

  // Lista wybranych załączników przed wysłaniem tematu / odpowiedzi / edycji
  document.querySelectorAll('[data-vf-attach-input]').forEach((input) => {
    const picker = input.closest('.vf-attach-picker');
    const previewList = picker?.querySelector('[data-vf-attach-preview]');
    if (!previewList) return;
    input.addEventListener('change', () => {
      const files = Array.from(input.files || []);
      previewList.innerHTML = '';
      if (files.length === 0) {
        previewList.hidden = true;
        return;
      }
      previewList.hidden = false;
      files.forEach((f, idx) => {
        const chip = document.createElement('span');
        const overCount = idx >= 5;
        const overSize = f.size > 5 * 1024 * 1024;
        chip.className = 'vf-attach-chip' + (overCount || overSize ? ' is-warn' : '');
        const sizeLabel = f.size >= 1048576
          ? `${(f.size / 1048576).toFixed(1)} MB`
          : `${Math.max(1, Math.round(f.size / 1024))} KB`;
        chip.textContent = `${f.name} · ${sizeLabel}${overCount ? ' (ponad limit 5 plików)' : overSize ? ' (za duży > 5 MB)' : ''}`;
        previewList.appendChild(chip);
      });
    });
  });

  // Lightbox do powiększania zdjęć z załączników i postów
  const lightboxEl = document.querySelector('[data-vf-lightbox]');
  const lightboxImg = document.querySelector('[data-vf-lightbox-img]');
  const lightboxClose = document.querySelector('[data-vf-lightbox-close]');
  if (lightboxEl && lightboxImg) {
    const openLightbox = (src, altText) => {
      if (!src) return;
      lightboxImg.src = src;
      lightboxImg.alt = altText || 'Podgląd obrazu';
      lightboxEl.hidden = false;
    };
    const closeLightbox = () => {
      lightboxEl.hidden = true;
      lightboxImg.src = '';
    };
    document.addEventListener('click', (e) => {
      const trigger = e.target?.closest?.('[data-vf-lightbox-src]');
      if (trigger) {
        e.preventDefault();
        openLightbox(
          trigger.getAttribute('data-vf-lightbox-src'),
          trigger.getAttribute('data-vf-lightbox-alt') || ''
        );
        return;
      }
      const postImg = e.target?.closest?.('.vf-post-content img');
      if (postImg && !postImg.closest('a')) {
        e.preventDefault();
        openLightbox(postImg.currentSrc || postImg.src, postImg.alt || '');
      }
    });
    lightboxClose?.addEventListener('click', closeLightbox);
    lightboxEl.addEventListener('click', (e) => {
      if (e.target === lightboxEl) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !lightboxEl.hidden) {
        closeLightbox();
      }
    });
  }

  // Real-time obecność (wszędzie: kto online, kto aktualnie w temacie, powiadomienia i nowe posty)
  const pageTypeMeta = document.querySelector('meta[name="vf-page-type"]')?.getAttribute('content') || 'home';
  const topicIdMeta = Number(document.querySelector('meta[name="vf-topic-id"]')?.getAttribute('content') || '0');
  const forumIdMeta = Number(document.querySelector('meta[name="vf-forum-id"]')?.getAttribute('content') || '0');
  const topicPresenceBar = document.querySelector('[data-vf-topic-presence]');
  const initialLastPostId = Number(topicPresenceBar?.getAttribute('data-last-post-id') || '0');
  const initialPostsCount = Number(topicPresenceBar?.getAttribute('data-posts-count') || '0');

  const safeColorHex = (hex) => (/^#[0-9a-fA-F]{3,8}$/.test(String(hex || '').trim()) ? String(hex).trim() : '#2563eb');
  const sanitizeAffix = (html) => {
    const raw = String(html || '').trim();
    if (!raw) return '';
    return raw
      .replace(/<(?!\/?(span|strong|b|i|em|u|small)\b)[^>]*>/gi, '')
      .replace(/\son[a-z]+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
      .replace(/javascript\s*:/gi, '');
  };
  const formatUsernameHtml = (username, colorHex, prefixHtml, suffixHtml, groupName) => {
    const safeName = esc(String(username || 'Użytkownik'));
    const color = /^#[0-9a-fA-F]{3,8}$/.test(String(colorHex || '').trim()) ? String(colorHex).trim() : '';
    const titleAttr = groupName && String(groupName).trim() !== '' ? ` title="Grupa: ${esc(String(groupName).trim())}"` : '';
    const prefix = sanitizeAffix(prefixHtml);
    const suffix = sanitizeAffix(suffixHtml);
    if (prefix && suffix) {
      const opens = prefix.match(/<(span|strong|b|i|em|u|small)\b/gi) || [];
      const closes = suffix.match(/<\/(span|strong|b|i|em|u|small)>/gi) || [];
      if (opens.length === closes.length) {
        const styleAttr = color ? ` style="color:${esc(color)};"` : '';
        return `<span class="vf-user-formatted"${styleAttr}${titleAttr}>${prefix}${safeName}${suffix}</span>`;
      }
    }
    const styleAttr = color ? ` style="color:${esc(color)};font-weight:700;"` : ' style="font-weight:700;"';
    return `<span class="vf-user-formatted"${styleAttr}${titleAttr}>${safeName}</span>`;
  };

  const syncPmCounters = (unreadMessages) => {
    const cnt = Math.max(0, Number(unreadMessages || 0));
    document.querySelectorAll('[data-vf-pm-count-text]').forEach((el) => {
      el.textContent = cnt > 0 ? ` (${cnt})` : '';
    });
    document.querySelectorAll('[data-vf-pm-badge], [data-vf-mob-pm-badge]').forEach((el) => {
      el.textContent = String(cnt);
      el.hidden = cnt <= 0;
    });
  };

  const applyLivePresencePayload = (data) => {
    if (!data || !data.ok) return;

    // 1. Nieprzeczytane powiadomienia i prywatne wiadomości w nawigacji
    if (typeof data.unread_count === 'number') {
      document.querySelectorAll('[data-vf-notif-count-text]').forEach((el) => {
        el.textContent = data.unread_count > 0 ? ` (${data.unread_count})` : '';
      });
      document.querySelectorAll('[data-vf-notif-badge], [data-vf-mob-notif-badge]').forEach((el) => {
        el.textContent = String(data.unread_count);
        el.hidden = data.unread_count <= 0;
      });
    }
    if (typeof data.unread_messages_count === 'number') {
      syncPmCounters(data.unread_messages_count);
    }

    // 1b. Widget boczny "Ostatnia aktywność" na żywo bez przeładowania strony
    const liveActivityEl = document.querySelector('[data-vf-live-activity]');
    if (liveActivityEl && Array.isArray(data.latest_activity)) {
      if (data.latest_activity.length === 0) {
        liveActivityEl.innerHTML = '<div class="vf-row"><span class="vf-muted">Brak ostatnich wpisów.</span></div>';
      } else {
        liveActivityEl.innerHTML = data.latest_activity
          .map((lt) => {
            const topicUrl = esc(lt.url || '#');
            const posterHtml = lt.last_poster_url
              ? `<a href="${esc(lt.last_poster_url)}">${lt.last_poster_html || esc(lt.last_poster_name || 'Użytkownik')}</a>`
              : (lt.last_poster_html || esc(lt.last_poster_name || 'Gość'));
            return `
              <div class="vf-row" style="display:block;padding:12px 18px;">
                <div style="font-weight:600;font-size:0.875rem;line-height:1.35;">
                  <a href="${topicUrl}" style="color:var(--ink);">${esc(lt.title || '')}</a>
                </div>
                <div class="vf-muted" style="margin-top:3px;">
                  ${esc(lt.forum_name || '')} · ${posterHtml} · ${esc(lt.relative_time || 'przed chwilą')}
                </div>
              </div>`;
          })
          .join('');
      }
    }

    // 2. Dolny widget "Kto jest online w społeczności" (tylko aktywni)
    const onlineCountEl = document.querySelector('[data-vf-online-count-text]');
    const onlineListEl = document.querySelector('[data-vf-online-list]');
    if (onlineCountEl) {
      const mCount = Number(data.online_members_count || 0);
      const gCount = Number(data.online_guests_count || 0);
      onlineCountEl.textContent = `${mCount} aktywnych${gCount > 0 ? ` · ${gCount} gości` : ''}`;
    }
    if (onlineListEl && Array.isArray(data.online_members)) {
      if (data.online_members.length === 0) {
        onlineListEl.innerHTML = '<span class="vf-muted" style="font-size:0.84rem;">Brak zalogowanych użytkowników aktywnych w ostatnich 20 minutach.</span>';
      } else {
        onlineListEl.innerHTML = data.online_members
          .map((om) => {
            const name = String(om.username || 'Użytkownik');
            const init = name.slice(0, 2).toUpperCase();
            const slug = String(om.slug || 'profil');
            const tip = om.topic_id && om.topic_title ? `Czyta temat: ${om.topic_title}` : 'Aktywny teraz';
            const avatarInner = om.avatar_url
              ? `<img src="${esc(om.avatar_url)}" alt="${esc(name)}" data-vf-initials="${esc(init)}">`
              : esc(init);
            const nameHtml = formatUsernameHtml(name, om.group_color, om.group_prefix, om.group_suffix, om.group_name);
            return `<a href="${vfUrl(`/member/${Number(om.id)}-${esc(slug)}`)}" class="vf-online-chip" title="${esc(tip)}"><span class="vf-avatar" style="width:24px;height:24px;border-radius:7px;font-size:0.62rem;">${avatarInner}<span class="vf-avatar-online" style="width:7px;height:7px;border-width:1.5px;" aria-hidden="true"></span></span><span>${nameHtml}</span></a>`;
          })
          .join('');
      }
    }

    // 3. Kto aktualnie przegląda ten temat + baner nowych odpowiedzi
    const viewersEl = document.querySelector('[data-vf-topic-viewers]');
    if (viewersEl && Array.isArray(data.topic_members)) {
      const guests = Number(data.topic_guests_count || 0);
      const chipsHtml = data.topic_members
        .map((tm) => {
          const name = String(tm.username || 'Użytkownik');
          const init = name.slice(0, 2).toUpperCase();
          const slug = String(tm.slug || 'profil');
          const avatarInner = tm.avatar_url
            ? `<img src="${esc(tm.avatar_url)}" alt="${esc(name)}" data-vf-initials="${esc(init)}">`
            : esc(init);
          const nameHtml = formatUsernameHtml(name, tm.group_color, tm.group_prefix, tm.group_suffix, tm.group_name);
          return `<a href="${vfUrl(`/member/${Number(tm.id)}-${esc(slug)}`)}" class="vf-participant-chip"><span class="vf-avatar" style="width:20px;height:20px;border-radius:6px;font-size:0.6rem;">${avatarInner}</span>${nameHtml}</a>`;
        })
        .join('');
      const suffix = guests > 0
        ? `<span class="vf-muted" style="font-size:0.78rem;">+ ${guests} gości</span>`
        : (data.topic_members.length === 0 ? '<span class="vf-muted" style="font-size:0.78rem;">Tylko Ty przeglądasz ten temat</span>' : '');
      viewersEl.innerHTML = chipsHtml + suffix;
    }

    if (topicPresenceBar && data.topic_state) {
      const newCount = Number(data.topic_state.posts_count || 0);
      const newLastId = Number(data.topic_state.last_post_id || 0);
      const bannerEl = document.querySelector('[data-vf-topic-new-banner]');
      const bannerTextEl = document.querySelector('[data-vf-topic-new-text]');
      if (bannerEl && ((initialPostsCount > 0 && newCount > initialPostsCount) || (initialLastPostId > 0 && newLastId > initialLastPostId))) {
        const diff = Math.max(1, newCount - initialPostsCount);
        if (bannerTextEl) {
          const poster = data.topic_state.last_poster_name ? ` (ostatnio: ${data.topic_state.last_poster_name})` : '';
          bannerTextEl.textContent = `Pojawiły się nowe odpowiedzi w tym wątku (+${diff})${poster}.`;
        }
        bannerEl.hidden = false;
      }
    }
  };

  let presenceInFlight = false;
  const pingPresence = async () => {
    if (presenceInFlight || document.visibilityState === 'hidden') return;
    presenceInFlight = true;
    try {
      const fd = new FormData();
      fd.append('page_type', pageTypeMeta);
      if (topicIdMeta > 0) fd.append('topic_id', String(topicIdMeta));
      if (forumIdMeta > 0) fd.append('forum_id', String(forumIdMeta));
      const res = await fetch(vfUrl('/api/presence/ping'), {
        method: 'POST',
        body: fd,
        headers: { Accept: 'application/json' },
      });
      if (res.ok) {
        const payload = await res.json();
        applyLivePresencePayload(payload);
      }
    } catch {
      // Cichy fallback sieciowy
    } finally {
      presenceInFlight = false;
    }
  };

  setTimeout(pingPresence, 1200);
  setInterval(() => {
    if (document.visibilityState === 'visible') pingPresence();
  }, 15000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') pingPresence();
  });
  window.addEventListener('pagehide', () => {
    if (navigator.sendBeacon) {
      navigator.sendBeacon(vfUrl('/api/presence/leave'));
    }
  });

  // Prywatne wiadomości na żywo (autouzupełnianie odbiorcy, odpowiedź AJAX, polling co 4s)
  const pmRoot = document.querySelector('[data-vf-pm-root]');
  if (pmRoot) {
    const recInput = pmRoot.querySelector('[data-vf-pm-recipient-input]');
    const recIdInput = pmRoot.querySelector('[data-vf-pm-recipient-id]');
    const recBox = pmRoot.querySelector('[data-vf-pm-recipient-suggestions]');
    let recTimer = null;

    if (recInput && recBox) {
      recInput.addEventListener('input', () => {
        if (recIdInput) recIdInput.value = '0';
        const q = recInput.value.trim();
        clearTimeout(recTimer);
        if (q.length < 1) {
          recBox.hidden = true;
          recBox.innerHTML = '';
          return;
        }
        recTimer = setTimeout(async () => {
          try {
            const res = await fetch(vfUrl(`/api/members/search?q=${encodeURIComponent(q)}`), {
              headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            const items = data.items || [];
            if (items.length === 0) {
              recBox.hidden = true;
              recBox.innerHTML = '';
              return;
            }
            recBox.innerHTML = '';
            items.forEach((m) => {
              const btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'vf-pm-autocomplete-opt';
              btn.innerHTML = `${formatUsernameHtml(m.username, m.group_color, m.group_prefix, m.group_suffix, m.group_name)}<span class="vf-muted" style="font-size:0.75rem;">${esc(m.group_name || 'Użytkownik')}</span>`;
              btn.addEventListener('click', () => {
                recInput.value = String(m.username || '');
                if (recIdInput) recIdInput.value = String(m.id || 0);
                recBox.hidden = true;
              });
              recBox.appendChild(btn);
            });
            recBox.hidden = false;
          } catch {
            recBox.hidden = true;
          }
        }, 160);
      });
      document.addEventListener('click', (e) => {
        if (!recBox.contains(e.target) && e.target !== recInput) {
          recBox.hidden = true;
        }
      });
    }

    const threadEl = pmRoot.querySelector('[data-vf-pm-thread]');
    const replyForm = pmRoot.querySelector('[data-vf-pm-reply-form]');
    const replyInput = pmRoot.querySelector('[data-vf-pm-reply-input]');
    const convListEl = pmRoot.querySelector('[data-vf-pm-conv-list]');

    const scrollThreadToBottom = () => {
      if (threadEl) {
        threadEl.scrollTop = threadEl.scrollHeight;
      }
    };
    scrollThreadToBottom();

    const appendMessageBubble = (msg) => {
      if (!threadEl || !msg || !msg.id) return;
      const msgId = Number(msg.id);
      if (threadEl.querySelector(`[data-msg-id="${msgId}"]`)) return;
      const row = document.createElement('div');
      row.className = 'vf-pm-msg-row' + (msg.is_mine ? ' is-mine' : ' is-peer');
      row.setAttribute('data-msg-id', String(msgId));
      const senderHtml = formatUsernameHtml(
        msg.sender_name,
        msg.sender_color,
        msg.sender_prefix,
        msg.sender_suffix,
        msg.sender_group
      );
      row.innerHTML = `
        <div class="vf-pm-bubble${msg.is_mine ? ' is-mine' : ' is-peer'}">
          <div class="vf-pm-bubble-meta">
            <span>${senderHtml}</span>
            <span>${esc(msg.relative_time || 'przed chwilą')}</span>
          </div>
          <div class="vf-pm-bubble-body">${msg.content_html || ''}</div>
        </div>`;
      threadEl.appendChild(row);
      const curLast = Number(pmRoot.getAttribute('data-last-msg-id') || '0');
      if (msgId > curLast) {
        pmRoot.setAttribute('data-last-msg-id', String(msgId));
      }
      scrollThreadToBottom();
    };

    if (replyInput && replyForm) {
      replyInput.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
          e.preventDefault();
          replyForm.requestSubmit ? replyForm.requestSubmit() : replyForm.submit();
        }
      });
    }

    if (replyForm && replyInput) {
      let sendingReply = false;
      replyForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (sendingReply) return;
        const text = replyInput.value.trim();
        if (!text) return;
        sendingReply = true;
        try {
          const res = await fetch(replyForm.action, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'fetch',
            },
            body: new FormData(replyForm),
          });
          stopNavLoader();
          if (!res.ok) {
            replyForm.submit();
            return;
          }
          const data = await res.json();
          if (data.ok && data.message) {
            appendMessageBubble(data.message);
            replyInput.value = '';
            if (typeof data.unread_messages_count === 'number') {
              syncPmCounters(data.unread_messages_count);
            }
          }
        } catch {
          stopNavLoader();
          replyForm.submit();
        } finally {
          sendingReply = false;
        }
      });
    }

    let pmPolling = false;
    const pollMessagesLive = async () => {
      if (pmPolling || document.visibilityState === 'hidden') return;
      pmPolling = true;
      try {
        const convId = Number(pmRoot.getAttribute('data-conv-id') || '0');
        const afterId = Number(pmRoot.getAttribute('data-last-msg-id') || '0');
        const res = await fetch(vfUrl(`/api/messages/live?c=${convId}&after_id=${afterId}`), {
          headers: { Accept: 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        if (typeof data.unread_messages_count === 'number') {
          syncPmCounters(data.unread_messages_count);
        }
        if (Array.isArray(data.new_messages) && data.new_messages.length > 0) {
          data.new_messages.forEach(appendMessageBubble);
        }
        if (convListEl && Array.isArray(data.conversations) && data.conversations.length > 0) {
          data.conversations.forEach((c) => {
            const itemEl = convListEl.querySelector(`[data-conv-item-id="${Number(c.id)}"]`);
            if (!itemEl) return;
            itemEl.classList.toggle('is-unread', Boolean(c.is_unread));
            const snipEl = itemEl.querySelector('.vf-pm-snippet');
            if (snipEl) snipEl.textContent = String(c.last_snippet || '');
            let dotEl = itemEl.querySelector('.vf-pm-unread-dot');
            if (c.is_unread && !dotEl) {
              dotEl = document.createElement('span');
              dotEl.className = 'vf-pm-unread-dot';
              dotEl.title = 'Nieprzeczytana wiadomość';
              itemEl.appendChild(dotEl);
            } else if (!c.is_unread && dotEl) {
              dotEl.remove();
            }
          });
        }
      } catch {
        // Ciche odświeżanie w tle
      } finally {
        pmPolling = false;
      }
    };

    setInterval(() => {
      if (document.visibilityState === 'visible') pollMessagesLive();
    }, 4000);
  }

  // Podgląd tła profilowego (cover) przed zapisaniem profilu
  const coverInput = document.querySelector('[data-vf-cover-input]');
  if (coverInput) {
    const coverPreview = document.querySelector('[data-vf-cover-preview]');
    const coverFilename = document.querySelector('[data-vf-cover-filename]');
    coverInput.addEventListener('change', () => {
      const file = coverInput.files && coverInput.files[0];
      if (!file) return;
      const sizeKb = Math.max(1, Math.round(file.size / 1024));
      const tooBig = file.size > 4 * 1024 * 1024;
      if (coverFilename) {
        coverFilename.textContent = tooBig
          ? `${file.name} (${sizeKb} KB) — przekracza limit 4 MB!`
          : `${file.name} (${sizeKb} KB)`;
        coverFilename.style.color = tooBig ? '#dc2626' : '';
      }
      if (coverPreview && file.type.startsWith('image/') && !tooBig) {
        const objectUrl = URL.createObjectURL(file);
        coverPreview.style.backgroundImage = `linear-gradient(135deg, rgba(15, 23, 42, 0.55), rgba(15, 23, 42, 0.85)), url("${objectUrl}")`;
        const img = new Image();
        img.onload = () => URL.revokeObjectURL(objectUrl);
        img.src = objectUrl;
      }
    });
  }

  // Interaktywny ranking społeczności AJAX (Reputacja / Posty / Tematy × Tydzień / Miesiąc / Rok / Ogólnie)
  document.querySelectorAll('[data-vf-leaderboard]').forEach((lbRoot) => {
    const listEl = lbRoot.querySelector('[data-vf-lb-list]');
    const metricBtns = Array.from(lbRoot.querySelectorAll('[data-vf-lb-metric]'));
    const periodBtns = Array.from(lbRoot.querySelectorAll('[data-vf-lb-period]'));
    if (!listEl) return;

    let currentMetric = lbRoot.getAttribute('data-metric') || 'reputation';
    let currentPeriod = lbRoot.getAttribute('data-period') || 'all';
    const lbCache = new Map();

    const unitLabel = (metric) => {
      if (metric === 'posts') return 'postów';
      if (metric === 'topics') return 'tematów';
      return 'pkt';
    };

    const renderLeaderboard = (items, metric, unitOverride) => {
      if (!Array.isArray(items) || items.length === 0) {
        listEl.innerHTML = '<div class="vf-row"><span class="vf-muted">Brak aktywności w wybranym okresie.</span></div>';
        return;
      }
      const topScore = Math.max(1, Number(items[0].score || 1));
      const unit = unitOverride || unitLabel(metric);
      listEl.innerHTML = items
        .map((rm, idx) => {
          const score = Number(rm.score || 0);
          const barPct = Math.max(8, Math.round((score / topScore) * 100));
          const rmName = String(rm.username || 'Użytkownik');
          const rmInit = rmName.slice(0, 2).toUpperCase();
          const slug = String(rm.slug || 'profil');
          const profileHref = rm.profile_url || vfUrl(`/member/${Number(rm.id)}-${esc(slug)}`);
          const avatarHtml = rm.avatar_url
            ? `<img src="${esc(rm.avatar_url)}" alt="${esc(rmName)}" data-vf-initials="${esc(rmInit)}" loading="lazy" decoding="async">`
            : esc(rmInit);
          const gColor = safeColorHex(rm.group_color);
          const nameHtml = rm.formatted_html || formatUsernameHtml(rmName, rm.group_color, rm.group_prefix, rm.group_suffix, rm.group_name);
          return `
            <div class="vf-rep-row">
              <div class="vf-rep-top">
                <div class="vf-rep-user">
                  <span class="vf-rep-rank">#${idx + 1}</span>
                  <span class="vf-avatar" style="width:32px;height:32px;border-radius:9px;">${avatarHtml}</span>
                  <div style="min-width:0;">
                    <a href="${esc(profileHref)}" style="display:block;font-size:0.84rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${nameHtml}</a>
                    <div style="font-size:0.72rem;font-weight:600;color:${esc(gColor)};">${esc(rm.group_name || 'Użytkownik')}</div>
                  </div>
                </div>
                <strong style="font-size:0.78rem;font-variant-numeric:tabular-nums;color:var(--accent);white-space:nowrap;">${score} ${esc(unit)}</strong>
              </div>
              <div class="vf-rep-bar" aria-hidden="true"><span style="width:${barPct}%;"></span></div>
            </div>`;
        })
        .join('');
    };

    const syncButtons = () => {
      metricBtns.forEach((b) => b.classList.toggle('is-active', b.getAttribute('data-vf-lb-metric') === currentMetric));
      periodBtns.forEach((b) => b.classList.toggle('is-active', b.getAttribute('data-vf-lb-period') === currentPeriod));
    };

    const loadLeaderboard = async (metric, period) => {
      currentMetric = metric;
      currentPeriod = period;
      syncButtons();
      const cacheKey = `${metric}:${period}`;
      if (lbCache.has(cacheKey)) {
        const cached = lbCache.get(cacheKey);
        renderLeaderboard(cached.rows, metric, cached.unit);
        return;
      }
      listEl.classList.add('is-loading');
      try {
        const res = await fetch(vfUrl(`/api/leaderboards?metric=${encodeURIComponent(metric)}&period=${encodeURIComponent(period)}`), {
          headers: { Accept: 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();
        const rows = Array.isArray(data?.rows) ? data.rows : (Array.isArray(data?.items) ? data.items : null);
        if (data && data.ok && rows) {
          lbCache.set(cacheKey, { rows, unit: data.unit || unitLabel(metric) });
          if (currentMetric === metric && currentPeriod === period) {
            renderLeaderboard(rows, metric, data.unit || unitLabel(metric));
          }
        }
      } catch {
        // Cichy fallback sieciowy
      } finally {
        listEl.classList.remove('is-loading');
      }
    };

    metricBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const m = btn.getAttribute('data-vf-lb-metric');
        if (m && m !== currentMetric) loadLeaderboard(m, currentPeriod);
      });
    });
    periodBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const p = btn.getAttribute('data-vf-lb-period');
        if (p && p !== currentPeriod) loadLeaderboard(currentMetric, p);
      });
    });
  });

  // Wizytówka użytkownika po najechaniu na nick (Member Hovercard)
  const hovercardEl = document.querySelector('[data-vf-hovercard]');
  const isTouchOnly = window.matchMedia && window.matchMedia('(hover: none)').matches;
  if (hovercardEl && !isTouchOnly) {
    const cardCache = new Map();
    let showTimer = null;
    let hideTimer = null;
    let activeMemberId = 0;

    const extractMemberIdFromLink = (anchor) => {
      if (!anchor || anchor.closest('[data-vf-hovercard]') || anchor.classList.contains('vf-user-link')) return 0;
      const href = anchor.getAttribute('href') || '';
      const m = href.match(/\/member\/(\d+)(?:[-/?#]|$)/);
      return m ? Number(m[1]) : 0;
    };

    const positionHovercard = (anchorRect) => {
      const cardW = 318;
      const cardH = hovercardEl.offsetHeight || 248;
      const left = Math.max(12, Math.min(window.innerWidth - cardW - 12, anchorRect.left));
      let top = anchorRect.bottom + 8;
      if (top + cardH > window.innerHeight - 12) {
        top = Math.max(12, anchorRect.top - cardH - 8);
      }
      hovercardEl.style.left = `${left}px`;
      hovercardEl.style.top = `${top}px`;
    };

    const renderHovercard = (c, anchorRect) => {
      const name = String(c.username || 'Użytkownik');
      const init = name.slice(0, 2).toUpperCase();
      const avatarHtml = c.avatar_url
        ? `<img src="${esc(c.avatar_url)}" alt="${esc(name)}" data-vf-initials="${esc(init)}">`
        : esc(init);
      const coverStyle = c.cover_url ? ` style="background-image:url('${esc(c.cover_url)}')"` : '';
      const coverCls = c.cover_url ? 'vf-hovercard-cover has-cover' : 'vf-hovercard-cover';
      const badges = Array.isArray(c.achievements) ? c.achievements : [];
      const badgesHtml = badges.length > 0
        ? `<div class="vf-hovercard-badges">${badges.map((b) => `<span class="vf-author-badge-ico" style="background:${esc(b.color || '#2563eb')};" title="${esc(b.title || '')}">${b.svg || ''}</span>`).join('')}</div>`
        : '';

      hovercardEl.innerHTML = `
        <div class="${coverCls}"${coverStyle}></div>
        <div class="vf-hovercard-body">
          <div class="vf-hovercard-head">
            <span class="vf-avatar vf-hovercard-avatar">
              ${avatarHtml}
              ${c.is_online ? '<span class="vf-avatar-online" title="Aktywny teraz"></span>' : ''}
            </span>
            <div style="min-width:0;flex:1;">
              <div style="font-size:0.95rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${c.formatted_html || esc(name)}</div>
              <div style="font-size:0.75rem;font-weight:700;color:${esc(c.group_color || '#475569')};">${esc(c.group_name || 'Użytkownik')}${c.member_title ? ` · <span class="vf-muted">${esc(c.member_title)}</span>` : ''}</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span class="vf-presence-badge${c.is_online ? ' is-online' : ''}" style="font-size:0.7rem;">
              <span class="vf-presence-dot${c.is_online ? ' is-online' : ' is-offline'}" aria-hidden="true"></span>
              <span>${esc(c.presence_text || '')}</span>
            </span>
            <span class="vf-muted" style="font-size:0.72rem;">Od ${esc(c.joined_text || '')}</span>
          </div>
          <div class="vf-hovercard-stats">
            <div><strong>${Number(c.posts_count || 0)}</strong><span>Posty</span></div>
            <div><strong>${Number(c.topics_count || 0)}</strong><span>Tematy</span></div>
            <div><strong style="color:var(--accent);">${Number(c.reputation_points || 0)} pkt</strong><span>Reputacja</span></div>
          </div>
          ${badgesHtml}
          <div class="vf-hovercard-actions">
            <a href="${esc(c.profile_url || '#')}" class="vf-btn vf-btn-sm vf-btn-secondary">Profil</a>
            <a href="${esc(c.pm_url || '/messages')}" class="vf-btn vf-btn-sm">Wiadomość</a>
          </div>
        </div>`;
      hovercardEl.hidden = false;
      positionHovercard(anchorRect);
    };

    const scheduleHideHovercard = () => {
      clearTimeout(showTimer);
      clearTimeout(hideTimer);
      hideTimer = setTimeout(() => {
        hovercardEl.hidden = true;
        activeMemberId = 0;
      }, 190);
    };

    document.addEventListener('pointerover', (e) => {
      if (e.target && hovercardEl.contains(e.target)) {
        clearTimeout(hideTimer);
        return;
      }
      const anchor = e.target?.closest?.('a[href]');
      const mid = extractMemberIdFromLink(anchor);
      if (!mid) return;
      clearTimeout(hideTimer);
      clearTimeout(showTimer);
      showTimer = setTimeout(async () => {
        activeMemberId = mid;
        const rect = anchor.getBoundingClientRect();
        if (cardCache.has(mid)) {
          if (activeMemberId === mid) renderHovercard(cardCache.get(mid), rect);
          return;
        }
        try {
          const res = await fetch(vfUrl(`/api/member-card?id=${mid}`), { headers: { Accept: 'application/json' } });
          if (!res.ok) return;
          const data = await res.json();
          if (data && data.ok) {
            cardCache.set(mid, data);
            if (activeMemberId === mid) renderHovercard(data, anchor.getBoundingClientRect());
          }
        } catch {}
      }, 220);
    });

    document.addEventListener('pointerout', (e) => {
      const anchor = e.target?.closest?.('a[href]');
      const mid = extractMemberIdFromLink(anchor);
      if (mid || (e.target && hovercardEl.contains(e.target))) {
        const related = e.relatedTarget;
        if (related && hovercardEl.contains(related)) return;
        scheduleHideHovercard();
      }
    });

    hovercardEl.addEventListener('pointerenter', () => clearTimeout(hideTimer));
    hovercardEl.addEventListener('pointerleave', scheduleHideHovercard);
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !hovercardEl.hidden) {
        hovercardEl.hidden = true;
        activeMemberId = 0;
      }
    });
  }

  // Moduł Czat na żywo (Live Chatbox)
  const chatRoot = document.querySelector('[data-vf-chatbox]');
  if (chatRoot) {
    const csrf = chatRoot.getAttribute('data-csrf') || '';
    const allowGif = chatRoot.getAttribute('data-allow-gif') === '1';
    const allowReactions = chatRoot.getAttribute('data-allow-reactions') === '1';
    const canUseChat = chatRoot.getAttribute('data-can-use') === '1';
    const viewerId = Number(chatRoot.getAttribute('data-viewer-id') || '0');
    const streamEl = chatRoot.querySelector('[data-vf-chat-list]');
    const pinnedEl = chatRoot.querySelector('[data-vf-chat-pinned]');
    const formEl = chatRoot.querySelector('[data-vf-chat-form]');
    const inputEl = chatRoot.querySelector('[data-vf-chat-input]');
    const replyBanner = chatRoot.querySelector('[data-vf-chat-reply-banner]');
    const replyNameEl = chatRoot.querySelector('[data-vf-chat-reply-name]');
    const replyClearBtn = chatRoot.querySelector('[data-vf-chat-reply-clear]');
    const emojiBtn = chatRoot.querySelector('[data-vf-chat-emoji-btn]');
    const emojiPop = chatRoot.querySelector('[data-vf-chat-emoji-pop]');
    const gifBtn = chatRoot.querySelector('[data-vf-chat-gif-btn]');
    const gifPop = chatRoot.querySelector('[data-vf-chat-gif-pop]');
    const soundBtn = chatRoot.querySelector('[data-vf-chat-sound-btn]');

    const chatReactEmojis = ['👍', '❤️', '😂', '🔥', '🙌'];
    let lastKnownId = Number(chatRoot.getAttribute('data-last-id') || '0');
    let replyToUser = '';
    let soundOn = false;
    try {
      soundOn = localStorage.getItem('vf-chat-sound') === '1';
    } catch {}

    const syncSoundBtn = () => {
      if (!soundBtn) return;
      soundBtn.classList.toggle('is-accent', soundOn);
      soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
    };
    syncSoundBtn();

    const playChatPop = () => {
      if (!soundOn) return;
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(560, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(760, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.04, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.11);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.12);
      } catch {}
    };

    if (soundBtn) {
      soundBtn.addEventListener('click', () => {
        soundOn = !soundOn;
        try { localStorage.setItem('vf-chat-sound', soundOn ? '1' : '0'); } catch {}
        syncSoundBtn();
        if (soundOn) playChatPop();
      });
    }

    if (streamEl) {
      streamEl.scrollTop = streamEl.scrollHeight;
    }

    const setReplyTarget = (username) => {
      replyToUser = username || '';
      if (replyBanner && replyNameEl) {
        replyBanner.hidden = !replyToUser;
        replyNameEl.textContent = replyToUser ? `@${replyToUser}` : '';
      }
      if (replyToUser && inputEl) {
        if (!inputEl.value.includes(`@${replyToUser}`)) {
          inputEl.value = `@${replyToUser} ${inputEl.value}`.trimStart();
        }
        inputEl.focus();
      }
    };
    replyClearBtn?.addEventListener('click', () => setReplyTarget(''));

    const renderPinnedBar = (pinned, canMod) => {
      if (!pinnedEl) return;
      if (!pinned) {
        pinnedEl.hidden = true;
        pinnedEl.innerHTML = '';
        return;
      }
      const txt = String(pinned.body_html || '').replace(/<[^>]+>/g, '');
      pinnedEl.innerHTML = `
        <span class="vf-chat-pinned-tag">Przypięte</span>
        <strong style="white-space:nowrap;">${esc(pinned.username || '')}:</strong>
        <span class="vf-chat-pinned-text">${esc(txt)}</span>
        ${canMod ? `<button type="button" class="vf-chat-unpin-btn" data-vf-chat-pin="${Number(pinned.id)}" title="Odepnij wiadomość">✕</button>` : ''}`;
      pinnedEl.hidden = false;
    };

    const renderReactionsMarkup = (msgId, reactions) => {
      if (!allowReactions) return '';
      const chips = (Array.isArray(reactions) ? reactions : [])
        .map(
          (r) => `
          <button type="button" class="vf-chat-react-chip${r.reacted ? ' is-mine' : ''}" data-vf-chat-react="${msgId}" data-emoji="${esc(r.emoji)}"${!canUseChat ? ' disabled' : ''}>
            <span>${esc(r.emoji)}</span><span>${Number(r.count || 0)}</span>
          </button>`
        )
        .join('');
      const adder = canUseChat
        ? `<div class="vf-chat-react-adder"><button type="button" class="vf-chat-react-plus" title="Dodaj reakcję">+</button><div class="vf-chat-react-tray">${chatReactEmojis.map((ce) => `<button type="button" class="vf-chat-react-opt" data-vf-chat-react="${msgId}" data-emoji="${esc(ce)}">${ce}</button>`).join('')}</div></div>`
        : '';
      return `<div class="vf-chat-reactions" data-vf-chat-reactions="${msgId}">${chips}${adder}</div>`;
    };

    const renderChatMessages = (messages) => {
      if (!streamEl) return;
      const nearBottom = streamEl.scrollHeight - streamEl.scrollTop - streamEl.clientHeight < 70;
      if (!Array.isArray(messages) || messages.length === 0) {
        streamEl.innerHTML = '<div class="vf-chat-empty" data-vf-chat-empty>Brak wiadomości na czacie. Przywitaj się jako pierwszy!</div>';
        return;
      }

      let newestIncomingId = lastKnownId;
      let hasForeignNew = false;

      streamEl.innerHTML = messages
        .map((cm) => {
          const id = Number(cm.id || 0);
          if (id > newestIncomingId) {
            newestIncomingId = id;
            if (Number(cm.member_id || 0) !== viewerId) hasForeignNew = true;
          }
          const uname = String(cm.username || 'Użytkownik');
          const init = uname.slice(0, 2).toUpperCase();
          const avHtml = cm.avatar_url
            ? `<img src="${esc(cm.avatar_url)}" alt="${esc(uname)}" data-vf-initials="${esc(init)}" loading="lazy" decoding="async">`
            : esc(init);
          const attUrl = String(cm.attachment_url || '');
          const mediaHtml = attUrl
            ? `<div class="vf-chat-media">${/\.(mp4|webm)(\?|$)/i.test(attUrl) ? `<video src="${esc(attUrl)}" autoplay loop muted playsinline controls></video>` : `<img src="${esc(attUrl)}" alt="Klip z Klipy.com" loading="lazy">`}</div>`
            : '';

          return `
            <div class="vf-chat-msg${cm.is_action ? ' is-action' : ''}" data-chat-msg-id="${id}">
              <a href="${esc(cm.profile_url || '#')}" class="vf-avatar vf-chat-avatar">${avHtml}</a>
              <div class="vf-chat-msg-body">
                <div class="vf-chat-msg-meta">
                  <a href="${esc(cm.profile_url || '#')}" class="vf-chat-author">${cm.formatted_html || esc(uname)}</a>
                  ${cm.reply_to_username ? `<span class="vf-chat-reply-tag">↪ @${esc(cm.reply_to_username)}</span>` : ''}
                  <span class="vf-chat-time">${esc(cm.relative_time || 'przed chwilą')}</span>
                  <div class="vf-chat-msg-tools">
                    ${canUseChat ? `<button type="button" class="vf-chat-tool-btn" data-vf-chat-reply="${esc(uname)}" title="Odpowiedz @${esc(uname)}">@</button>` : ''}
                    ${cm.can_pin ? `<button type="button" class="vf-chat-tool-btn" data-vf-chat-pin="${id}" title="Przypnij / Odepnij">📌</button>` : ''}
                    ${cm.can_mute ? `<button type="button" class="vf-chat-tool-btn" data-vf-chat-mute="${Number(cm.member_id)}" data-mute-name="${esc(uname)}" title="Wycisz na 30 min">🔇</button>` : ''}
                    ${cm.can_delete ? `<button type="button" class="vf-chat-tool-btn is-danger" data-vf-chat-delete="${id}" title="Usuń wiadomość">✕</button>` : ''}
                  </div>
                </div>
                ${cm.body_html ? `<div class="vf-chat-msg-text">${cm.body_html}</div>` : ''}
                ${mediaHtml}
                ${renderReactionsMarkup(id, cm.reactions)}
              </div>
            </div>`;
        })
        .join('');

      if (newestIncomingId > lastKnownId) {
        if (lastKnownId > 0 && hasForeignNew) playChatPop();
        lastKnownId = newestIncomingId;
        chatRoot.setAttribute('data-last-id', String(lastKnownId));
      }
      if (nearBottom) {
        streamEl.scrollTop = streamEl.scrollHeight;
      }
    };

    const sendChatPayload = async (messageText, attachmentUrl) => {
      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('message', messageText || '');
      if (attachmentUrl) fd.append('attachment_url', attachmentUrl);
      if (replyToUser) fd.append('reply_to_username', replyToUser);

      const res = await fetch(vfUrl('/api/chat/send'), {
        method: 'POST',
        body: fd,
        headers: { Accept: 'application/json' },
      });
      stopNavLoader();
      const data = await res.json();
      if (!res.ok || !data.ok) {
        showToast(data.error || 'Nie udało się wysłać wiadomości.');
        return false;
      }
      setReplyTarget('');
      await pollChatLive(true);
      if (streamEl) streamEl.scrollTop = streamEl.scrollHeight;
      return true;
    };

    if (formEl && inputEl) {
      let chatSending = false;
      formEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (chatSending) return;
        const msg = inputEl.value.trim();
        if (!msg) return;
        chatSending = true;
        try {
          const ok = await sendChatPayload(msg, '');
          if (ok) inputEl.value = '';
        } finally {
          chatSending = false;
        }
      });
    }

    if (emojiBtn && emojiPop && inputEl) {
      emojiPop.innerHTML = `<div class="vf-emoji-grid">${quickEmojis.map((em) => `<button type="button" class="vf-emoji-opt" data-vf-chat-pick-emoji="${esc(em)}">${em}</button>`).join('')}</div>`;
      emojiBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (gifPop) gifPop.hidden = true;
        emojiPop.hidden = !emojiPop.hidden;
      });
      emojiPop.addEventListener('click', (e) => {
        const pick = e.target?.closest?.('[data-vf-chat-pick-emoji]');
        if (!pick) return;
        inputEl.value += (pick.getAttribute('data-vf-chat-pick-emoji') || '') + ' ';
        emojiPop.hidden = true;
        inputEl.focus();
      });
    }

    if (gifBtn && gifPop && allowGif) {
      gifPop.innerHTML = `
        <div style="display:grid;gap:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:0.8rem;">Klipy.com — Wyślij GIF</strong>
            <button type="button" class="vf-chat-tool-btn" data-vf-chat-gif-close>✕</button>
          </div>
          <div style="display:flex;gap:6px;">
            <input type="text" class="vf-input" data-vf-chat-gif-q placeholder="Szukaj GIF..." />
            <button type="button" class="vf-btn vf-btn-sm" data-vf-chat-gif-go>Szukaj</button>
          </div>
          <div data-vf-chat-gif-res style="font-size:0.75rem;color:var(--muted);"></div>
          <div style="display:flex;gap:6px;border-top:1px solid var(--border);padding-top:8px;">
            <input type="url" class="vf-input" data-vf-chat-gif-url placeholder="lub wklej link z klipy.com..." />
            <button type="button" class="vf-btn vf-btn-sm" data-vf-chat-gif-send>Wyślij</button>
          </div>
        </div>`;
      const qIn = gifPop.querySelector('[data-vf-chat-gif-q]');
      const resBox = gifPop.querySelector('[data-vf-chat-gif-res]');
      const uIn = gifPop.querySelector('[data-vf-chat-gif-url]');

      const runChatKlipySearch = async () => {
        if (!resBox) return;
        resBox.textContent = 'Ładowanie z Klipy.com...';
        try {
          const res = await fetch(vfUrl(`/api/klipy?q=${encodeURIComponent((qIn?.value || '').trim())}&type=gifs`), {
            headers: { Accept: 'application/json' },
          });
          const data = await res.json();
          if (!data.configured) {
            resBox.textContent = 'Brak klucza API Klipy w ACP — użyj bezpośredniego linku poniżej.';
            return;
          }
          const items = data.items || [];
          if (items.length === 0) {
            resBox.textContent = 'Brak wyników.';
            return;
          }
          resBox.innerHTML = `<div class="vf-klipy-grid">${items
            .map((it) => `<button type="button" class="vf-klipy-item" data-vf-chat-gif-pick="${esc(it.mediaUrl || it.previewUrl)}" title="${esc(it.title || 'GIF')}"><img src="${esc(it.previewUrl || it.mediaUrl)}" alt="" loading="lazy"></button>`)
            .join('')}</div>`;
        } catch {
          resBox.textContent = 'Nie udało się połączyć z Klipy.com.';
        }
      };

      gifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (emojiPop) emojiPop.hidden = true;
        gifPop.hidden = !gifPop.hidden;
        if (!gifPop.hidden) setTimeout(() => qIn?.focus(), 20);
      });
      gifPop.querySelector('[data-vf-chat-gif-close]')?.addEventListener('click', () => { gifPop.hidden = true; });
      gifPop.querySelector('[data-vf-chat-gif-go]')?.addEventListener('click', runChatKlipySearch);
      qIn?.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') { ev.preventDefault(); runChatKlipySearch(); }
      });
      gifPop.addEventListener('click', async (e) => {
        const pick = e.target?.closest?.('[data-vf-chat-gif-pick]');
        if (!pick) return;
        const url = pick.getAttribute('data-vf-chat-gif-pick') || '';
        gifPop.hidden = true;
        if (url) await sendChatPayload('', url);
      });
      gifPop.querySelector('[data-vf-chat-gif-send]')?.addEventListener('click', async () => {
        const url = (uIn?.value || '').trim();
        if (!url) return;
        const ok = await sendChatPayload('', url);
        if (ok) {
          uIn.value = '';
          gifPop.hidden = true;
        }
      });
    }

    document.addEventListener('click', (e) => {
      if (emojiPop && !emojiPop.contains(e.target) && e.target !== emojiBtn) emojiPop.hidden = true;
      if (gifPop && !gifPop.contains(e.target) && e.target !== gifBtn) gifPop.hidden = true;
    });

    chatRoot.addEventListener('click', async (e) => {
      const replyBtn = e.target?.closest?.('[data-vf-chat-reply]');
      if (replyBtn) {
        setReplyTarget(replyBtn.getAttribute('data-vf-chat-reply') || '');
        return;
      }

      const reactBtn = e.target?.closest?.('[data-vf-chat-react]');
      if (reactBtn && canUseChat) {
        const msgId = Number(reactBtn.getAttribute('data-vf-chat-react') || '0');
        const em = reactBtn.getAttribute('data-emoji') || '';
        if (!msgId || !em) return;
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('message_id', String(msgId));
        fd.append('emoji', em);
        try {
          const res = await fetch(vfUrl('/api/chat/react'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json();
          if (data && data.ok) {
            const box = chatRoot.querySelector(`[data-vf-chat-reactions="${msgId}"]`);
            if (box) box.outerHTML = renderReactionsMarkup(msgId, data.reactions || []);
          }
        } catch {}
        return;
      }

      const delBtn = e.target?.closest?.('[data-vf-chat-delete]');
      if (delBtn) {
        const msgId = Number(delBtn.getAttribute('data-vf-chat-delete') || '0');
        if (!msgId) return;
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('message_id', String(msgId));
        try {
          const res = await fetch(vfUrl('/api/chat/delete'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json();
          if (data && data.ok) {
            chatRoot.querySelector(`[data-chat-msg-id="${msgId}"]`)?.remove();
            showToast('Usunięto wiadomość z czatu.');
          }
        } catch {}
        return;
      }

      const pinBtn = e.target?.closest?.('[data-vf-chat-pin]');
      if (pinBtn) {
        const msgId = Number(pinBtn.getAttribute('data-vf-chat-pin') || '0');
        if (!msgId) return;
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('message_id', String(msgId));
        try {
          const res = await fetch(vfUrl('/api/chat/pin'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json();
          if (data && data.ok) {
            renderPinnedBar(data.pinned || null, true);
            showToast(data.is_pinned ? 'Przypięto wiadomość na czacie.' : 'Odpięto wiadomość.');
          }
        } catch {}
        return;
      }

      const muteBtn = e.target?.closest?.('[data-vf-chat-mute]');
      if (muteBtn) {
        const mid = Number(muteBtn.getAttribute('data-vf-chat-mute') || '0');
        const mName = muteBtn.getAttribute('data-mute-name') || 'użytkownika';
        if (!mid) return;
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('member_id', String(mid));
        fd.append('minutes', '30');
        try {
          const res = await fetch(vfUrl('/api/chat/mute'), { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
          const data = await res.json();
          if (data && data.ok) {
            showToast(`Wyciszono ${mName} na czacie (30 min).`);
          }
        } catch {}
      }
    });

    let chatPolling = false;
    async function pollChatLive(force) {
      if (chatPolling || (!force && document.visibilityState === 'hidden')) return;
      chatPolling = true;
      try {
        const res = await fetch(vfUrl('/api/chat/live?after_id=0'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.ok || !data.enabled) return;
        renderPinnedBar(data.pinned || null, Boolean(data.can_moderate));
        if (Array.isArray(data.messages)) renderChatMessages(data.messages);
      } catch {
      } finally {
        chatPolling = false;
      }
    }

    setInterval(() => {
      if (document.visibilityState === 'visible') pollChatLive(false);
    }, 4500);
  }
})();



