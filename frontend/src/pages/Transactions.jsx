import { useState } from 'react';
import { Trash2 } from 'lucide-react';
import { api } from '../api';
import { dt, ErrorBox, Loading, Modal, rp, useLoad } from '../ui';

export default function Transactions() {
  const [q, setQ] = useState('');
  const [cart, setCart] = useState([]);
  const [filter, setFilter] = useState({ from: '', to: '', search: '' });
  const [page, setPage] = useState(1);
  const [detail, setDetail] = useState(null);
  const [paying, setPaying] = useState(false);
  const [payErr, setPayErr] = useState('');

  const prods = useLoad(() => api('/products?per_page=100&search=' + encodeURIComponent(q)).then((r) => r.data), [q]);
  const qs = new URLSearchParams({ page, ...Object.fromEntries(Object.entries(filter).filter(([, v]) => v)) }).toString();
  const hist = useLoad(() => api('/transactions?' + qs), [qs]);

  const add = (p) => setCart((c) => c.find((i) => i.id === p.id)
    ? c.map((i) => (i.id === p.id ? { ...i, qty: Math.min(i.qty + 1, p.stock) } : i))
    : [...c, { id: p.id, name: p.name, price: +p.price, qty: 1, max: p.stock }]);
  const total = cart.reduce((s, i) => s + i.price * i.qty, 0);

  const pay = async () => {
    if (paying) return; // cegah klik ganda -> transaksi ganda
    setPaying(true); setPayErr('');
    try {
      const t = await api('/transactions', { method: 'POST', body: { items: cart.map((i) => ({ product_id: i.id, quantity: i.qty })) } });
      setCart([]); prods.reload(); hist.reload(); setDetail(t);
    } catch (x) { setPayErr(x.message); prods.reload(); } finally { setPaying(false); }
  };
  const open = (t) => api('/transactions/' + t.id).then(setDetail).catch((x) => alert(x.message));
  const set = (k) => (e) => { setFilter({ ...filter, [k]: e.target.value }); setPage(1); };
  const H = hist.data;

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <div className="card space-y-3">
        <h2 className="font-bold">Kasir</h2>
        <input className="input" placeholder="Cari produk…" value={q} onChange={(e) => setQ(e.target.value)} />
        {prods.error ? <ErrorBox message={prods.error} onRetry={prods.reload} /> : (
          <div className="grid max-h-64 grid-cols-2 gap-2 overflow-y-auto">
            {(prods.data || []).map((p) => (
              <button key={p.id} disabled={!p.is_active || p.stock < 1} onClick={() => add(p)}
                className="rounded-lg bg-white/10 p-2 text-left text-[11px] hover:bg-white/20 disabled:opacity-40">
                <div className="font-medium">{p.name}</div>
                <div className="text-slate-400">{rp(p.price)} · stok {p.stock}</div>
              </button>
            ))}
          </div>
        )}
        <div className="space-y-2 border-t border-white/10 pt-3">
          {cart.map((i) => (
            <div key={i.id} className="flex items-center gap-2 text-[11px]">
              <span className="flex-1">{i.name}</span>
              <input type="number" min="1" max={i.max} value={i.qty} className="input !w-16"
                onChange={(e) => setCart(cart.map((c) => (c.id === i.id ? { ...c, qty: Math.max(1, Math.min(+e.target.value || 1, i.max)) } : c)))} />
              <span className="w-24 text-right">{rp(i.price * i.qty)}</span>
              <button onClick={() => setCart(cart.filter((c) => c.id !== i.id))} className="text-rose-400"><Trash2 size={14} /></button>
            </div>
          ))}
          {payErr && <p className="text-xs text-rose-400">{payErr}</p>}
          <div className="flex items-center justify-between pt-2">
            <span className="text-base font-bold text-accent">{rp(total)}</span>
            <button className="btn" disabled={!cart.length || paying} onClick={pay}>{paying ? 'MENYIMPAN…' : 'SIMPAN TRANSAKSI'}</button>
          </div>
        </div>
      </div>

      <div className="card space-y-3 overflow-x-auto">
        <h2 className="font-bold">Riwayat Transaksi</h2>
        <div className="flex flex-wrap gap-2">
          <input className="input !w-40" placeholder="Cari invoice" value={filter.search} onChange={set('search')} />
          <input type="date" className="input !w-36" value={filter.from} max={filter.to || undefined} onChange={set('from')} title="Dari tanggal" />
          <input type="date" className="input !w-36" value={filter.to} min={filter.from || undefined} onChange={set('to')} title="Sampai tanggal" />
        </div>
        {hist.error ? <ErrorBox message={hist.error} onRetry={hist.reload} /> : !H ? <Loading /> : (
          <>
            <table className="w-full min-w-[420px]">
              <thead><tr><th className="th">Invoice</th><th className="th">Kasir</th><th className="th">Total</th><th className="th">Waktu</th></tr></thead>
              <tbody>
                {H.data.map((t) => (
                  <tr key={t.id} onClick={() => open(t)} className="cursor-pointer border-t border-white/5 hover:bg-white/5">
                    <td className="td">{t.invoice}{t.is_demo && <span className="ml-1 rounded bg-white/10 px-1 text-[8px] text-slate-400">demo</span>}</td>
                    <td className="td">{t.user?.name}</td><td className="td">{rp(t.total)}</td><td className="td text-slate-400">{dt(t.created_at)}</td>
                  </tr>
                ))}
                {H.data.length === 0 && <tr><td className="td text-slate-400" colSpan="4">Tidak ada transaksi pada filter ini.</td></tr>}
              </tbody>
            </table>
            <div className="flex items-center justify-end gap-1">
              <span className="mr-2 text-[10px] text-slate-400">Halaman {H.current_page} / {H.last_page}</span>
              <button className="rounded bg-white/10 px-2 py-1 text-[10px] disabled:opacity-40" disabled={page === 1} onClick={() => setPage(page - 1)}>Previous</button>
              <button className="rounded bg-white/10 px-2 py-1 text-[10px] disabled:opacity-40" disabled={page >= H.last_page} onClick={() => setPage(page + 1)}>Next</button>
            </div>
          </>
        )}
      </div>

      {detail && (
        <Modal title={`Invoice ${detail.invoice}`} onClose={() => setDetail(null)}>
          <p className="mb-2 text-[10px] text-slate-400">{detail.user?.name} · {dt(detail.created_at)}</p>
          <table className="w-full">
            <thead><tr><th className="th">Produk</th><th className="th">Qty</th><th className="th">Harga</th><th className="th">Subtotal</th></tr></thead>
            <tbody>{detail.items.map((i) => (
              <tr key={i.id} className="border-t border-white/5"><td className="td">{i.product_name}</td><td className="td">{i.quantity}</td><td className="td">{rp(i.price)}</td><td className="td">{rp(i.subtotal)}</td></tr>
            ))}</tbody>
          </table>
          <div className="mt-3 flex items-center justify-between"><b className="text-accent">{rp(detail.total)}</b>
            <button className="btn" onClick={() => setDetail(null)}>TUTUP</button></div>
        </Modal>
      )}
    </div>
  );
}
