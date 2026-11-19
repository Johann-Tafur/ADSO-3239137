// Cliente HTTP del sembrador. Usa solo la API pública (api-contract.md): nunca toca la base directamente,
// así los datos pasan por las mismas reglas que los de una persona.

export class ApiError extends Error {
  constructor(status, body) {
    super(`${status} ${body?.code ?? ''}: ${body?.message ?? 'sin mensaje'}`);
    this.status = status;
    this.code = body?.code;
  }
}

export function createClient(baseUrl) {
  const base = baseUrl.replace(/\/$/, '');
  let token = null;

  async function call(method, path, body) {
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    let response;
    try {
      response = await fetch(`${base}${path}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
    } catch (error) {
      throw new Error(`No hay conexión con la API en ${base}. ¿Está encendida? (${error.message})`);
    }
    if (response.status === 204) return null;
    const data = await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, data);
    return data;
  }

  return {
    async login(username, password) {
      const data = await call('POST', '/auth/login', { username, password });
      token = data.access_token;
      return data;
    },
    get: (path) => call('GET', path),
    post: (path, body) => call('POST', path, body ?? {}),
  };
}
