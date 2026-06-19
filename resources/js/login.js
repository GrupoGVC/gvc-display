/* ============================================================
   GVC Display — Login JS
   ============================================================ */

const _base = window.location.pathname
  .replace(/\/(login)(\/.*)?$/, '')
  .replace(/\/+$/, '');
const _api = window.location.origin + _base + '/api';

// Se já autenticado, redireciona para o painel
if (localStorage.getItem('gvc_token')) {
  window.location.replace(_base + '/');
}

function showErr(msg) {
  const el  = document.getElementById('l-err');
  const txt = document.getElementById('l-err-txt');
  txt.textContent  = msg;
  el.style.display = 'flex';
}

async function doLogin(e) {
  if (e) e.preventDefault();

  const email = document.getElementById('l-email').value.trim();
  const pass  = document.getElementById('l-pass').value;
  const btn   = document.getElementById('btn-login');

  document.getElementById('l-err').style.display = 'none';

  if (!email || !pass) { showErr('Preencha e-mail e senha'); return; }

  btn.disabled  = true;
  btn.innerHTML = '<span class="spin"></span> Entrando...';

  try {
    const res  = await fetch(_api + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pass }),
    });
    const raw  = await res.text();
    const data = JSON.parse(raw.replace(/^[^{[]*/, ''));

    if (!res.ok) throw new Error(data?.error || 'Credenciais inválidas');

    localStorage.setItem('gvc_token', data.data?.token ?? data.token);
    window.location.replace(_base + '/');
  } catch (err) {
    showErr(err.message || 'E-mail ou senha inválidos');
    btn.disabled  = false;
    btn.innerHTML = 'Entrar <i class="bi bi-arrow-right"></i>';
  }
}
