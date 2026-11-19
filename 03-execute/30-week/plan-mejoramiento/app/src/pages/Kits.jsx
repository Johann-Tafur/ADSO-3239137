import { useCallback, useEffect, useState } from 'react';
import { api, query } from '../api/client.js';
import { useAuth } from '../auth/AuthContext.jsx';
import { ErrorBox, Pager, SuccessBox } from '../components/ui.jsx';

const EMPTY = { name: '', manufacturer: '', reference: '', category_id: '', total_units: 1 };

/** HU-03 y HU-04: buscar kits (todos) y, para el admin, crear, editar, dar de baja y manejar la foto. */
export default function Kits() {
  const { isAdmin } = useAuth();
  const [categories, setCategories] = useState([]);
  const [text, setText] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState({ items: [], page: 1, size: 20, total: 0 });
  const [form, setForm] = useState(null); // null = sin formulario; { id?, ...campos } = creando o editando
  const [photoUrl, setPhotoUrl] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState('');

  useEffect(() => {
    api.get('/categories').then((data) => setCategories(data.items)).catch(setError);
  }, []);

  const load = useCallback(async () => {
    try {
      setResult(await api.get(`/kits${query({ q: text, category_id: categoryId, page, size: 20 })}`));
    } catch (err) {
      setError(err);
    }
  }, [text, categoryId, page]);

  useEffect(() => { load(); }, [load]);

  async function run(action, message) {
    setError(null);
    setSuccess('');
    try {
      await action();
      setSuccess(message);
      await load();
      return true;
    } catch (err) {
      setError(err);
      return false;
    }
  }

  async function save(event) {
    event.preventDefault();
    const body = { ...form, total_units: Number(form.total_units) };
    delete body.id;
    const ok = await run(
      () => (form.id ? api.put(`/kits/${form.id}`, body) : api.post('/kits', body)),
      form.id ? 'Kit actualizado.' : 'Kit creado.',
    );
    if (ok) setForm(null);
  }

  function edit(kit) {
    setForm({
      id: kit.id, name: kit.name, manufacturer: kit.manufacturer, reference: kit.reference,
      category_id: kit.category.id, total_units: kit.total_units,
    });
  }

  function remove(kit) {
    if (!window.confirm(`¿Dar de baja «${kit.name}»? Seguirá en los préstamos pasados.`)) return;
    run(() => api.del(`/kits/${kit.id}`), 'Kit dado de baja.');
  }

  function uploadPhoto(kit, file) {
    if (!file) return;
    const data = new FormData();
    data.append('file', file);
    // PHP solo lee archivos en POST: se envía POST con _method=PUT y Laravel lo atiende como PUT /kits/{id}/photo
    data.append('_method', 'PUT');
    run(() => api.upload(`/kits/${kit.id}/photo`, data), 'Foto guardada.');
  }

  async function showPhoto(kit) {
    setError(null);
    try {
      // La foto pide token, por eso no basta un <img src>: se descarga y se muestra como blob
      const blob = await api.get(`/kits/${kit.id}/photo`);
      setPhotoUrl((old) => { if (old) URL.revokeObjectURL(old); return URL.createObjectURL(blob); });
    } catch (err) {
      setError(err);
    }
  }

  return (
    <section>
      <h1>Catálogo de kits</h1>

      <form className="filters" onSubmit={(e) => e.preventDefault()}>
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
        {isAdmin && !form && <button type="button" className="primary" onClick={() => setForm({ ...EMPTY, category_id: categories[0]?.id ?? '' })}>Nuevo kit</button>}
      </form>

      <ErrorBox error={error} />
      <SuccessBox message={success} />

      {form && (
        <form className="card form-grid" onSubmit={save}>
          <h2 style={{ gridColumn: '1 / -1', margin: 0 }}>{form.id ? 'Editar kit' : 'Nuevo kit'}</h2>
          <label>Nombre<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required /></label>
          <label>Fabricante<input value={form.manufacturer} onChange={(e) => setForm({ ...form, manufacturer: e.target.value })} required /></label>
          <label>Referencia (AAA-0000)<input value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value.toUpperCase() })} placeholder="ARD-0001" required /></label>
          <label>
            Categoría
            <select value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })} required>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </label>
          <label>Unidades totales<input type="number" min="1" value={form.total_units} onChange={(e) => setForm({ ...form, total_units: e.target.value })} required /></label>
          <div className="actions">
            <button type="submit" className="primary">Guardar</button>
            <button type="button" onClick={() => setForm(null)}>Cancelar</button>
          </div>
        </form>
      )}

      {photoUrl && (
        <div className="card photo-view">
          <img src={photoUrl} alt="Foto del kit" />
          <button type="button" onClick={() => { URL.revokeObjectURL(photoUrl); setPhotoUrl(null); }}>Cerrar foto</button>
        </div>
      )}

      <div className="table-wrap">
        <table>
          <thead>
            <tr><th>Referencia</th><th>Nombre</th><th>Fabricante</th><th>Categoría</th><th>Disponibles</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            {result.items.length === 0 && <tr><td colSpan="6" className="muted">No hay kits que coincidan.</td></tr>}
            {result.items.map((kit) => (
              <tr key={kit.id}>
                <td><code>{kit.reference}</code></td>
                <td>{kit.name}</td>
                <td>{kit.manufacturer}</td>
                <td>{kit.category.name}</td>
                <td className={kit.available_units === 0 ? 'danger' : ''}>{kit.available_units} de {kit.total_units}</td>
                <td>
                  <div className="actions">
                    {kit.photo_key && <button type="button" onClick={() => showPhoto(kit)}>Ver foto</button>}
                    {isAdmin && (
                      <>
                        <button type="button" onClick={() => edit(kit)}>Editar</button>
                        <label className="file-button">
                          Foto
                          <input type="file" accept="image/jpeg,image/png" onChange={(e) => { uploadPhoto(kit, e.target.files[0]); e.target.value = ''; }} />
                        </label>
                        {kit.photo_key && <button type="button" onClick={() => run(() => api.del(`/kits/${kit.id}/photo`), 'Foto quitada.')}>Quitar foto</button>}
                        <button type="button" className="danger-button" onClick={() => remove(kit)}>Dar de baja</button>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Pager page={result.page} size={result.size} total={result.total} onPage={setPage} />
    </section>
  );
}
