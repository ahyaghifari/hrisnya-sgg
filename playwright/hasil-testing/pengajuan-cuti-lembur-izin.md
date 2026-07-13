# Hasil Testing — Pengajuan & Persetujuan Cuti, Lembur, Izin

**Tanggal eksekusi:** 13 Juli 2026
**Tool:** Playwright MCP (browser otomatis)
**Environment:** `http://localhost:8000` (local, `APP_ENV=local`)
**Referensi plan:** [../test-plan-cuti-lembur.md](../test-plan-cuti-lembur.md), [../test-plan-izin.md](../test-plan-izin.md)

## Akun yang dipakai

| Peran | Email | Jabatan |
|---|---|---|
| Pemohon | ahyaghifari288@gmail.com | IT Programmer Staff |
| Atasan 1 | itcoordinator@syifaglobalgroup.com | IT Supervisor (Lutfi Salisa Setiawati) |
| Atasan 2 | hcga.manager@syifaglobalgroup.com | PJ HCGA Manager (Muhammad Sandy Alfianoor) |

Alur approval "Alur Perizinan IT": step 1 IT Supervisor → step 2 PJ HCGA Manager (final).

## Ringkasan hasil

| Skenario | Record ID | Status Akhir | Catatan |
|---|---|---|---|
| Cuti Tahunan (14 Jul 2026) | `01kxcyx6ah7kajx94cr17zy8vw` | ✅ Disetujui | Approve step 1 & 2 sukses |
| Lembur Biasa (14 Jul 2026, 18:00-20:00) | `01kxcz66fyrjbych8ctbkv1fy5` | ✅ Disetujui | Approve step 1 & 2 sukses |
| Izin Terlambat (14 Jul 2026, 08:00-09:00) | `01kxczz6cbg35j3p8qcknm1x2b` | ✅ Disetujui | Approve step 1 & 2 sukses |

Ketiga jenis pengajuan (cuti, lembur, izin) berhasil melewati alur 2-tingkat approval end-to-end: submit oleh pemohon → approve atasan 1 (IT Supervisor) → approve atasan 2 (PJ HCGA Manager, final) → status akhir "Disetujui". Riwayat approval, catatan (note), dan timestamp tercatat benar di setiap tahap.

Skenario opsional (reject, izin dengan pengganti tugas) **belum dijalankan**.

## Bug ditemukan & diperbaiki

### 1. Console error `filamentTable is not defined` saat navigasi SPA (wire:navigate) — FIXED
- **Gejala:** Saat berpindah halaman lewat link internal (`wire:navigate`) ke halaman yang memuat Filament Table (`/pengajuan-absensi`, `/my-staff`, `/struktur-organisasi`), muncul beberapa `ReferenceError`: `filamentTable is not defined`, `getSelectedRecordsCount is not defined`, `isRecordSelected is not defined`, `filamentActionModals is not defined`.
- **Root cause:** `@filamentStyles`/`@filamentScripts` dipush per-halaman lewat `@push('head')`/`@push('scripts')` di 4 view (`my-attendance-requests`, `my-employees`, `organization-structure`, `edit-my-employee`), bukan di layout bersama. `@filamentScripts` sempat ditaruh di `<body>` — Livewire's `wire:navigate` hanya `await` script baru di `<head>`, sedangkan script di `<body>` dieksekusi fire-and-forget sehingga Alpine sempat mengevaluasi `x-data="filamentTable(...)"` sebelum registrasinya selesai dimuat.
- **Fix:** `@filamentStyles` + `@filamentScripts` dipindah ke `<head>` [layout.blade.php](../../resources/views/components/layout.blade.php) (satu tempat, load sekali, persist selama sesi SPA). 4 push block duplikat di masing-masing view dihapus.
- **Verifikasi:** Navigasi berulang dashboard → Pengajuan → Beranda → Pengajuan via `wire:navigate` — 0 console error di semua percobaan setelah fix (sebelumnya 6-7 error per transisi).

### 2. Tombol "Setujui" kadang perlu diklik 2x sebelum textarea "Catatan" muncul
- **Gejala:** Klik pertama di beberapa sesi baru tidak memunculkan form catatan; klik kedua baru berhasil.
- **Status:** Belum diperbaiki (butuh investigasi lebih lanjut, kemungkinan race Livewire saat komponen baru dimuat). Workaround saat testing: klik ulang jika textarea belum muncul.

### 3. Tombol "Keluar" (logout) sering timeout di Playwright
- **Gejala:** `TimeoutError` 5 detik saat klik tombol Keluar, meski proses logout tetap berhasil di server (dikonfirmasi via navigasi manual ke `/login`).
- **Status:** Belum diperbaiki — kemungkinan besar false positive dari sisi tooling Playwright (redirect POST form lambat direspons), bukan bug aplikasi. Workaround: setelah klik, langsung `navigate` ke `/login` untuk konfirmasi sesi.

## Temuan tambahan: sistem push notifikasi

Di luar scope pengajuan cuti/lembur/izin, sesi ini juga menginvestigasi apakah push notification sudah terhubung end-to-end (dipicu oleh pertanyaan user setelah lihat hasil approval). Ringkasan:

- **Backend sudah lengkap:** subscribe endpoint (`/push/subscribe`), `PushSubscriptionController`, notification class `RequestStatusChanged` (channel `database` + `WebPushChannel`), service worker (`public/sw.js`) menangani event `push` dan `notificationclick`.
- **Bukti nyata dari tes:** 6 job `RequestStatusChanged` masuk ke tabel `jobs` — persis sesuai 3 approval **final** yang kita jalankan (cuti, lembur, izin) × 2 channel. Job menumpuk pending karena tidak ada `queue:work` yang berjalan di environment ini.
- **Gap 1 — notifikasi hanya di step final:** Approval di step *antara* (mis. IT Supervisor approve step 1) tidak memicu notifikasi sama sekali — baik ke pemohon maupun ke atasan berikutnya. Notifikasi baru terkirim saat status final (approved/rejected/cancelled). Root cause: `ApprovalFlowService::advanceStep()` hanya panggil `->notify()` di percabangan `else` (tidak ada next approver), bukan di percabangan `if ($next)`.
- **Gap 2 — klik notifikasi salah arah — FIXED:** `notificationclick` di `sw.js` sebelumnya hardcode buka `/dashboard`, padahal payload sudah membawa `request_id`. Diperbaiki agar buka `/pengajuan-absensi/{request_id}` (lihat [sw.js](../../public/sw.js)).
- **Belum diperbaiki:** gap notifikasi step-antara (poin di atas) — didiskusikan tapi belum diminta untuk dieksekusi.

## File terkait
- Plan: [test-plan-cuti-lembur.md](../test-plan-cuti-lembur.md), [test-plan-izin.md](../test-plan-izin.md)
- Fix bug Filament: [resources/views/components/layout.blade.php](../../resources/views/components/layout.blade.php)
- Fix push notification click: [public/sw.js](../../public/sw.js)
- Notification class: [app/Notifications/RequestStatusChanged.php](../../app/Notifications/RequestStatusChanged.php)
- Trigger notifikasi: [app/Services/Approval/ApprovalFlowService.php](../../app/Services/Approval/ApprovalFlowService.php)
