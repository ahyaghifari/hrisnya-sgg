# Rencana: Rapikan Role & Hak Akses (per 2026-07-04, diperbarui 2026-07-05)

Status: **Pilar 1 & 3 sudah dieksekusi (beda cara dari draft awal, lihat catatan tiap pilar). Pilar 2 dibatalkan.**

## Latar Belakang

Konteks: [`knowledge/markdown/kesetaraan-jabatan-lintas-perusahaan.md`](../knowledge/markdown/kesetaraan-jabatan-lintas-perusahaan.md) — Role dan Job Position hari ini sama sekali tidak terhubung di database, permission ditulis tangan flat di 1 file, dan halaman Karyawan/User di admin panel belum ada pembatasan "cuma lihat bawahan sendiri". Tujuan: rapikan ketiganya supaya hak akses gampang diatur dan konsisten.

**Di luar cakupan rencana ini** (dibahas terpisah nanti): penyetaraan jabatan lintas company pakai Golongan/Kelas untuk keperluan payroll/tunjangan. `Kelas` khususnya nyaris tidak dipakai (6 dari 141 jabatan terisi, tidak ada relasi ke perhitungan gaji) — itu topik payroll, bukan topik role & hak akses.

## Tiga Pilar yang Dikerjakan

Tiga hal terpisah yang saling melengkapi, bukan satu fitur besar:

1. **Job Position → Role default** (saran otomatis saat assign user ke jabatan)
2. **Otorisasi resource CRUD** (boleh apa saja terhadap suatu Resource) — pakai Filament Shield
3. **Data scoping** (baris mana yang kelihatan) — custom, berbasis hierarki org

---

## Pilar 1 — Job Position → Role Default ✅ SELESAI

**Keputusan: Opsi B** (saran/pre-fill, bukan otomatis-terkunci). Alasan: aman, reversibel, tidak butuh beresin dulu ketidakkonsistenan permission antar company (beda Opsi A yang mengunci penuh).

### Skema (implementasi final — beda dari draft awal)
**Bukan** tabel terpisah `job_position_role_defaults` — cukup 1 kolom, karena relasinya 1:1 nullable (YAGNI, tabel terpisah gak perlu):
- `role_id` ditambahkan langsung ke `m_job_positions` (Role Default untuk jabatan itu).
- `role_id` ditambahkan langsung ke `user_transitions` (role yang dipilih HRD saat proses promosi/demosi).

Lihat detail lengkap: [`knowledge/changes/2026-07-04-pilar-1-job-position-role-default.md`](../knowledge/changes/2026-07-04-pilar-1-job-position-role-default.md).

### Perilaku
- Form assign-user-ke-jabatan baca tabel ini → pre-select role yang disarankan.
- HRD tetap bebas override manual (checkbox biasa, tidak dikunci).
- **Efek ke hak akses: nol.** Tabel ini cuma dibaca sekali saat form dibuka, tidak pernah dibaca saat cek permission (`$user->can()`/Shield/Policy tetap 100% baca dari `model_has_roles` + `role_has_permissions`, tidak berubah). Kalau tabel ini dihapus, tidak ada user yang kehilangan akses — lihat diagram di riwayat diskusi.

### Kapan upgrade ke "otomatis terkunci" (Opsi A)
Kalau nanti proses HR sudah stabil & yakin tidak ada kasus Plt./rangkap jabatan: tidak perlu ubah skema tabel, tinggal ubah perilaku kode (form jadi read-only + listener re-sync role otomatis tiap Job Position user berubah).

---

## Pilar 2 — Otorisasi Resource CRUD via Filament Shield

## Pilar 2 — Otorisasi Resource CRUD via Filament Shield ❌ DIBATALKAN

**Keputusan (2026-07-05): tidak jadi dipakai.** Alasan: yang bisa masuk `/admin` cuma **Admin company & Super Admin** — dua-duanya memang harus punya akses penuh by design. Shield gunanya generate permission granular PER ROLE per Resource; itu cuma berguna kalau ada role lain yang perlu dibatasi di dalam Filament. Karena role lain (Supervisor, Manager, HC, dst) diarahkan ke **portal depan** (bukan `/admin` sama sekali — lihat Pilar 3), bukan Filament, jadi granularitas ala Shield di admin panel tidak ada gunanya. `PermissionsSeeder.php` yang ditulis manual tetap dipakai apa adanya untuk hak akses di `/admin`.

---

## Pilar 3 — Data Scoping (cuma lihat/edit bawahan) ✅ SELESAI (sebagian, di portal depan)

**Dikonfirmasi tidak ada plugin siap pakai** (Shield tidak menangani row-level scoping, dan lagipula batal dipakai — lihat Pilar 2) — ini custom.

### Keputusan penting: scoping HANYA di portal depan, TIDAK di Filament
Sejalan dengan alasan pembatalan Pilar 2 — karena Filament (`/admin`) cuma diakses Admin/Super Admin yang memang harus lihat semua data, `EmployeeResource::getEloquentQuery()` di admin panel **sengaja tidak disentuh/tidak di-scope**. Scoping cuma diterapkan di halaman portal depan yang baru (`/karyawanku`), tempat Supervisor/Manager/dst benar-benar beraktivitas.

### Implementasi final (beda dari draft awal — bukan permission-variant, tapi structural check)
- **`OrgHierarchyService`** (`app/Services/OrgHierarchyService.php`) — hitung `job_position_id` bawahan rekursif dari `m_job_positions.parent_id`, dan query User yang menempatinya.
- **`User::hasSubordinates()`** — cek struktural (job position user itu punya `children()` atau tidak) → dipakai buat show/hide menu "Karyawan" di nav & guard halaman.
- **`User::canBrowseAllCompanies()`** — khusus role "Direktur" di company holding → boleh pilih company manapun & lihat semua karyawannya (dipakai karena Direktur holding ternyata `hasSubordinates()`-nya `false`, posisinya belum ke-link sebagai parent di data jabatan).
- **Halaman `/karyawanku`** (`MyEmployees.php`) — reuse `EmployeesTable::configure()` (kolom sama persis kayak admin), query di-override pakai `OrgHierarchyService` (atau company terpilih untuk Direktur holding). Search & pagination otomatis dari Filament Table.
- **Halaman `/karyawanku/{record}/edit`** (`EditMyEmployee.php`) — reuse `EmployeeForm::configure()` (form sama persis kayak admin), tapi full halaman sendiri (bukan modal). Guard: permission `edit_karyawan` + `canManage()` (cek target itu beneran bawahan rekursif, atau user-nya Direktur holding).
- **Catatan berbeda dari draft**: TIDAK pakai permission variant `view_karyawan_self/_subordinates/_all` seperti rencana awal. Semua pengecekan cakupan sekarang hardcoded di method `User` model & `canManage()`, bukan dikontrol lewat centang permission di form Role.

### 🔶 OPEN QUESTION — belum diputuskan: scope Edit vs View beda?
Muncul dari diskusi 2026-07-05: mungkin **Edit** perlu dibatasi lebih sempit dari **View** — misal HC Manager boleh **lihat** semua bawahan rekursif (Supervisor + staff di bawahnya), tapi cuma boleh **edit** direct report-nya langsung (Supervisor-nya saja, bukan staff yang di bawah Supervisor itu).

Ini butuh diputuskan dulu mekanismenya, dua opsi:
1. **Hardcode di `canManage()`** — ganti jadi cek "direct child" saja (bukan rekursif penuh) untuk edit. Simpel, tapi kalau nanti perlu beda-beda per role (ada yang boleh edit rekursif, ada yang cuma direct), harus hardcode kondisi lagi.
2. **Balik ke permission-variant** (mirip draft awal) — misal `edit_karyawan_direct` vs `edit_karyawan_subordinates`, dicentang per role lewat form Role yang sudah ada. Lebih fleksibel & "diatur manual lewat permission" (bisa beda per role tanpa ubah kode), tapi berarti bikin ulang sebagian mekanisme yang sekarang sudah structural-only.

**Belum dieksekusi — nunggu keputusan user mekanisme mana yang dipakai.**

### Resource lain yang berpotensi butuh scoping sama (belum diputuskan)
Kemungkinan pola yang sama dibutuhkan juga di resource lain yang datanya per-user (approval, attendance report, payroll report) — belum dibahas detail, direview belakangan.

## Status Ringkas

- Pilar 1: ✅ selesai.
- Pilar 2: ❌ dibatalkan (tidak relevan — akses Filament cuma Admin/Super Admin).
- Pilar 3: ✅ selesai untuk Karyawan/User di portal depan (view + edit). 🔶 Terbuka: apakah scope Edit perlu lebih sempit dari View (lihat Open Question di atas). Resource lain yang mungkin butuh pola sama — belum digarap.
