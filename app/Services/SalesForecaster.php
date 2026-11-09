<?php
namespace App\Services;

use Carbon\Carbon;

/**
 * Prediksi pendapatan harian: tren linier (OLS) pada data yang sudah dibersihkan dari pola hari-dalam-minggu.
 * Algoritma yang sama dipakai di data-analyst/analyze_sales.py (lengkap dengan backtest).
 * Metode sederhana dan transparan, cocok untuk data MVP; bukan model produksi.
 */
class SalesForecaster {
    /**
     * @param float[] $y     pendapatan harian, urut dari yang terlama
     * @param string  $lastDate tanggal (Y-m-d) dari elemen terakhir $y
     * @return array{forecast: array, trend_per_day: float, band: float}|null  null bila data < 14 hari
     */
    public static function forecast(array $y, string $lastDate, int $horizon = 14): ?array {
        $n = count($y);
        if ($n < 14) return null;
        $last = Carbon::parse($lastDate)->startOfDay();
        $dow = fn (int $idx) => $last->copy()->subDays($n - 1 - $idx)->dayOfWeek; // 0..6

        $mean = array_sum($y) / $n;
        $factor = array_fill(0, 7, 1.0);
        if ($mean > 0) {
            for ($w = 0; $w < 7; $w++) {
                $vals = [];
                foreach ($y as $i => $v) if ($dow($i) === $w) $vals[] = $v;
                if ($vals) $factor[$w] = max(0.05, (array_sum($vals) / count($vals)) / $mean);
            }
        }
        $z = [];
        foreach ($y as $i => $v) $z[] = $v / $factor[$dow($i)];

        // regresi linier z = a + b*t
        $tMean = ($n - 1) / 2;
        $zMean = array_sum($z) / $n;
        $num = $den = 0.0;
        foreach ($z as $t => $v) { $num += ($t - $tMean) * ($v - $zMean); $den += ($t - $tMean) ** 2; }
        $b = $den > 0 ? $num / $den : 0.0;
        $a = $zMean - $b * $tMean;

        // simpangan baku residual -> pita ketidakpastian kira-kira 80%
        $ss = 0.0;
        foreach ($y as $t => $v) $ss += ($v - ($a + $b * $t) * $factor[$dow($t)]) ** 2;
        $band = 1.28 * sqrt($ss / max(1, $n - 2));

        $out = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $d = $last->copy()->addDays($h);
            $yhat = max(0.0, ($a + $b * ($n - 1 + $h)) * $factor[$d->dayOfWeek]);
            $out[] = [
                'date' => $d->format('Y-m-d'),
                'revenue' => round($yhat, 2),
                'lower' => round(max(0.0, $yhat - $band), 2),
                'upper' => round($yhat + $band, 2),
            ];
        }
        return ['forecast' => $out, 'trend_per_day' => round($b, 2), 'band' => round($band, 2)];
    }
}
