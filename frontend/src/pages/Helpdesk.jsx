import { useEffect, useState } from 'react';
import { api } from '../api';
import { dt, ErrorBox, Loading } from '../ui';

const STATUS = { open: 'bg-sky-500', in_progress: 'bg-amber-500', resolved: 'bg-emerald-500', closed: 'bg-slate-500' };

export default function Helpdesk({ user }) {
  const staff = ['admin', 'support'].includes(user.role.name);
  const [tickets, setTickets] = useState(null);
  const [error, setError] = useState('');
  const [f, setF] = useState({ title: '', description: '', category: 'Aplikasi', priority: 'medium' });
  const [msg, setMsg] = useState({});
  const load = () => api('/tickets').then((t) => { setTickets(t); setError(''); }).catch((x) => setError(x.message));
  useEffect(() => { load(); }, []);

  const create = async (e) => {
    e.preventDefault();
    try { await api('/tickets', { method: 'POST', body: f }); setF({ ...f, title: '', description: '' }); load(); }
    catch (x) { alert(x.message); }
  };
  const reply = async (id) => {
    if (!msg[id]?.trim()) return;
    try { await api(`/tickets/${id}/replies`, { method: 'POST', body: { message: msg[id] } }); setMsg({ ...msg, [id]: '' }); load(); }
    catch (x) { alert(x.message); }
  };
  const setStatus = (id, status) => api(`/tickets/${id}/status`, { method: 'PATCH', body: { status } }).then(load).catch((x) => alert(x.message));

  return (
    <div className="grid gap-4 lg:grid-cols-3">
      <form onSubmit={create} className="card h-fit space-y-2">
        <h2 className="font-bold">Buat Tiket</h2>
        <input className="input" placeholder="Judul masalah" value={f.title} onChange={(e) => setF({ ...f, title: e.target.value })} required />
        <textarea className="input" rows="4" placeholder="Deskripsi" value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} required />
        <div className="grid grid-cols-2 gap-2">
          <select className="input" value={f.category} onChange={(e) => setF({ ...f, category: e.target.value })}>
            {['Aplikasi', 'Jaringan', 'Perangkat', 'Akun'].map((c) => <option key={c} className="text-black">{c}</option>)}
          </select>
          <select className="input" value={f.priority} onChange={(e) => setF({ ...f, priority: e.target.value })}>
            {['low', 'medium', 'high'].map((c) => <option key={c} className="text-black">{c}</option>)}
          </select>
        </div>
        <button className="btn w-full">KIRIM</button>
      </form>

      <div className="space-y-3 lg:col-span-2">
        {error && <ErrorBox message={error} onRetry={load} />}
        {!tickets && !error && <Loading />}
        {tickets?.length === 0 && <p className="text-slate-400">Belum ada tiket.</p>}
        {(tickets || []).map((t) => (
          <div key={t.id} className="card space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <b className="flex-1">#{t.id} {t.title}</b>
              <span className="rounded bg-white/10 px-2 py-0.5 text-[10px]">{t.priority}</span>
              {staff
                ? <select value={t.status} onChange={(e) => setStatus(t.id, e.target.value)} className={`rounded px-2 py-0.5 text-[10px] ${STATUS[t.status]}`}>
                    {Object.keys(STATUS).map((s) => <option key={s} value={s} className="text-black">{s.replace('_', ' ')}</option>)}
                  </select>
                : <span className={`rounded px-2 py-0.5 text-[10px] ${STATUS[t.status]}`}>{t.status.replace('_', ' ')}</span>}
            </div>
            <p className="text-[11px] text-slate-300">{t.description}</p>
            <p className="text-[9px] text-slate-500">{t.user?.name} · {t.category} · {dt(t.created_at)}</p>
            {t.replies.map((r) => <div key={r.id} className="rounded-lg bg-white/5 p-2 text-[11px]"><b>{r.user?.name}:</b> {r.message}</div>)}
            {t.status !== 'closed' && (
              <div className="flex gap-2">
                <input className="input" placeholder="Tulis tanggapan…" value={msg[t.id] || ''} onChange={(e) => setMsg({ ...msg, [t.id]: e.target.value })} />
                <button className="btn" onClick={() => reply(t.id)}>KIRIM</button>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
