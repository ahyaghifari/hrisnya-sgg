# Setelah Migrate — Revisi Absensi (Cutoff, Kuota Izin/Cuti, Grup Presensi)

Checklist khusus untuk deploy revisi absensi (`planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md`) — pelengkap `deployment/CHECKLIST.md` yang generik, fokus ke langkah **setelah `php artisan migrate --force`** khusus perubahan sesi ini. Ikuti `CHECKLIST.md` §3-4 dulu untuk proses deploy standarnya, baru langkah di bawah ini.

## 1. Seeder yang WAJIB dijalankan (tidak auto-run tiap deploy)

```bash
php artisan db:seed --class=AttendanceSettingsSeeder
```

Idempotent (aman diulang) — isi 3 setting baru module `attendance`: `attendance_period_cutoff_day` (default 1 = kalender biasa), `cuti_tahunan_max_per_bulan` (default 0 = tidak dibatasi, override Holding = 5), `izin_max_per_periode` (default 2). **Tanpa langkah ini, halaman Pengaturan Absensi akan menampilkan field kosong** (bukan error, tapi setting belum ada barisnya di DB sampai di-save manual sekali lewat UI atau seeder ini).

Kalau belum pernah dijalankan sebelumnya di server ini, jalankan juga (independen dari revisi ini, tapi berkaitan — 2 key attendance dulu ada di sini sebelum dipindah):
```bash
php artisan db:seed --class=PayrollSettingsSeeder
```

## 2. Permission — cek ulang assignment `sistem_absensi`

Halaman **Pengaturan Absensi** dan **Hari Libur & Cuti Bersama** (baru dipindah dari grup Master) sekarang SAMA-SAMA butuh permission `sistem_absensi` (`CustomPermissionEnum::SISTEM_ABSENSI`), bukan `sistem_master` lagi untuk Hari Libur.

- [ ] Cek role yang SEBELUMNYA punya akses Hari Libur lewat `sistem_master` — kalau role itu **tidak** juga punya `sistem_absensi`, mereka akan **kehilangan akses** ke halaman ini setelah deploy. Assign ulang manual kalau perlu:
  ```bash
  php artisan tinker --execute="\App\Models\Role::where('name','NAMA_ROLE')->first()->givePermissionTo('sistem_absensi');"
  ```
- [ ] Verifikasi role yang memang perlu kelola Jadwal/Pengaturan Absensi/Hari Libur sudah punya `sistem_absensi` — cek lewat `/admin/roles`.

## 3. Data master yang perlu diisi manual (bukan bug, memang butuh input admin)

- [ ] **Kategori "Cuti Tahunan"** — buka `/admin/leave-types`, edit baris "Cuti Tahunan" tiap company yang mau kena batas `cuti_tahunan_max_per_bulan` (mis. Holding = 5 hari/periode), pilih dropdown "Kategori" → **Cuti Tahunan**. Tanpa ini, limitnya **tidak akan pernah aktif** meski setting angkanya sudah ada — dicek langsung di data production, kolom ini masih kosong per 2026-07-19.
- [ ] **Kategori Jenis Izin** — buka `/admin/permission-types`, field "Kategori" baru ditambahkan sesi ini. Data Holding (4 baris: Izin Terlambat, Izin Pulang Lebih Awal, Izin Tidak Masuk, Izin Meninggalkan Jam Kerja) **sudah benar terisi** dari sesi sebelumnya, tidak perlu diulang. Company lain yang mau pakai limit `izin_max_per_periode` perlu isi sendiri per jenis izinnya.
- [ ] (Opsional, kalau mau aktifkan cutoff non-kalender) Buka **Pengaturan Absensi** (`/presensi/pengaturan`), pilih company, isi "Tanggal Cutoff Periode Absensi" (mis. 21 untuk periode 21-20). Default 1 kalau dibiarkan kosong = tidak berubah dari perilaku kalender biasa.

## 4. Halaman baru — belum ada link navigasi (sengaja, sesuai keputusan sesi ini)

- [ ] `/presensi/kalender-libur` (versi read-only Hari Libur untuk karyawan biasa) — **belum dipasang link di nav/dashboard manapun**. Kalau mau diaktifkan untuk karyawan, itu kerjaan terpisah (lihat `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` §9.3) — untuk sekarang cukup tahu route-nya sudah hidup, bisa diakses langsung lewat URL kalau perlu.

## 5. Verifikasi fungsional setelah deploy

- [ ] Buka `/presensi/pengaturan`, `/presensi/hari-libur` — pastikan tidak 403 buat role yang seharusnya bisa akses, dan memang 403 buat yang tidak (test 2 arah, bukan cuma "bisa dibuka").
- [ ] Submit 1 pengajuan **Cuti** dan 1 **Izin** lewat portal (`/pengajuan-absensi/cuti` dan `/pengajuan-absensi/izin`) pakai akun yang approval flow-nya aktif — pastikan tidak error saat submit.
- [ ] Approve salah satu pengajuan itu → buka Data Diri/kartu Sisa Cuti di beranda → **sisa harus berkurang setelah approve**, bukan sudah berkurang sejak submit (ini inti dari bug fix sesi ini — kalau masih berkurang saat submit, ada yang salah).
- [ ] Reject 1 pengajuan lain → pastikan sisa kuota **tidak berkurang sama sekali** (dulu ini yang jadi bug: submit langsung motong, reject tidak pernah balikin).
- [ ] Buka halaman detail pengajuan (portal & `/admin/attendance-requests`) — blok "Kuota Pemohon" muncul dan angkanya masuk akal (Kuota/Cuti Bersama/Digunakan/Sisa untuk Cuti; Batas/Sudah Diajukan/Sisa untuk Izin).
- [ ] Coba ajukan Izin ke-3 dalam periode yang sama (kalau setting `izin_max_per_periode`=2 & kategori sudah diisi) — pengajuan **harus ditolak** dengan pesan jelas, bukan silent fail.

## 6. Yang PERLU diketahui, bukan tugas deploy (informasi, supaya tidak salah paham saat testing)

- Cuma **1 dari 8 company** (Holding/Syifa Global Group) yang punya data Jenis Cuti & Jenis Izin sama sekali per 2026-07-19 — company lain belum bisa dites alur Cuti/Izin sampai master data-nya diisi, ini di luar scope revisi ini.
- Flow approval **"default"** untuk company Holding pernah di-soft-delete (`m_approval_flows`, ada `deleted_at`) — cuma tersisa flow `specific` per role/departemen (IT, HCGA, Finance, Manajer, dst, semua masih aktif). User yang rolenya tidak match salah satu flow spesifik (mis. Super Admin murni) akan gagal submit dengan pesan "No active approval flow found" — ini bukan bug dari revisi ini, murni gap data approval flow yang sudah ada sebelumnya.

## 7. Assign role manual (permintaan khusus sesi ini)

Tambahkan role **Super Admin** ke akun `ahyaghifari288@gmail.com` — dijalankan manual sekali lewat tinker, bukan bagian rutin tiap deploy:

```bash
php artisan tinker --execute="App\Models\User::where('email','ahyaghifari288@gmail.com')->first()->assignRole('Super Admin');"
```

Verifikasi setelah dijalankan:
```bash
php artisan tinker --execute="print_r(App\Models\User::where('email','ahyaghifari288@gmail.com')->first()->getRoleNames()->toArray());"
```

Catatan: role **"Super Admin"** di project ini konvensinya cuma dipakai company holding (lihat README §Modul Utama) — pastikan akun ini memang milik/scoped ke company holding sebelum assign, kalau belum, `assignRole()` tetap jalan (Spatie Permission tidak validasi company scope otomatis) tapi bisa jadi tidak sesuai konvensi yang berlaku.

## Referensi
- `planning/2026-07-19-revisi-absensi-cutoff-kuota-izin-widget-pimpinan.md` — dokumen rencana lengkap revisi ini.
- `deployment/CHECKLIST.md` — checklist deploy umum, dijalankan dulu sebelum langkah-langkah di atas.
