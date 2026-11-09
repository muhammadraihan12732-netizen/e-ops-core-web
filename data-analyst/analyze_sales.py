"""Analisis cleaned_sales.csv: ringkasan, produk/kategori terbaik, tren bulanan, prediksi 14 hari + backtest.
Menghasilkan analysis_summary.md dan charts/*.png. Jalankan: python analyze_sales.py
Algoritma prediksi identik dengan backend/app/Services/SalesForecaster.php.
"""
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd

HERE = Path(__file__).parent
CHARTS = HERE / "charts"


def forecast(y: pd.Series, horizon=14):
    """Tren linier + faktor hari-dalam-minggu. y: pendapatan harian berindeks tanggal berurutan."""
    n = len(y)
    if n < 14:
        raise ValueError("minimal 14 hari data")
    dow = y.index.dayofweek.to_numpy()
    mean = y.mean()
    factor = np.ones(7)
    if mean > 0:
        for w in range(7):
            vals = y.to_numpy()[dow == w]
            if len(vals):
                factor[w] = max(0.05, vals.mean() / mean)
    z = y.to_numpy() / factor[dow]
    t = np.arange(n)
    b, a = np.polyfit(t, z, 1)
    resid = y.to_numpy() - (a + b * t) * factor[dow]
    band = 1.28 * np.sqrt((resid ** 2).sum() / max(1, n - 2))
    idx = pd.date_range(y.index[-1] + pd.Timedelta(days=1), periods=horizon)
    yhat = np.maximum(0, (a + b * (n - 1 + np.arange(1, horizon + 1))) * factor[idx.dayofweek])
    return pd.DataFrame({"yhat": yhat, "lower": np.maximum(0, yhat - band), "upper": yhat + band}, index=idx), b


def backtest(y: pd.Series, holdout=28):
    """Latih pada data sebelum 28 hari terakhir, uji pada 28 hari itu; bandingkan dengan baseline rata-rata."""
    train, test = y.iloc[:-holdout], y.iloc[-holdout:]
    pred, _ = forecast(train, holdout)
    base = np.full(holdout, train.iloc[-28:].mean())
    mae = lambda p: float(np.abs(test.to_numpy() - p).mean())
    # MAPE dihitung pada total mingguan (harian terlalu bergejolak untuk data transaksi kecil)
    wk = lambda s: pd.Series(np.asarray(s), index=test.index).resample("W").sum()
    wape = lambda p: float(np.abs(wk(test) - wk(p)).sum() / wk(test).sum() * 100)
    return {"mae_model": mae(pred["yhat"].to_numpy()), "mae_baseline": mae(base),
            "wape_week_model": wape(pred["yhat"].to_numpy()), "wape_week_baseline": wape(base)}


def rp(x):
    return "Rp " + f"{x:,.0f}".replace(",", ".")


def main():
    df = pd.read_csv(HERE / "cleaned_sales.csv", parse_dates=["date"])
    CHARTS.mkdir(exist_ok=True)
    daily = df.groupby("date")["total"].sum().asfreq("D", fill_value=0)

    top = df.groupby("product").agg(qty=("qty", "sum"), omzet=("total", "sum")).sort_values("qty", ascending=False)
    cat = df.groupby("category")["total"].sum().sort_values(ascending=False)
    month = df.groupby(df["date"].dt.to_period("M")).agg(omzet=("total", "sum"), transaksi=("invoice", "nunique"))
    dow = daily.groupby(daily.index.dayofweek).mean()
    names = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"]

    f, slope = forecast(daily.iloc[-84:], 14)
    bt = backtest(daily.iloc[-112:] if len(daily) >= 112 else daily)

    # ---- grafik ----
    plt.rcParams.update({"font.size": 10, "axes.spines.top": False, "axes.spines.right": False})
    fig, ax = plt.subplots(figsize=(9, 4))
    ax.plot(daily.index[-60:], daily.iloc[-60:], color="#1e90ff", lw=1.2, label="Aktual")
    ax.plot(f.index, f["yhat"], color="#e0457b", lw=1.8, label="Prediksi")
    ax.fill_between(f.index, f["lower"], f["upper"], color="#e0457b", alpha=.15, label="Rentang ~80%")
    ax.set_title("Pendapatan harian: 60 hari terakhir + prediksi 14 hari"); ax.legend(frameon=False)
    ax.yaxis.set_major_formatter(lambda v, _: f"{v/1e6:.1f} jt"); fig.tight_layout(); fig.savefig(CHARTS / "forecast.png", dpi=130); plt.close(fig)

    fig, ax = plt.subplots(figsize=(9, 4)); ax.bar(month.index.astype(str), month["omzet"] / 1e6, color="#22b07d")
    ax.set_title("Omzet bulanan (juta Rp)"); fig.tight_layout(); fig.savefig(CHARTS / "monthly_revenue.png", dpi=130); plt.close(fig)

    fig, ax = plt.subplots(figsize=(9, 4)); t10 = top.head(10).iloc[::-1]
    ax.barh(t10.index, t10["qty"], color="#e0a82e"); ax.set_title("10 produk terlaris (unit terjual)")
    fig.tight_layout(); fig.savefig(CHARTS / "top_products.png", dpi=130); plt.close(fig)

    fig, ax = plt.subplots(figsize=(9, 4)); ax.bar(names, dow.reindex(range(7)).to_numpy() / 1e3, color="#1fc8c9")
    ax.set_title("Rata-rata omzet per hari dalam seminggu (ribu Rp)"); fig.tight_layout(); fig.savefig(CHARTS / "weekday.png", dpi=130); plt.close(fig)

    # ---- ringkasan ----
    gain = (bt["mae_baseline"] - bt["mae_model"]) / bt["mae_baseline"] * 100   # % perbaikan MAE vs baseline
    if gain >= 5:
        verdict = f"model **lebih baik** daripada baseline (MAE {gain:.0f}% lebih rendah)."
    elif gain > -5:
        verdict = f"model **hampir setara** baseline (selisih MAE {gain:+.1f}%), jadi belum terbukti menambah akurasi."
    else:
        verdict = f"model **lebih buruk** daripada baseline (MAE {-gain:.0f}% lebih tinggi)."
    month.index.name = "bulan"
    md = f"""# Ringkasan Analisis Penjualan

Sumber: `cleaned_sales.csv` ({len(df)} baris, {df['date'].min():%d %b %Y} s/d {df['date'].max():%d %b %Y}). **Data simulasi** hasil `generate_raw_sales.py`, bukan data bisnis nyata.

## Angka utama
- Total omzet: **{rp(df['total'].sum())}** dari {df['invoice'].nunique()} transaksi
- Rata-rata nilai transaksi: **{rp(df.groupby('invoice')['total'].sum().mean())}**
- Produk terlaris (unit): **{top.index[0]}** ({int(top['qty'].iloc[0])} unit); paling sedikit: **{top.index[-1]}** ({int(top['qty'].iloc[-1])} unit)
- Kategori dengan omzet terbesar: **{cat.index[0]}** ({cat.iloc[0] / cat.sum() * 100:.0f}% dari total)
- Hari paling ramai: **{names[int(dow.idxmax())]}**; paling sepi: **{names[int(dow.idxmin())]}**

## Omzet bulanan
{month.assign(omzet=month['omzet'].map(rp)).to_markdown() if hasattr(month, 'to_markdown') and _has_tabulate() else month.to_string()}

## Prediksi 14 hari ke depan
- Tren harian (setelah menghapus pola hari-dalam-minggu): **{slope:+,.0f} Rp/hari**
- Total prediksi 7 hari pertama: **{rp(f['yhat'].iloc[:7].sum())}**
- Rentang ketidakpastian harian: kira-kira ±{rp((f['upper'] - f['yhat']).iloc[0])} (~80%)

### Backtest (28 hari terakhir disembunyikan, model dilatih sebelumnya)
| Metrik | Model | Baseline (rata-rata 28 hari) |
|---|---|---|
| MAE harian | {rp(bt['mae_model'])} | {rp(bt['mae_baseline'])} |
| Galat total mingguan (WAPE) | {bt['wape_week_model']:.1f}% | {bt['wape_week_baseline']:.1f}% |

Kesimpulan backtest: {verdict} Galat total mingguan masih tinggi karena data simulasi punya noise acak besar per transaksi. Jadi prediksi ini hanya layak dipakai sebagai gambaran arah dan pola mingguan, bukan angka target. Dengan data bisnis nyata yang lebih stabil, akurasinya perlu diuji ulang dengan backtest yang sama.

Grafik: `charts/forecast.png`, `charts/monthly_revenue.png`, `charts/top_products.png`, `charts/weekday.png`.
"""
    (HERE / "analysis_summary.md").write_text(md, encoding="utf-8")
    print(md)


def _has_tabulate():
    try:
        import tabulate  # noqa: F401
        return True
    except ImportError:
        return False


if __name__ == "__main__":
    main()
