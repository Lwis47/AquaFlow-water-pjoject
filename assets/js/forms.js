document.addEventListener('DOMContentLoaded', () => {
  const api = window.AquaFlowApi;
  const notify = window.AquaFlowToast || (() => {});

  function value(id) {
    return document.getElementById(id)?.value.trim() || '';
  }

  function buttonState(button, loading) {
    if (!button) return;
    button.disabled = loading;
    button.dataset.label ||= button.textContent;
    button.textContent = loading ? 'Saving...' : button.dataset.label;
  }

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
  }[character]));
  const dateLabel = value => value ? new Intl.DateTimeFormat('en-KE', { month: 'short', year: 'numeric' }).format(new Date(`${String(value).replace(' ', 'T')}Z`)) : 'Today';

  async function refreshClients() {
    const list = document.getElementById('client-list');
    if (!list) return;
    try {
      const clients = await api.clients();
      const quoteClient = document.getElementById('quote-client');
      const quoteOptions = document.getElementById('quote-client-options');
      if (quoteClient && quoteOptions) {
        quoteClient.dataset.clients = JSON.stringify(clients.map(client => ({ id: client.id, name: client.name })));
        quoteOptions.innerHTML = clients.map(client => `<option value="${escapeHtml(client.name)}">${escapeHtml(client.category)} · ${escapeHtml(client.telephone)}</option>`).join('');
      }
      list.innerHTML = clients.length ? clients.map(client => {
        const initials = client.name.split(/\s+/).map(part => part[0]).slice(0, 2).join('').toUpperCase();
        const projects = Number(client.application_count) || 0;
        return `<article class="client-card" data-client-id="${client.id}">
          <span class="mini-avatar">${escapeHtml(initials)}</span>
          <div><h3>${escapeHtml(client.name)}</h3><p>${escapeHtml(client.category)} · ${escapeHtml(client.address)} · ${escapeHtml(client.telephone)}</p></div>
          <div class="client-meta"><b>${projects ? `${projects} active project${projects === 1 ? '' : 's'}` : 'No active projects'}</b>Client since ${dateLabel(client.created_at)}<span><button class="link" type="button" data-edit-client="${client.id}">Edit</button> <button class="link" type="button" data-delete-client="${client.id}">Remove</button></span></div>
        </article>`;
      }).join('') : '<div class="empty-state"><h3>No clients yet</h3><p>Add your first client below to begin.</p></div>';
    } catch (error) { notify(error.message); }
  }

  async function refreshApplications() {
    const body = document.querySelector('#applications-table tbody');
    if (!body) return;
    try {
      const applications = await api.applications();
      body.innerHTML = applications.length ? applications.map(application => `<tr>
        <td class="primary">${escapeHtml(application.reference)}</td><td>${escapeHtml(application.client_name)}</td>
        <td>${escapeHtml(application.service_name || 'Service assessment')}</td><td>${escapeHtml(application.location)}</td>
        <td><select class="filter-select" data-application-status="${application.id}" aria-label="Status for ${escapeHtml(application.reference)}">
          ${['Draft', 'Review', 'Survey booked', 'Approved', 'Completed'].map(status => `<option${status === application.status ? ' selected' : ''}>${status}</option>`).join('')}
        </select></td><td><span class="link">${escapeHtml(application.created_at.slice(0, 10))}</span></td>
      </tr>`).join('') : '<tr><td colspan="6">No applications yet. Register one below.</td></tr>';
    } catch (error) { notify(error.message); }
  }

  async function refreshReports() {
    if (!document.getElementById('revenue')) return;
    try {
      const report = await api.reports();
      const money = value => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0 }).format(Number(value || 0));
      const revenueList = document.querySelector('#revenue .rate-list');
      if (revenueList) revenueList.innerHTML = report.services.length
        ? report.services.map(item => `<div class="rate-item"><span>${escapeHtml(item.service)}</span><b>${money(item.revenue)}</b></div>`).join('')
        : '<p class="panel-subtitle">No saved quotations yet.</p>';
      const feeBody = document.querySelector('#fees tbody');
      if (feeBody) feeBody.innerHTML = `<tr><td class="primary">Survey fees</td><td colspan="3">From saved quotations</td><td class="primary">${money(report.fees.survey_fees)}</td></tr>
        <tr><td class="primary">Authority fees</td><td colspan="3">From saved quotations</td><td class="primary">${money(report.fees.authority_fees)}</td></tr>
        <tr><td class="primary">16% tax</td><td colspan="3">From saved quotations</td><td class="primary">${money(report.fees.tax)}</td></tr>
        <tr><td class="primary">Plumbing charged</td><td colspan="3">From saved quotations</td><td class="primary">${money(report.fees.plumbing_total)}</td></tr>`;
      const activity = document.querySelector('#activity .rate-list');
      if (activity) activity.innerHTML = `<div class="rate-item"><span>Clients</span><b>${report.summary.clients}</b></div><div class="rate-item"><span>Active projects</span><b>${report.summary.active_projects}</b></div><div class="rate-item"><span>Pending applications</span><b>${report.summary.pending_applications}</b></div><div class="rate-item"><span>Accepted revenue</span><b>${money(report.summary.collected_revenue)}</b></div>${(report.client_totals || []).slice(0, 3).map(client => `<div class="rate-item"><span>${escapeHtml(client.name)} total quoted</span><b>${money(client.total)}</b></div>`).join('')}`;
    } catch (error) { notify(error.message); }
  }

  async function refreshServiceAdmin() {
    const body = document.getElementById('service-admin-list');
    if (!body) return;
    try {
      const services = await api.services();
      const money = value => value === null ? 'On assessment' : new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0 }).format(Number(value));
      body.innerHTML = services.map(service => `<tr><td class="primary">${escapeHtml(service.name)}</td><td>${escapeHtml(service.service_group)}</td><td>${money(service.base_price)}</td><td>${escapeHtml(service.pricing_model)}</td><td><button class="link" type="button" data-edit-service="${service.id}">Edit</button></td></tr>`).join('') || '<tr><td colspan="5">No services in catalogue.</td></tr>';
    } catch (error) { notify(error.message); }
  }

  async function refreshDashboard() {
    const applicationsBody = document.getElementById('dashboard-applications');
    if (!applicationsBody) return;
    try {
      const [report, applications] = await Promise.all([api.reports(), api.applications()]);
      const money = value => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0, notation: 'compact' }).format(Number(value || 0));
      document.getElementById('dashboard-active-projects').innerHTML = `${report.summary.active_projects} <small class="stat-change">Live</small>`;
      document.getElementById('dashboard-pending-applications').innerHTML = `${report.summary.pending_applications} <small class="stat-change">Live</small>`;
      document.getElementById('dashboard-revenue').innerHTML = `${money(report.summary.collected_revenue)} <small class="stat-change">Accepted</small>`;
      document.getElementById('dashboard-pending-quotes').innerHTML = `${report.summary.pending_quotes} <small class="stat-change">Awaiting decision</small>`;
      applicationsBody.innerHTML = applications.slice(0, 5).map(application => `<tr><td class="primary">${escapeHtml(application.client_name)}</td><td>${escapeHtml(application.service_name || 'Assessment')}</td><td>${escapeHtml(application.reference)}</td><td><span class="badge">${escapeHtml(application.status)}</span></td><td>${escapeHtml(application.created_at.slice(0, 10))}</td></tr>`).join('') || '<tr><td colspan="5">No applications yet.</td></tr>';
    } catch (error) { notify(error.message); }
  }

  const clientForm = document.querySelector('[data-client-form]');

  clientForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = clientForm.querySelector('[type="submit"]');
    buttonState(submit, true);

    try {
      const data = {
        name: value('client-name'),
        category: value('client-type'),
        telephone: value('client-phone'),
        email: value('client-email'),
        address: value('client-address'),
      };
      const editingId = clientForm.dataset.clientId;
      const client = editingId ? await api.updateClient(editingId, data) : await api.createClient(data);
      clientForm.reset();
      delete clientForm.dataset.clientId;
      submit.textContent = 'Save client';
      notify(`${client.name} has been ${editingId ? 'updated' : 'added'} to the client directory.`);
      refreshClients();
    } catch (error) {
      notify(error.message);
    } finally {
      buttonState(submit, false);
    }
  });

  const applicationForm = document.querySelector('[data-application-form]');

  applicationForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = applicationForm.querySelector('[type="submit"]');
    buttonState(submit, true);

    try {
      const client = await api.createClient({
        name: value('app-name'),
        category: value('app-category'),
        telephone: value('app-phone'),
        address: value('app-location'),
      });
      const service = value('app-service');
      const serviceCode = {
        'Symmetric drilling': 'symmetric',
        'Core drilling': 'core',
        'Geo-technical drilling': 'geo-technical',
        'Water pump installation': 'submersible',
        'Tank installation': 'tank',
        'Plumbing service': 'plumbing',
      }[service];
      const application = await api.createApplication({
        client_id: client.id,
        service_code: serviceCode,
        location: value('app-location'),
        notes: value('app-notes'),
      });
      applicationForm.reset();
      notify(`Application ${application.reference} has been registered.`);
      refreshApplications();
    } catch (error) {
      notify(error.message);
    } finally {
      buttonState(submit, false);
    }
  });

  const quoteButton = document.querySelector('[data-save-quote]');
  const quoteForm = document.querySelector('[data-quote-form]');
  const serviceForm = document.querySelector('[data-service-form]');
  const exportReport = document.querySelector('[data-export-report]');

  exportReport?.addEventListener('click', async () => {
    buttonState(exportReport, true);
    try {
      const report = await api.reports();
      const rows = [
        ['AquaFlow operational report'],
        ['Generated', new Date().toLocaleString('en-KE')], [],
        ['Metric', 'Amount (KES)'],
        ['Survey fees', report.fees.survey_fees], ['Authority fees', report.fees.authority_fees],
        ['Tax', report.fees.tax], ['Plumbing charged', report.fees.plumbing_total],
        ['Accepted revenue', report.summary.collected_revenue], [],
        ['Revenue by service', 'Amount (KES)'],
        ...report.services.map(item => [item.service, item.revenue]), [],
        ['Client', 'Total quoted (KES)'],
        ...(report.client_totals || []).map(item => [item.name, item.total]),
      ];
      const csv = rows.map(row => row.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
      const link = document.createElement('a');
      link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
      link.download = 'aquaflow-report.csv'; link.click(); URL.revokeObjectURL(link.href);
      notify('Live report downloaded.');
    } catch (error) { notify(error.message); } finally { buttonState(exportReport, false); }
  });

  serviceForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = serviceForm.querySelector('[type="submit"]');
    buttonState(submit, true);
    const data = {
      code: value('service-code'), name: value('service-name'), service_group: value('service-group'),
      pricing_model: value('service-model'), base_price: value('service-price'), description: value('service-description'), active: true,
    };
    try {
      const id = serviceForm.dataset.serviceId;
      const service = id ? await api.updateService(id, data) : await api.createService(data);
      serviceForm.reset(); delete serviceForm.dataset.serviceId;
      document.getElementById('service-code').disabled = false;
      submit.textContent = 'Add service';
      notify(`${service.name} has been ${id ? 'updated' : 'added'}.`);
      refreshServiceAdmin();
    } catch (error) { notify(error.message); } finally { buttonState(submit, false); }
  });

  quoteButton?.addEventListener('click', async () => {
    buttonState(quoteButton, true);

    try {
      const quote = await api.saveQuote({
        category: quoteForm.elements.category.value,
        drilling_type: quoteForm.elements.drilling.value,
        pump_type: quoteForm.elements.pump.value,
        depth_metres: quoteForm.elements.depth.value,
        tank_height_metres: quoteForm.elements.height.value,
        client_id: document.getElementById('quote-client-id')?.value || '',
        pipe_type: quoteForm.elements.pipe_type.value,
        pipe_diameter_inches: quoteForm.elements.pipe_diameter_inches.value,
        pipe_length_metres: quoteForm.elements.pipe_length_metres.value,
        pipe_rate_per_metre: quoteForm.elements.pipe_rate_per_metre.value,
        outlets: quoteForm.elements.outlets.value,
        outlet_rate: quoteForm.elements.outlet_rate.value,
        notes: quoteForm.elements['quote-note'].value.trim(),
      });
      notify(`Quote ${quote.reference} has been saved.`);
      refreshReports();
    } catch (error) {
      notify(error.message);
    } finally {
      buttonState(quoteButton, false);
    }
  });

  document.addEventListener('click', async event => {
    const edit = event.target.closest('[data-edit-client]');
    if (edit) {
      const id = edit.dataset.editClient;
      const card = edit.closest('[data-client-id]');
      try {
        const clients = await api.clients();
        const client = clients.find(item => String(item.id) === id);
        if (!client || !clientForm) return;
        document.getElementById('client-name').value = client.name;
        document.getElementById('client-type').value = client.category;
        document.getElementById('client-phone').value = client.telephone;
        document.getElementById('client-email').value = client.email || '';
        document.getElementById('client-address').value = client.address;
        clientForm.dataset.clientId = client.id;
        clientForm.querySelector('[type="submit"]').textContent = 'Update client';
        document.getElementById('new-client')?.scrollIntoView({ behavior: 'smooth' });
      } catch (error) { notify(error.message); }
      return;
    }
    const remove = event.target.closest('[data-delete-client]');
    if (remove && window.confirm('Remove this client? Clients with applications are kept for record history.')) {
      try { await api.deleteClient(remove.dataset.deleteClient); notify('Client removed.'); refreshClients(); } catch (error) { notify(error.message); }
    }
    const editService = event.target.closest('[data-edit-service]');
    if (editService && serviceForm) {
      try {
        const service = (await api.services()).find(item => String(item.id) === editService.dataset.editService);
        if (!service) return;
        document.getElementById('service-code').value = service.code;
        document.getElementById('service-code').disabled = true;
        document.getElementById('service-name').value = service.name;
        document.getElementById('service-group').value = service.service_group;
        document.getElementById('service-model').value = service.pricing_model;
        document.getElementById('service-price').value = service.base_price || '';
        document.getElementById('service-description').value = service.description || '';
        serviceForm.dataset.serviceId = service.id;
        serviceForm.querySelector('[type="submit"]').textContent = 'Update service';
        document.getElementById('manage-services')?.scrollIntoView({ behavior: 'smooth' });
      } catch (error) { notify(error.message); }
    }
  });

  document.addEventListener('change', async event => {
    const status = event.target.closest('[data-application-status]');
    if (!status) return;
    try { await api.updateApplication(status.dataset.applicationStatus, { status: status.value }); notify('Application status updated.'); } catch (error) { notify(error.message); refreshApplications(); }
  });

  document.getElementById('quote-client')?.addEventListener('change', event => {
    try {
      const clients = JSON.parse(event.target.dataset.clients || '[]');
      const client = clients.find(item => item.name === event.target.value);
      document.getElementById('quote-client-id').value = client ? client.id : '';
    } catch (_) { document.getElementById('quote-client-id').value = ''; }
  });

  refreshClients();
  refreshApplications();
  refreshReports();
  refreshServiceAdmin();
  refreshDashboard();
});
