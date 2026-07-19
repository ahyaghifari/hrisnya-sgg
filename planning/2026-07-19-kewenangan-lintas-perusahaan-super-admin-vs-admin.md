# Pertanyaan Terbuka: Kewenangan Lintas Perusahaan (Super Admin vs Admin per Company) (2026-07-19)

**Status: BELUM DIKERJAKAN — bukan untuk eksekusi, cuma catatan diskusi buat dilanjutkan nanti.**

Gabungan 2 topik yang saling terkait: (1) siapa boleh isi Hari Libur & Cuti Bersama, (2) filter perusahaan di seluruh portal disembunyikan buat admin non-global. Sebelumnya file terpisah (`2026-07-19-siapa-yang-isi-hari-libur-cuti-bersama.md`), digabung karena keduanya sebenarnya 1 pertanyaan besar: **struktur kewenangan lintas company yang belum jelas**.

## 1. Filter Perusahaan Disembunyikan Buat Admin Non-Global

### Ide Awal
- **Super Admin** → lihat filter perusahaan seperti biasa di semua halaman (Periode Gaji, Payroll, Komponen Gaji, Penerima Komponen Gaji, Mapping Komponen Gaji, Insentif, Koperasi, Pengaturan Absensi, Hari Libur, dst).
- **Admin** (pengelola 1 company) → filter perusahaan **disembunyikan total**, otomatis terkunci ke `company_id` miliknya sendiri. Saat create/edit, `company_id` di-set otomatis dari user yang login, tidak bisa pilih company lain.

### Yang Bikin Ini Belum Final
Dari diskusi, ternyata kebutuhannya lebih dari 2 level (Super Admin vs Admin per company) — ada kemungkinan pihak Holding yang BUKAN Super Admin tapi tetap perlu lihat lintas company (misal Direktur Holding). Codebase ini **sudah punya 3 pattern beda** buat "siapa yang boleh lintas company", belum disatukan:

1. `PermissionHelper::hasGlobalRole($user)` — cuma cek `company_id === Holding` (TIDAK cek role, namanya menyesatkan — siapa pun user di company Holding kena `true`, walau role-nya karyawan biasa).
2. `PermissionHelper::hasPermission()` — ada inline check `$user->hasRole('Super Admin')` di dalamnya (return `true` unconditional kalau role-nya Super Admin, di company mana pun).
3. `User::canBrowseAllCompanies()` — `hasRole('Direktur') && hasGlobalRole($this)` (kombinasi role + company Holding), dipakai khusus di `/my-staff` biar Direktur Holding bisa lihat semua company.

### Arah yang Didiskusikan (belum diputuskan)
Rekomendasi sementara: bikin 1 method baru di `User`, misal `canFilterByCompany()` = `hasRole('Super Admin') || $this->canBrowseAllCompanies()` — gabungan pattern #2 dan #3 yang sudah ada, dipakai seragam di semua halaman yang punya filter perusahaan (ganti `<x-company-filter>` supaya otomatis sembunyi/kunci kalau method ini `false`).

**Belum diputuskan:**
- Apakah "siapa boleh lintas company" itu SAMA buat semua halaman, atau beda-beda per halaman (misal Payroll cuma Super Admin, tapi MyStaff boleh Direktur Holding juga — seperti kondisi `canBrowseAllCompanies()` sekarang)? Kalau beda-beda, 1 method tunggal (`canFilterByCompany()`) kurang cukup, perlu granular per fitur.
- Kalau filter disembunyikan di UI, tetap butuh guard server-side di tiap halaman (`updatedCompanyId()`/`saveForm()`) supaya admin company tidak bisa akal-akalin lewat manipulasi request Livewire langsung (bukan cuma sembunyiin tombolnya doang).

## 2. Siapa yang Berwenang Isi Hari Libur & Cuti Bersama

### Konteks
Halaman `/presensi/hari-libur` (`HolidaysPage`, dibangun ulang 2026-07-19 dari Filament ke custom List.js + kalender) mengizinkan Tambah/Edit/Hapus hari libur, dengan pilihan "Perusahaan":

- **Kosong (Semua Perusahaan)** — hari libur global, `company_id` NULL, otomatis berlaku ke SEMUA company.
- **Company tertentu** — cuma berlaku ke company itu.

Saat ini akses halaman ini digerbangi permission `sistem_absensi` (Shield) — siapa pun yang role-nya dikasih permission itu bisa Tambah/Edit/Hapus hari libur, **termasuk yang global** (berdampak ke SEMUA company sekaligus). Ini gejala yang sama dengan topik #1 di atas — begitu ada jawaban soal "siapa yang boleh lintas company", jawaban itu juga langsung menjawab siapa yang boleh isi hari libur global vs company sendiri.

### Pertanyaan (perlu ditanyakan ke HC/pemilik proses)
1. Siapa yang boleh isi hari libur **global** (berlaku semua company)?
2. Siapa yang boleh isi hari libur **khusus company sendiri**?
3. Kalau jawabannya beda kewenangan, apakah perlu pembatasan pilihan dropdown "Perusahaan" di form (admin non-holding cuma bisa pilih company sendiri, tidak bisa pilih "Semua Perusahaan" atau company lain)?

### Kenapa Ini Penting
Hari libur (terutama yang ditandai **Cuti Bersama**) langsung mempengaruhi perhitungan **saldo Cuti Tahunan** karyawan lintas company (`AttendaceReqService`/`LeaveReqRepository`). Kalau tidak dibatasi, admin 1 company bisa tanpa sadar (atau sengaja) mengubah jatah cuti karyawan di company lain lewat opsi "Semua Perusahaan".

## Belum Dikerjakan

- Tidak ada perubahan kode dari catatan ini — murni diskusi & pertanyaan yang perlu dijernihkan dulu (siapa yang benar-benar dianggap "lintas company": Super Admin saja, atau Super Admin + Direktur Holding + kombinasi lain).
- Setelah struktur kewenangan disepakati, baru eksekusi: `User::canFilterByCompany()` (atau nama lain yang lebih tepat), update `<x-company-filter>` + semua halaman yang pakainya, plus guard server-side di `saveForm()`/`updatedCompanyId()` masing-masing halaman (termasuk `HolidaysPage::saveForm()`).
