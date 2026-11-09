import { useState } from 'react';
import { Line } from 'react-chartjs-2';
import { api } from '../api';
import { chartOpts, dt, ErrorBox, Loading, useLoad } from '../ui';

const dur = (s) => (s == null ? 'n/a' : s >= 86400 ? `${Math.floor(s / 86400)}h ${Math.floor((s % 86400) / 3600)}j` : `${Math.floor(s / 3600)}j ${Math.floor((s % 3600) / 60)}m`);
const pct = (v) => (v == null ? 'n/a' : v + '%');
const level = (v, warn, bad) => (v == null ? undefined : v < warn ? true : v < bad ? 'warn' : false);

function Card({ t, v, ok, hint }) {
  const color = ok === undefined ? '' : ok === true ? 'text-emerald-400' : ok === 'warn' ? 'text-amber-400' : 'text-rose-400';
  return (
    <div className="card !p-3">
      <div className="text-[10px] uppercase text-slate-400">{t}</div>
      <div className={`text-lg font-bold ${color}`}>{v}</div>
      {hint && <div className="text-[9px] text-slate-500">{hint}</div>}
    </div>
  );
}

export default function Monitoring() {
  const [hours, setHours] = useState(6);
  const health = useLoad(() => api('/monitoring/health'), [], { every: 30000 });
  const logs = useLoad(() => api('/logs'), [], { every: 30000 });
  const metrics = useLoad(() => api('/monitoring/metrics?hours=' + hours), [hours], { every: 60000 });

  if (health.error && !health.data) return <ErrorBox message={health.error} onRetry={health.reload} />;
  const h = health.data;
  if (!h) return <Loading />;

  const m = metrics.data;
  const series = (label, key, color) => ({ label, data: m[key], borderColor: color, pointRadius: 0, tension: 0.3, spanGaps: false });
  const times = m ? m.time.map((t) => new Date(t).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })) : [];
  const L = logs.data || { activity: [], errors: [] };

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-bold">System Monitoring</h1>
      {health.error && <ErrorBox message={`Gagal memperbarui status: ${health.error}`} onRetry={health.reload} />}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
        <Card t="Database" v={h.database ? `${h.db_ms} ms` : 'DOWN'} ok={h.database ? level(h.db_ms, 200, 1000) : false} hint="waktu respons query" />
        <Card t="Aplikasi" v={h.app} ok />
        <Card t="Cache" v={h.cache ? 'OK' : 'GAGAL'} ok={h.cache} />
        <Card t="Penyimpanan tulis" v={h.storage_writable ? 'OK' : 'GAGAL'} ok={h.storage_writable} />
        <Card t="CPU" v={pct(h.cpu_pct)} ok={level(h.cpu_pct, 70, 90)} hint="load rata-rata 1 menit" />
        <Card t="Memori sistem" v={pct(h.memory_pct)} ok={level(h.memory_pct, 80, 92)} />
        <Card t="Disk terpakai" v={pct(h.storage_used_pct)} ok={level(h.storage_used_pct, 80, 90)} />
        <Card t="Uptime server" v={dur(h.server_uptime_seconds)} hint={h.server_uptime_seconds == null ? 'tidak tersedia di OS ini' : undefined} />
        <Card t="Error 24 jam" v={h.errors_24h} ok={h.errors_24h === 0 ? true : h.errors_24h < 10 ? 'warn' : false} />
        <Card t="Memori PHP (puncak)" v={h.php_memory_mb + ' MB'} />
      </div>
      <p className="text-[10px] text-slate-400">PHP {h.php} · Laravel {h.laravel} · diperbarui otomatis tiap 30 detik · nilai "n/a" berarti OS server tidak menyediakan metrik itu</p>

      <div className="card">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-xs font-semibold">Riwayat metrik</h2>
          <select className="input !w-auto" value={hours} onChange={(e) => setHours(+e.target.value)}>
            {[1, 6, 24, 168].map((x) => <option key={x} value={x} className="text-black">{x >= 24 ? `${x / 24} hari` : `${x} jam`}</option>)}
          </select>
        </div>
        {metrics.error ? <ErrorBox message={metrics.error} onRetry={metrics.reload} /> : !m ? <Loading /> : m.time.length < 2 ? (
          <p className="text-[11px] text-slate-400">Belum cukup data riwayat. Titik baru tercatat tiap menit saat halaman ini dibuka atau saat <code>php artisan schedule:work</code> berjalan.</p>
        ) : (
          <div className="grid gap-4 lg:grid-cols-2">
            <div className="h-48"><Line options={{ ...chartOpts, scales: { ...chartOpts.scales, y: { ...chartOpts.scales.y, min: 0, max: 100 } } }}
              data={{ labels: times, datasets: [series('CPU %', 'cpu', '#e0a82e'), series('Memori %', 'memory', '#1e90ff'), series('Disk %', 'disk', '#22b07d')] }} /></div>
            <div className="h-48"><Line options={chartOpts} data={{ labels: times, datasets: [series('DB (ms)', 'db_ms', '#e0457b')] }} /></div>
          </div>
        )}
      </div>

      {logs.error && <ErrorBox message={logs.error} onRetry={logs.reload} />}
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="card"><h2 className="mb-2 text-xs font-semibold">Activity Logs</h2>
          <div className="max-h-80 overflow-y-auto">{L.activity.map((l) => (
            <div key={l.id} className="border-t border-white/5 py-1.5 text-[11px]"><b>{l.user?.name || 'system'}</b> · {l.action} {l.description && <span className="text-slate-400">— {l.description}</span>}<div className="text-[9px] text-slate-500">{dt(l.created_at)}</div></div>
          ))}</div></div>
        <div className="card"><h2 className="mb-2 text-xs font-semibold text-rose-300">Error Logs</h2>
          <div className="max-h-80 overflow-y-auto">{L.errors.length === 0 && <p className="text-[11px] text-slate-400">Belum ada error.</p>}
            {L.errors.map((l) => (
              <div key={l.id} className="border-t border-white/5 py-1.5 text-[11px]">{l.message}<div className="text-[9px] text-slate-500">{l.context} · {dt(l.created_at)}</div></div>
            ))}</div></div>
      </div>
    </div>
  );
}
