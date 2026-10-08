import re
import sys

path = sys.argv[1] if len(sys.argv) > 1 else r"database/u700125577_keuangan (1).sql"
kas = {784: "Kas", 785: "Bank BNU", 786: "Mandiri", 787: "BNI", 788: "BRI"}
akun = {}
for line in open(path, encoding="utf-8", errors="replace"):
    m = re.search(
        r"\((\d+), '([^']+)', '([^']+)', '[^']+', '[^']+', '[^']+', [^,]+, [^,]+, '[^']+', 6\)",
        line,
    )
    if m:
        akun[int(m.group(1))] = f"{m.group(2)} - {m.group(3)}"

d = {i: 0.0 for i in kas}
k = {i: 0.0 for i in kas}
pm = pg = 0.0
rows = []
pat = re.compile(
    r"\((\d+), '(\d{4}-\d{2}-\d{2})', (\d+), (\d+), '((?:''|[^'])*)', '([^']+)', ([0-9.]+),"
)

for line in open(path, encoding="utf-8", errors="replace"):
    if not re.search(r",\s*\d+,\s*6,\s*'20", line):
        continue
    m = pat.match(line.strip().rstrip(","))
    if not m:
        continue
    tid, tgl, ad, ak, ket, jenis, jml = m.groups()
    ad, ak, tid = int(ad), int(ak), int(tid)
    jml = float(jml)
    ket = ket.replace("''", "'")
    rows.append((tid, tgl, ad, ak, jenis, jml, ket))
    if jenis == "pemasukan":
        pm += jml
    if jenis == "pengeluaran":
        pg += jml
    if ad in kas:
        d[ad] += jml
    if ak in kas:
        k[ak] += jml

print(f"Transaksi KAS BUMNU: {len(rows)}")
print(f"Dashboard (pemasukan - pengeluaran): {pm - pg:,.0f}")
print(f"  pemasukan {pm:,.0f}  pengeluaran {pg:,.0f}\n")

total = 0.0
for i, label in kas.items():
    s = d[i] - k[i]
    total += s
    print(f"{label:12} debit {d[i]:15,.0f}  kredit {k[i]:15,.0f}  saldo {s:15,.0f}")
print(f"\nTOTAL Kas & Bank (jurnal): {total:,.0f}\n")

# Account 866 = modal?
print("=== Akun kunci ===")
for aid in [850, 866, 880, 913, 912, 910, 867, 861]:
    if aid in akun:
        print(f"  {aid}: {akun[aid]}")

modal_out = sum(j for _, _, ad, ak, jenis, j, _ in rows if ak == 785 and ad == 866 and jenis == "pengeluaran")
basil_in = sum(j for _, _, ad, ak, jenis, j, _ in rows if ad == 785 and ak == 850 and jenis == "pemasukan")
angsuran = sum(j for _, _, ad, ak, jenis, j, _ in rows if ak == 785 and ad == 913)
sewa_mobil = sum(j for _, _, ad, ak, jenis, j, _ in rows if ad == 785 and ak == 912 and jenis == "pemasukan")
bonus = sum(j for _, _, ad, ak, jenis, j, _ in rows if ad == 785 and ak == 910 and jenis == "pemasukan")
peng_out_bank = sum(j for _, _, ad, ak, jenis, j, _ in rows if ak == 785 and jenis == "pengeluaran")
pem_in_bank = sum(j for _, _, ad, ak, jenis, j, _ in rows if ad == 785 and jenis == "pemasukan")

print("\n=== Ringkasan Bank BNU (785) ===")
print(f"Masuk (debit bank), jenis pemasukan: {pem_in_bank:,.0f}")
print(f"Keluar (kredit bank), jenis pengeluaran: {peng_out_bank:,.0f}")
print(f"  └ Basil MBG (kredit akun 850): {basil_in:,.0f}")
print(f"  └ Sewa mobil (kredit 912): {sewa_mobil:,.0f}")
print(f"  └ Bonus tabungan (kredit 910): {bonus:,.0f}")
print(f"Keluar pengembalian modal (866→785): {modal_out:,.0f}")
print(f"Keluar angsuran/beban bunga (913→785): {angsuran:,.0f}")

print("\n=== Transaksi pengembalian modal (kredit Bank BNU) ===")
for tid, tgl, ad, ak, jenis, jml, ket in sorted(rows, key=lambda x: -x[5]):
    if ak == 785 and ad == 866:
        print(f"  #{tid} {tgl} {jml:,.0f} [{jenis}] {ket[:70]}")

print("\n=== Tanpa saldo awal: semua transaksi mulai Des 2025 ===")
print("Tidak ada jurnal 'saldo awal' / RK opening balance ke Bank BNU.")
print("Kolom akun.saldo Bank BNU di dump = 552.084.689 (tidak dipakai sistem jurnal).")
