// Cliente mínimo para hablar con la misma API JSON que usa la app Android
// (ApiController). En el panel web la sesión va por cookie nativa de PHP,
// no por el header X-Session-Id (eso es solo para el celular).
const Api = (() => {
  const BASE = 'index.php';

  async function handle(res) {
    let data;
    try { data = await res.json(); } catch (e) { data = {}; }
    if (!res.ok && data.success === undefined && data.error) {
      data = { success: false, message: data.error };
    }
    return data;
  }

  function qs(params) {
    const p = new URLSearchParams({ controller: 'api', ...params });
    return p.toString();
  }

  async function get(action, params = {}) {
    const res = await fetch(`${BASE}?${qs({ action, ...params })}`, {
      credentials: 'same-origin',
    });
    return handle(res);
  }

  async function post(action, data = {}, extraParams = {}) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => {
      if (v === null || v === undefined) return;
      if (Array.isArray(v)) {
        v.forEach((item) => fd.append(`${k}[]`, item));
      } else {
        fd.append(k, v);
      }
    });
    const res = await fetch(`${BASE}?${qs({ action, ...extraParams })}`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    });
    return handle(res);
  }

  async function postJson(action, data = {}) {
    const res = await fetch(`${BASE}?${qs({ action })}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'same-origin',
    });
    return handle(res);
  }

  return { get, post, postJson };
})();
