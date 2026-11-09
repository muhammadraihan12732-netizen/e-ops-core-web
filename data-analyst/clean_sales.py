"""Membersihkan raw_sales.csv -> cleaned_sales.csv + cleaning_report.md
Setiap aturan dihitung dan dilaporkan, tidak ada baris yang hilang diam-diam.
Jalankan: python clean_sales.py [raw.csv] [cleaned.csv]
"""
import re
import sys
from pathlib import Path

import pandas as pd

HERE = Path(__file__).parent
CANON = ["Bread Knife", "Black Marker", "Black Pen", "Cake Moulds", "Cookies Moulds", "Cutter",
         "Duster", "Drawing Book", "Eraser", "Fruit Bowl", "Stapler", "Desk Lamp"]
MAX_QTY = 50  # batas wajar qty per baris untuk toko ritel kecil; di atasnya dianggap salah input


def parse_price(v):
    """'Rp 25.000' -> 25000.0 ; angka biasa tetap ; selain itu NaN."""
    if pd.isna(v):
        return float("nan")
    s = re.sub(r"[^\d,.-]", "", str(v))
    if re.fullmatch(r"-?\d{1,3}(\.\d{3})+", s):      # titik sebagai pemisah ribuan
        s = s.replace(".", "")
    s = s.replace(",", ".")
    try:
        return float(s)
    except ValueError:
        return float("nan")


def parse_date(v):
    """Mendukung YYYY-MM-DD dan DD/MM/YYYY, DD-MM-YYYY (hari di depan). Tanggal mustahil -> NaT."""
    s = str(v).strip()
    if re.fullmatch(r"\d{4}-\d{2}-\d{2}", s):
        return pd.to_datetime(s, format="%Y-%m-%d", errors="coerce")
    if re.fullmatch(r"\d{1,2}[/-]\d{1,2}[/-]\d{4}", s):
        return pd.to_datetime(s.replace("-", "/"), format="%d/%m/%Y", errors="coerce")
    return pd.NaT


def canonical_product(name):
    """Normalisasi huruf/spasi lalu perbaiki salah ketik ringan (selisih <= 2 karakter, pilihan unik)."""
    from difflib import get_close_matches
    s = re.sub(r"\s+", " ", str(name)).strip().title()
    if s in CANON:
        return s
    m = get_close_matches(s, CANON, n=2, cutoff=0.8)
    return m[0] if len(m) >= 1 and (len(m) == 1 or m[0] != m[1]) else None


def clean(raw: pd.DataFrame):
    log = {"baris_awal": len(raw)}
    df = raw.copy()
    df.columns = [c.strip().lower() for c in df.columns]

    df = df.drop_duplicates()
    log["duplikat_dihapus"] = log["baris_awal"] - len(df)

    df["date"] = df["date"].map(parse_date)
    bad_date = df["date"].isna()
    log["tanggal_tidak_valid_dibuang"] = int(bad_date.sum())
    df = df[~bad_date]

    before = df["product"].astype(str).str.strip()
    df["product"] = df["product"].map(canonical_product)
    log["nama_produk_dinormalisasi"] = int((before != df["product"]).sum())
    log["produk_tak_dikenal_dibuang"] = int(df["product"].isna().sum())
    df = df[df["product"].notna()]

    df["price"] = df["price"].map(parse_price)
    df["qty"] = pd.to_numeric(df["qty"], errors="coerce")
    df["total"] = pd.to_numeric(df["total"], errors="coerce")

    # kategori kosong -> ambil dari produk lain yang sama
    cat_map = df[df["category"].astype(str).str.strip().ne("") & df["category"].notna()].groupby("product")["category"].agg(lambda s: s.mode().iat[0])
    empty_cat = df["category"].isna() | df["category"].astype(str).str.strip().eq("")
    df.loc[empty_cat, "category"] = df.loc[empty_cat, "product"].map(cat_map)
    log["kategori_diisi_otomatis"] = int(empty_cat.sum())

    # qty kosong: dibuang (bukan ditebak), karena mengubah omzet
    log["qty_kosong_dibuang"] = int(df["qty"].isna().sum())
    df = df[df["qty"].notna()]
    log["qty_negatif_dibuang"] = int((df["qty"] <= 0).sum())
    df = df[df["qty"] > 0]
    log["qty_outlier_dibuang"] = int((df["qty"] > MAX_QTY).sum())
    df = df[df["qty"] <= MAX_QTY]

    # harga resmi per produk = modus; total dihitung ulang dari qty x harga
    price_ref = df.groupby("product")["price"].agg(lambda s: s.mode().iat[0])
    df["price"] = df["price"].fillna(df["product"].map(price_ref))
    recomputed = df["qty"] * df["price"]
    log["total_dikoreksi"] = int((df["total"] != recomputed).sum())
    df["total"] = recomputed

    df["qty"] = df["qty"].astype(int)
    df["kasir"] = df["kasir"].astype(str).str.strip().str.title()
    df["payment_method"] = df["payment_method"].astype(str).str.strip().str.lower()
    df = df.sort_values(["date", "invoice"]).reset_index(drop=True)
    df["date"] = df["date"].dt.strftime("%Y-%m-%d")
    out_cols = ["invoice", "date", "kasir", "product", "category", "qty", "price", "total", "payment_method"]
    log["baris_akhir"] = len(df)
    return df[out_cols], log


def report(log):
    rows = "\n".join(f"| {k.replace('_', ' ')} | {v} |" for k, v in log.items())
    kept = log["baris_akhir"] / log["baris_awal"] * 100
    return f"# Laporan Pembersihan Data\n\n| Langkah | Jumlah baris |\n|---|---|\n{rows}\n\nBaris yang dipertahankan: **{kept:.1f}%** dari data mentah.\n"


if __name__ == "__main__":
    src = Path(sys.argv[1]) if len(sys.argv) > 1 else HERE / "raw_sales.csv"
    dst = Path(sys.argv[2]) if len(sys.argv) > 2 else HERE / "cleaned_sales.csv"
    cleaned, log = clean(pd.read_csv(src, dtype=str, keep_default_na=False, na_values=[""]))
    cleaned.to_csv(dst, index=False)
    (HERE / "cleaning_report.md").write_text(report(log), encoding="utf-8")
    print(report(log))
