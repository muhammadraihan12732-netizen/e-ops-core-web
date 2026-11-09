"""Unit test pipeline data. Jalankan: python -m unittest -v test_pipeline"""
import unittest

import numpy as np
import pandas as pd

from analyze_sales import backtest, forecast
from clean_sales import canonical_product, clean, parse_date, parse_price


class ParseTests(unittest.TestCase):
    def test_price_formats(self):
        self.assertEqual(parse_price("Rp 25.000"), 25000)
        self.assertEqual(parse_price("Rp 1.250.000"), 1250000)
        self.assertEqual(parse_price(5000), 5000)
        self.assertEqual(parse_price("12.5"), 12.5)          # desimal, bukan ribuan
        self.assertTrue(np.isnan(parse_price("abc")))
        self.assertTrue(np.isnan(parse_price(None)))

    def test_date_formats_day_first(self):
        self.assertEqual(parse_date("2026-03-05"), pd.Timestamp("2026-03-05"))
        self.assertEqual(parse_date("05/03/2026"), pd.Timestamp("2026-03-05"))   # 5 Maret, bukan 3 Mei
        self.assertEqual(parse_date("5-3-2026"), pd.Timestamp("2026-03-05"))
        self.assertTrue(pd.isna(parse_date("31/02/2026")))
        self.assertTrue(pd.isna(parse_date("")))
        self.assertTrue(pd.isna(parse_date("tidak ada")))

    def test_product_names(self):
        self.assertEqual(canonical_product("  black PEN "), "Black Pen")
        self.assertEqual(canonical_product("Black Pne"), "Black Pen")
        self.assertEqual(canonical_product("Desk Lmap"), "Desk Lamp")
        self.assertIsNone(canonical_product("Laptop Gaming"))


class CleanTests(unittest.TestCase):
    def raw(self, **over):
        row = dict(invoice="A1", date="2026-01-05", kasir=" sari ", product="Eraser", category="Office Appliances",
                   qty="2", price="3000", total="6000", payment_method=" QRIS ")
        row.update(over)
        return row

    def run_clean(self, *rows):
        df, log = clean(pd.DataFrame(rows))
        return df, log

    def test_good_row_passes_unchanged_and_text_is_normalized(self):
        df, _ = self.run_clean(self.raw())
        self.assertEqual(len(df), 1)
        r = df.iloc[0]
        self.assertEqual((r.kasir, r.payment_method, r.total), ("Sari", "qris", 6000))

    def test_exact_duplicates_removed(self):
        df, log = self.run_clean(self.raw(), self.raw())
        self.assertEqual((len(df), log["duplikat_dihapus"]), (1, 1))

    def test_bad_rows_are_dropped_not_guessed(self):
        rows = [self.raw(invoice="B1", qty=""), self.raw(invoice="B2", qty="-3"), self.raw(invoice="B3", qty="999"),
                self.raw(invoice="B4", date="31/02/2026"), self.raw(invoice="B5", product="Laptop")]
        df, log = self.run_clean(*rows)
        self.assertEqual(len(df), 0)
        self.assertEqual(log["baris_awal"] - log["baris_akhir"], 5)

    def test_total_recomputed_from_qty_times_price(self):
        df, log = self.run_clean(self.raw(total="99999"))
        self.assertEqual(df.iloc[0].total, 6000)
        self.assertEqual(log["total_dikoreksi"], 1)

    def test_missing_category_filled_from_same_product(self):
        df, _ = self.run_clean(self.raw(invoice="C1"), self.raw(invoice="C2", category=""))
        self.assertEqual(set(df["category"]), {"Office Appliances"})

    def test_idempotent(self):
        df1, _ = self.run_clean(self.raw(invoice="D1"), self.raw(invoice="D2", date="06/01/2026", price="Rp 3.000"))
        df2, _ = clean(df1.astype(str))
        pd.testing.assert_frame_equal(df1.reset_index(drop=True), df2.reset_index(drop=True), check_dtype=False)


class ForecastTests(unittest.TestCase):
    def series(self, f, n=60):
        return pd.Series([f(i) for i in range(n)], index=pd.date_range("2026-01-01", periods=n), dtype=float)

    def test_too_short_raises(self):
        with self.assertRaises(ValueError):
            forecast(self.series(lambda i: 100, 13))

    def test_flat_series_stays_flat(self):
        pred, slope = forecast(self.series(lambda i: 100))
        self.assertAlmostEqual(slope, 0, places=6)
        self.assertTrue(np.allclose(pred["yhat"], 100, atol=0.5))

    def test_rising_trend_continues(self):
        s = self.series(lambda i: 100 + 5 * i)
        pred, slope = forecast(s)
        self.assertGreater(slope, 4.9)
        self.assertGreater(pred["yhat"].iloc[-1], s.iloc[-1])

    def test_all_zero_and_never_negative(self):
        pred, _ = forecast(self.series(lambda i: 0))
        self.assertTrue((pred["yhat"] == 0).all())
        pred, _ = forecast(self.series(lambda i: max(0, 50 - 3 * i)))
        self.assertTrue((pred[["yhat", "lower"]] >= 0).all().all())

    def test_weekly_pattern_is_reproduced(self):
        s = self.series(lambda i: 200 if pd.Timestamp("2026-01-01").dayofweek + i in () else (300 if (i + 3) % 7 in (5, 6) else 100), 70)
        pred, _ = forecast(s)
        wk = pred[pred.index.dayofweek >= 5]["yhat"].mean(); wd = pred[pred.index.dayofweek < 5]["yhat"].mean()
        self.assertGreater(wk, 2 * wd)

    def test_backtest_returns_finite_metrics(self):
        s = self.series(lambda i: 100 + (i % 7) * 10, 90)
        bt = backtest(s)
        self.assertTrue(all(np.isfinite(v) for v in bt.values()))


if __name__ == "__main__":
    unittest.main()
