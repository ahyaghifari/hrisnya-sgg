# Redesign Coaching & Counseling (2026-07-26)

Dokumen ini merangkum satu rangkaian kerja panjang dalam sesi yang sama: dari sekadar memperbaiki nama sender email slip gaji, berkembang jadi audit menyeluruh modul Coaching & Counseling (yang sebelumnya masih banyak placeholder/gap, lihat `planning/2026-07-18-portal-coaching-counseling.md` untuk rencana awal), lalu redesign penuh: konsep Konselor bebas pilih, kerahasiaan ketat, restrukturisasi nav & URL, upload bukti pelaksanaan, status per-bukti, notifikasi, sampai permission baru. Ditulis supaya sesi berikutnya bisa lanjut tanpa gali ulang percakapan.

## Ringkasan Keputusan (kalau cuma sempat baca 1 bagian)

1. **Coaching dan Counseling punya model akses BEDA, sengaja tidak disamakan** — Coaching soal struktur bawahan, Counseling soal kerahasiaan personal. Jangan coba unifikasi dua gate ini jadi satu aturan, itu sudah dicoba dan dikoreksi user di sesi ini.
2. **Konselor bisa siapa saja** (karyawan menunjuk sendiri rekan kerja mana pun di company yang sama saat submit "Konsul") — bukan role/permission tertentu.
3. **Kerahasiaan Counseling ketat sungguhan**: cuma `user_id` (yang konsul) dan `counselor_id` yang boleh baca isi keluhan/tanggapan — **Super Admin TIDAK bypass otomatis**, harus benar-benar salah satu dari dua pihak itu (lihat juga `[[shield-super-admin-tidak-bypass-otomatis]]` di memory, prinsip sama).
4. **URL direstrukturisasi**: `/coaching` + `/counseling` = personal (punya sendiri). `/pembinaan/coaching*` + `/pembinaan/counseling*` = sisi coach/atasan/konselor. Route NAME tidak berubah, cuma path-nya, jadi semua `route()` call otomatis ikut.
5. **`CoachingDetailPage` satu halaman, tiga peran** (coach, atasan/HC, karyawan yang dibina) — bukan tiga halaman terpisah. Ini keputusan eksplisit user ("kenapa ngga menggunakan halaman detail yang sama") setelah sempat dibikinkan halaman baru duluan.
6. **Status per-bukti (`CoachingProof.status`) sempat disederhanakan lalu DIKEMBALIKAN** — riwayat penuh: lihat §4.
7. **Permission baru `COACHING_KARYAWAN_LAINNYA`** — company-wide coaching access buat role kayak HC yang bukan atasan siapa-siapa di struktur organisasi.
8. **4 notifikasi baru** ditambahkan (`coaching_assigned`, `coaching_proof_submitted`, `coaching_review_decided`, `counseling_received`) — sebelumnya modul ini sama sekali tidak punya notifikasi.
9. **Bug lingkungan yang tidak berhubungan tapi ketemu di jalan**: `public/storage` symlink basi (nunjuk ke path project lama), bikin upload berhasil tapi gambar 404 — sudah diperbaiki, lihat README Known Gaps.

## 1. Coaching — Dua Jalur Akses Beda Scope

| Jalur | Syarat | Scope data |
|---|---|---|
| **Company-wide** | Permission Shield `ViewAny:Coaching`/`Create:Coaching`/`View:Coaching` **ATAU** custom `COACHING_KARYAWAN_LAINNYA` | Semua karyawan di company |
| **Subordinate-only** | Sekadar `canAccessKaryawanMenu()` (= `hasSubordinates()`, gate SAMA PERSIS MyStaff/Riwayat Approval) | Cuma `OrgHierarchyService::subordinates()` UNION `coach_by = diri sendiri` |

Kenapa dua jalur: awalnya nav row 2 "Coaching & Counseling" digerbangi permission Shield doang (`SISTEM_COACHING_COUNSELING`). User lalu eksplisit minta disamakan dengan MyStaff/Riwayat Approval ("punya bawahan"). Tapi kalau nav-nya cuma "punya bawahan", role HC (yang biasanya bukan atasan siapa-siapa secara struktur org) kehilangan akses — makanya permission `COACHING_KARYAWAN_LAINNYA` dibuat sebagai jalur company-wide alternatif yang independen dari permission Shield `ViewAny:Coaching` (supaya bisa di-assign terpisah tanpa perlu buka semua permission CRUD Coaching lainnya).

**Implementasi `hasFullAccess`** (pola sama di 3 file): `CoachingPage`, `CreateCoachingPage`, `CoachingDetailPage` masing-masing punya computed property yang OR-kan kedua kondisi permission itu. Query/dropdown/validasi server-side semua cek flag ini, BUKAN cuma nav visibility — jadi tidak ada dead link (klik dari nav lalu 403, atau sebaliknya bisa akses tapi nav-nya hilang).

**Filter perusahaan** — sempat jadi bug: `getCanBrowseAllCompaniesProperty()` awalnya cuma cek `canFilterByCompany()` (permission `LIHAT_PERUSAHAAN_LAIN`, independen dari Coaching), jadi subordinate-only user yang KEBETULAN juga punya `LIHAT_PERUSAHAAN_LAIN` bisa buka dropdown company tapi baseQuery tetap scoped ke bawahan → pilih company lain jadi dead-end (list kosong). Fix: `canBrowseAllCompanies = hasFullAccess && canFilterByCompany()` — dua-duanya harus benar.

### 1.1 Alur Status

```
IN_PROGRESS (dibuat coach/HC)
    │  karyawan upload bukti pelaksanaan
    ▼
SUBMITTED
    │  coach Approve/Reject
    ├──────────────┐
    ▼              ▼
COMPLETED      REJECTED ──► karyawan upload ULANG (canUpload masih true) ──► SUBMITTED lagi
```

`PENDING` di `CoachingStatus` enum **dead/tidak pernah dipakai** — baik `CreateCoachingPage` (portal) maupun `CreateCoaching` (admin Filament) langsung set `IN_PROGRESS` saat create, skip `PENDING` sama sekali. Action admin "Mulai Coaching" (`EditCoaching.php`, gated `status === PENDING`) jadi ikut tidak pernah bisa dipencet — dicatat, sengaja tidak dihapus di sesi ini (di luar scope).

### 1.2 Status Per-Bukti — Riwayat Keputusan (Penting, Jangan Diulang Bolak-Balik)

Ini satu-satunya bagian yang berubah arah **dua kali** dalam sesi ini:

1. **Awalnya**: `CoachingProof.status` ikut di-mutate saat approve/reject (`SUBMITTED → COMPLETED/REJECTED`), grouping ditampilkan per-tanggal-per-status (nested).
2. **Disederhanakan** (user: "kenapa ngga main di status coaching-nya aja"): `approve()`/`reject()` cuma ubah status `Coaching`, TIDAK lagi sentuh `CoachingProof.status` — bukti jadi kumpulan permanen tanpa status sendiri, ditampilkan flat per-tanggal saja.
3. **Dikembalikan lagi** (user: "kembalikan lagi konsep per bukti per status, tapi keterangannya di bawah gambar langsung"): `approve()`/`reject()` KEMBALI mutate `CoachingProof.status`, tapi tampilannya bukan group-header terpisah — cukup keterangan kecil di bawah tiap foto (`{tanggal} · {status}`), tidak ada grouping sama sekali.

**State final (yang berlaku sekarang)**: `CoachingService::approve()`/`reject()` update `Coaching.status` DAN `CoachingProof.status` (cuma yang masih `SUBMITTED`, riwayat lama yang sudah `REJECTED` sebelumnya tidak ikut disentuh ulang). `Coaching::getGroupedProofsAttribute()` cuma group by tanggal (bukan tanggal+status lagi) — dipakai admin Filament (`CoachingForm.php`, nested `RepeatableEntry`). Portal blade (`coaching-detail-page.blade.php`) malah tidak pakai grouping sama sekali, langsung iterasi `$record->proofs` flat, tiap foto tampilkan tanggal+status di bawahnya.

**3 tempat yang harus konsisten kalau field ini disentuh lagi**:
- `App\Services\Coaching\CoachingService::approve()`/`reject()` — mutate proof status.
- `resources/views/livewire/coaching/coaching-detail-page.blade.php` — caption per foto.
- `app/Filament/Resources/Coachings/Schemas/CoachingForm.php` (`editSchema()`, nested `RepeatableEntry::make('proofs')`) — badge status per foto di admin.
- `App\Http\Resources\Coaching\CoachingProofResource` — expose `status` ke mobile API juga (buat parity, walau mobile belum ada UI approve/reject-nya sendiri).

## 2. Counseling — Konselor Bebas, Kerahasiaan Ketat

**Beda total dari Coaching**: konselor **BISA SIAPA SAJA** (`User::where('company_id', ...)->where('id', '!=', Auth::id())->where('is_active', true)`, dipilih sendiri oleh karyawan yang konsul), tidak ada hubungannya sama struktur organisasi/bawahan sama sekali. Ini sebabnya nav row 2 Counseling **tidak** ikut digerbangi "punya bawahan" — karyawan biasa yang ditunjuk jadi konselor akses inbox-nya lewat tombol **"Konsul ke Saya"** di halaman personal `/counseling` sendiri (`CounselingPage::getShowInboxButtonProperty()` — muncul kalau `! canAccessKaryawanMenu()` DAN ada minimal 1 counseling `status = PENDING` yang `counselor_id`-nya dia).

### 2.1 Kerahasiaan (`Counseling::isVisibleTo()`)

```php
public function isVisibleTo(?string $userId): bool
{
    return $userId !== null && ($userId === $this->user_id || $userId === $this->counselor_id);
}
```

Dipakai di:
- `CounselingDetailPage::mount()` — `abort_unless($record->isVisibleTo(Auth::id()), 403)`. TIDAK ADA lagi gate `View:Counseling` Shield permission di sini.
- `CounselingManagePage::baseQuery()` — selalu `where('counselor_id', Auth::id())`, tidak peduli permission apa pun.
- Admin Filament `CounselingsTable` — kolom/field `issue`/`response`/`solution` di-redact jadi **"🔒 Rahasia"** kalau `Auth::id()` bukan `user_id`/`counselor_id`nya record itu (helper `self::redacted()`). Tombol respond (`EditAction`) cuma visible kalau `Auth::id() === counselor_id`.
- `config/portal-pages.php` untuk `counseling.kelola`/`counseling.kelola.show` — `permission => null`, biar middleware `EnsurePortalPageAccess` tidak nge-block sebelum sempat sampai ke gate asli di komponen.

**Kenapa ini penting diingat**: kalau nanti ada yang mau kasih Super Admin/HC akses "lihat semua counseling buat monitoring", itu BUKAN sekadar assign permission Shield — perlu keputusan sadar untuk mengubah `isVisibleTo()` sendiri, karena semua gate di atas nempel ke method itu.

### 2.2 Anti-Konsul-Diri-Sendiri

Dropdown UI sudah exclude diri sendiri, tapi payload Livewire/API bisa ditembak manual — ditambah `Rule::notIn([Auth::id()])` di:
- `CounselingPage::submitConsult()` (portal)
- `App\Http\Requests\Counseling\StoreCounselingRequest` (mobile API)

### 2.3 Status

Cuma 2: `pending` ("Menunggu Respon" — sebelumnya "Tertunda", diganti karena "Tertunda" ambigu kesannya sengaja ditunda) dan `done` ("Selesai").

## 3. Notifikasi Baru

Sebelumnya modul ini nihil notifikasi — sekarang ikut pola yang sudah ada (`database` + `WebPushChannel`, gated `NotificationPreferenceService::isEnabled()`, lihat `knowledge/markdown/manajemen-notifikasi.md` untuk mekanisme umumnya):

| Enum case | Class | Ke siapa | Kapan |
|---|---|---|---|
| `coaching_assigned` | `App\Notifications\CoachingAssigned` | Karyawan yang dibina (`user_id`) | `CoachingService::create()` |
| `coaching_proof_submitted` | `App\Notifications\CoachingProofSubmitted` | Coach (`coach_by`) | `CoachingService::update()`/`uploadProofs()` |
| `coaching_review_decided` | `App\Notifications\CoachingReviewDecided` | Karyawan yang dibina | `CoachingService::approve()`/`reject()` |
| `counseling_received` | `App\Notifications\CounselingReceived` | Konselor (`counselor_id`) | `CounselingService::create()` |

`CounselingReceived` **sengaja tidak menyertakan isi keluhan** di body notifikasi (rahasia) — cuma bilang "ada yang perlu ditanggapi".

## 4. Permission Baru: `COACHING_KARYAWAN_LAINNYA`

`App\Enum\CustomPermissionEnum::COACHING_KARYAWAN_LAINNYA` (`coaching_karyawan_lainnya`) — otomatis ke-seed lewat `CustomPermissionsSeeder` yang sudah ada (tidak perlu seeder baru). Ditaruh sebagai anak dari grup `SISTEM_COACHING_COUNSELING` di `RoleForm::portalGroups()`, jadi muncul nested di section "Sistem Coaching & Counseling" pada form Role (`/admin/roles`).

**Efek**: siapa pun yang di-assign permission ini dapat akses `hasFullAccess` (company-wide) di `CoachingPage`/`CreateCoachingPage`/`CoachingDetailPage` — sama seperti punya `ViewAny:Coaching`, tapi lewat permission yang lebih spesifik/independen dari Shield.

## 5. Route Restructuring

```
Personal (punya sendiri):
  GET /coaching              coaching.saya       MyCoachingPage (list)
  GET /coaching/{record}     coaching.show       CoachingDetailPage (3 peran, lihat §1)
  GET /counseling            counseling          CounselingPage

Sisi coach/atasan/konselor:
  GET /pembinaan/coaching            coaching           CoachingPage (list)
  GET /pembinaan/coaching/tambah     coaching.create    CreateCoachingPage
  GET /pembinaan/coaching/{record}   coaching.show      CoachingDetailPage (SAMA route/component dgn personal — gate di dalam yang bedain peran)
  GET /pembinaan/counseling          counseling.kelola       CounselingManagePage
  GET /pembinaan/counseling/{record} counseling.kelola.show  CounselingDetailPage
```

Route NAME sengaja tidak berubah waktu prefix `/pembinaan` ditambahkan — semua `route()` call di blade/component otomatis ikut tanpa perlu disentuh satu-satu.

**Penting**: prefix `/pembinaan` itu murni pengelompokan URL, **BUKAN penanda otorisasi**. `CoachingDetailPage`/`CounselingDetailPage` yang dipakai dari kedua sisi (personal maupun `/pembinaan`) py gate sendiri yang tidak peduli lewat prefix mana halamannya diakses — lihat §1 dan §2.1.

## 6. Belum Dikerjakan / Known Gaps

Urutan bukan prioritas, cuma daftar apa adanya:

1. **Mobile API ketinggalan dari portal** — `Api/Coaching/CoachingController` & `Api/Counseling/CounselingController` cuma expose submit/lihat punya sendiri. Kalau karyawan ditunjuk jadi konselor atau coach, mobile app **tidak ada endpoint** buat lihat/balas — fitur itu cuma ada di portal web.
2. **`MyCoachingPage`** ("Coaching Saya", personal) belum ada filter status/search, beda dari `CoachingPage` (sisi coach) yang sudah ada.
3. **`CoachingPage::createSchema()` di admin Filament** masih scope "departemen sama" (`org_structure_id`), BEDA dari portal yang pakai `OrgHierarchyService::subordinates()` — dua aturan beda ini sudah dicatat dari planning awal (§4.1 `planning/2026-07-18-portal-coaching-counseling.md`), belum diselaraskan.
4. **`PENDING` di `CoachingStatus`** dead code (§1.1) — belum dihapus, termasuk action "Mulai Coaching" di `EditCoaching.php` yang jadi ikut tidak pernah bisa dipencet.
5. Belum ada UI buat lihat riwayat "siapa saja yang pernah saya jadi konselor-nya secara total" — cuma bisa lihat per-record lewat `/pembinaan/counseling` (list yang masih `pending` ATAU pernah `done`, tidak ada filter/pencarian tambahan di luar status+nama yang sudah ada).

## 7. Referensi Test

- `tests/Feature/Coaching/CoachingSubordinateAccessTest.php` — 2 jalur akses (subordinate vs `COACHING_KARYAWAN_LAINNYA`/permission), scope query, validasi `improvement_due_date`.
- `tests/Feature/Coaching/CoachingProofUploadTest.php` — upload bukti, resubmit setelah ditolak, status per-bukti, notifikasi `CoachingProofSubmitted`/`CoachingReviewDecided`.
- `tests/Feature/Counseling/CounselingConfidentialityTest.php` — kerahasiaan `isVisibleTo()`, anti-konsul-diri-sendiri, tombol "Konsul ke Saya", notifikasi `CounselingReceived`.

## 8. Referensi File Kode

- `app/Livewire/Coaching/{CoachingPage,CreateCoachingPage,CoachingDetailPage,MyCoachingPage}.php`
- `app/Livewire/Counseling/{CounselingPage,CounselingManagePage,CounselingDetailPage}.php`
- `app/Services/Coaching/CoachingService.php`, `app/Services/Counseling/CounselingService.php`
- `app/Models/Coaching/Coaching.php` (`getGroupedProofsAttribute()`), `app/Models/Counseling/Counseling.php` (`isVisibleTo()`)
- `app/Enum/{CoachingStatus,CounselingStatus,CustomPermissionEnum,NotificationTypeEnum}.php`
- `app/Notifications/{CoachingAssigned,CoachingProofSubmitted,CoachingReviewDecided,CounselingReceived}.php`
- `app/Filament/Resources/{Coachings,Counselings}/**`
- `app/Filament/Resources/Roles/Schemas/RoleForm.php` (`portalGroups()`)
- `config/portal-pages.php`, `resources/views/components/{nav,pwa-bottom-nav}.blade.php`
- `routes/web.php` (§5)
- `planning/2026-07-18-portal-coaching-counseling.md` — rencana awal (banyak yang sudah berubah, baca sebagai histori bukan state saat ini)
- `knowledge/markdown/manajemen-notifikasi.md` — mekanisme notifikasi umum (§3 dokumen ini nambah 4 case baru ke situ)
