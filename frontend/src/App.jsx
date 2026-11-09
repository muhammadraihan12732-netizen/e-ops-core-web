import { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { api } from './api';
import Layout, { can } from './Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Inventory from './pages/Inventory';
import Transactions from './pages/Transactions';
import Analytics from './pages/Analytics';
import Monitoring from './pages/Monitoring';
import Helpdesk from './pages/Helpdesk';
import Users from './pages/Users';
import Categories from './pages/Categories';

export default function App() {
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (!localStorage.getItem('token')) return setReady(true);
    api('/user').then(setUser).catch(() => localStorage.removeItem('token')).finally(() => setReady(true));
  }, []);

  // Token kedaluwarsa / akun dinonaktifkan -> kembali ke login
  useEffect(() => {
    const out = () => { localStorage.removeItem('token'); setUser(null); };
    window.addEventListener('eops:unauthorized', out);
    return () => window.removeEventListener('eops:unauthorized', out);
  }, []);

  if (!ready) return null;
  if (!user) return <Login onLogin={setUser} />;

  const logout = async () => {
    await api('/logout', { method: 'POST' }).catch(() => {});
    localStorage.removeItem('token');
    setUser(null);
  };
  const guard = (roles, el) => (can(user.role.name, roles) ? el : <Navigate to="/" replace />);

  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Layout user={user} onLogout={logout} />}>
          <Route path="/" element={<Dashboard user={user} />} />
          <Route path="/inventory" element={guard(['admin', 'kasir', 'analyst'], <Inventory user={user} />)} />
          <Route path="/transactions" element={guard(['admin', 'kasir'], <Transactions />)} />
          <Route path="/analytics" element={guard(['admin', 'analyst'], <Analytics />)} />
          <Route path="/monitoring" element={guard(['admin', 'support'], <Monitoring />)} />
          <Route path="/categories" element={guard(['admin'], <Categories />)} />
          <Route path="/users" element={guard(['admin'], <Users user={user} />)} />
          <Route path="/helpdesk" element={<Helpdesk user={user} />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}
