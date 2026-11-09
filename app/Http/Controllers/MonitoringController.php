<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, ErrorLog, SystemMetric};
use App\Services\SystemMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller {
    public function health(SystemMetrics $m) {
        $s = $m->snapshot();
        $m->record(60); // merekam riwayat otomatis (maks. 1x/menit) walau scheduler belum dijalankan
        $since = Cache::rememberForever('monitoring_since', fn () => now()->toIso8601String());
        return [
            'app' => 'online',
            'database' => $s['db_up'],
            'db_ms' => $s['db_ms'],
            'cache' => $this->cacheOk(),
            'storage_writable' => is_writable(storage_path()),
            'cpu_pct' => $s['cpu_pct'],
            'memory_pct' => $s['memory_pct'],
            'storage_used_pct' => $s['disk_pct'],
            'server_uptime_seconds' => $s['server_uptime'],   // null bila OS tidak menyediakan
            'monitoring_since' => $since,
            'php_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'errors_24h' => ErrorLog::where('created_at', '>=', now()->subDay())->count(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ];
    }

    /** Riwayat metrik untuk grafik, maksimal ~120 titik. */
    public function metrics(Request $r) {
        $hours = min(max((int) $r->get('hours', 6), 1), 168);
        $rows = SystemMetric::where('created_at', '>=', now()->subHours($hours))->orderBy('created_at')->get();
        $step = max(1, (int) ceil($rows->count() / 120));
        $rows = $rows->filter(fn ($x, $i) => $i % $step === 0)->values();
        return [
            'hours' => $hours,
            'time' => $rows->map(fn ($x) => $x->created_at->toIso8601String()),
            'cpu' => $rows->pluck('cpu_pct'),
            'memory' => $rows->pluck('memory_pct'),
            'disk' => $rows->pluck('disk_pct'),
            'db_ms' => $rows->pluck('db_ms'),
        ];
    }

    public function logs() {
        return [
            'activity' => ActivityLog::with('user:id,name')->latest()->limit(50)->get(),
            'errors' => ErrorLog::latest()->limit(50)->get(),
        ];
    }

    private function cacheOk(): bool {
        try { Cache::put('eops_ping', 1, 10); return Cache::get('eops_ping') === 1; }
        catch (\Throwable $e) { return false; }
    }
}
