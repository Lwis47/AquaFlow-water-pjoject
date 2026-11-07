document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-menu-toggle]');
  const sidebar = document.querySelector('.sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
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
    input.addEventListener('input', () => {
      const term = input.value.toLowerCase();
      const rows = table.matches('table') ? table.querySelectorAll('tbody tr') : table.querySelectorAll('.client-card');
      rows.forEach(row => {
        const contents = row.textContent.toLowerCase();
        row.hidden = !contents.includes(term);
      });
    });
  });
});
