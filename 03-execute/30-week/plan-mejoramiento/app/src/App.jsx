import { useEffect, useState } from 'react';
import { useAuth } from './auth/AuthContext.jsx';
import Login from './pages/Login.jsx';
import Kits from './pages/Kits.jsx';
import Participants from './pages/Participants.jsx';
import NewLoan from './pages/NewLoan.jsx';
import Loans from './pages/Loans.jsx';
import Overdue from './pages/Overdue.jsx';
import Report from './pages/Report.jsx';
import Users from './pages/Users.jsx';

// Enrutador mínimo por la parte «#» de la URL (#/kits, #/loans…): sin librerías.
// Cada ruta dice qué roles la ven; el menú solo muestra esas. La API vuelve a revisar el rol (403).
const ROUTES = [
  { path: '#/kits', label: 'Kits', roles: ['admin', 'mentor'], component: Kits },
  { path: '#/participants', label: 'Participantes', roles: ['admin', 'mentor'], component: Participants },
  { path: '#/new-loan', label: 'Prestar', roles: ['admin', 'mentor'], component: NewLoan },
  { path: '#/loans', label: 'Préstamos', roles: ['admin', 'mentor'], component: Loans },
  { path: '#/overdue', label: 'Vencidos', roles: ['admin', 'mentor'], component: Overdue },
  { path: '#/report', label: 'Reporte', roles: ['admin'], component: Report },
  { path: '#/users', label: 'Mentores', roles: ['admin'], component: Users },
];

function useHash() {
  const [hash, setHash] = useState(window.location.hash || '#/kits');
  useEffect(() => {
    const update = () => setHash(window.location.hash || '#/kits');
    window.addEventListener('hashchange', update);
    return () => window.removeEventListener('hashchange', update);
  }, []);
  return hash;
}

export default function App() {
  const { session, logout } = useAuth();
  const hash = useHash();

  if (!session) return <Login />;

  const allowed = ROUTES.filter((route) => route.roles.includes(session.role));
  const current = allowed.find((route) => hash.startsWith(route.path)) ?? allowed[0];
  const Page = current.component;

  return (
    <>
      <header className="topbar">
        <strong className="brand">Robotics</strong>
        <nav aria-label="Menú principal">
          {allowed.map((route) => (
            <a key={route.path} href={route.path} aria-current={route === current ? 'page' : undefined}>{route.label}</a>
          ))}
        </nav>
        <span className="who">{session.username} · {session.role}</span>
        <button type="button" onClick={logout}>Salir</button>
      </header>
      <main className="content">
        <Page />
      </main>
    </>
  );
}
