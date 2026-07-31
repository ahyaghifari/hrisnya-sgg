# Planning: Portal Penilaian Karyawan (Assessment) — 2026-08-01

## 1. Latar Belakang

Modul Assessment (Penilaian Karyawan) sudah jadi modul nyata di admin panel Filament (`app/Filament/Clusters/Assessment/`) — full CRUD, kalkulasi skor otomatis (`AssessmentService::calculateUserAssessmentScore()`), skema data yang benar (`Types → Templates → Items berbobot → Assessments → AssessmentUsers → AssessmentScores`). Detail lengkap kondisi modul ini ada di `[[analisis-assessment-penilaian-karyawan]]` — baca itu dulu kalau butuh konteks penuh, dokumen ini fokus ke rencana portalnya saja.

**Tapi belum ada satu pun halaman portal karyawan** untuk modul ini. Dua masalah nyata:
1. Karyawan yang dinilai **tidak bisa lihat hasil penilaiannya sendiri** — cuma manager/HC yang buka `/admin` yang bisa lihat.
2. Atasan yang mau menilai bawahan harus masuk `/admin`, padahal role Supervisor/Manager sehari-hari beraktivitas di portal karyawan (`/dashboard`, `/my-staff`, dst), bukan Filament.

Sempat dibahas juga ide lebih besar (KPI engine terpusat lintas modul, jembatan Task→Assessment) — **keduanya sengaja TIDAK masuk scope dokumen ini**, itu arah jangka panjang yang belum diputuskan (lihat §7-8 di dokumen analisis). Yang dieksekusi di sini murni portalisasi fitur yang sudah ada, langkah kecil yang tidak bergantung pada keputusan besar itu.

## 2. Scope (disepakati sesi ini)

| Fitur | Termasuk? |
|---|---|
| Karyawan lihat hasil penilaian sendiri | ✅ |
| Atasan isi form penilaian bawahan dari portal | ✅ |
| Alur acknowledge/konfirmasi hasil oleh karyawan | ❌ (opsi ini di-skip user, kolom `status`/`submitted_at` tetap menganggur) |
| Perbaikan gap `AssesmentTypeResource`, `AssessmentRatingScales`, tipe MCQ/SIMPLE_SCALE | ❌ (topik terpisah, lihat §6 dokumen analisis) |

## 3. Pendekatan: reuse tab yang sudah ada, bukan halaman baru berdiri sendiri

Untuk "lihat riwayat", **manfaatkan tab yang sudah ada** di `App\Livewire\EmployeeAttendanceStatistics` — komponen yang sama dipakai di `/data-diri` (lihat data sendiri) dan `/my-staff/{id}` (atasan lihat bawahan), sudah punya pola multi-tab Alpine (`x-show="tab==='...'"`) dan guard `canManage()` yang membedakan "punya sendiri" vs "punya bawahan". Ini komponen yang sudah menampung tab serupa (presensi, dokumen, riwayat pengajuan) — Penilaian tinggal jadi tab baru, bukan bikin list+guard dari nol.

Untuk pengisian nilai (form kompleks: repeater indikator + orientation), satu halaman baru `/penilaian/{assessmentUser}` yang meniru mekanisme Filament `EditUserAssessment` — form yang sama dipakai untuk **mengisi** (`final_score` masih null, penilai = atasan langsung) maupun **melihat read-only** (sudah final, atau dilihat karyawan yang dinilai). Field otomatis disabled begitu `final_score` terisi — pola yang sudah ada di `UserAssessmentForm.php`, tinggal dicontek.

## 4. Perubahan

### 4.1 Extract shared logic ke Service (DRY, dipakai admin & portal)

`app/Services/Assessment/AssessmentService.php` — tambah method `ensureAssessmentScoresExist(AssessmentUsers $assessmentUser): void`, isinya persis logic yang sekarang ada di `EditUserAssessment::ensureAssessmentScoresExist()` (`firstOrCreate` baris `AssessmentScores` kosong untuk tiap `AssessmentTemplateItems` di template terkait).

`app/Filament/Resources/Assessment/UserAssessments/Pages/EditUserAssessment.php` — ganti isi method privat itu jadi panggil `app(AssessmentService::class)->ensureAssessmentScoresExist($this->record)`. Perilaku admin tidak berubah, cuma hilangkan duplikasi sebelum dipakai juga oleh portal.

### 4.2 Halaman isi/lihat nilai — `app/Livewire/AssessmentUserForm.php` (baru) + view

Route baru di `routes/web.php` (group portal yang sudah ada, `Route::middleware(['auth','portal.access'])`):
```php
Route::get('/penilaian/{assessmentUser}', AssessmentUserForm::class)->name('assessment.show');
```
Daftarkan di `config/portal-pages.php` dengan `permission: null, guard: null` — guard per-record dicek di dalam komponen sendiri (pola sama seperti entry `karyawan.show`).

Component logic:
- `mount(AssessmentUsers $assessmentUser)`:
  - `$isSelf = Auth::id() === $assessmentUser->target_id`
  - `$isManager = Auth::user()->canAccessKaryawanMenu() && app(OrgHierarchyService::class)->subordinates(Auth::user())->whereKey($assessmentUser->target_id)->exists()`
  - `abort_unless($isSelf || $isManager, 403)`
  - `$this->canEdit = $isManager && is_null($assessmentUser->final_score)` — atasan boleh isi selama belum final; karyawan sendiri & siapa pun setelah final = read-only.
  - Panggil `app(AssessmentService::class)->ensureAssessmentScoresExist($assessmentUser)` (idempotent).
  - Load `template.templateItems` (order `sort_order`) + `scores` existing, split jadi kelompok generik (`type === null`) vs orientation (`type === 'orientation'`) — persis pola Filament. Item `mcq`/`simple_scale` tidak dirender (gap yang sama seperti admin, bukan regresi baru — di luar scope perbaikan di sini).
- Form: plain Livewire public properties (bukan Filament form), pola persis `SubmitLeaveRequest.php` — `public array $scores` keyed by `template_item_id`.
- `rules()`: score numeric, min 0, max = `templateItem.max_score`.
- `submit()` (cuma jalan kalau `$canEdit`):
  - Loop tiap item → `AssessmentScores::updateOrCreate([...], [...])`.
  - Panggil `app(AssessmentService::class)->calculateUserAssessmentScore($assessmentUser->id)` — reuse penuh, tidak menulis ulang rumus skor.
  - Kirim notifikasi ke karyawan (§4.4).
  - `Notification::make()->success()->send()`, refresh record (form otomatis read-only karena `final_score` sekarang terisi).
- View blade: reuse class shared dari pola `submit-leave-request.blade.php` (`form-page-wrap`, `page-hero`, `form-card`, `form-label`, `form-input`). Footer tampilkan `total_weighted_score`/`final_score`/`final_category` kalau sudah final (badge warna sama seperti admin).

### 4.3 Tab baru di halaman detail karyawan — `app/Livewire/EmployeeAttendanceStatistics.php` + blade

Tambah section-nav item "Penilaian" (pola Alpine yang sudah ada).

- `getAssessmentsProperty()` — `AssessmentUsers::where('target_id', $record->id)->with(['assessments.template.assessmentType'])->latest()->get()`. Tiap baris link ke `route('assessment.show', $row->id)`. Status ditampilkan: "Menunggu diisi" (belum final, viewer bukan atasan) / "Belum dinilai — isi sekarang" (viewer = atasan & belum final) / skor+kategori (sudah final).
- Kalau `$this->canManage($record) && $record->id !== Auth::id()` (viewer = atasan, bukan lihat diri sendiri) — tampilkan juga `getAvailableAssessmentsToAssignProperty()`: `Assessments::where('company_id', $record->company_id)->where('status','active')->whereDoesntHave('userAssessments', fn($q) => $q->where('target_id', $record->id))->get()` + tombol per baris "Buat Penilaian" → method `createAssessmentUser(string $assessmentId)`:
  - Guard ulang `canManage`.
  - `AssessmentUsers::firstOrCreate(['assessment_id'=>..,'target_id'=>$record->id], ['created_by'=>Auth::id()])` — **pakai `firstOrCreate`**, bukan `create()` polos seperti relation manager admin, supaya tidak numpuk baris duplikat kalau tombol ke-klik dua kali (bug yang ada di admin, sengaja tidak dibawa ke portal).
  - `$this->redirect(route('assessment.show', $row->id), navigate: true)`.

### 4.4 Notifikasi ke karyawan saat hasil siap (kecil, boleh di-skip kalau mau scope lebih kecil)

- `app/Enum/NotificationTypeEnum.php` — tambah case `ASSESSMENT_RESULT_READY`.
- `app/Notifications/AssessmentResultReady.php` (baru) — mirror `CoachingAssigned.php` (constructor terima `AssessmentUsers`, `via()` cek `NotificationPreferenceService`, `toDatabase()`/`toWebPush()`).
- Dipanggil dari `AssessmentUserForm::submit()` setelah kalkulasi sukses: `$assessmentUser->target->notify(new AssessmentResultReady($assessmentUser))`.

## 5. Yang TIDAK dikerjakan (di luar scope sesi ini)

- Alur acknowledge/konfirmasi hasil oleh karyawan.
- Perbaikan `AssesmentTypeResource`, gap MCQ/SIMPLE_SCALE, sambungan `AssessmentRatingScales` — lihat §6 di `[[analisis-assessment-penilaian-karyawan]]`, topik terpisah.
- Tidak ada halaman/nav item baru berdiri sendiri untuk "Penilaian" — sengaja numpang di tab `EmployeeAttendanceStatistics` yang sudah ada, biar tidak nambah rute/menu yang tidak perlu.
- Ide KPI engine terpusat & jembatan Task→Assessment (§7-8 dokumen analisis) — arah jangka panjang, belum diputuskan, tidak masuk scope ini.

## 6. Verifikasi

Manual di browser (dev server, login sebagai 2 user: atasan & bawahan langsungnya):
1. HC bikin 1 `AssessmentTemplates` (kalau belum ada) + `Assessments` aktif via `/admin`.
2. Login sebagai atasan → `/my-staff/{bawahan}` → tab Penilaian → "Buat Penilaian" pada assessment aktif → isi nilai → submit → cek `final_score`/kategori muncul & field terkunci.
3. Login sebagai karyawan yang dinilai → `/data-diri` → tab Penilaian → lihat hasil (read-only) → cek notifikasi (§4.4) muncul di tempat yang menampilkan database notification.
4. Cek isolasi: login sebagai atasan lain (bukan langsung) coba akses `/penilaian/{id}` bawahan orang lain → harus 403.

Tidak ada test otomatis existing untuk modul Assessment (README Known Gaps: "modul lain selain portal attendance belum punya test"). Tambah 1 Feature test kecil untuk guard akses (`/penilaian/{id}` 403 untuk bukan-atasan-langsung, 200 untuk atasan & untuk diri sendiri) sebagai smoke check, mengikuti pola `tests/Feature/Presensi/ExtraShiftPageTest.php` yang sudah ada.

## 7. Status

**Belum dieksekusi** — ini rencana yang sudah disepakati strukturnya (lewat plan mode), menunggu sesi implementasi.
