# DCMS → HRIS: Fase 1 (Database Terpisah, Model+Admin+Portal)

> **Status: BATAL DIEKSEKUSI (2026-08-15)** — plan ini disetujui lalu eksekusi kode mulai jalan (config/database.php + .env sempat ditambah koneksi `dcms`), tapi dibatalkan user di tengah jalan sebelum migration/model/dsb dibuat. Semua perubahan file sudah di-revert bersih (`config/database.php`, `.env.example`, `.env` balik ke state semula). Dokumen ini disimpan sebagai referensi arah yang sudah dirancang & disetujui, buat dilanjutkan kapan-kapan — bukan mendeskripsikan state kode saat ini.

## Context

DCMS (meeting/notulensi/dokumen SOP) mau digabung ke HRIS ini secara kode (satu codebase Laravel, satu login), tapi **database tetap terpisah** (schema/database beda, server MySQL sama) — bukan menyatu ke database HRIS. Kode-nya juga digrup jelas di bawah namespace `Dcms` di setiap layer (Model/Service/Repository/Filament/Livewire) dan route-nya di file terpisah — supaya kalau suatu saat harus dicabut/dipisah lagi jadi service sendiri, jejaknya rapi dan minim tercecer ke file inti. Company mapping (§8 `knowledge/markdown/analisis-dcms-migrasi.md`) disepakati **dilewati** — tidak perlu diputuskan di fase ini.

Konsekuensi dari database terpisah (harus disadari, bukan cuma isolasi gratis):
- **Tidak ada FK constraint DB** ke `users`/`m_companies`/`m_org_structures` (beda schema/koneksi, MySQL gak bisa FK lintas database via Eloquent `->constrained()` yang biasa). Referential integrity jadi tanggung jawab application layer (Service validasi existence sebelum insert).
- **Eloquent relation LINTAS KONEKSI tetap jalan** — `belongsTo`/`hasMany` Eloquent secara default query terpisah (bukan SQL `JOIN` literal), jadi `DcmsMeeting::with('creator')` ke `User` (koneksi `mysql` default) tetap bisa lazy/eager load normal, asal modelnya set `$connection` masing-masing dengan benar. Yang TIDAK bisa: `Builder::join()` SQL langsung dan transaksi atomic lintas 2 database.
- Karena approval Document dipilih **flat kolom** (bukan numpang `ApprovalFlowService` core), tidak ada kebutuhan transaksi lintas-database sama sekali di fase ini — semua write DCMS tetap di 1 koneksi `dcms`.

Keputusan yang sudah diambil user di sesi ini:
- **Database**: koneksi baru `dcms` di `config/database.php`, host/port/kredensial server SAMA dengan HRIS, `DB_DATABASE` beda (schema baru, misal `hrisnya_dcms`).
- Approval Document: **flat `approved_by`/`approved_at`/`rejected_by`/`rejected_at`** — zero sentuhan ke `app/Enum/Approval/ApprovalModuleEnum.php` atau file core approval manapun.
- Scope kerja: **admin panel + portal karyawan sekalian** (bukan admin-only fase 1).
- **Navigasi portal row 1**: dropdown baru **"Rapat & Dokumen"** sejajar dropdown "Pembinaan Saya" existing (bukan masuk grup module dropdown row 2 kayak Keuangan/Kepegawaian/LMS) — isi 2 item: Rapat, Dokumen.
- Skema notulensi terstruktur & SOP-sebagai-Document — desain minimal diusulkan di sini, masih bisa direvisi (bukan keputusan final).

Prinsip desain: makin sedikit file di luar folder `Dcms`/`dcms.php`/nav yang disentuh, makin gampang dicabut nanti. Nav row 1 & permission enum tetap harus disentuh karena itu wiring UI, bukan coupling data.

## Konvensi yang Diikuti (dari eksplorasi codebase)

- **Models**: subfolder per domain sudah pola standar (`app/Models/{LMS,Payroll,Coaching,...}`) → pakai `app/Models/Dcms/*.php`.
- **Services/Repositories**: sama, `app/Services/Dcms/*.php`, `app/Repositories/Dcms/*.php`.
- **Filament Resources**: auto-discovered dari `app/Filament/Resources/` (`AdminPanelProvider.php:63`, `discoverResources`) — taruh di `app/Filament/Resources/Dcms/{DcmsMeetings,DcmsDocuments,...}/` dengan `$navigationGroup = 'DCMS'`. Tidak ada Cluster dipakai di project ini — tetap konsisten, jangan introduce Cluster.
- **Livewire portal**: `app/Livewire/Dcms/*.php`. **Penting**: pakai `Dcms` (bukan `DCMS` all-caps) sebagai nama folder/namespace — Livewire auto-kebab nama kelas jadi path view, `LMS` → `l-m-s` (jelek, sudah kejadian di project ini), `Dcms` → `dcms` (bersih). View di `resources/views/livewire/dcms/*.blade.php`.
- **Routes**: tidak ada pola `require` file terpisah yang existing (`web.php`/`api.php` monolitik, di-daftarkan lewat `bootstrap/app.php` `withRouting()`). Untuk `routes/dcms.php`: tambah **satu baris** `require __DIR__.'/dcms.php';` di dalam grup middleware `auth`+`portal.access` yang sudah ada di `web.php` (~baris 108) — itu satu-satunya sentuhan ke `web.php`.
- **Permission**: Shield auto-generate dari Filament Resource (`ViewAny/Create/Update/Delete:DcmsMeeting`, dst) — tidak perlu bikin custom `scopeAccess()` duplikat kayak DCMS lama.
- **Company scoping**: reuse trait `app/Models/Concerns/BelongsToCompany.php` (global scope otomatis filter `company_id` kecuali role Super Admin) — pasang di `DcmsMeeting`/`DcmsDocument`, tidak perlu logic scope custom baru.
- **Migration**: file migration DCMS taruh di folder terpisah `database/migrations/dcms/` (Laravel 12 support banyak path migration lewat `$app->booted()`/`Schema::defaultStringLength` config biasa, cukup daftar path tambahan di `bootstrap/app.php` atau jalankan `php artisan migrate --path=database/migrations/dcms`), tiap migration set `protected $connection = 'dcms';` di class-nya (atau pass `--database=dcms` ke command). Nama tabel TANPA prefix `dcms_` (gak perlu — udah di database terpisah, prefix jadi ganda/redundan): `meetings`, `meeting_participants`, `meeting_action_items`, `documents`, `document_categories`, `document_numberings`.
- **Connection config**: `config/database.php` tambah key `'dcms' => [...sama seperti 'mysql' tapi 'database' => env('DCMS_DB_DATABASE', 'hrisnya_dcms')...]`. `.env` tambah `DCMS_DB_DATABASE=hrisnya_dcms` (host/port/user/pass reuse variable `DB_HOST`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` yang sama, cukup 1 variable baru).
- **Referensi ke users/companies/org_structures**: kolom ULID polos (`char(26)`, tanpa `->foreignId()->constrained()`) — tidak ada FK constraint DB lintas koneksi. Validasi existence dilakukan di Service layer sebelum insert/update (query eksplisit ke `User::find($id)` dst, lempar exception kalau gak ketemu).
- **config/portal-pages.php**: registry route-name → permission/guard, dibaca middleware `EnsurePortalPageAccess` (alias `portal.access`). Route yang tidak terdaftar lolos tanpa gate (allowlist-of-restrictions) — tambah entry di sini cuma untuk route DCMS yang butuh gate permission, ini **satu file core yang disentuh** (tambah data, bukan ubah logic).

## Struktur File Baru

### Migrations (`database/migrations/dcms/`, koneksi `dcms`, prefix tanggal `2026_08_15_*`)
1. `create_document_categories_table` — id, name, prefix, company_id (ULID polos, no FK)
2. `create_document_numberings_table` — id, category_id (FK lokal ke `document_categories`, sama koneksi jadi FK ini AMAN dipasang), company_id, org_structure_id (ULID polos, nullable), year, last_number
3. `create_documents_table` — id, code_number, title, description, document_type enum(file/form), status enum(draft/pending_review/approved/rejected/archived), file_path, content, category_id (FK lokal), company_id, org_structure_id (ULID polos, nullable), created_by (ULID polos), approved_by/approved_at, rejected_by/rejected_at, SoftDeletes
4. `create_meetings_table` — id, title, doc_number, agenda, content, file_path, attachments(json), date_time, location, status enum(scheduled/completed/cancelled), company_id (ULID polos), org_structure_id (ULID polos, nullable), created_by, notulis_id (ULID polos)
5. `create_meeting_participants_table` — id, meeting_id (FK lokal ke `meetings`), user_id (ULID polos), attendance
6. `create_meeting_action_items_table` — id, meeting_id (FK lokal), item (text), pic_user_id (ULID polos, nullable), due_date (nullable), is_decision (bool) — **skema notulensi terstruktur minimal**, ganti pola DCMS lama (1 RichEditor bebas). Field `content` tetap ada di `meetings` buat narasi bebas, action item jadi baris terpisah biar bisa di-query/di-assign PIC. Tabel `meeting_minutes`/`document_templates` DCMS lama **tidak diporting** (dead, sudah dikonfirmasi 0 baris).

FK cuma dipasang ANTAR tabel DCMS sendiri (sama koneksi `dcms`, aman). Kolom yang nunjuk ke `users`/`m_companies`/`m_org_structures` (beda koneksi) ULID polos tanpa constraint, divalidasi di Service layer.

### Models (`app/Models/Dcms/`)
`DcmsMeeting`, `DcmsMeetingParticipant`, `DcmsMeetingActionItem`, `DcmsDocument`, `DcmsDocumentCategory`, `DcmsDocumentNumbering` — semua set `protected $connection = 'dcms';` + `HasUlids`, `DcmsMeeting`/`DcmsDocument` pakai trait `BelongsToCompany` (aman dipasang apa adanya — `company_id` tetap kolom lokal di tabel `dcms`, cuma isinya ULID dari koneksi lain tanpa FK). `belongsTo(User::class)` (creator/notulis/PIC/approver) tetap didefinisikan normal — Eloquent resolve lewat koneksi `User` sendiri (`mysql`), lintas koneksi otomatis jalan asal bukan `Builder::join()`.

### Services/Repositories (`app/Services/Dcms/`, `app/Repositories/Dcms/`)
- `MeetingService`/`MeetingRepository` — CRUD + assign participant + action item
- `DocumentService`/`DocumentRepository` — CRUD + numbering generation (benerin bug DCMS lama: field numbering harus beneran generate dari `DcmsDocumentNumbering`, bukan hardcode string `'AUTO-GENERATED'`) + transisi status submit-for-review → approve/reject beneran jalan (bukan dead status kayak DCMS lama)

### Filament Admin (`app/Filament/Resources/Dcms/`)
`DcmsMeetings/`, `DcmsDocuments/`, `DcmsDocumentCategories/` — masing-masing `Resource.php` + `Pages/` + `Schemas/` + `Tables/` (pola sama persis LMS/Incentives — CRUD lewat modal `ViewAction`/`EditAction`/`DeleteAction`, tanpa halaman Create/Edit terpisah, contoh `app/Filament/Resources/Incentives/`). `$navigationGroup = 'DCMS'`.

### Portal Livewire (`app/Livewire/Dcms/`, route `routes/dcms.php`)
- `/rapat` — daftar meeting milik/company user (list + detail, lihat notulen + action item)
- `/dokumen` — daftar dokumen/SOP (list + detail + submit-for-review kalau creator, approve/reject kalau approver)
- Entry `config/portal-pages.php` untuk kedua route ini, gate ke permission Shield hasil auto-generate (`ViewAny:DcmsMeeting`, `ViewAny:DcmsDocument`).

**Nav row 1 — dropdown "Rapat & Dokumen"** (pola sama persis dropdown "Pembinaan Saya" existing di `resources/views/components/nav.blade.php:44-54`):
```blade
@if ($canAccessRapatDokumen)
    <div class="dropdown">
        <a href="#" class="nav-item {{ request()->routeIs(['rapat*', 'dokumen*']) ? 'active' : '' }}">
            <i class="fa-solid fa-file-lines text-xs"></i> Rapat & Dokumen <i class="fa-solid fa-chevron-down text-[10px]"></i>
        </a>
        <div class="dropdown-menu">
            <a href="{{ route('rapat') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-calendar-days"></i> Rapat</a>
            <a href="{{ route('dokumen') }}" wire:navigate class="dropdown-item"><i class="fa-solid fa-folder-open"></i> Dokumen</a>
        </div>
    </div>
@endif
```
Sentuhan file yang perlu (murni UI wiring, bukan coupling data — dicatat eksplisit sebagai pengecualian isolasi):
- `app/Enum/CustomPermissionEnum.php` — 1 case baru `RAPAT_DOKUMEN_SAYA` (pola sama `TUGAS_SAYA`/`PELATIHAN_SAYA`).
- `resources/views/components/layout.blade.php` — 1 baris gate baru `'canAccessRapatDokumen' => auth()->user()->can(...)` di array `$navGates` (~baris 15-18).
- `resources/views/components/nav.blade.php` — dropdown di atas, ditaruh sejajar "Pembinaan Saya" (baris ~44-54).
- `resources/views/components/pwa-bottom-nav.blade.php` — padanan mobile, taruh di sheet "Penugasan" (bareng Tugas/Pelatihan, baris ~64-70) sebagai item ke-3, bukan bikin sheet baru.

### Artisan command (`app/Console/Commands/`)
`DcmsMatchUsersCommand` (`php artisan dcms:match-users {--dry-run}`) — baca DB DCMS lama (koneksi ketiga di `config/database.php`, read-only, beda lagi dari koneksi `dcms` yang baru), match ke `users` HRIS by `email` (utama), fallback nama+company kalau perlu, output tabel hasil match/tidak-match ke console. **Ini bukan bagian dari alur aplikasi** — command sekali-jalan buat data migration, dijalankan manual pas eksekusi migrasi data nanti (bukan bagian rilis fitur DCMS).

### Fix keamanan/bug yang diporting sekaligus (bukan dibawa dari DCMS lama)
- Widget kalender meeting (kalau ada drag-reschedule serupa `MyCalendarWidget` DCMS): wajib ada `authorize('update', $meeting)` sebelum reschedule.
- Document numbering & status transition beneran jalan (lihat Services di atas).

## Yang TIDAK disentuh (menjaga isolasi data)
- `app/Enum/Approval/ApprovalModuleEnum.php` — tidak ada case baru (approval Document pakai kolom flat sendiri).
- `config/database.php`/`database/migrations/` HRIS existing — tidak ada migration baru yang nulis ke koneksi `mysql` default, semua tabel DCMS di koneksi `dcms`.
- Tidak ada trait/gate custom baru di luar `Dcms` namespace — reuse `BelongsToCompany` existing apa adanya.

## Yang disentuh di luar folder Dcms (wiring UI, disengaja & minimal)
- `web.php` — 1 baris `require __DIR__.'/dcms.php';`.
- `config/database.php` — 1 key koneksi `dcms` baru.
- `config/portal-pages.php` — 2 entry route (`rapat`, `dokumen`).
- `app/Enum/CustomPermissionEnum.php` — 1 case baru (`RAPAT_DOKUMEN_SAYA`).
- `layout.blade.php`/`nav.blade.php`/`pwa-bottom-nav.blade.php` — gate + dropdown nav row 1 (lihat § Portal Livewire).

## Verifikasi
- Schema `hrisnya_dcms` dibuat manual dulu di MySQL server yang sama (`CREATE DATABASE hrisnya_dcms;`) sebelum migrate — Laravel gak auto-create database.
- Migration jalan bersih ke koneksi terpisah: `php artisan migrate --path=database/migrations/dcms --database=dcms`, cek tabel HRIS existing di database default TIDAK berubah.
- Filament Resource muncul di sidebar `/admin` grup "DCMS", CRUD dasar jalan (create/edit/delete Meeting & Document lewat panel), data beneran nulis ke `hrisnya_dcms` bukan database HRIS (cek langsung lewat `mysql`/Tinker `DB::connection('dcms')->table('meetings')->count()`).
- Eager-load lintas koneksi: buka detail Meeting, pastikan nama creator/notulis (dari `users` di koneksi `mysql`) tampil benar tanpa error "table not found"/N+1 yang gagal resolve koneksi.
- Shield permission ke-generate otomatis (`php artisan shield:generate` atau cek `/admin/roles` ada entry `*:DcmsMeeting`/`*:DcmsDocument`).
- Portal: dropdown "Rapat & Dokumen" muncul di nav row 1 desktop & mobile cuma buat user dengan permission `RAPAT_DOKUMEN_SAYA`, `/rapat` & `/dokumen` diakses normal, user tanpa permission gak lihat dropdown-nya & ditolak kalau akses URL langsung (cek `config/portal-pages.php` + `EnsurePortalPageAccess`).
- `BelongsToCompany` scoping: user company A tidak bisa lihat Meeting/Document company B (kecuali Super Admin) — meski `company_id` gak ada FK, filter query tetap jalan karena kolomnya lokal.
- `php artisan dcms:match-users --dry-run` jalan tanpa error terhadap koneksi DB DCMS LAMA (koneksi ketiga, beda lagi dari `dcms` yang baru — butuh kredensial DB lama dulu; kalau belum tersedia di environment ini, cukup verifikasi command ke-register & validasi struktur query-nya, eksekusi sungguhan nanti pas data migration beneran dilakukan).

## Catatan eksekusi (2026-08-15, dibatalkan)
Sempat mulai eksekusi: `config/database.php` (tambah koneksi `dcms`) + `.env.example`/`.env` (tambah `DCMS_DB_DATABASE`) sudah ditulis, lalu ketahuan environment eksekusi ini gak punya `php`/`mysql` CLI buat verifikasi (`command not found`) — sebelum lanjut ke migration/model/dst, user memutuskan batalkan dulu. Semua 3 file itu sudah di-revert ke state semula (`git checkout` buat yang tracked, manual revert buat `.env`). Belum ada migration/model/service/Filament Resource/Livewire/route DCMS apapun yang benar-benar dibuat — restart dari nol persis sesuai struktur file di atas kalau mau lanjut.
