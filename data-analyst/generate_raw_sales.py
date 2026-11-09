"""Membuat raw_sales.csv: data penjualan SIMULASI yang sengaja dibuat kotor,
supaya langkah cleaning punya bahan nyata. Seed tetap -> hasil selalu sama.
Jalankan: python generate_raw_sales.py
"""
import csv
import random
from datetime import date, timedelta
from pathlib import Path

random.seed(2026)
OUT = Path(__file__).parent / "raw_sales.csv"

PRODUCTS = {  # nama baku: (kategori, harga)
    "Bread Knife": ("Kitchen Appliances", 85000), "Black Marker": ("Office Appliances", 12000),
    "Black Pen": ("Office Appliances", 5000), "Cake Moulds": ("Kitchen Appliances", 45000),
    "Cookies Moulds": ("Kitchen Appliances", 38000), "Cutter": ("Office Appliances", 15000),
    "Duster": ("Electronics", 22000), "Drawing Book": ("Office Appliances", 28000),
    "Eraser": ("Office Appliances", 3000), "Fruit Bowl": ("Kitchen Appliances", 55000),
    "Stapler": ("Office Appliances", 32000), "Desk Lamp": ("Electronics", 120000),
}
POPULARITY = [6, 9, 12, 5, 3, 4, 2, 5, 11, 3, 7, 2]
KASIR = ["Kasir", "Sari", "Budi"]
PAY = ["cash", "qris", "debit"]
names = list(PRODUCTS)
start = date(2026, 1, 1)
rows = []

for i in range(1200):
    d = start + timedelta(days=random.randint(0, 300))
    if d.weekday() >= 5:                       # akhir pekan lebih ramai
        pass
    elif random.random() < 0.25:
        d = d + timedelta(days=(5 - d.weekday()))
    name = random.choices(names, POPULARITY)[0]
    cat, price = PRODUCTS[name]
    qty = random.choices([1, 2, 3, 4, 5, 8], [40, 25, 15, 10, 7, 3])[0]
    total = price * qty
    r = {"invoice": f"INV-{d:%y%m%d}-{i:04d}", "date": d.isoformat(), "kasir": random.choice(KASIR),
         "product": name, "category": cat, "qty": qty, "price": price, "total": total,
         "payment_method": random.choice(PAY)}
    rows.append(r)

# ---- sisipkan masalah data (jumlahnya dicatat di cleaning_report) ----
for r in random.sample(rows, 90):              # format tanggal campur
    y, m, dd = r["date"].split("-")
    r["date"] = random.choice([f"{dd}/{m}/{y}", f"{dd}-{m}-{y}", f"{int(dd)}/{int(m)}/{y}"])
for r in random.sample(rows, 120):             # spasi & huruf besar-kecil tidak konsisten
    r["product"] = random.choice([f"  {r['product']} ", r["product"].upper(), r["product"].lower()])
for r in random.sample(rows, 25):              # salah ketik
    r["product"] = r["product"].replace("Pen", "Pne").replace("Knife", "Knfe").replace("Lamp", "Lmap")
for r in random.sample(rows, 60):              # harga berformat teks
    r["price"] = f"Rp {r['price']:,}".replace(",", ".")
for r in random.sample(rows, 30):              # qty kosong
    r["qty"] = ""
for r in random.sample(rows, 12):              # qty negatif (retur salah input)
    r["qty"] = -abs(int(r["qty"] or 1))
for r in random.sample(rows, 6):               # outlier ekstrem
    r["qty"] = random.choice([250, 480, 999])
for r in random.sample(rows, 20):              # total tidak cocok dengan qty*price
    r["total"] = int(r["total"]) + random.choice([-5000, 1000, 20000])
for r in random.sample(rows, 15):              # kategori kosong
    r["category"] = ""
for r in random.sample(rows, 8):               # tanggal rusak
    r["date"] = random.choice(["", "31/02/2026", "tidak ada"])
rows += [dict(r) for r in random.sample(rows, 40)]   # duplikat persis
random.shuffle(rows)

with OUT.open("w", newline="", encoding="utf-8") as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0]))
    w.writeheader()
    w.writerows(rows)
print(f"{len(rows)} baris -> {OUT}")
