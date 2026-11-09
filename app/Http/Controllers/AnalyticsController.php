<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Category, Product, Transaction};
use App\Services\SalesForecaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller {
    public function overview(Request $r) {
        // Dashboard terbuka untuk semua role, tetapi angka pendapatan hanya untuk admin & analyst.
        $money = in_array($r->user()->role?->name, ['admin', 'analyst']);
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $from = $months->first();
        $trx = DB::table('transactions')->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') m, SUM(total) revenue, COUNT(*) cnt")->groupBy('m')->get()->keyBy('m');
        $items = DB::table('transaction_items')->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') m, SUM(quantity) q")->groupBy('m')->pluck('q', 'm');
        $lowest = Product::orderBy('stock')->limit(12)->get(['name', 'stock']);
        $cats = Category::withCount('products')->orderBy('name')->get();
        return [
            'labels' => $months->map->format('M')->values(),
            'total_revenue' => $money ? (float) Transaction::sum('total') : null,
            'total_transactions' => Transaction::count(),
            'total_products' => Product::count(),
            'total_items_sold' => (int) DB::table('transaction_items')->sum('quantity'),
            'low_stock_products' => Product::whereColumn('stock', '<=', 'min_stock')->count(),
            'series' => [
                'revenue' => $money ? $months->map(fn ($m) => (float) ($trx[$m->format('Y-m')]->revenue ?? 0))->values() : null,
                'transactions' => $months->map(fn ($m) => (int) ($trx[$m->format('Y-m')]->cnt ?? 0))->values(),
                'items' => $months->map(fn ($m) => (int) ($items[$m->format('Y-m')] ?? 0))->values(),
                'stock' => $lowest->pluck('stock'),
                'stock_labels' => $lowest->pluck('name'),
                'category_labels' => $cats->pluck('name'),
                'category_products' => $cats->pluck('products_count'),
            ],
        ];
    }

    public function sales() {
        $rows = DB::table('transactions')->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) d, SUM(total) revenue')->groupBy('d')->pluck('revenue', 'd');
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        return [
            'labels' => $days->map(fn ($d) => substr($d, 5))->values(),
            'revenue' => $days->map(fn ($d) => (float) ($rows[$d] ?? 0))->values(),
        ];
    }

    public function top() {
        $base = fn () => DB::table('transaction_items')->selectRaw('product_name name, SUM(quantity) qty')->groupBy('product_name')->limit(5);
        return [
            'top' => $base()->orderByDesc('qty')->get(),
            'bottom' => $base()->orderBy('qty')->get(),
            'categories' => DB::table('transaction_items as ti')
                ->join('products as p', 'p.id', '=', 'ti.product_id')
                ->join('categories as c', 'c.id', '=', 'p.category_id')
                ->selectRaw('c.name, SUM(ti.subtotal) total')->groupBy('c.name')->get(),
        ];
    }

    /** Prediksi pendapatan harian (lihat SalesForecaster). Riwayat 84 hari -> prediksi 7-60 hari. */
    public function forecast(Request $r) {
        $r->validate(['days' => 'nullable|integer|min:7|max:60']);
        $horizon = (int) $r->get('days', 14);
        $window = 84;
        $rows = DB::table('transactions')->where('created_at', '>=', now()->subDays($window - 1)->startOfDay())
            ->selectRaw('DATE(created_at) d, SUM(total) revenue')->groupBy('d')->pluck('revenue', 'd');
        $dates = collect(range($window - 1, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $y = $dates->map(fn ($d) => (float) ($rows[$d] ?? 0))->all();

        $f = SalesForecaster::forecast($y, $dates->last(), $horizon);
        if (!$f) return response()->json(['message' => 'Data penjualan belum cukup (minimal 14 hari) untuk membuat prediksi'], 422);

        return [
            'history' => ['labels' => $dates->slice(-30)->map(fn ($d) => substr($d, 5))->values(), 'revenue' => array_slice($y, -30)],
            'forecast' => $f['forecast'],
            'trend_per_day' => $f['trend_per_day'],
            'next_7_total' => round(array_sum(array_column(array_slice($f['forecast'], 0, 7), 'revenue')), 2),
            'method' => 'Tren linier + pola hari-dalam-minggu, data ' . $window . ' hari terakhir',
        ];
    }

    public function export(Request $r) {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        ActivityLog::record('report.export', ($r->from ?: 'awal') . ' s/d ' . ($r->to ?: 'sekarang'));
        $q = Transaction::with('user:id,name')
            ->when($r->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($r->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return response()->streamDownload(function () use ($q) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF"); // BOM: Excel membaca UTF-8 dengan benar
            fputcsv($o, ['Invoice', 'Kasir', 'Total', 'Tanggal', 'Sumber'], ',', '"', '\\');
            foreach ($q->lazyById(500) as $t) {
                fputcsv($o, [
                    $this->safe($t->invoice), $this->safe($t->user?->name), $t->total,
                    $t->created_at->format('Y-m-d H:i:s'), $t->is_demo ? 'demo' : 'operasional',
                ], ',', '"', '\\');
            }
            fclose($o);
        }, 'laporan-penjualan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV injection: sel yang diawali = + - @ akan dieksekusi sebagai rumus oleh Excel. */
    private function safe($v): string {
        $v = (string) $v;
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    }
}
