import { useState } from 'react';
import { Pencil, Plus, Search } from 'lucide-react';
import { api } from '../api';
import { ErrorBox, Loading, Modal, Toggle, useLoad } from '../ui';

const EMPTY = { name: '', email: '', password: '', role_id: '', is_active: true };

export default function Users({ user }) {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState(null);
  const [err, setErr] = useState('');
  const { data: roles } = useLoad(() => api('/roles'));
  const { data: res, error, reload } = useLoad(() => api(`/users?page=${page}&search=${encodeURIComponent(search)}`), [page, search]);

  const save = async (e) => {
    e.preventDefault();
    try {
      const body = { ...form };
      if (form.id && !body.password) delete body.password;
      await api(form.id ? `/users/${form.id}` : '/users', { method: form.id ? 'PUT' : 'POST', body });
      setForm(null); setErr(''); reload();
    } catch (x) { setErr(x.message); }
  };
  const toggle = async (u) => {
    try { await api(`/users/${u.id}`, { method: 'PUT', body: { name: u.name, email: u.email, role_id: u.role_id, is_active: !u.is_active } }); reload(); }
    catch (x) { alert(x.message); }
  };
  const open = (u) => { setErr(''); setForm(u ? { ...u, password: '' } : { ...EMPTY, role_id: roles?.[1]?.id ?? '' }); };

  if (error && !res) return <ErrorBox message={error} onRetry={reload} />;
  if (!res || !roles) return <Loading />;
  const pg = 'rounded px-2 py-1 text-[10px] disabled:opacity-40 bg-white/10';
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-lg font-bold">User Management</h1>
        <div className="relative ml-auto min-w-48 md:max-w-xs md:flex-1">
          <Search size={13} className="absolute left-3 top-2.5 text-slate-400" />
          <input className="input !pl-8" placeholder="Cari nama / email" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
        <button className="btn flex items-center gap-1" onClick={() => open(null)}>TAMBAH USER <Plus size={13} /></button>
      </div>
      <div className="card overflow-x-auto !p-2">
        <table className="w-full min-w-[640px]">
          <thead><tr><th className="th">Nama</th><th className="th">Email</th><th className="th">Role</th><th className="th">Aktif</th><th className="th">Aksi</th></tr></thead>
          <tbody>
            {res.data.map((u) => (
              <tr key={u.id} className="border-t border-white/5">
                <td className="td font-medium">{u.name}{u.id === user.id && <span className="ml-1 text-[9px] text-accent">(Anda)</span>}</td>
                <td className="td text-slate-400">{u.email}</td>
                <td className="td capitalize">{u.role?.name}</td>
                <td className="td"><Toggle on={u.is_active} disabled={u.id === user.id} onClick={() => toggle(u)} /></td>
                <td className="td"><button title="Ubah" onClick={() => open(u)} className="text-emerald-400"><Pencil size={14} /></button></td>
              </tr>
            ))}
            {res.data.length === 0 && <tr><td className="td text-slate-400" colSpan="5">Tidak ada pengguna.</td></tr>}
          </tbody>
        </table>
        <div className="flex items-center justify-end gap-1 p-2">
          <span className="mr-2 text-[10px] text-slate-400">Halaman {res.current_page} / {res.last_page}</span>
          <button className={pg} disabled={page === 1} onClick={() => setPage(page - 1)}>Previous</button>
          <button className={pg} disabled={page >= res.last_page} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      </div>
      {form && (
        <Modal title={form.id ? 'Ubah Pengguna' : 'Tambah Pengguna'} onClose={() => setForm(null)}>
          <form onSubmit={save} className="space-y-2">
            <input className="input" placeholder="Nama lengkap" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            <input className="input" type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
            <input className="input" type="password" minLength={form.id ? 0 : 8} placeholder={form.id ? 'Password baru (kosongkan jika tidak diganti)' : 'Password (min. 8 karakter)'}
              value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required={!form.id} autoComplete="new-password" />
            <select className="input" value={form.role_id} disabled={form.id === user.id} onChange={(e) => setForm({ ...form, role_id: +e.target.value })} required>
              {roles.map((r) => <option key={r.id} value={r.id} className="text-black">{r.name}</option>)}
            </select>
            {err && <p className="text-xs text-rose-400">{err}</p>}
            <div className="flex justify-end gap-2 pt-2">
              <button type="button" className="rounded-md bg-white/10 px-3 py-2 text-[11px]" onClick={() => setForm(null)}>Batal</button>
              <button className="btn">SIMPAN</button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
