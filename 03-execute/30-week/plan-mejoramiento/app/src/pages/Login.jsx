import { useState } from 'react';
import { useAuth } from '../auth/AuthContext.jsx';
import { ErrorBox } from '../components/ui.jsx';

/** HU-01: iniciar sesión. Un error no dice si falló el usuario o la clave (CA-01.2): se muestra el mensaje de la API. */
export default function Login() {
  const { login } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await login(username, password);
      window.location.hash = '#/kits';
    } catch (err) {
      setError(err);
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="login">
      <form className="card login-card" onSubmit={submit}>
        <h1>Robotics</h1>
        <p className="muted">Préstamo de kits del semillero</p>
        <ErrorBox error={error} />
        <label>
          Usuario
          <input value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" required autoFocus />
        </label>
        <label>
          Clave
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required />
        </label>
        <button type="submit" className="primary" disabled={busy}>{busy ? 'Entrando…' : 'Entrar'}</button>
      </form>
    </main>
  );
}
