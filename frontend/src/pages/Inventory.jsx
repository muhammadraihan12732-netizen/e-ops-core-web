import { useEffect, useState } from 'react';
import { Pencil, Trash2, Search, Plus } from 'lucide-react';
import { api } from '../api';
import { ErrorBox, Modal, Toggle, rp } from '../ui';

const EMPTY = { sku: '', name: '', category_id: '', price: '', stock: '', is_active: true };

export default function Inventory({ user }) {
  const admin = user.role.name === 'admin';
  const [res, setRes] = useState({ data: [], last_page: 1 });
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [cats, setCats] = useState([]);
  const [form, setForm] = useState(null);
  const [error, setError] = useState('');

  const load = () => api(`/products?page=${page}&search=${encodeURIComponent(search)}`).then((r) => { setRes(r); setError(''); }).catch((x) => setError(x.message));
  useEffect(() => { load(); }, [page, search]);
  useEffect(() => { api('/categories').then(setCats).catch((x) => setError(x.message)); }, []);

  const save = async (e) => {
    e.preventDefault();
    try {
      await api(form.id ? `/products/${form.id}` : '/products', { method: form.id ? 'PUT' : 'POST', body: form });
      setForm(null); load();
    } catch (x) { alert(x.message); }
  };
  const toggle = (p) => api(`/products/${p.id}`, { method: 'PUT', body: { ...p, is_active: !p.is_active } }).then(load).catch((x) => alert(x.message));
  const del = (p) => confirm(`Hapus ${p.name}?`) && api(`/products/${p.id}`, { method: 'DELETE' }).then(load).catch((x) => alert(x.message));

  const pages = Array.from({ length: res.last_page }, (_, i) => i + 1).filter((n) => Math.abs(n - page) <= 2);
  const pg = 'rounded px-2 py-1 text-[10px] disabled:opacity-40 ';

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-48 flex-1 md:max-w-xs">
          <Search size={13} className="absolute left-3 top-2.5 text-slate-400" />
          <input className="input !pl-8" placeholder="Search" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
        {admin && <button className="btn ml-auto flex items-center gap-1" onClick={() => setForm({ ...EMPTY, category_id: cats[0]?.id })}>ADD NEW PRODUCT <Plus size={13} /></button>}
      </div>

      {error && <ErrorBox message={error} onRetry={load} />}
      <div className="card overflow-x-auto !p-2">
        <table className="w-full min-w-[700px]">
          <thead>
            <tr><th className="th"></th><th className="th">Name</th><th className="th">ID</th><th className="th">Category</th><th className="th">Stock</th><th className="th">Availability</th><th className="th">Price / Unit</th>{admin && <th className="th">Action</th>}</tr>
          </thead>
          <tbody>
            {res.data.map((p) => (
              <tr key={p.id} className="border-t border-white/5">
                <td className="td"><input type="checkbox" className="accent-accent" /></td>
                <td className="td font-medium">{p.name}</td>
                <td className="td text-slate-400">{p.sku}</td>
                <td className="td text-slate-400">{p.category?.name}</td>
                <td className="td">{String(p.stock).padStart(3, '0')} / {p.max_stock}</td>
                <td className="td"><Toggle on={p.is_active} disabled={!admin} onClick={() => toggle(p)} /></td>
                <td className="td">{rp(p.price)}</td>
                {admin && (
                  <td className="td">
                    <div className="flex gap-3">
                      <button onClick={() => setForm({ ...p })} className="text-emerald-400"><Pencil size={14} /></button>
                      <button onClick={() => del(p)} className="text-rose-400"><Trash2 size={14} /></button>
                    </div>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
        <div className="flex justify-end gap-1 p-2">
          <button className={pg + 'bg-white/10'} disabled={page === 1} onClick={() => setPage(1)}>First</button>
          <button className={pg + 'bg-white/10'} disabled={page === 1} onClick={() => setPage(page - 1)}>Previous</button>
          {pages.map((n) => <button key={n} onClick={() => setPage(n)} className={pg + (n === page ? 'bg-white text-slate-900' : 'bg-white/10')}>{n}</button>)}
          <button className={pg + 'bg-white/10'} disabled={page >= res.last_page} onClick={() => setPage(page + 1)}>Next</button>
          <button className={pg + 'bg-white/10'} disabled={page >= res.last_page} onClick={() => setPage(res.last_page)}>Last</button>
        </div>
      </div>

      {form && (
        <Modal title={form.id ? 'Edit Produk' : 'Tambah Produk'} onClose={() => setForm(null)}>
          <form onSubmit={save} className="space-y-2">
            <input className="input" placeholder="SKU (mis. BS34580)" value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} required />
            <input className="input" placeholder="Nama produk" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            <select className="input" value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })}>
              {cats.map((c) => <option key={c.id} value={c.id} className="text-black">{c.name}</option>)}
            </select>
            <div className="grid grid-cols-2 gap-2">
              <input className="input" type="number" min="0" placeholder="Harga" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} required />
              <input className="input" type="number" min="0" placeholder="Stok" value={form.stock} onChange={(e) => setForm({ ...form, stock: e.target.value })} required />
            </div>
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
