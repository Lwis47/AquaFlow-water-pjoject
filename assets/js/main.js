document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-menu-toggle]');
  const sidebar = document.querySelector('.sidebar');
  if (toggle && sidebar) {
    const closeMenu = () => { sidebar.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.setAttribute('aria-expanded', 'false');
    toggle.addEventListener('click', () => {
      toggle.setAttribute('aria-expanded', String(sidebar.classList.toggle('open')));
    });
    document.addEventListener('click', event => {
      if (!sidebar.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  }

  const toast = document.querySelector('.toast');
  const showToast = (message) => {
    if (!toast) return;
    toast.querySelector('span').textContent = message;
    toast.classList.add('show');
    window.clearTimeout(window.aquaToastTimer);
    window.aquaToastTimer = window.setTimeout(() => {
      toast.classList.remove('show');
    }, 3200);
  };
  window.AquaFlowToast = showToast;
  document.querySelectorAll('[data-toast]').forEach(button => {
    button.addEventListener('click', event => {
      event.preventDefault();
      showToast(button.dataset.toast);
    });
  });

  document.querySelectorAll('[data-filter-table]').forEach(input => {
    const table = document.querySelector(input.dataset.filterTable);
    if (!table) return;
    const companion = input.dataset.filterSelect ? document.querySelector(input.dataset.filterSelect) : null;
    const apply = () => {
      const term = input.value.toLowerCase();
      const category = companion ? companion.value : '';
      const rows = table.matches('table') ? table.querySelectorAll('tbody tr') : table.querySelectorAll('.client-card');
      rows.forEach(row => {
        const statusSelect = row.querySelector('[data-application-status]');
        const contents = statusSelect
          ? [...row.cells].map(cell => cell.contains(statusSelect) ? statusSelect.value : cell.textContent).join(' ').toLowerCase()
          : row.textContent.toLowerCase();
        const categoryMatch = !category || (statusSelect ? statusSelect.value === category : row.dataset.category === category);
        row.hidden = !contents.includes(term) || !categoryMatch;
      });
    };
    input.addEventListener('input', apply);
    companion?.addEventListener('change', apply);
  });

  const dateEl = document.getElementById('dashboard-date');
  if (dateEl) {
    dateEl.textContent = new Intl.DateTimeFormat('en-KE', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());
  }
  const greetingEl = document.getElementById('dashboard-greeting');
  if (greetingEl) {
    const hour = new Date().getHours();
    const part = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    greetingEl.textContent = `Good ${part}, Alex.`;
  }
});
