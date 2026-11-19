import { useCallback, useEffect, useState } from 'react';
import { api, query } from '../api/client.js';
import { ErrorBox, Pager } from '../components/ui.jsx';

/** HU-04: buscar kits por texto y categoría, paginado (CA-04.1, CA-04.2). */
export default function Kits() {
  const [categories, setCategories] = useState([]);
  const [text, setText] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState({ items: [], page: 1, size: 20, total: 0 });
  const [error, setError] = useState(null);

  useEffect(() => {
    api.get('/categories').then((data) => setCategories(data.items)).catch(setError);
  }, []);

  const load = useCallback(async () => {
    try {
      setError(null);
      setResult(await api.get(`/kits${query({ q: text, category_id: categoryId, page, size: 20 })}`));
    } catch (err) {
      setError(err);
    }
  }, [text, categoryId, page]);

  useEffect(() => { load(); }, [load]);

  return (
    <section>
      <h1>Catálogo de kits</h1>
      <form className="filters" onSubmit={(e) => { e.preventDefault(); setPage(1); load(); }}>
        <label>
          Buscar
          <input placeholder="Nombre o fabricante" value={text} onChange={(e) => { setText(e.target.value); setPage(1); }} />
        </label>
        <label>
          Categoría
          <select value={categoryId} onChange={(e) => { setCategoryId(e.target.value); setPage(1); }}>
            <option value="">Todas</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </label>
      </form>

      <ErrorBox error={error} />

      <div className="table-wrap">
        <table>
          <thead>
            <tr><th>Referencia</th><th>Nombre</th><th>Fabricante</th><th>Categoría</th><th>Disponibles</th></tr>
          </thead>
          <tbody>
            {result.items.length === 0 && <tr><td colSpan="5" className="muted">No hay kits que coincidan.</td></tr>}
            {result.items.map((kit) => (
              <tr key={kit.id}>
                <td><code>{kit.reference}</code></td>
                <td>{kit.name}</td>
                <td>{kit.manufacturer}</td>
                <td>{kit.category.name}</td>
                <td className={kit.available_units === 0 ? 'danger' : ''}>{kit.available_units} de {kit.total_units}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={result.page} size={result.size} total={result.total} onPage={setPage} />
    </section>
  );
}
