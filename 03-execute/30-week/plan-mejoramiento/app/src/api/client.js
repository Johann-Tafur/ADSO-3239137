// Cliente de la API (architecture.md §6): fetch con el token Bearer y lectura del cuerpo de error único
// { code, message, details } (api-contract.md §1). Ninguna pantalla llama a fetch directamente.

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');

/** Error de la API con su código estable (KIT_NOT_AVAILABLE, …) y el mensaje en español para mostrar. */
export class ApiError extends Error {
  constructor(status, code, message, details = []) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

let currentToken = null;
let onUnauthenticated = () => {};

export function setToken(token) {
  currentToken = token;
}

/** La sesión se cierra sola cuando la API responde 401 (token vencido a las 8 horas). */
export function onSessionExpired(callback) {
  onUnauthenticated = callback;
}

async function request(method, path, { body, form } = {}) {
  const headers = { Accept: 'application/json' };
  if (currentToken) headers.Authorization = `Bearer ${currentToken}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: form ?? (body !== undefined ? JSON.stringify(body) : undefined),
    });
  } catch {
    throw new ApiError(0, 'NETWORK', 'No hay conexión con la API. ¿Está encendida?');
  }

  if (response.status === 204) return null;
  const isJson = (response.headers.get('Content-Type') || '').includes('application/json');
  const data = isJson ? await response.json() : await response.blob();

  if (!response.ok) {
    const error = isJson
      ? new ApiError(response.status, data.code, data.message, data.details)
      : new ApiError(response.status, 'UNKNOWN', 'Ocurrió un error inesperado.');
    if (error.code === 'UNAUTHENTICATED') onUnauthenticated();
    throw error;
  }
  return data;
}

/** Arma "?a=1&b=2" ignorando los valores vacíos. */
export function query(params) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value).trim() !== '') search.set(key, value);
  });
  const text = search.toString();
  return text ? `?${text}` : '';
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body) => request('POST', path, { body: body ?? {} }),
  put: (path, body) => request('PUT', path, { body }),
  del: (path) => request('DELETE', path),
  upload: (path, formData) => request('POST', path, { form: formData }),
};
