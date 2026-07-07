# Planning: PWA Portal Karyawan (`/pwa`) — 2026-07-05

Status: **bagian A (shell mobile) sudah diimplementasikan.** B/C/D/E belum.

## Progress (update setelah eksekusi)

- ✅ Route `/pwa` (`pwa.home`), terdaftar di `config/portal-pages.php`, guard sama seperti `/dashboard` (login saja).
- ✅ Layout mobile terpisah: `components/pwa-layout.blade.php`, `components/pwa-app-bar.blade.php` (app bar navy custom, judul dinamis per halaman), `components/pwa-bottom-nav.blade.php` (4 tab: Beranda aktif, Presensi/Pengajuan/Akun masih mengarah ke anchor/route desktop existing).
- ✅ Halaman Beranda (`livewire/pwa/home.blade.php`) — copy independen dari `dashboard.blade.php` (sengaja tidak share partial lagi karena kontennya akan terus berbeda): tanpa "Aksi Cepat", tanpa "Riwayat Pengajuan Terbaru", tanpa stat presensi & pending-approval, saldo cuti versi compact (`LeaveBalanceCard` dapat prop `compact`), card presensi tanpa tombol aksi (`.absen-card-compact`, cuma placeholder — dikonfirmasi: data presensi nanti murni dari sinkronisasi Hikvision, bukan input manual).
- ✅ Dikonfirmasi: **tidak perlu route login terpisah** — middleware `auth` bawaan Laravel sudah redirect ke `/login` + `redirect()->intended()` sudah otomatis balik ke `/pwa` setelah login berhasil.
- ❌ **Belum ada manifest.json, service worker, ikon** — jadi `/pwa` saat ini SECARA TEKNIS BELUM INSTALLABLE, baru "halaman web bergaya mobile app". Ini gap utama sebelum PWA-nya bisa benar-benar disebut PWA.

## Konfirmasi dari Diskusi

1. **Bukan seluruh sistem jadi PWA** — cuma sub-route baru `/pwa/*` yang installable, sisanya (`/admin`, `/dashboard`, `/karyawanku`, dll) tidak berubah sama sekali.
2. **Tampilan mobile-only**, bottom navigation bar seperti aplikasi mobile pada umumnya. Halaman depan (beranda) tidak jauh beda dari `Dashboard` desktop sekarang (saldo cuti, status pengajuan aktif, riwayat) — cuma dibungkus layout mobile.
3. **Tetap pakai Livewire + Blade**, bukan Vue/SPA — instalabilitas PWA (manifest + service worker) tidak butuh SPA framework, dan codebase ini sengaja konsisten tanpa SPA framework (lihat README). Ganti stack cuma untuk satu sub-route = paradigma baru + build pipeline baru untuk manfaat yang belum jelas dibutuhkan.
4. **Push notification** dibutuhkan untuk event approve/reject pengajuan absensi (notifikasi ke requester, bukan cuma ke approver seperti sekarang).
5. **Presensi**: bukan tap-to-check-in dari HP. Sumber data presensi adalah **alat presensi Hikvision** — datanya ditembak/ditarik langsung dari database alat tersebut, bukan input manual karyawan.
6. **Ganti password**: reuse logic existing, tidak bikin baru.

## Cakupan Halaman `/pwa`

| Halaman | Isi | Sumber |
|---|---|---|
| Beranda | Saldo cuti, status pengajuan aktif, ringkasan presensi | Reuse widget `Dashboard` yang sudah ada |
| Presensi | Riwayat check-in/out harian | Baca dari `attendance_records` (diisi sinkronisasi Hikvision — lihat bawah) |
| Pengajuan Absensi | List riwayat + form cuti/izin/lembur + detail | Reuse penuh Livewire component existing (`MyAttendanceRequests`, `SubmitLeaveRequest`, `SubmitPermissionRequest`, `SubmitOvertimeRequest`, `AttendanceRequestDetail`) |
| Akun | Data diri (reuse `EmployeeAttendanceStatistics`), ganti password (baru, reuse logic), logout | - |

Guard: middleware `auth` (guard `web`) + `portal.access` seperti route portal lain, didaftarkan ke `config/portal-pages.php`.

## A. Layout & Navigasi Mobile

- Layout baru terpisah, bukan reuse `resources/views/components/layout.blade.php` (itu didesain untuk sidebar desktop).
- Bottom nav bar tetap (4 tab: Beranda, Presensi, Pengajuan, Akun).
- Component Livewire yang direuse dari portal desktop dipasang di layout baru ini — logic/service tidak diduplikasi, cuma wrapper tampilan yang beda.

## B. Manifest + Service Worker (scoped ke `/pwa/`)

- `public/manifest.json` — `scope` dan `start_url` diarahkan ke `/pwa/`, cuma di-link dari layout `/pwa`.
- Service worker didaftarkan dengan `scope: '/pwa/'` — tidak menguasai path di luar itu.
- Ikon baru dibutuhkan (192x192, 512x512 + maskable) — `favicon.ico` saja tidak cukup.
- SW cache dibatasi ke asset statis (CSS/JS/font/icon), bukan halaman ber-session — hindari cache response yang bergantung CSRF/state.

## C. Push Notification

- **Perlu 1 dependency baru**: `laravel-notification-channels/webpush` (wraps `minishlink/web-push`). Web Push Protocol wajib signing VAPID — tidak realistis ditulis manual, dan tidak ada alternatif stdlib/dependency existing yang cover ini.
- Generate VAPID key pair, simpan di `.env`.
- Migration tabel `push_subscriptions` (disediakan package) — endpoint + key per user/device.
- Endpoint baru: simpan subscription saat user mengizinkan notifikasi di `/pwa` (JS `PushManager.subscribe()` → POST ke backend).
- **Hook baru di `ApprovalFlowService`** (titik status berubah approve/reject) untuk kirim push ke requester. Saat ini `NotificationService` cuma kirim ke approver saat submit (`sendOvertimeRequestNotification`, dst) — belum ada notifikasi balik ke requester sama sekali, jadi ini gap yang perlu ditutup, bukan sekadar nyambungin yang sudah ada.

## D. Integrasi Presensi Hikvision

**Belum dianalisis detail — item riset terbesar sebelum implementasi.** Yang perlu digali sesi berikutnya:

1. **Jenis akses ke database alat**: Hikvision biasanya expose data presensi lewat database on-prem software-nya (mis. iVMS-4200 / HikCentral, umumnya SQL Server), atau lewat ISAPI/HTTP event push dari device. Perlu dipastikan device di lokasi ini pakai skema yang mana — user bilang "ditembak langsung ke database", jadi kemungkinan konek langsung ke DB tersebut, bukan API.
2. **Koneksi DB kedua**: kalau benar konek langsung ke DB Hikvision, perlu `config/database.php` connection baru (kemungkinan `sqlsrv` driver kalau SQL Server) — read-only, jangan pernah tulis ke DB itu.
3. **Mapping karyawan**: field employee ID di sisi Hikvision (biasanya `pin`/`employeeNo`) perlu dipetakan ke `nip`/`user_id` di tabel `attendance_records` — cek dulu apakah NIP di HRIS ini sama persis dengan yang didaftarkan di alat.
4. **Mekanisme sync**: job terjadwal (`php artisan schedule`) yang poll tabel log presensi Hikvision (biasanya semacam `CheckInOut`/`ACC_TransactionRecords` tergantung versi software), insert ke `attendance_records` dengan `source` baru (kolom `source` sudah ada di model, tinggal tambah value baru mis. `hikvision`) — hindari duplikasi kalau job jalan berkali-kali (cek `user_id`+`date`+`check_in_time` sebelum insert, atau `firstOrCreate`).
5. **Frekuensi sync**: real-time (tiap device push event) atau polling interval (tiap X menit)? Ini nentuin apakah butuh queue listener atau cukup scheduled command.
6. **Akses jaringan**: server Laravel (terutama kalau di-deploy staging/production lewat Bitbucket Pipelines ke server terpisah) perlu bisa reach DB/device Hikvision — kalau device ada di jaringan lokal kantor beda dari server hosting, ini jadi blocker infrastruktur, bukan cuma kode.

## E. Ganti Password

- Reuse logic `AuthController::changePassword` (API, guard `sanctum`) — sama pola dengan adaptasi `AttendaceReqService` di planning pengajuan absensi: ganti hard-code guard jadi terima `User $user` dari `Auth::user()` (guard `web`), jangan tulis ulang validasi/logicnya.
- Tinggal 1 Livewire form baru di halaman Akun `/pwa`.

## Belum Diputuskan / Perlu Sesi Berikutnya

- Detail akses Hikvision (poin D) — ini blocker utama sebelum "Presensi" bisa dikerjakan, sisanya (layout, manifest, push, ganti password, reuse pengajuan) sudah cukup jelas untuk langsung eksekusi.
- Ikon & branding PWA (nama app, warna theme, ikon) — belum dibahas.
- Apakah butuh fallback kalau push notification permission ditolak user (in-app notification list tetap ada sebagai fallback, karena `DatabaseNotification` sudah ada).
