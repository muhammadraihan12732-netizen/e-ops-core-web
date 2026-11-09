<?php
namespace App\Services;

use App\Models\SystemMetric;
use Illuminate\Support\Facades\{Cache, DB};

/**
 * Mengumpulkan metrik server. Linux membaca /proc; Windows (XAMPP) memakai PowerShell.
 * Metrik yang tidak tersedia di OS tertentu dikembalikan null (bukan angka palsu).
 */
class SystemMetrics {
    public function snapshot(): array {
        $t = microtime(true);
        try { DB::select('select 1'); $dbUp = true; } catch (\Throwable $e) { $dbUp = false; }
        $dbMs = round((microtime(true) - $t) * 1000, 1);

        $os = $this->osStats();
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'cpu_pct' => $os['cpu'],
            'memory_pct' => $os['memory'],
            'disk_pct' => $total ? round((1 - $free / $total) * 100, 1) : 0,
            'db_ms' => $dbMs,
            'db_up' => $dbUp,
            'server_uptime' => $os['uptime'],
        ];
    }

    /** Simpan satu titik riwayat, maksimal sekali per $minGapSeconds (aman dipanggil dari endpoint). */
    public function record(int $minGapSeconds = 60): ?SystemMetric {
        if ($minGapSeconds > 0 && !Cache::add('metrics_recorded', 1, $minGapSeconds)) return null;
        $s = $this->snapshot();
        unset($s['server_uptime']);
        $row = SystemMetric::create($s + ['created_at' => now()]);
        SystemMetric::where('created_at', '<', now()->subDays(7))->delete(); // jaga tabel tetap kecil
        return $row;
    }

    private function osStats(): array {
        return Cache::remember('os_stats', 15, fn () => PHP_OS_FAMILY === 'Windows' ? $this->windows() : $this->linux());
    }

    private function linux(): array {
        $cpu = $mem = $up = null;
        if (is_readable('/proc/loadavg')) {
            $load = (float) explode(' ', trim(file_get_contents('/proc/loadavg')))[0];
            $cores = max(1, preg_match_all('/^processor/m', @file_get_contents('/proc/cpuinfo') ?: ''));
            $cpu = round(min(100, $load / $cores * 100), 1); // load average 1 menit, dinormalkan per core
        }
        if (is_readable('/proc/meminfo')) {
            $m = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $m, $a) && preg_match('/MemAvailable:\s+(\d+)/', $m, $b) && $a[1] > 0) {
                $mem = round((1 - $b[1] / $a[1]) * 100, 1);
            }
        }
        if (is_readable('/proc/uptime')) $up = (int) explode(' ', trim(file_get_contents('/proc/uptime')))[0];
        return ['cpu' => $cpu, 'memory' => $mem, 'uptime' => $up];
    }

    private function windows(): array {
        $none = ['cpu' => null, 'memory' => null, 'uptime' => null];
        if (!function_exists('shell_exec')) return $none;
        try {
            $ps = 'powershell -NoProfile -Command "$o=Get-CimInstance Win32_OperatingSystem;'
                . '$c=(Get-CimInstance Win32_Processor | Measure-Object LoadPercentage -Average).Average;'
                . '@{cpu=$c;free=$o.FreePhysicalMemory;total=$o.TotalVisibleMemorySize;'
                . 'up=[int]((Get-Date)-$o.LastBootUpTime).TotalSeconds} | ConvertTo-Json -Compress"';
            $j = json_decode((string) @shell_exec($ps), true);
            if (!is_array($j) || empty($j['total'])) return $none;
            return [
                'cpu' => isset($j['cpu']) ? round((float) $j['cpu'], 1) : null,
                'memory' => round((1 - $j['free'] / $j['total']) * 100, 1),
                'uptime' => $j['up'] ?? null,
            ];
        } catch (\Throwable $e) {
            return $none;
        }
    }
}
