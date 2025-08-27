document.addEventListener('DOMContentLoaded', () => {
  const codeEl  = document.getElementById('tok-value');
  const toggle  = document.getElementById('tok-toggle');
  const copyBtn = document.getElementById('tok-copy');

  function maskMiddle(s) {
    if (!s) return '';
    if (s.length <= 8) return '•'.repeat(s.length);
    return s.slice(0,4) + '•'.repeat(s.length - 8) + s.slice(-4);
    // Note: visually masked; underlying full token lives in data-token
  }

  if (codeEl && toggle) {
    toggle.addEventListener('click', () => {
      const visible = codeEl.dataset.visible === 'true';
      const raw = codeEl.dataset.token || '';
      codeEl.dataset.visible = (!visible).toString();
      codeEl.textContent = visible ? maskMiddle(raw) : raw;
      toggle.textContent = visible ? 'Show' : 'Hide';
    });
  }

  if (codeEl && copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const raw = codeEl.dataset.token || '';
      try {
        await navigator.clipboard.writeText(raw);
        const old = copyBtn.textContent;
        copyBtn.textContent = 'Copied!';
        setTimeout(() => (copyBtn.textContent = old), 1200);
      } catch {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = raw;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } finally { document.body.removeChild(ta); }
      }
    });
  }
});

// /public/assets/dashboard-tools.js
(function () {
  'use strict';
  if (window.__copyBound) return; window.__copyBound = true;

  function flash(btn, msg) {
    const label = btn.querySelector('span') || btn;
    const prev  = label.textContent;
    label.textContent = msg;
    setTimeout(() => { label.textContent = prev; }, 1200);
  }

  function fallbackCopy(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (_) { ok = false; }
    ta.remove();
    if (!ok) {
      // Last resort so the user can copy under strict sandbox/CSP
      window.prompt('Copy URL:', text);
      ok = true;
    }
    flash(btn, ok ? 'Copied!' : 'Copy failed');
  }

  async function copyText(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
      try { await navigator.clipboard.writeText(text); flash(btn, 'Copied!'); return; }
      catch (_) { /* fall back */ }
    }
    fallbackCopy(text, btn);
  }

  // One global delegated listener (works for dynamically rendered cards)
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-copy');
    if (!btn) return;
    e.preventDefault();
    const text = btn.getAttribute('data-copy') || '';
    if (!text) { flash(btn, 'No URL'); return; }
    copyText(text, btn);
  });
})();

