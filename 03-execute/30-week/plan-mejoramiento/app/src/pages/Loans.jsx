import { useCallback, useEffect, useState } from 'react';
import { api, query } from '../api/client.js';
import { ErrorBox, Pager, STATUS_LABEL, SuccessBox, formatDate } from '../components/ui.jsx';

/** HU-07 y HU-08: listar préstamos por rango y estado, ver sus líneas y devolver una a una. */
export default function Loans() {
  const [filters, setFilters] = useState({ from: '', to: '', status: '' });
  const [page, setPage] = useState(1);
  const [result, setResult] = useState({ items: [], page: 1, size: 20, total: 0 });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState('');

  const load = useCallback(async () => {
    try {
      setResult(await api.get(`/loans${query({ ...filters, page, size: 20 })}`));
    } catch (err) {
      setError(err);
    }
  }, [filters, page]);

  useEffect(() => { load(); }, [load]);

  async function giveBack(loan, item) {
    setError(null);
    setSuccess('');
    try {
      await api.post(`/loans/${loan.id}/items/${item.id}/return`);
      setSuccess(`«${item.kit_name}» devuelto.`);
      await load();
    } catch (err) {
      setError(err);
    }
  }

  const change = (field) => (e) => { setFilters({ ...filters, [field]: e.target.value }); setPage(1); };

  return (
    <section>
      <h1>Préstamos</h1>
      <div className="filters">
        <label>Desde<input type="date" value={filters.from} onChange={change('from')} /></label>
        <label>Hasta<input type="date" value={filters.to} onChange={change('to')} /></label>
        <label>
          Estado
          <select value={filters.status} onChange={change('status')}>
            <option value="">Todos</option>
            <option value="open">Abiertos</option>
            <option value="overdue">Vencidos</option>
            <option value="closed">Cerrados</option>
          </select>
        </label>
      </div>
      <p className="muted">Para filtrar por fechas se necesitan las dos.</p>

      <ErrorBox error={error} />
      <SuccessBox message={success} />

      {result.items.length === 0 && <p className="muted">No hay préstamos con esos filtros.</p>}
      {result.items.map((loan) => (
        <article key={loan.id} className="card loan">
          <header className="loan-header">
            <span className={`badge badge-${loan.status}`}>{STATUS_LABEL[loan.status]}</span>
            <span>Prestado el {formatDate(loan.loaned_at)}</span>
          </header>
          <div className="table-wrap">
            <table>
              <thead><tr><th>Kit</th><th>Referencia</th><th>Vence</th><th>Devuelto</th><th></th></tr></thead>
              <tbody>
                {loan.items.map((item) => (
                  <tr key={item.id}>
                    <td>{item.kit_name}</td>
                    <td><code>{item.kit_reference}</code></td>
                    <td>{formatDate(item.due_at)}</td>
                    <td>{formatDate(item.returned_at)}</td>
                    <td>{!item.returned_at && <button type="button" className="primary" onClick={() => giveBack(loan, item)}>Devolver</button>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </article>
      ))}
      <Pager page={result.page} size={result.size} total={result.total} onPage={setPage} />
    </section>
  );
}
