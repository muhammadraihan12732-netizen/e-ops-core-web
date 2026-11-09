import { useState } from 'react';
import { Line, Bar, Doughnut } from 'react-chartjs-2';
import { Download } from 'lucide-react';
import { api, download } from '../api';
import { chartOpts, ErrorBox, Loading, rp, useLoad } from '../ui';

const COLORS = ['#1e90ff', '#22b07d', '#e0a82e', '#e0457b', '#1fc8c9'];

export default function Analytics() {
  const { data, error, reload } = useLoad(() => Promise.all([api('/analytics/sales'), api('/analytics/top')]));
  const fc = useLoad(() => api('/analytics/forecast?days=14'));
  const [range, setRange] = useState({ from: '', to: '' });
  const [busy, setBusy] = useState(false);
  const [exportErr, setExportErr] = useState('');

  const exportCsv = async () => {
    setBusy(true); setExportErr('');
    const qs = new URLSearchParams(Object.entries(range).filter(([, v]) => v)).toString();
    try { await download('/reports/sales.csv' + (qs ? '?' + qs : ''), 'laporan-penjualan.csv'); }
    catch (x) { setExportErr(x.message); }
    finally { setBusy(false); }
  };

  if (error) return <ErrorBox message={error} onRetry={reload} />;
  if (!data) return <Loading />;
  const [sales, top] = data;
  const bar = (rows, color) => ({ labels: rows.map((r) => r.name), datasets: [{ data: rows.map((r) => +r.qty), backgroundColor: color, borderRadius: 4 }] });
  const noLegend = { ...chartOpts, plugins: { legend: { display: false } } };
  const money = { ...noLegend, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => rp(c.parsed.y) } } } };

  const f = fc.data;
  const fLabels = f ? [...f.history.labels, ...f.forecast.map((x) => x.date.slice(5))] : [];
  const pad = f ? Array(f.history.labels.length - 1).fill(null) : [];
  const last = f ? f.history.revenue[f.history.revenue.length - 1] : null;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <h1 className="mr-auto text-lg font-bold">Analytics & Reports</h1>
        <input type="date" className="input !w-36" value={range.from} max={range.to || undefined} onChange={(e) => setRange({ ...range, from: e.target.value })} title="Dari tanggal" />
        <input type="date" className="input !w-36" value={range.to} min={range.from || undefined} onChange={(e) => setRange({ ...range, to: e.target.value })} title="Sampai tanggal" />
        <button className="btn flex items-center gap-1" disabled={busy} onClick={exportCsv}><Download size={13} /> {busy ? 'MENGUNDUH…' : 'EXPORT CSV'}</button>
      </div>
      {exportErr && <ErrorBox message={exportErr} />}

      <div className="card">
        <h2 className="mb-2 text-xs font-semibold">Pendapatan 30 hari terakhir</h2>
        <div className="h-64"><Line options={money} data={{ labels: sales.labels, datasets: [{ data: sales.revenue, borderColor: '#1fc8c9', backgroundColor: 'rgba(31,200,201,.15)', fill: true, tension: 0.35, pointRadius: 0 }] }} /></div>
      </div>

      <div className="card">
        <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="text-xs font-semibold">Prediksi pendapatan 14 hari</h2>
          {f && <span className="text-[10px] text-slate-400">Prediksi 7 hari: <b className="text-slate-200">{rp(f.next_7_total)}</b> · tren {f.trend_per_day >= 0 ? '+' : ''}{rp(f.trend_per_day)}/hari</span>}
        </div>
        {fc.error ? <ErrorBox message={fc.error} onRetry={fc.reload} /> : !f ? <Loading /> : (
          <>
            <div className="h-64">
              <Line options={{ ...money, plugins: { legend: { display: true, labels: { color: '#cbd5e1', font: { size: 10 } } }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${rp(c.parsed.y)}` } } } }}
                data={{ labels: fLabels, datasets: [
                  { label: 'Aktual', data: [...f.history.revenue, ...f.forecast.map(() => null)], borderColor: '#1fc8c9', pointRadius: 0, tension: 0.3 },
                  { label: 'Prediksi', data: [...pad, last, ...f.forecast.map((x) => x.revenue)], borderColor: '#e0457b', borderDash: [5, 4], pointRadius: 0, tension: 0.3 },
                  { label: 'Batas atas', data: [...pad, last, ...f.forecast.map((x) => x.upper)], borderColor: 'transparent', backgroundColor: 'rgba(224,69,123,.12)', pointRadius: 0, fill: '+1' },
                  { label: 'Batas bawah', data: [...pad, last, ...f.forecast.map((x) => x.lower)], borderColor: 'transparent', pointRadius: 0 },
                ] }} />
            </div>
            <p className="mt-2 text-[10px] text-slate-500">{f.method}. Area merah muda = rentang ±80%. Ini perkiraan kasar dari data historis, bukan jaminan.</p>
          </>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="card"><h2 className="mb-2 text-xs font-semibold">Produk terlaris</h2><div className="h-52"><Bar options={noLegend} data={bar(top.top, '#22b07d')} /></div></div>
        <div className="card"><h2 className="mb-2 text-xs font-semibold">Paling sedikit terjual</h2><div className="h-52"><Bar options={noLegend} data={bar(top.bottom, '#e0457b')} /></div></div>
        <div className="card"><h2 className="mb-2 text-xs font-semibold">Pendapatan per kategori</h2>
          <div className="h-52"><Doughnut options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1', font: { size: 10 } } } } }}
            data={{ labels: top.categories.map((c) => c.name), datasets: [{ data: top.categories.map((c) => +c.total), backgroundColor: COLORS, borderWidth: 0 }] }} /></div>
        </div>
      </div>
    </div>
  );
}
