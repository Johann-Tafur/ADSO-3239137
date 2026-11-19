import { useState } from 'react';
import { api, query } from '../api/client.js';
import { ErrorBox } from '../components/ui.jsx';

function firstDayOfMonth() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
}

/** HU-10 (solo admin): kits más solicitados en un rango, con nombres congelados y sin personas (CA-10.1 a CA-10.4). */
export default function Report() {
  const [from, setFrom] = useState(firstDayOfMonth());
  const [to, setTo] = useState(new Date().toISOString().slice(0, 10));
  const [report, setReport] = useState(null);
  const [error, setError] = useState(null);

  async function load(event) {
    event.preventDefault();
    setError(null);
    try {
      setReport(await api.get(`/reports/most-requested${query({ from, to })}`));
    } catch (err) {
      setReport(null);
      setError(err);
    }
  }

  return (
    <section>
      <h1>Kits más solicitados</h1>
      <form className="filters" onSubmit={load}>
        <label>Desde<input type="date" value={from} onChange={(e) => setFrom(e.target.value)} required /></label>
        <label>Hasta<input type="date" value={to} onChange={(e) => setTo(e.target.value)} required /></label>
        <button type="submit" className="primary">Ver reporte</button>
      </form>
      <ErrorBox error={error} />
      {report && report.rows.length === 0 && <p className="muted">No hubo préstamos entre {report.from} y {report.to}.</p>}
      {report && report.rows.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Kit</th><th>Categoría</th><th>Veces prestado</th></tr></thead>
            <tbody>
              {report.rows.map((row, i) => (
                <tr key={`${row.kit_id}-${row.kit_name}-${row.category_name}`}>
                  <td>{i + 1}</td><td>{row.kit_name}</td><td>{row.category_name}</td><td><strong>{row.times_requested}</strong></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
