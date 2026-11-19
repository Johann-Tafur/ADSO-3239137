import { useState } from 'react';
import { api } from '../api/client.js';
import { ErrorBox, SuccessBox } from '../components/ui.jsx';

/** HU-02 (solo admin): dar de alta mentores. El rol siempre es mentor: la API rechaza admin (CA-02.3). */
export default function Users() {
  const [form, setForm] = useState({ username: '', password: '' });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState('');

  async function create(event) {
    event.preventDefault();
    setError(null);
    setSuccess('');
    try {
      const user = await api.post('/users', { ...form, role: 'mentor' });
      setSuccess(`Mentor «${user.username}» creado. Ya puede iniciar sesión.`);
      setForm({ username: '', password: '' });
    } catch (err) {
      setError(err);
    }
  }

  return (
    <section>
      <h1>Mentores</h1>
      <ErrorBox error={error} />
      <SuccessBox message={success} />
      <form className="card form-grid" onSubmit={create}>
        <label>Usuario<input value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} autoComplete="off" required /></label>
        <label>Clave inicial<input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete="new-password" required /></label>
        <div className="actions"><button type="submit" className="primary">Crear mentor</button></div>
      </form>
    </section>
  );
}
