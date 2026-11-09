import { useCallback, useEffect, useState } from 'react';
import { Bar } from 'react-chartjs-2';

export const rp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);
export const dt = (s) => new Date(s).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const tick = { color: '#94a3b8', font: { size: 9 } };
export const chartOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { labels: { color: '#cbd5e1' } } },
  scales: { x: { ticks: tick, grid: { display: false } }, y: { ticks: tick, grid: { color: 'rgba(255,255,255,.06)' } } },
};

export function StatCard({ title, value, sub, color, labels, data }) {
  return (
    <div className="rounded-2xl border border-white/5 bg-panel p-3 shadow-lg">
      <div className="flex items-start justify-between gap-2">
        <span className="text-[10px] font-semibold uppercase leading-tight">{title}</span>
        <span className="text-lg font-bold" style={{ color }}>{value}</span>
      </div>
      <div className="text-right text-[9px] text-slate-400">{sub}</div>
      <div className="mt-1 h-16">
        <Bar
          data={{ labels, datasets: [{ data, backgroundColor: color, borderRadius: 3, barPercentage: 0.35, categoryPercentage: 1 }] }}
          options={{
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false }, border: { display: false }, ticks: { color: '#94a3b8', font: { size: 8 } } }, y: { display: false } },
          }}
        />
      </div>
    </div>
  );
}

export function Toggle({ on, onClick, disabled }) {
  return (
    <button disabled={disabled} onClick={onClick} className={`h-4 w-8 rounded-full p-0.5 transition ${on ? 'bg-emerald-400' : 'bg-slate-500'} disabled:opacity-60`}>
      <span className={`block h-3 w-3 rounded-full bg-white transition ${on ? 'translate-x-4' : ''}`} />
    </button>
  );
}

export function Modal({ title, onClose, children }) {
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <h2 className="mb-3 text-sm font-bold">{title}</h2>
        {children}
      </div>
    </div>
  );
}

/** Memuat data dengan loading, error, dan tombol coba lagi. */
export function useLoad(fn, deps = [], { every } = {}) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const load = useCallback(() => {
    setLoading(true);
    return fn().then((d) => { setData(d); setError(''); }).catch((e) => setError(e.message)).finally(() => setLoading(false));
  }, deps);
  useEffect(() => {
    load();
    if (!every) return;
    const t = setInterval(load, every);
    return () => clearInterval(t);
  }, [load, every]);
  return { data, error, loading, reload: load, setData };
}

export function ErrorBox({ message, onRetry }) {
  return (
    <div className="card flex items-center justify-between gap-3 border border-rose-400/30 text-rose-300">
      <span className="text-xs">{message}</span>
      {onRetry && <button className="btn" onClick={onRetry}>COBA LAGI</button>}
    </div>
  );
}

export const Loading = () => <p className="text-slate-400">Memuat…</p>;
