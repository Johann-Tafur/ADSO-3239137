import { useEffect, useState } from 'react';
import { api } from '../api/client.js';
import { ErrorBox, formatDate } from '../components/ui.jsx';

/** HU-09: kits vencidos, de más días de retraso a menos (CA-09.1, CA-09.2). */
export default function Overdue() {
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    api.get('/loans/overdue').then((data) => setItems(data.items)).catch(setError);
  }, []);

  return (
    <section>
      <h1>Kits vencidos</h1>
      <ErrorBox error={error} />
      {items && items.length === 0 && <p className="muted">No hay kits vencidos. 🎉</p>}
      {items && items.length > 0 && (
        <div className="table-wrap">
          <table>
            <thead><tr><th>Días de retraso</th><th>Kit</th><th>Referencia</th><th>Participante</th><th>Venció</th></tr></thead>
            <tbody>
              {items.map((line) => (
                <tr key={line.item_id}>
                  <td className="danger">{line.days_late}</td>
                  <td>{line.kit_name}</td>
                  <td><code>{line.kit_reference}</code></td>
                  <td>{line.participant_name}</td>
                  <td>{formatDate(line.due_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
