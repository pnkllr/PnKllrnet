(() => {
  const f = (id) => {
    const inp  = document.getElementById(id+'Search');
    const list = document.getElementById(id === 'user' ? 'users' : 'tokens');
    if (!inp || !list) return;

    inp.addEventListener('input', () => {
      const q = inp.value.trim().toLowerCase();
      list.querySelectorAll('[data-q]').forEach(card => {
        const hay = (card.getAttribute('data-q') || '').toLowerCase();
        card.style.display = hay.includes(q) ? '' : 'none';
      });
    });
  };
  f('user');
  f('token');
})();