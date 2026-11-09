<?php
namespace Tests\Feature;

use App\Services\SalesForecaster;
use PHPUnit\Framework\TestCase; // murni logika, tanpa database

class SalesForecasterTest extends TestCase {
    public function test_returns_null_when_less_than_14_days(): void {
        $this->assertNull(SalesForecaster::forecast(array_fill(0, 13, 100.0), '2026-01-31'));
    }

    public function test_flat_series_forecasts_flat_and_never_negative(): void {
        $f = SalesForecaster::forecast(array_fill(0, 42, 100.0), '2026-01-31', 7);
        $this->assertCount(7, $f['forecast']);
        foreach ($f['forecast'] as $row) {
            $this->assertEqualsWithDelta(100.0, $row['revenue'], 0.5);
            $this->assertGreaterThanOrEqual(0, $row['lower']);
        }
    }

    public function test_rising_trend_gives_positive_slope(): void {
        $y = array_map(fn ($i) => 100.0 + $i * 5, range(0, 41));
        $f = SalesForecaster::forecast($y, '2026-01-31', 7);
        $this->assertGreaterThan(0, $f['trend_per_day']);
        $this->assertGreaterThan(end($y), $f['forecast'][6]['revenue']);
    }

    public function test_all_zero_series_does_not_divide_by_zero(): void {
        $f = SalesForecaster::forecast(array_fill(0, 30, 0.0), '2026-01-31', 7);
        $this->assertSame(0.0, $f['forecast'][0]['revenue']);
    }
}
