// Piezas pequeñas que comparten todas las pantallas.

/** Muestra el error de la API tal como viene: mensaje en español y, si hay, el detalle por campo. */
export function ErrorBox({ error }) {
  if (!error) return null;
  return (
    <div className="alert alert-error" role="alert">
      <strong>{error.message}</strong>
      {error.code && <span className="code"> ({error.code})</span>}
      {error.details?.length > 0 && (
        <ul>
          {error.details.map((d, i) => <li key={i}><code>{d.field}</code>: {d.issue}</li>)}
        </ul>
      )}
    </div>
  );
}

export function SuccessBox({ message }) {
  if (!message) return null;
  return <div className="alert alert-success" role="status">{message}</div>;
}

/** Paginación de las listas: { page, size, total } (api-contract.md §1). */
export function Pager({ page, size, total, onPage }) {
  const pages = Math.max(1, Math.ceil(total / size));
  return (
    <nav className="pager" aria-label="Paginación">
      <button type="button" disabled={page <= 1} onClick={() => onPage(page - 1)}>Anterior</button>
      <span>Página {page} de {pages} · {total} resultado(s)</span>
      <button type="button" disabled={page >= pages} onClick={() => onPage(page + 1)}>Siguiente</button>
    </nav>
  );
}

/** Fecha ISO de la API → texto local legible. */
export function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
}

export const STATUS_LABEL = { open: 'Abierto', overdue: 'Vencido', closed: 'Cerrado' };
