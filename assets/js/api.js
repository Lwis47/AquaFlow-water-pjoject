const AquaFlowApi = (() => {
  const endpoint = 'backend/public/api.php';

  async function request(route, options = {}) {
    const separator = endpoint.includes('?') ? '&' : '?';
    const response = await fetch(`${endpoint}${separator}route=${encodeURIComponent(route)}`,
      {
        headers: {
          Accept: 'application/json',
          ...(options.body ? { 'Content-Type': 'application/json' } : {}),
          ...options.headers,
        },
        ...options,
      });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok || payload.ok === false) {
      throw new Error(payload.error || 'The server could not complete this request.');
    }

    return payload.data;
  }

  return {
    health: () => request('health'),
    clients: search => request(`clients&search=${encodeURIComponent(search || '')}`),
    createClient: data => request('clients', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    updateClient: (id, data) => request(`clients/${id}`, {
      method: 'PUT', body: JSON.stringify(data),
    }),
    deleteClient: id => request(`clients/${id}`, { method: 'DELETE' }),
    services: () => request('services'),
    createService: data => request('services', { method: 'POST', body: JSON.stringify(data) }),
    updateService: (id, data) => request(`services/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    applications: (search = '', status = '') => request(`applications&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`),
    createApplication: data => request('applications', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    updateApplication: (id, data) => request(`applications/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    calculateQuote: data => request('quotes/calculate', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    saveQuote: data => request('quotes', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    reports: () => request('reports/overview'),
  };
})();

window.AquaFlowApi = AquaFlowApi;
