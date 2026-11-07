document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-quote-form]');
  if (!form) return;
  const money = new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0 });
  const fees = { Domestic: [7000, 10000], Commercial: [15000, 30000], Industrial: [20000, 50000] };
  const drilling = { None: 0, Symmetric: 130000, Core: 225000, Geotechnical: 335000 };
  const pumps = { None: 0, Submersible: 90000, Solar: 65000, Hand: 30000 };
  const number = name => Math.max(0, Number(form.elements[name]?.value) || 0);
  const rateFor = metres => metres <= 100 ? 1000 : metres <= 200 ? 1500 : metres <= 300 ? 2000 : 2500;
  const out = (id, amount) => {
    const field = document.querySelector(`[data-quote-${id}]`);
    if (field) field.textContent = money.format(amount);
  };
  function calculate() {
    const category = form.elements.category.value;
    const drill = form.elements.drilling.value;
    const pump = form.elements.pump.value;
    const depth = number('depth');
    const height = number('height');
    const pipeLength = number('pipe_length_metres');
    const pipeRate = number('pipe_rate_per_metre');
    const outlets = number('outlets');
    const outletRate = number('outlet_rate');
    const [survey, authority] = fees[category];
    const depthCost = (depth + height) * rateFor(Math.max(depth, height));
    const plumbing = (pipeLength * pipeRate) + (outlets * outletRate);
    const services = drilling[drill] + pumps[pump];
    const subtotal = survey + authority + services + depthCost + plumbing;
    const tax = Math.round(subtotal * 0.16);
    out('fees', survey + authority); out('services', services); out('depth', depthCost);
    out('plumbing', plumbing); out('subtotal', subtotal); out('tax', tax); out('total', subtotal + tax);
    form.dataset.total = String(subtotal + tax);
  }
  form.addEventListener('input', calculate);
  form.addEventListener('change', calculate);
  calculate();
});
