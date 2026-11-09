<?php
namespace App\Console\Commands;

use App\Services\SystemMetrics;
use Illuminate\Console\Command;

class CollectMetrics extends Command {
    protected $signature = 'eops:collect-metrics';
    protected $description = 'Simpan snapshot metrik sistem (CPU, memori, disk, database) untuk halaman Monitoring';

    public function handle(SystemMetrics $m): int {
        $row = $m->record(0);
        $this->info('Metrik tersimpan #' . $row->id);
        return self::SUCCESS;
    }
}
