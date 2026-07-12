# Planning: Menu "Keuangan" di Portal Karyawan (Komponen Gaji, Mapping, Insentif) — 2026-07-12

Status: **rencana, belum dieksekusi.** Lanjutan dari redesign payroll Filament sesi ini ([`planning/2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md`](2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md) + addendum-nya) — 3 Resource yang baru dibangun (`SalaryComponentResource`, `SalaryComponentAssignmentResource`, `IncentiveResource`) sekarang mau dibuka juga di **portal karyawan** (`routes/web.php`, bukan cuma `/admin`), supaya staf finance bisa akses tanpa masuk panel admin penuh.

## Kenapa

Sekarang cuma role `Admin`/`Super Admin` yang bisa masuk `/admin` (`User::canAccessPanel()`). Staf finance yang perlu urus komponen gaji/insentif tapi bukan admin panel penuh, jadi tidak punya akses sama sekali. Solusinya bukan kasih role Admin (kebanyakan akses), tapi buka 3 halaman spesifik ini di portal, digerbangi 1 permission baru.

## Keputusan Desain (sudah dikonfirmasi user)

1. **Permission baru `sistem_keuangan`** — permission tunggal/global (BUKAN format Shield `Verb:Subject` per-resource), dipakai sebagai gerbang akses ke ketiga halaman baru ini sekaligus. User **assign manual** ke role lewat halaman Roles yang sudah ada — tidak perlu default assignment otomatis di sini.
2. **2 item nav baru** di portal:
   - Dropdown **"Master Gaji"** — isinya 2 link: *Komponen Gaji* & *Mapping Komponen Gaji*.
   - Link **"Insentif"** — langsung, bukan dropdown.
3. **Reuse Filament Table + Form buat Komponen Gaji & Insentif** — pakai LANGSUNG `SalaryComponentsTable`/`SalaryComponentForm` dan `IncentivesTable`/`IncentiveForm` yang sudah ada, dibungkus Livewire component portal baru (bukan bikin ulang tabel/form dari nol). Otorisasinya juga reuse.
4. **Mapping Komponen Gaji beda** — TIDAK reuse struktur admin (`SalaryComponentAssignmentResource`: list ringkasan per komponen → klik Lihat → RelationManager). Itu didesain buat konteks admin panel (drill-down 2 halaman), kurang praktis buat portal. Perlu halaman baru yang lebih simpel — lihat §D untuk usulan.

## Riset Infrastruktur Portal yang Sudah Ada (wajib dipakai, jangan bikin pola baru)

### Pola reuse Filament Table di luar panel — sudah ada, bukan hal baru
`app/Livewire/MyEmployees.php` adalah contoh matang: Livewire component `implements HasTable, HasSchemas, HasActions` (+ trait `InteractsWithTable`/`InteractsWithSchemas`/`InteractsWithActions`), method `table(Table $table)` manggil `EmployeesTable::configure($table)` (Table class yang SAMA dipakai `/admin`), lalu di Blade cukup `{{ $this->table }}`. Guard akses dicek 2 lapis: middleware (route-level) + `abort_unless(...)` eksplisit di `mount()`.

### Middleware permission portal — sudah ada, TIDAK PERLU bikin middleware baru
`EnsurePortalPageAccess` (`app/Http/Middleware/EnsurePortalPageAccess.php`, alias `portal.access` di `bootstrap/app.php`) baca `config/portal-pages.php` per route name:
```php
'nama-route' => [
    'label' => '...',
    'permission' => 'nama_permission',   // $user->can(...), null = skip cek permission
    'guard' => 'namaMethodDiUserModel',  // $user->{guard}(), opsional
],
```
Route yang TIDAK terdaftar di registry ini otomatis lolos (cuma dijaga `auth`). Jadi bikin permission `sistem_keuangan` + isi `'permission' => 'sistem_keuangan'` di config ini untuk 3 route baru — **persis** fungsi "middleware permission" yang diminta user, infrastrukturnya sudah ada, tinggal didaftarkan.

### Nav — hardcoded, duplikat 2 tempat (bukan single source of truth)
`resources/views/components/nav.blade.php` (desktop) dan `resources/views/components/pwa-sidebar.blade.php` (mobile) masing-masing hardcode daftar link sendiri-sendiri, digate pakai variabel `@php $canX = auth()->user()->canX(); @endphp` lalu `@if ($canX) ... @endif`. Item baru harus ditambah manual di KEDUA file. Belum ada pola dropdown di nav utama (cuma avatar menu kanan atas yang pakai CSS class `.dropdown`/`.dropdown-menu` — bisa direuse buat dropdown "Master Gaji").

## Rencana Teknis

### A. Permission `sistem_keuangan`
Migration data-seed kecil (bukan `shield:generate`, karena ini bukan format Shield): pastikan 1 row `permissions` (`name = 'sistem_keuangan'`, `guard_name = 'web'`, `company_id = null`) ada. Otomatis masuk kategori "Dibuat Manual" di halaman `/admin/permissions` (sesuai aturan baru yang baru dibangun sesi ini — nama tanpa `:` = manual, editable/deletable manual, tidak disentuh `shield:generate`). Tidak ada default role assignment — user atur sendiri lewat `/admin/roles`.

### B. Route baru (`routes/web.php`, masuk grup `auth + portal.access` yang sudah ada)
```php
Route::get('/keuangan/komponen-gaji', SalaryComponentsPage::class)->name('keuangan.komponen-gaji');
Route::get('/keuangan/mapping-komponen-gaji', SalaryComponentMappingPage::class)->name('keuangan.mapping-komponen-gaji');
Route::get('/keuangan/insentif', IncentivesPage::class)->name('keuangan.insentif');
```
+ 3 entry baru di `config/portal-pages.php`, ketiganya `'permission' => 'sistem_keuangan'`.

### C. Livewire component: Komponen Gaji & Insentif (pola `MyEmployees.php`, paling murah)
2 component baru di `app/Livewire/Keuangan/` (folder baru, biar rapi terpisah dari component portal lain yang sudah ada):
- `SalaryComponentsPage.php` — `table()` manggil `SalaryComponentsTable::configure($table)` apa adanya (sudah punya Create/Edit modal + filter Perusahaan + guard hapus, tinggal pakai). `mount()` cek `abort_unless($user->can('sistem_keuangan'), 403)`.
- `IncentivesPage.php` — sama, reuse `IncentivesTable::configure($table)`.

Kedua Table class ini **sudah full CRUD modal** dari kerjaan sesi ini (bukan reuse View/Edit page terpisah) — jadi cocok dipakai langsung di portal tanpa penyesuaian, asalkan action `EditAction`/`CreateAction`/`DeleteAction` di dalamnya tidak menunjuk ke URL Filament panel (dicek dulu pas eksekusi — `SalaryComponentsTable` & `IncentivesTable` setahu saya semua modal, tidak ada `->url()` ke `/admin/...`, aman).

### D. Halaman khusus Mapping Komponen Gaji — USULAN (perlu dikonfirmasi user)

Desain admin (summary per komponen + drill-down RelationManager) dianggap terlalu berlapis buat portal. Usulan: **1 halaman flat**, mirip desain awal `CompanyAssignmentsTable` yang sempat dibangun lalu diganti (sebelum pivot ke summary+drilldown) — reuse pola query-nya (scope ke 1 company: assignment langsung ke company itu + ke pangkat-pangkatnya + ke jabatan-jabatannya, digabung 1 tabel):

- **Select Perusahaan** di atas (kalau user cuma boleh akses 1 company, auto-pilih & sembunyikan select-nya — pola sama kaya `MyEmployees::canBrowseAllCompanies()`).
- **1 tabel** di bawahnya: kolom Komponen / Peruntukan (Company langsung/Pangkat/Jabatan + nama) / Nilai, scoped ke company yang dipilih.
- **Create** (modal): pilih Komponen Gaji (opsinya cuma komponen milik company itu), Untuk (Company/Pangkat/Jabatan), Pilih (scoped ke company yang sama), Nilai.
- **Edit/Delete** per baris (modal), sama kaya pola `AssignmentsRelationManager` yang sudah ada tapi di-flatten jadi 1 halaman (tidak lewat drill-down "Lihat" dulu).

Ini pada dasarnya membangun ulang (versi lebih sederhana) dari apa yang sempat dibangun sebelum diganti jadi model ringkasan — bedanya sekarang PASTI dibutuhkan karena buat portal, bukan admin. Kalau desain ini oke, saya bangun `SalaryComponentMappingPage.php` (component baru, bukan reuse Table class Resource manapun — 100% baru, tapi konsisten style Filament Table biar tetap dalam 1 sistem).

**Pertanyaan buat user**: apakah desain flat-per-company ini sudah pas, atau ada bentuk lain yang dimaksud "biar mudah digunakan di halaman depan"?

### E. Nav — 2 tempat, digate `sistem_keuangan`

`nav.blade.php` (desktop) — dropdown baru pakai pola CSS `.dropdown`/`.dropdown-menu` yang sudah ada (dicontek dari avatar menu):
```blade
@if (auth()->user()->can('sistem_keuangan'))
    <div class="dropdown">
        <a href="#" class="nav-item">Master Gaji <i class="fa-solid fa-chevron-down text-xs"></i></a>
        <div class="dropdown-menu">
            <a href="{{ route('keuangan.komponen-gaji') }}" wire:navigate class="dropdown-item">Komponen Gaji</a>
            <a href="{{ route('keuangan.mapping-komponen-gaji') }}" wire:navigate class="dropdown-item">Mapping Komponen Gaji</a>
        </div>
    </div>
    <a href="{{ route('keuangan.insentif') }}" wire:navigate class="nav-item {{ request()->routeIs('keuangan.insentif*') ? 'active' : '' }}">Insentif</a>
@endif
```
`pwa-sidebar.blade.php` (mobile) — link yang sama (flat list, dropdown biasanya tidak perlu di mobile sidebar — 3 link langsung di bawah header "Master Gaji" kalau mau grouping visual), gate sama.

### F. Yang SENGAJA belum dikerjakan di rencana ini
- **Versi mobile card manual** (pola `MyEmployees` yang punya tampilan desktop-table + mobile-card terpisah) — belum diputuskan perlu atau tidak untuk 3 halaman ini. Default: desktop-only dulu (Filament Table sudah responsive sendiri, cuma tidak sepenuh "native app" kaya pola card portal lain). Bisa ditambah belakangan kalau user minta.
- **Default role assignment** buat permission `sistem_keuangan` — user assign manual sendiri.
- Halaman View detail terpisah (kaya `/my-staff/{id}`) — 3 halaman ini semua modal-based (Create/Edit/Delete di tempat), tidak ada halaman View/detail terpisah, konsisten sama Table class yang direuse.

## Urutan Eksekusi (kalau sudah dikonfirmasi)
1. Migration/seed permission `sistem_keuangan`.
2. 3 Livewire component (`Keuangan/SalaryComponentsPage`, `Keuangan/IncentivesPage`, `Keuangan/SalaryComponentMappingPage`) + Blade view masing-masing (cukup `{{ $this->table }}` buat 2 yang reuse, custom buat Mapping).
3. Route + entry `config/portal-pages.php`.
4. Nav (2 file, desktop + mobile).
5. Verifikasi: login sebagai user tanpa permission `sistem_keuangan` → 403 kalau akses langsung URL-nya, nav item tidak muncul. Assign permission ke 1 role test → ketiga halaman bisa diakses & CRUD-nya jalan sama seperti di `/admin`.

## Referensi
- `app/Livewire/MyEmployees.php` — pola reuse Table class di luar panel (dicontek buat §C).
- `app/Http/Middleware/EnsurePortalPageAccess.php` + `config/portal-pages.php` — infrastruktur gate permission portal yang direuse (§A, §B).
- `resources/views/components/nav.blade.php` + `resources/views/components/pwa-sidebar.blade.php` — nav yang perlu diedit (§E).
- `app/Filament/Resources/SalaryComponents/`, `app/Filament/Resources/Incentives/`, `app/Filament/Resources/SalaryComponentAssignments/` — Resource yang di-reuse/dijadikan referensi (dari sesi yang sama, lihat addendum `2026-07-12-redesign-payroll-simplifikasi-nama-dan-provenance.md`).
