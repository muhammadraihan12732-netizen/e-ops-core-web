import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { api } from '../api';
import { ErrorBox, Loading, Modal, useLoad } from '../ui';

export default function Categories() {
  const { data, error, reload } = useLoad(() => api('/categories'));
  const [form, setForm] = useState(null);
  const [err, setErr] = useState('');

  const save = async (e) => {
    e.preventDefault();
    try {
      await api(form.id ? `/categories/${form.id}` : '/categories', { method: form.id ? 'PUT' : 'POST', body: { name: form.name } });
      setForm(null); setErr(''); reload();
    } catch (x) { setErr(x.message); }
  };
  const del = async (c) => {
    if (!confirm(`Hapus kategori ${c.name}?`)) return;
    try { await api(`/categories/${c.id}`, { method: 'DELETE' }); reload(); } catch (x) { alert(x.message); }
  };

  if (error) return <ErrorBox message={error} onRetry={reload} />;
  if (!data) return <Loading />;
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-bold">Kategori Produk</h1>
        <button className="btn flex items-center gap-1" onClick={() => { setErr(''); setForm({ name: '' }); }}>TAMBAH KATEGORI <Plus size={13} /></button>
      </div>
      <div className="card overflow-x-auto !p-2">
        <table className="w-full min-w-[420px]">
          <thead><tr><th className="th">Nama</th><th className="th">Jumlah produk</th><th className="th">Aksi</th></tr></thead>
          <tbody>
            {data.map((c) => (
              <tr key={c.id} className="border-t border-white/5">
                <td className="td font-medium">{c.name}</td>
                <td className="td text-slate-400">{c.products_count}</td>
                <td className="td">
                  <div className="flex gap-3">
                    <button title="Ubah" onClick={() => { setErr(''); setForm({ ...c }); }} className="text-emerald-400"><Pencil size={14} /></button>
                    <button title="Hapus" onClick={() => del(c)} className="text-rose-400"><Trash2 size={14} /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {form && (
        <Modal title={form.id ? 'Ubah Kategori' : 'Tambah Kategori'} onClose={() => setForm(null)}>
          <form onSubmit={save} className="space-y-2">
            <input className="input" placeholder="Nama kategori" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required autoFocus />
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
