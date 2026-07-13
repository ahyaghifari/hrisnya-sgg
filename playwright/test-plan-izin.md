# Test Plan — Pengajuan & Persetujuan Izin (Playwright MCP)

Pelengkap dari `test-plan-cuti-lembur.md`. Cuti dan lembur sudah dieksekusi dan lolos (lihat riwayat di bawah). Izin belum pernah dites — plan ini menutup gap itu.

## Data akun (sama seperti test cuti/lembur)

| Peran | Email | Password | Jabatan |
|---|---|---|---|
| Pemohon | ahyaghifari288@gmail.com | password | IT Programmer Staff |
| Atasan 1 | itcoordinator@syifaglobalgroup.com | password | IT Supervisor |
| Atasan 2 | hcga.manager@syifaglobalgroup.com | password | PJ HCGA Manager |

Alur approval sama: IT Supervisor (step 1) → PJ HCGA Manager (step 2, final).

## Form Izin — field konkret

Route: `/pengajuan-absensi/izin` (Livewire `SubmitPermissionRequest`)

Jenis izin tersedia (company Syifa Global Group):
- Izin Terlambat
- Izin Pulang Lebih Awal
- Izin Tidak Masuk
- Izin Meninggalkan Jam Kerja

Field form:
- `Jenis Izin *` — select
- `Tanggal Izin *` — date input
- `Jam Izin *` — `Jam Mulai` + `Jam Berakhir` (time input)
- `Keperluan Izin` — textarea (opsional)
- `Lokasi Izin` — text (opsional)
- Toggle "Pengganti Tugas" (opsional) → jika aktif: `Nama Pengganti`, `Tugas yang Diberikan`
- Submit: tombol "Ajukan Izin"

## Skenario

### 1. Pengajuan Izin
1. Login sebagai ahyaghifari288@gmail.com.
2. Buka `/pengajuan-absensi/izin`.
3. Isi: Jenis Izin = "Izin Terlambat", Tanggal = 14 Juli 2026, Jam Mulai = 08:00, Jam Berakhir = 09:00, Keperluan = "Tes Playwright MCP - izin terlambat".
4. (Skip toggle pengganti tugas — opsional, tidak perlu untuk skenario dasar)
5. Klik "Ajukan Izin" → assert redirect ke list `/pengajuan-absensi`, entri baru muncul dengan status "Sedang Direview".

### 2. Approval Izin — Atasan 1 (IT Supervisor)
1. Logout (via `/akun` → tombol Keluar; fallback: navigasi manual ke `/login` jika logout timeout, lalu verifikasi sesi benar-benar keluar).
2. Login sebagai itcoordinator@syifaglobalgroup.com.
3. Buka detail request izin yang baru diajukan (`/pengajuan-absensi/{record}` — ambil ID dari redirect step 1).
4. Klik "Setujui" → isi Catatan (wajib) → Konfirmasi.
5. Assert: step "IT Supervisor" → nama approver terisi + "Disetujui"; step "PJ HCGA Manager" → "Sedang diproses". Status pengajuan keseluruhan masih "Sedang Direview".

### 3. Approval Izin — Atasan 2 (PJ HCGA Manager, final)
1. Logout, login sebagai hcga.manager@syifaglobalgroup.com.
2. Buka detail request yang sama.
3. Klik "Setujui" → isi Catatan → Konfirmasi.
4. Assert status akhir = **Disetujui**, riwayat approval menampilkan 3 entri: Diajukan → Disetujui (IT Supervisor) → Disetujui (PJ HCGA Manager).

### 4. (Opsional) Skenario reject
Ulangi pengajuan izin baru, salah satu atasan klik "Tolak" + catatan wajib → assert status `Ditolak`, alur berhenti (approver berikutnya tidak diminta bertindak).

### 5. (Opsional) Skenario dengan Pengganti Tugas
Ulangi pengajuan izin baru dengan toggle "Pengganti Tugas" aktif, isi Nama Pengganti + Tugas yang Diberikan → assert data pengganti tampil di halaman detail.

## Catatan teknis (dari temuan sesi test cuti/lembur sebelumnya)

- **Login/logout**: klik tombol "Masuk"/"Keluar" sering melempar `TimeoutError` dari Playwright meski submit sebenarnya berhasil (form POST redirect lambat direspons events). Pola aman: setelah klik, langsung `browser_navigate` ke URL tujuan (`/dashboard` atau `/login`) untuk konfirmasi status sesi, jangan andalkan snapshot dari klik yang timeout.
- **Tombol "Setujui"**: pada percobaan pertama di sesi baru kadang tidak langsung memunculkan textarea "Catatan *" — klik kedua baru memunculkannya. Selalu snapshot ulang setelah klik, dan klik lagi jika textarea catatan belum muncul.
- **Bug console error Filament sudah diperbaiki** ([layout.blade.php](../resources/views/components/layout.blade.php)) — tidak perlu diverifikasi ulang di plan ini, tapi kalau muncul lagi console error `filamentTable is not defined` dkk. saat navigasi, itu regresi dari fix sebelumnya dan perlu dilaporkan.
- Gunakan `http://localhost:8000` (bukan port 80).
- Jangan pernah arahkan ke domain produksi — ini data organisasi asli yang jalan di `APP_ENV=local`.

## Status eksekusi
- [x] Skenario 1: Pengajuan Izin — record `01kxczz6cbg35j3p8qcknm1x2b` (Izin Terlambat, 14 Juli 2026)
- [x] Skenario 2: Approval Atasan 1 — Lutfi Salisa Setiawati (IT Supervisor), 13 Jul 2026 12:44
- [x] Skenario 3: Approval Atasan 2 (final) — Muhammad Sandy Alfianoor (PJ HCGA Manager) → status akhir **Disetujui**
- [ ] Skenario 4: Reject (opsional) — belum dijalankan
- [ ] Skenario 5: Dengan Pengganti Tugas (opsional) — belum dijalankan

Console bersih (0 error terkait Filament/Alpine) selama eksekusi — mengonfirmasi fix di `layout.blade.php` tidak regresi.
