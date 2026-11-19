import { useCallback, useEffect, useState } from 'react';
import { api, query } from '../api/client.js';
import { ErrorBox, Pager, SuccessBox } from '../components/ui.jsx';

/** HU-05: registrar participantes y buscarlos por nombre o documento (CA-05.1, CA-05.2). No se editan ni borran. */
export default function Participants() {
  const [text, setText] = useState('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState({ items: [], page: 1, size: 20, total: 0 });
  const [form, setForm] = useState({ full_name: '', document_number: '' });
  const [detail, setDetail] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState('');

  const load = useCallback(async () => {
    try {
      setResult(await api.get(`/participants${query({ q: text, page, size: 20 })}`));
    } catch (err) {
      setError(err);
    }
  }, [text, page]);

  useEffect(() => { load(); }, [load]);

  async function create(event) {
    event.preventDefault();
    setError(null);
    setSuccess('');
    try {
      const created = await api.post('/participants', form);
      setSuccess(`Participante «${created.full_name}» registrado.`);
      setForm({ full_name: '', document_number: '' });
      await load();
    } catch (err) {
      setError(err);
    }
  }

  async function open(participant) {
    try {
      setDetail(await api.get(`/participants/${participant.id}`));
    } catch (err) {
      setError(err);
    }
  }

  return (
    <section>
      <h1>Participantes</h1>

      <form className="card form-grid" onSubmit={create}>
        <label>Nombre completo<input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} required /></label>
        <label>Documento<input value={form.document_number} onChange={(e) => setForm({ ...form, document_number: e.target.value })} required /></label>
        <div className="actions"><button type="submit" className="primary">Registrar</button></div>
      </form>

      <ErrorBox error={error} />
      <SuccessBox message={success} />

      {detail && (
        <div className="card" style={{ marginBottom: '1rem' }}>
          <strong>{detail.full_name}</strong> · documento {detail.document_number} ·{' '}
          <span className={detail.active_items >= 2 ? 'danger' : ''}>{detail.active_items} kit(s) sin devolver (máximo 2)</span>
          {' '}<button type="button" onClick={() => setDetail(null)}>Cerrar</button>
        </div>
      )}

      <div className="filters">
        <label>Buscar<input placeholder="Nombre o documento" value={text} onChange={(e) => { setText(e.target.value); setPage(1); }} /></label>
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Nombre</th><th>Documento</th><th></th></tr></thead>
          <tbody>
            {result.items.length === 0 && <tr><td colSpan="3" className="muted">No hay participantes que coincidan.</td></tr>}
            {result.items.map((p) => (
              <tr key={p.id}>
                <td>{p.full_name}</td>
                <td>{p.document_number}</td>
                <td><button type="button" onClick={() => open(p)}>Ver kits en su poder</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={result.page} size={result.size} total={result.total} onPage={setPage} />
    </section>
  );
}
