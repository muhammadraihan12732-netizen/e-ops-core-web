import { api } from '../api';
import { ErrorBox, Loading, StatCard, rp, useLoad } from '../ui';

const n = (x) => new Intl.NumberFormat('id-ID').format(x);

export default function Dashboard({ user }) {
  const { data: d, error, reload } = useLoad(() => api('/analytics/overview'), [], { every: 30000 }); // polling 30 detik
  if (error && !d) return <ErrorBox message={error} onRetry={reload} />;
  if (!d) return <Loading />;
  const { labels, series: s } = d;
  return (
    <div>
      <h1 className="mb-4 text-lg font-bold">Halo, {user.name}</h1>
      {error && <div className="mb-3"><ErrorBox message={`Gagal memperbarui data: ${error}`} onRetry={reload} /></div>}
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <StatCard title="Jenis Produk" value={n(d.total_products)} sub="produk per kategori" color="#1e90ff" labels={s.category_labels} data={s.category_products} />
        <StatCard title="Barang Terjual" value={n(d.total_items_sold)} sub="unit terjual / bulan" color="#8b7cf6" labels={labels} data={s.items} />
        {s.revenue
          ? <StatCard title="Total Sales" value={rp(d.total_revenue)} sub="pendapatan / bulan" color="#22b07d" labels={labels} data={s.revenue} />
          : null}
        <StatCard title="Total Orders" value={n(d.total_transactions)} sub="transaksi / bulan" color="#e0a82e" labels={labels} data={s.transactions} />
        <StatCard title="Stok Menipis" value={n(d.low_stock_products)} sub="12 stok terendah" color="#e0457b" labels={s.stock_labels.map((x) => x[0])} data={s.stock} />
      </div>
    </div>
  );
}
