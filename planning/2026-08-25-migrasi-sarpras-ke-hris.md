# Planning: Migrasi Sarpras (Sarana & Prasarana) ke HRIS (2026-08-25)

Status: **planning awal, belum dieksekusi.** Analisis lengkap sumbernya ada di [`knowledge/markdown/analisis-sarpras-migrasi.md`](../knowledge/markdown/analisis-sarpras-migrasi.md). Sumber: `another-app/sarpras/sarpras-php-v2/sarpras` (PHP native, proyek baru — bukan legacy production, tidak ada data lama yang perlu dimigrasi).

## 0. Keputusan yang sudah diambil

- **Nama modul di HRIS: "Sarana & Prasarana"** (bukan singkatan "Sarpras" di nav/label user-facing) — "Sarpras" tetap dipakai sebagai istilah internal/kode dokumen (`SPR-`), sama seperti nama file/planning ini.
- **Scope: full parity** — alur approval + item partial approve/reject/pangkas jumlah + tanda tangan elektronik berbarcode + dokumen cetak resmi + halaman verifikasi publik (tanpa login). Dikonfirmasi user 2026-08-25 lewat pertanyaan scope eksplisit (bukan "core dulu, ttd/cetak menyusul").
- **Approval routing**: reuse Approval Engine HRIS apa adanya (`MasterApprovalFlows`/`MasterApprovalFlowSteps`/`ApprovalFlowAssignments`, snapshot per pengajuan) buat alur Unit→Kabid→Direktur — sama pola 11 modul lain, cukup 1 case baru di `App\Enum\Approval\ApprovalModuleEnum` (`SARPRAS_REQUEST`).
- **Keputusan per-item TIDAK masuk Approval Engine** — engine cuma tau step "Direktur: approved/rejected" buat 1 dokumen utuh, tidak punya konsep baris item dengan status masing-masing. Detail per-item (approve/reject/pangkas jumlah per baris) hidup di tabel domain sendiri, di luar tabel approval engine — persis pola ManpowerRequests (approval engine buat alur & notifikasi, tabel domain sendiri buat detail bisnis). Step "Direktur" di engine di-resolve *approved* begitu semua item sudah diputuskan (apa pun campuran hasilnya); detail keputusan per-item ditampilkan di halaman domain sendiri, bukan di riwayat approval generik.

## 1. Struktur data yang diusulkan

Tidak reuse `attendance_requests` (domain beda total) — bikin set tabel sendiri, mengikuti pola `permohonan`/`permohonan_item` di sumber tapi dengan ULID (konsisten seluruh HRIS) dan `company_id` (multi-tenant, sumbernya single-tenant):

- `sarpras_requests` — kepala dokumen (**SUDAH dibangun & jalan**, beda dari draft awal §ini): `id` (ULID), `user_id` (pemohon), `kode` (`SPR-{tahun}-{urut}`), `unit` (string, **diisi otomatis dari Divisi pemohon** saat submit — lihat §3 poin 1, bukan FK, bukan input bebas), `jenis`, `prioritas`, `perihal`, `uraian`, `status` (7 nilai), `catatan_kabid`/`kabid_id`/`kabid_at`, `catatan_direktur`/`direktur_id`/`direktur_at`, `selesai_at`. Tidak ada `company_id` eksplisit (scope lewat relasi `user->company_id`, pola sama Sikartap).
- `sarpras_request_items` — baris item. `nama`, `spesifikasi`, `jumlah`, `satuan`, `harga_satuan`, `status` (`menunggu`/`disetujui`/`ditolak`), `jumlah_disetujui`, `catatan_direktur`, `diputuskan_at`.
- `sarpras_request_attachments` — lampiran, mirror `lampiran` sumber (nama asli, nama simpan acak, mime, ukuran) — bukan kolom JSON kayak `attachments_files` di attendance, karena butuh listing + validasi jumlah/ukuran per file yang lebih alami sebagai tabel sendiri (juga dipertahankan biar konsisten dengan pola sumbernya).
- `sarpras_request_signatures` — tanda tangan barcode. `peran` (`unit`/`kabid`/`direktur`), `kode` (unique, format pendek 14 karakter — lihat §D analisis buat alasan panjang kode), `nama`/`jabatan` snapshot saat ttd dibubuhkan (bukan FK live ke `users`, karena jabatan bisa berubah setelah ttd dibubuhkan — sumbernya juga snapshot).
- Riwayat/audit: **reuse `ApprovalHistories`** yang sudah ada di Approval Engine (append-only, sudah dipakai lintas modul) — bukan bikin tabel `riwayat` terpisah lagi seperti sumber, supaya konsisten dengan pola HRIS existing dan gak duplikasi konsep.

Fungsi `segarkan_status()` (hitung ulang status kepala dari status semua item) porting jadi method service, dipanggil tiap kali ada keputusan item — sama logikanya, bukan reimplementasi dari nol (lihat §C analisis buat aturan lengkapnya).

## 2. Barcode & dokumen cetak

- **Barcode Code 128**: `includes/barcode.php` sumber generate SVG murni PHP tanpa pustaka luar — port langsung, gak perlu dependency baru. Constraint modul lebar (README: minimal 1,5, dipakai 1,9) dan panjang kode (14 karakter) **wajib dipertahankan persis**, itu hasil uji pindai fisik, bukan preferensi kosmetik — kalau format kode diubah jadi lebih panjang, README sumber sudah warning barcode berisiko gak kebaca pemindai.
- **Halaman cetak**: gerbang sama seperti `boleh_cetak()` sumber (status final + minimal 1 item disetujui). Perlu halaman baru (Blade view khusus print, bukan Livewire biasa — mirip kebutuhan slip gaji payroll yang juga punya cetak).
- **Verifikasi publik**: route baru tanpa auth (`config/portal-pages.php` gak relevan di sini karena ini publik penuh, bukan portal karyawan) — cari kode ttd → tampilkan detail pengajuan+peran+nama penandatangan. Perlu dipastikan **tidak bocorin data sensitif** (nominal harga misalnya) ke halaman yang bisa diakses siapa saja.

## 3. Pertanyaan terbuka (perlu diputuskan sebelum mulai coding)

1. ~~**Unit di sumber vs struktur organisasi HRIS**~~ — **RESOLVED, direvisi 2026-08-25**: keputusan awal (unit input bebas, gak dipetakan) DIBATALKAN — user klarifikasi ulang: "unit" itu sebenarnya **Divisi (OrgStructure level 2)**. Kolom `unit` di `sarpras_requests` tetap `string` (skema DB TIDAK berubah, tidak jadi FK), tapi nilainya sekarang **diambil otomatis** dari `Auth::user()->profile?->division?->name` (`UserProfiles::getDivisionAttribute()`, accessor yang sudah ada) saat submit — bukan input bebas/dropdown lagi. Form ajuan cuma menampilkan read-only. Submit ditolak (`RuntimeException`) kalau pemohon belum terhubung ke Divisi manapun di struktur organisasi. Akses "boleh mengajukan" tetap digerbangi permission (`AJUKAN_SARANA_PRASARANA`), itu terpisah dari soal unit/divisi-nya.
2. **Approval flow assignment**: **ditunda** (dikonfirmasi user) — dibuat manual belakangan lewat `/admin/approval-flows`/`/kepegawaian/alur-persetujuan` begitu `ApprovalModuleEnum::SARPRAS_REQUEST` (sudah dibuat) siap dipasangi step. Tidak menunggu ini buat lanjut ke service/portal layer — flow bisa nyusul kapan saja tanpa ubah kode.
3. **Permission**: entry baru di `CustomPermissionEnum` atau cukup Shield `Verb:Subject` biasa (kalau modul ini dibikin sebagai Filament Resource beneran)? Sumbernya 4 role flat (admin/unit/kabid/direktur) dengan tabel hak-akses eksplisit di README — perlu dipetakan ke pola permission HRIS (kemungkinan besar Shield Resource buat admin panel + beberapa custom permission portal, sama pola `LIHAT_ALUR_PERSETUJUAN`/`KELOLA_ALUR_PERSETUJUAN`).
4. **Halaman**: portal Livewire (konsisten pola self-service HRIS lain) untuk Unit mengajukan + lihat riwayat, **dan** halaman Filament/portal khusus buat Direktur memutuskan per item (UI custom, gak bisa pakai halaman approval generik `AttendanceRequestDetail`-style karena butuh form per-baris item).
5. **Notifikasi**: reuse `NotificationService` existing (`sendOvertimeRequestNotification` dkk polanya) — tambah method baru `sendSarprasRequestNotification`, ikut pola yang sudah ada.

## 4. Urutan eksekusi yang disarankan (usulan awal, belum difinalkan)

1. Jawab pertanyaan §3 dulu (terutama #1 dan #2, blocker desain form & flow) — bagian ini butuh input dari RS/HC, bukan keputusan teknis semata.
2. Migrasi tabel (§1) + enum `SARPRAS_REQUEST` di `ApprovalModuleEnum`.
3. Service layer: `SarprasRequestService` (submit, item decision, `segarkanStatus()`) + repository, ikut pola `AttendaceReqService`/`LeaveReqRepository` yang sudah ada.
4. Portal Livewire: form ajuan multi-item (mirror `tambah.php` sumber), halaman detail+keputusan per item (mirror `detail.php`), riwayat.
5. Barcode + cetak + verifikasi publik (§2) — paling independen, bisa dikerjakan terakhir/paralel setelah alur data intinya jalan.
6. Filament Resource admin (opsional tergantung jawaban §3 poin 3 — apakah Direktur/Kabid akses lewat portal aja atau juga butuh sisi `/admin`).

## 5. File referensi dari sumber (buat porting logika, bukan disalin mentah — beda bahasa/ORM)

- `includes/functions.php` — `segarkan_status()`, `boleh_cetak()`, `boleh_unggah()`, `bubuhkan_ttd()`, `aksi_tersedia()`.
- `includes/barcode.php` — generator Code 128 SVG murni PHP.
- `database/sarpras.sql` — skema lengkap 6 tabel (dianalisis penuh di knowledge doc §B–C).
- `proses.php` — satu-satunya entry point POST buat semua aksi alur, referensi urutan validasi/otorisasi per aksi.
