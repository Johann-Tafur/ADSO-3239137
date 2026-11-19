import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, onSessionExpired, setToken } from '../api/client.js';

// Sesión: token y rol (architecture.md §6). Se guarda en sessionStorage: se borra al cerrar la pestaña.
// Las pantallas solo ocultan lo que el rol no puede hacer; la autorización real la hace la API (403).

const STORAGE_KEY = 'robotics.session';
const AuthContext = createContext(null);

function readStoredSession() {
  try {
    const session = JSON.parse(sessionStorage.getItem(STORAGE_KEY));
    return session && session.expiresAt > Date.now() ? session : null;
  } catch {
    return null;
  }
}

export function AuthProvider({ children }) {
  const [session, setSession] = useState(readStoredSession);

  // El cliente lleva el token en cada petición
  setToken(session?.token ?? null);

  const logout = useCallback(() => {
    try { sessionStorage.removeItem(STORAGE_KEY); } catch { /* sin almacenamiento: no pasa nada */ }
    setSession(null);
  }, []);

  useEffect(() => onSessionExpired(logout), [logout]);

  const login = useCallback(async (username, password) => {
    const data = await api.post('/auth/login', { username, password });
    const next = {
      token: data.access_token,
      role: data.role,
      username: username.trim().toLowerCase(),
      expiresAt: Date.now() + data.expires_in * 1000,
    };
    try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next)); } catch { /* sesión solo en memoria */ }
    setSession(next);
  }, []);

  const value = useMemo(() => ({
    session,
    isAdmin: session?.role === 'admin',
    login,
    logout,
  }), [session, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}
