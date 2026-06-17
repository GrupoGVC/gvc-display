// ── Resolução da BASE URL da API ──────────────────────────────
// Prioridade:
//   1. <meta name="gvc-api-url"> injetado pelo PHP (via API_BASE_URL no .env)
//   2. Auto-detect pelo window.location (comportamento original)
//
// Isso permite que, no futuro, a API seja movida para outro domínio
// bastando setar API_BASE_URL no .env — sem alterar nenhum outro arquivo.

const _metaApiUrl = document.querySelector('meta[name="gvc-api-url"]')?.content?.trim();

const _basePath = window.location.pathname
  .replace(/\/(index|login)\.(html|php).*$/, '')
  .replace(/\/+$/, '');

export const BASE = window.location.origin + _basePath;

// Se o meta existe e não está vazio, usa ele; senão auto-detect com /api/v1
export const API = (_metaApiUrl && _metaApiUrl !== '')
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
        window.location.href = _basePath + '/login.php';
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
