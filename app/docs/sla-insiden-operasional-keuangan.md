# SLA Insiden Operasional Keuangan

Tanggal: 2026-02-28

## Tujuan
Menetapkan target waktu respons dan penyelesaian untuk insiden operasional keuangan.

## Klasifikasi Insiden

- Sev-1 (Kritis): operasional utama berhenti / risiko finansial besar.
- Sev-2 (Tinggi): operasional jalan terbatas / risiko menengah.
- Sev-3 (Sedang): gangguan minor, workaround tersedia.

## Matriks SLA

| Jenis Insiden | Severity | Respons Awal | Mitigasi Sementara | Target Selesai |
|---|---|---|---|---|
| Kas minus terdeteksi | Sev-1 | 15 menit | 30 menit | 4 jam |
| Selisih kas shift material | Sev-2 | 30 menit | 4 jam | H+1 |
| Selisih stok material | Sev-2 | 30 menit | 4 jam | H+2 |
| Hutang jatuh tempo kritikal terlewat | Sev-2 | 30 menit | 4 jam | H+1 |
| Error data transaksi non-kritis | Sev-3 | 4 jam | H+1 | H+3 |

## PIC Utama

- Kasir: identifikasi awal selisih kas/operasional shift.
- Admin: validasi data, investigasi transaksi, dokumentasi insiden.
- Owner: keputusan finansial dan approval tindakan korektif.

## Alur Penanganan

1. Catat insiden (waktu, lokasi, jenis, nilai dampak).
2. Klasifikasi severity.
3. Eksekusi mitigasi sementara.
4. Lakukan root cause check.
5. Tutup insiden dengan catatan tindakan pencegahan.

## Exit Criteria Insiden

- Penyebab utama teridentifikasi.
- Data transaksi sudah dikoreksi sesuai audit trail.
- PIC menandatangani penutupan insiden.
- Preventive action disepakati untuk mencegah berulang.
