# Portal Modul Kepegawaian (Halaman Karyawan) + Skeleton Navigasi Dashboard per Modul

Status: **planning, belum dieksekusi**. Ditulis 2026-07-20 setelah investigasi bug permission `sistem_kepegawaian` (lihat sesi sebelumnya) — user memutuskan untuk lanjut fokus membangun modul Kepegawaian sungguhan di portal, bukan cuma perbaikan akses.

## 1. Konteks & Tujuan

Grup permission `sistem_kepegawaian` (`CustomPermissionEnum::SISTEM_KEPEGAWAIAN`) sudah ada dan sudah muncul sebagai dropdown "Kepegawaian" di nav desktop portal (`resources/views/components/nav.blade.php:97-108`), tapi isinya SEKARANG cuma link ke Izin/Cuti/Lembur (`my-attendance-requests.permission/leave/overtime`) — itu peninggalan perubahan 2026-07-19 (lihat `knowledge/changes/2026-07-19-jadwal-shift-rotasi-detail-penjadwalan-dan-permission-kepegawaian.md` §6), bukan modul kepegawaian yang sesungguhnya.

Yang diminta user sekarang: bangun **halaman "Karyawan"** di portal (di bawah grup Kepegawaian) — padanan dari admin Filament `EmployeeResource` (`/admin/employees`, lihat `app/Filament/Resources/Employees/`), dengan tombol Tambah Karyawan, Cari Karyawan, dst. — supaya HC/Kepegawaian bisa kelola data karyawan langsung dari portal, bukan lewat `/admin`.

Selain itu, user ingin nav portal punya entry **"Dashboard"** terpisah, dengan rencana JANGKA PANJANG tiap modul (Penggajian, Absensi, Kepegawaian) punya dashboard analitiknya sendiri-sendiri. **Sesi ini cuma bikin skeleton nav-nya** (dropdown/menu kosong atau placeholder) — halaman dashboard sungguhan belum dibangun.

## 2. Baseline yang Sudah Ada (referensi, jangan didesain ulang dari nol)

- **Admin `EmployeeResource`** (`app/Filament/Resources/Employees/`) — kolom: foto, Nama+email, NIP, Departemen, Jabatan/Role, Perusahaan (khusus global role). Filter: Perusahaan (khusus global role), Departemen. Aksi: Edit (guard `EmployeeResource::canEdit()`), bulk delete. Scoping `getEloquentQuery()`: non-global role otomatis dibatasi ke `company_id` sendiri.
- **Portal `MyStaff`** (`app/Livewire/MyEmployees.php` + `my-staff.*` routes) — pola LIST KARYAWAN YANG SUDAH ADA di portal, tapi scoped ke bawahan (rekursif via `OrgHierarchyService`), bukan semua karyawan company. Ini contoh pola "Filament Table di desktop / card list di mobile" yang harus diikuti (lihat `DESIGN.md` § Responsive requirement) — dipakai juga di `/my-staff/{id}` (detail) dan `/my-staff/{id}/edit`.
- **Gating & routing portal**: route didaftarkan di `routes/web.php`, lalu WAJIB didaftarkan juga di registry `config/portal-pages.php` (lihat entry `my-staff`, `my-staff.edit`, `my-staff.show`) supaya kena middleware `EnsurePortalPageAccess` dengan benar (kalau tidak didaftarkan, route lolos tanpa cek — bukan whitelist ketat, tapi sebaiknya tetap didaftarkan biar konsisten).
- **Nav desktop** (`resources/views/components/nav.blade.php`) sudah punya variabel `$canAccessKepegawaian` dan dropdown-nya (baris 97-108).
- **Nav mobile bottom sheet** (`resources/views/components/pwa-bottom-nav.blade.php`) — **BELUM PERNAH cek `SISTEM_KEPEGAWAIAN` sama sekali** (ditemukan saat investigasi sebelumnya). Kalau modul Kepegawaian dibangun, sheet menu mobile juga harus ditambah blok `@if ($canAccessKepegawaian)`, kalau tidak menu ini cuma muncul di desktop.

## 3. Lingkup Kerja (usulan, urutan)

1. **Halaman "Karyawan" portal** — Livewire baru (pola sama `MyEmployees`/admin `EmployeesTable`), route `presensi.kepegawaian.karyawan` atau `kepegawaian.karyawan` (nama route final tergantung keputusan §5.1), scoped company (bukan cuma bawahan seperti MyStaff — scoping sama seperti admin `EmployeeResource::getEloquentQuery()`, company sendiri kecuali global role).
   - List: foto, nama+email, NIP, Departemen, Jabatan/Role, Perusahaan (khusus global role) — kolom sama seperti admin.
   - Search: nama/email/NIP (sudah `searchable()` di admin table, tinggal port).
   - Filter: Perusahaan (khusus global role), Departemen.
   - Tombol "Tambah Karyawan" — form create, kemungkinan reuse `EmployeeForm` schema atau bikin versi Livewire-nya (perlu dicek field-fieldnya, `app/Filament/Resources/Employees/Schemas/EmployeeForm.php` belum diperiksa detail di sesi ini).
   - Detail/Edit — kemungkinan besar BISA REUSE halaman detail yang sudah ada (`/my-staff/{id}` = `EmployeeAttendanceStatistics`, sudah generic tidak spesifik ke "bawahan" doang, lihat README §Portal Karyawan) — supaya tidak duplikasi. Perlu konfirmasi ke user apakah halaman Karyawan baru ini **link ke `/my-staff/{id}` yang sudah ada** untuk detail, atau bikin halaman detail terpisah.
2. **Update nav desktop** (`nav.blade.php`) — dropdown "Kepegawaian" isinya diubah: tambah "Karyawan" (link baru), keputusan soal Izin/Cuti/Lembur yang sudah ada di situ lihat pertanyaan §5.2.
3. **Update nav mobile** (`pwa-bottom-nav.blade.php`) — tambah gate `$canAccessKepegawaian` + section shortcut, konsisten dengan yang lain (Keuangan, Absensi).
4. **Skeleton nav "Dashboard"** — tambah 1 dropdown/menu baru "Dashboard" di nav desktop (dan mobile sheet) berisi placeholder link per modul (Penggajian, Absensi, Kepegawaian) — TANPA route/halaman sungguhan dulu (bisa `href="#"` disabled, atau route yang mengarah ke halaman "Coming Soon" sederhana). Detail exact behavior lihat pertanyaan §5.3.
5. **Registrasi ke `config/portal-pages.php`** untuk route-route baru di atas.

## 4. Test Coverage

Ikuti pola project (`tests/Feature/`, sqlite in-memory, `RefreshDatabase`) — perlu test baru untuk halaman Karyawan portal (list scoping per company, search, create), mengikuti pola test MyStaff yang sudah ada kalau ada (`tests/Feature/` — perlu dicek nama filenya saat eksekusi).

## 5. Keputusan (dikonfirmasi user 2026-07-20)

1. **Route/URL halaman Karyawan**: `/kepegawaian/karyawan` — nama route `kepegawaian.karyawan`.
2. **Dropdown "Kepegawaian"**: TETAP SATU dropdown, bukan dipecah. "Karyawan" jadi item PALING ATAS, lalu divider, lalu Izin/Cuti/Lembur di bawahnya (pola sama seperti divider yang sudah dipakai di dropdown "Penggajian" — `style="height:1px;background:var(--border-soft);margin:6px 8px;"`, lihat `nav.blade.php` baris 75/79).
3. **"Dashboard" per modul**: BUKAN dropdown baru terpisah dari Beranda. Tiap dropdown modul yang SUDAH ADA (Penggajian, Absensi, Kepegawaian) nambah 1 item "Dashboard [Modul]" di dalam dropdown masing-masing (skeleton link dulu, halaman sungguhan menyusul). "Beranda" (`/dashboard`, `dashboard.blade.php`) TETAP seperti sekarang — sudah role-adaptive (kotak "Butuh Persetujuan Anda" utk atasan, dst.) — tidak diapa-apakan di task ini.
4. **Form Tambah/Edit Karyawan**: REDESIGN, bukan port langsung dari admin. Admin `EmployeeForm.php` (dibaca detail — `Wizard` 3 step: Akun → Pribadi → Pekerjaan, ~30 field total termasuk relasi berjenjang Departemen→Divisi→Sub Divisi, dan blok read-only "Kuota Cuti") jadi REFERENSI FUNGSIONAL (field & validasi mana yang wajib ada), bukan cetakan visual. Desain baru portal harus:
   - Konsisten dengan design tokens & pola existing (`DESIGN.md`) — bukan mendesain identitas baru, portal sudah punya bahasa visual sendiri (warna per kartu, dark mode via `var(--token)`, komponen `.pwa-list`/`.pwa-list-row`).
   - Mobile-first: form panjang begini WAJIB dipecah per section/step (bukan 1 halaman panjang di-scroll), dengan indikator progres yang jelas dan bisa lompat balik ke step sebelumnya tanpa kehilangan data.
   - Microcopy aktif & spesifik ("Simpan Karyawan", bukan "Submit"; pesan error bilang persis apa yang salah dan cara benerinnya — pola sama kayak validasi kuota jabatan yang sudah ada di admin: `"Kuota jabatan ini sudah penuh (actual manpower: X, terisi: Y)"`).
   - Relasi berjenjang Departemen→Divisi→Sub Divisi dan Perusahaan→Departemen (reset otomatis saat parent berubah) tetap dipertahankan logikanya, cuma disajikan lebih ringkas di UI portal (mis. progressive disclosure — jangan tampilkan semua field sekaligus kalau belum relevan).
   - **Saat eksekusi nanti, load skill `frontend-design` di awal sesi desain form ini** (bukan cuma dibaca sekali di planning) — biar tokens & keputusan layout final digali ulang dengan konteks form yang sudah stabil, bukan ditebak dari planning doc ini.
5. **Halaman detail karyawan**: `/my-staff/{id}` (`EmployeeAttendanceStatistics`, route name `my-staff.show`) DI-RENAME jadi `/karyawan/{id}` (route name kemungkinan `karyawan.show` — final naming dicek lagi saat eksekusi biar konsisten sama route list `kepegawaian.karyawan`). Ini jadi halaman detail BERSAMA buat 2 entry point: dari MyStaff (bawahan) dan dari Karyawan (company-wide). Dampak yang perlu diubah saat eksekusi (sudah di-grep, cuma 2 file yang refer ke route ini):
   - `resources/views/livewire/my-employees.blade.php` baris 42 & 130 — link tombol "Lihat" dari list MyStaff, ganti ke route baru.
   - `routes/web.php` baris 83-84 — definisi route `/my-staff/{record}` → pindah ke path/URL baru.
   - `config/portal-pages.php` baris 70 (`my-staff.show`) — key registry ikut di-rename.
   - **Perlu diputuskan saat eksekusi**: apakah route EDIT (`/my-staff/{record}/edit`, `EditMyEmployee`) ikut di-rename jadi `/karyawan/{id}/edit` juga (untuk konsistensi), atau dibiarkan di `/my-staff/{record}/edit` — user cuma eksplisit minta halaman detail (show) yang pindah.
   - **Perlu diputuskan saat eksekusi**: tombol "Kembali" di `employee-attendance-statistics.blade.php` baris 3 (`$backRoute`) saat ini selalu balik ke `route('my-staff')` (list MyStaff) kalau bukan lihat data sendiri — begitu halaman ini juga diakses dari list Karyawan (company-wide, bukan cuma bawahan), balik ke MyStaff jadi salah kalau user datang dari `/kepegawaian/karyawan`. Perlu logic asal-halaman (mis. query param `?from=karyawan` atau deteksi lewat referrer/session) supaya tombol Kembali balik ke list yang benar.

## 6. Referensi
- `app/Filament/Resources/Employees/` — baseline admin (Resource, Form, Table, Pages).
- `app/Livewire/MyEmployees.php` + `routes/web.php` (`my-staff*`) — pola portal list karyawan yang sudah jalan.
- `resources/views/components/nav.blade.php` — nav desktop, dropdown Kepegawaian existing.
- `resources/views/components/pwa-bottom-nav.blade.php` — nav mobile, BELUM ada gate Kepegawaian sama sekali.
- `config/portal-pages.php` — registry akses route portal.
- `knowledge/changes/2026-07-19-jadwal-shift-rotasi-detail-penjadwalan-dan-permission-kepegawaian.md` §6 — histori permission `sistem_kepegawaian` dibuat untuk apa awalnya (Izin/Cuti/Lembur).
- `DESIGN.md` § Responsive requirement — pola wajib list/detail (Filament Table desktop vs card mobile).
