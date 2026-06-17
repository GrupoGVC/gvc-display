// ── BASE URL da API ────────────────────────────────────────────
// Prioridade:
//   1. window.__BASE_URL__ injetado pelo TvController (player da TV)
//   2. <meta name="gvc-api-url"> injetado pelo PHP (API em domínio separado)
//   3. Auto-detect pelo window.location.pathname

const _metaApiUrl = document.querySelector('meta[name="gvc-api-url"]')?.content?.trim();

// Remove /login, / e qualquer subpath de view para obter a raiz do app
// Ex: http://localhost/gvc-display-mvc/login → /gvc-display-mvc
// Ex: http://localhost/gvc-display-mvc/      → /gvc-display-mvc
const _basePath = window.location.pathname
  .replace(/\/(login|tv)(\/.*)?$/, '')  // remove /login ou /tv/slug
  .replace(/\/+$/, '');                 // remove trailing slash

export const BASE = window.__BASE_URL__ ||
  (window.location.origin + _basePath);
export const API  = (_metaApiUrl && _metaApiUrl !== '')
  ? _metaApiUrl
  : BASE + '/api';

// ── Token JWT ─────────────────────────────────────────────────
export const token = {
  get:   ()  => localStorage.getItem('gvc_token') ?? '',
  set:   (t) => localStorage.setItem('gvc_token', t),
  clear: ()  => localStorage.removeItem('gvc_token'),
};

// ── Fetch genérico com tratamento de erro ─────────────────────
async function req(method, endpoint, body = null, isForm = false) {
  const t   = token.get();
  const url = endpoint.startsWith('http') ? endpoint : `${API}/${endpoint}`;

  const headers = {};
  if (t) headers['Authorization'] = `Bearer ${t}`;
  if (body && !isForm) headers['Content-Type'] = 'application/json';

  const opts = { method, headers };
  if (body) opts.body = isForm ? body : JSON.stringify(body);

  let res;
  try {
    res = await fetch(url, opts);
  } catch (e) {
    throw Object.assign(new Error('Sem conexão com o servidor'), { status: 0 });
  }

  // Lê como texto para poder limpar avisos PHP antes do JSON
  const raw = await res.text();
  let data;
  try {
    const clean = raw.replace(/^[^{[]*/, ''); // remove warnings PHP antes do JSON
    data = JSON.parse(clean);
  } catch {
    throw Object.assign(new Error('Resposta inválida do servidor'), { status: res.status, raw });
  }

  if (!res.ok) {
    const err = Object.assign(
      new Error(data?.error || `Erro ${res.status}`),
      { status: res.status, data }
    );
    if (res.status === 401) {
      token.clear();
      if (!window.location.pathname.includes('login')) {
        window.location.href = _basePath + '/login';
      }
    }
    throw err;
  }

  return data?.data ?? data;
}

export const get    = (ep)           => req('GET',    ep);
export const post   = (ep, body)     => req('POST',   ep, body);
export const put    = (ep, body)     => req('PUT',    ep, body);
export const del    = (ep)           => req('DELETE', ep);
export const upload = (ep, formData) => req('POST',   ep, formData, true);
