<?php
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\{Artisan, Schedule};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jalankan `php artisan schedule:work` (dev) atau cron `schedule:run` (server) agar riwayat terisi.
// Tanpa scheduler, halaman Monitoring tetap merekam satu titik per menit saat dibuka.
Schedule::command('eops:collect-metrics')->everyMinute();
