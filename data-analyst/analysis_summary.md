# Ringkasan Analisis Penjualan

Sumber: `cleaned_sales.csv` (1145 baris, 01 Jan 2026 s/d 31 Oct 2026). **Data simulasi** hasil `generate_raw_sales.py`, bukan data bisnis nyata.

## Angka utama
- Total omzet: **Rp 77.311.000** dari 1145 transaksi
- Rata-rata nilai transaksi: **Rp 67.521**
- Produk terlaris (unit): **Eraser** (473 unit); paling sedikit: **Desk Lamp** (73 unit)
- Kategori dengan omzet terbesar: **Kitchen Appliances** (55% dari total)
- Hari paling ramai: **Sabtu**; paling sepi: **Jumat**

## Omzet bulanan
| bulan   | omzet        |   transaksi |
|:--------|:-------------|------------:|
| 2026-01 | Rp 8.112.000 |         121 |
| 2026-02 | Rp 9.333.000 |         116 |
| 2026-03 | Rp 8.050.000 |         115 |
| 2026-04 | Rp 7.253.000 |          99 |
| 2026-05 | Rp 8.474.000 |         126 |
| 2026-06 | Rp 6.282.000 |         117 |
| 2026-07 | Rp 8.220.000 |         115 |
| 2026-08 | Rp 8.210.000 |         122 |
| 2026-09 | Rp 7.149.000 |         115 |
| 2026-10 | Rp 6.228.000 |          99 |

## Prediksi 14 hari ke depan
- Tren harian (setelah menghapus pola hari-dalam-minggu): **-789 Rp/hari**
- Total prediksi 7 hari pertama: **Rp 1.333.595**
- Rentang ketidakpastian harian: kira-kira ±Rp 273.278 (~80%)

### Backtest (28 hari terakhir disembunyikan, model dilatih sebelumnya)
| Metrik | Model | Baseline (rata-rata 28 hari) |
|---|---|---|
| MAE harian | Rp 183.992 | Rp 186.423 |
| Galat total mingguan (WAPE) | 64.2% | 71.4% |

Kesimpulan backtest: model **hampir setara** baseline (selisih MAE +1.3%), jadi belum terbukti menambah akurasi. Galat total mingguan masih tinggi karena data simulasi punya noise acak besar per transaksi. Jadi prediksi ini hanya layak dipakai sebagai gambaran arah dan pola mingguan, bukan angka target. Dengan data bisnis nyata yang lebih stabil, akurasinya perlu diuji ulang dengan backtest yang sama.

Grafik: `charts/forecast.png`, `charts/monthly_revenue.png`, `charts/top_products.png`, `charts/weekday.png`.
