/* ============================================================
   GVC Signage — Cliente API compartilhado
   Uso: import { get, post, put, del, upload } from './api.js'
   ============================================================ */

"use strict";

// Base URL detectada automaticamente
export const BASE = (() => {
  const p = window.location.pathname;
  // Remove /html/ ou /index.html do caminho para obter a raiz
  return (
    window.location.origin +
    p.replace(/\/(html\/.*|index\.html|login\.html).*$/, "")
  );
})();

export const API = BASE + "/api";

// ── Token store ───────────────────────────────────────────────
export const token = {
  get: () => localStorage.getItem("gvc_token"),
  set: (v) => localStorage.setItem("gvc_token", v),
  clear: () => localStorage.removeItem("gvc_token"),
};

// ── Fetch wrapper ─────────────────────────────────────────────
async function req(method, path, body = null, isFile = false) {
  const headers = {};
  const t = token.get();
  if (t) headers["Authorization"] = `Bearer ${t}`;

  if (body && !isFile) headers["Content-Type"] = "application/json";

  const opts = {
    method,
    headers,
    body: body && !isFile ? JSON.stringify(body) : (body ?? undefined),
  };

  const res = await fetch(`${API}/${path}`, opts);
  const json = await res.json().catch(() => ({}));

  if (!res.ok) {
    const err = new Error(json.error || `HTTP ${res.status}`);
    err.status = res.status;
    throw err;
  }

  return json.data ?? json;
}

export const get = (path) => req("GET", path);
export const post = (path, body) => req("POST", path, body);
export const put = (path, body) => req("PUT", path, body);
export const del = (path) => req("DELETE", path);
export const upload = (path, formData) => req("POST", path, formData, true);
