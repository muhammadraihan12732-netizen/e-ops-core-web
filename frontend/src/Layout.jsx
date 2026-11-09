import { NavLink, Outlet } from 'react-router-dom';
import { LayoutGrid, Boxes, ReceiptText, BarChart3, Activity, LifeBuoy, LogOut, Tags, Users } from 'lucide-react';

export const can = (role, roles) => roles === '*' || roles.includes(role);

const MENU = [
  { to: '/', label: 'Dashboard', icon: LayoutGrid, roles: '*' },
  { to: '/inventory', label: 'Inventory', icon: Boxes, roles: ['admin', 'kasir', 'analyst'] },
  { to: '/transactions', label: 'Payment', icon: ReceiptText, roles: ['admin', 'kasir'] },
  { to: '/analytics', label: 'Analytics', icon: BarChart3, roles: ['admin', 'analyst'] },
  { to: '/monitoring', label: 'Monitor', icon: Activity, roles: ['admin', 'support'] },
  { to: '/categories', label: 'Kategori', icon: Tags, roles: ['admin'] },
  { to: '/users', label: 'Users', icon: Users, roles: ['admin'] },
  { to: '/helpdesk', label: 'Helpdesk', icon: LifeBuoy, roles: '*' },
];

export default function Layout({ user, onLogout }) {
  return (
    <div className="flex min-h-screen">
      <aside className="sticky top-0 flex h-screen w-20 shrink-0 flex-col items-center bg-side py-4">
        <div className="mb-6 grid h-10 w-10 place-items-center rounded-xl bg-accent text-sm font-black text-slate-900">EO</div>
        <nav className="flex flex-1 flex-col gap-2">
          {MENU.filter((m) => can(user.role.name, m.roles)).map(({ to, label, icon: Icon }) => (
            <NavLink key={to} to={to} end={to === '/'}
              className={({ isActive }) => `flex flex-col items-center gap-1 border-l-2 px-2 py-2 text-[8px] uppercase ${isActive ? 'border-accent text-accent' : 'border-transparent text-slate-400 hover:text-slate-200'}`}>
              <Icon size={18} /> {label}
            </NavLink>
          ))}
        </nav>
        <div className="text-center text-[9px] text-slate-400">
          <div className="mb-1 capitalize">{user.role.name}</div>
          <button onClick={onLogout} title="Keluar"><LogOut size={18} /></button>
        </div>
      </aside>
      <main className="min-w-0 flex-1 p-4 md:p-6"><Outlet /></main>
    </div>
  );
}
