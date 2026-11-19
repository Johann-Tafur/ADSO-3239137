import { useEffect, useState } from 'react';
import { api, query } from '../api/client.js';
import { ErrorBox, SuccessBox, formatDate } from '../components/ui.jsx';

/**
 * HU-06: registrar un préstamo de 1 o 2 kits. Las reglas (máximo 2 sin devolver, vencidos, sin unidades, kit repetido)
 * las aplica la API; esta pantalla solo muestra su respuesta.
 */
export default function NewLoan() {
  const [participantText, setParticipantText] = useState('');
  const [participants, setParticipants] = useState([]);
  const [participant, setParticipant] = useState(null);
  const [kitText, setKitText] = useState('');
  const [kits, setKits] = useState([]);
  const [chosen, setChosen] = useState([]);
  const [loan, setLoan] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      api.get(`/participants${query({ q: participantText, size: 8 })}`).then((d) => setParticipants(d.items)).catch(setError);
    }, 250);
    return () => clearTimeout(timer);
  }, [participantText]);

  useEffect(() => {
    const timer = setTimeout(() => {
      api.get(`/kits${query({ q: kitText, size: 8 })}`).then((d) => setKits(d.items)).catch(setError);
    }, 250);
    return () => clearTimeout(timer);
  }, [kitText]);

  function toggle(kit) {
    setChosen((current) => (current.some((k) => k.id === kit.id)
      ? current.filter((k) => k.id !== kit.id)
      : [...current, kit]));
  }

  async function submit() {
    setError(null);
    try {
      const created = await api.post('/loans', {
        participant_id: participant.id,
        items: chosen.map((kit) => ({ kit_id: kit.id })),
      });
      setLoan(created);
      setChosen([]);
    } catch (err) {
      setError(err);
    }
  }

  function restart() {
    setLoan(null);
    setParticipant(null);
    setChosen([]);
    setError(null);
  }

  if (loan) {
    return (
      <section>
        <h1>Préstamo registrado</h1>
        <SuccessBox message={`Préstamo para ${participant.full_name} registrado el ${formatDate(loan.loaned_at)}.`} />
        <div className="table-wrap">
          <table>
            <thead><tr><th>Kit</th><th>Referencia</th><th>Categoría</th><th>Vence</th></tr></thead>
            <tbody>
              {loan.items.map((item) => (
                <tr key={item.id}><td>{item.kit_name}</td><td><code>{item.kit_reference}</code></td><td>{item.category_name}</td><td>{formatDate(item.due_at)}</td></tr>
              ))}
            </tbody>
          </table>
        </div>
        <p><button type="button" className="primary" onClick={restart}>Registrar otro préstamo</button></p>
      </section>
    );
  }

  return (
    <section>
      <h1>Nuevo préstamo</h1>
      <ErrorBox error={error} />

      <h2>1. Participante</h2>
      {participant ? (
        <p className="card">
          <strong>{participant.full_name}</strong> · {participant.document_number}{' '}
          <button type="button" onClick={() => setParticipant(null)}>Cambiar</button>
        </p>
      ) : (
        <>
          <div className="filters"><label>Buscar participante<input placeholder="Nombre o documento" value={participantText} onChange={(e) => setParticipantText(e.target.value)} /></label></div>
          <div className="actions">
            {participants.map((p) => <button key={p.id} type="button" onClick={() => setParticipant(p)}>{p.full_name} · {p.document_number}</button>)}
            {participants.length === 0 && <span className="muted">No hay participantes. Regístralo en «Participantes».</span>}
          </div>
        </>
      )}

      <h2>2. Kits (uno o dos)</h2>
      <div className="filters"><label>Buscar kit<input placeholder="Nombre o fabricante" value={kitText} onChange={(e) => setKitText(e.target.value)} /></label></div>
      <div className="table-wrap">
        <table>
          <thead><tr><th></th><th>Kit</th><th>Referencia</th><th>Disponibles</th></tr></thead>
          <tbody>
            {kits.map((kit) => {
              const selected = chosen.some((k) => k.id === kit.id);
              return (
                <tr key={kit.id}>
                  <td><input type="checkbox" aria-label={`Elegir ${kit.name}`} checked={selected} disabled={!selected && chosen.length >= 2} onChange={() => toggle(kit)} /></td>
                  <td>{kit.name}</td>
                  <td><code>{kit.reference}</code></td>
                  <td className={kit.available_units === 0 ? 'danger' : ''}>{kit.available_units}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <h2>3. Confirmar</h2>
      <p className="muted">Cada kit vence a los 12 días.</p>
      <button type="button" className="primary" disabled={!participant || chosen.length === 0} onClick={submit}>
        Prestar {chosen.length} kit(s)
      </button>
    </section>
  );
}
