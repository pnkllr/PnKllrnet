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
