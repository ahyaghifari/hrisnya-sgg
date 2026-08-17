# Fitur "Ajukan Tukar Shift" (Portal Karyawan)

## Context

Backend buat tukar shift udah lengkap & teruji (dibangun 2026-08-14/15):
- `App\Services\Shift\ShiftSwapRequestService::store()` — bikin pengajuan (mode `schedule` tukar 2 jadwal, atau `off_day` serahkan shift ke rekan yang libur), validasi bulan sama + tidak bentrok + kompatibilitas shift (pagi/siang bisa saling tukar, malam cuma sesama malam).
- `App\Services\Approval\ApprovalFlowService::advanceStep()` — approve BENERAN nge-swap `ScheduleShiftAssignment` (re-validasi konflik + handle kasus tanggal sama vs beda), reject nandain ditolak. Notifikasi generik (`ApprovalNeedsAction`/`RequestStatusChanged`) otomatis jalan, tidak perlu kerjaan tambahan.
- `App\Enum\Approval\ApprovalModuleEnum::SWAP_SHIFT_REQUEST` sudah ada.

Yang kosong total: **UI-nya**. Tidak ada form ajukan, tidak ada halaman riwayat pengajuan sendiri, dan approver tidak lihat pengajuan ini di manapun (`PendingApprovalsCard` belum include modul ini). README.md yang bilang modul ini "orphan, belum ada advanceStep()" sudah usang — itu true per 2026-08-10, sudah diperbaiki 2026-08-14, catatan itu perlu diupdate juga.

Prasyarat non-kode: admin harus sudah setel **Alur Approval** (Master Approval Flow) untuk modul "Permintaan Tukar Shift" di company terkait — kalau belum, `store()` throw `RuntimeException` ("Tidak ada alur persetujuan aktif"). Ini setup admin biasa (menu Alur Approval existing), bukan kode baru.

## Bagian A — Form Ajukan (modal, nempel di bawah widget "Jadwal Shift Saya")

**Instruksi user soal entry point**: BUKAN halaman/route terpisah — tombol "Tukar Shift" ditaruh DI BAWAH widget kalender "Jadwal Shift Saya" di Dashboard (`resources/views/livewire/partials/home-cards.blade.php`, section yang barusan dikerjakan hari ini), klik tombol itu buka **modal** berisi form, bukan pindah halaman.

**File baru:**
- `app/Livewire/Presensi/ShiftSwapRequestModal.php` — komponen Livewire kecil KHUSUS buat modal ini (state: `showModal`, field form, dsb), di-embed langsung di `home-cards.blade.php` lewat `<livewire:presensi.shift-swap-request-modal />` tepat di bawah blok kalender shift (`@if ($this->canJadwalShiftSaya) ... @endif`, di luar `@endif` itu atau di dalamnya — sama gate `hasShiftSchedule()`).
- `resources/views/livewire/presensi/shift-swap-request-modal.blade.php` — tombol "Tukar Shift" (buka `showModal=true`) + markup modal (pola modal yang sudah ada di codebase, mis. modal `reviewingEnrollmentId` di `PendingApprovalsCard`/`pending-approvals-card.blade.php` — reuse struktur overlay+card yang sama, jangan bikin pola modal baru).

**Alur form di dalam modal (isi sama seperti sebelumnya, cuma wadahnya modal bukan halaman):**
1. Pilih jadwal shift MILIK SENDIRI yang mau ditukar — dropdown dari `ScheduleShiftAssignment::where('user_id', Auth::id())->where('date','>=',today())->whereNotNull('shift_type_id')->with('shiftType')->orderBy('date')` (skip yang off_day).
2. Pilih mode: `schedule` (Tukar dengan Jadwal Rekan) atau `off_day` (Serahkan ke Rekan yang Libur).
3. Cari rekan kerja — text input `wire:model.live` filter `name`/`nip` (pola sama `App\Livewire\Presensi\Concerns\HandlesShiftScheduleForm` baris ~181-188), scoped `company_id` sendiri, exclude diri sendiri.
4. Mode `schedule`: pilih salah satu jadwal shift rekan itu di bulan yang sama (`target_schedule_id`). Mode `off_day`: cukup pilih rekan (`target_user_off`).
5. Catatan (opsional).
6. Submit → `app(ShiftSwapRequestService::class)->store([...])` try/catch, sukses → `Notification::make()->success()->send()`, tutup modal (`$this->showModal = false`, reset field), TIDAK perlu redirect (masih di Dashboard). Gagal → `Notification::make()->danger()->send()`, modal tetap terbuka biar user bisa koreksi.
7. Tombol "Tukar Shift" cuma muncul kalau `Auth::user()->hasShiftSchedule()` (sejalan gate widget kalender di atasnya).

Halaman `/tukar-shift` (list riwayat, Bagian B) tetap ada sebagai halaman biasa — cuma titik MULAI pengajuannya yang jadi modal di Dashboard, bukan halaman `/tukar-shift/ajukan` terpisah (dihapus dari rencana).

## Bagian A2 — Tanggapan Rekan (opsional, TIDAK menggerbang alur approval)

**Instruksi user**: rekan yang jadwalnya mau ditukar (`target_id`) boleh dimintain tanggapan (terima/tolak), tapi sifatnya **tidak wajib** — alur approval atasan tetap jalan seperti biasa TANPA nunggu tanggapan itu. Jadi ini murni informasi tambahan buat approver ("rekannya sudah oke belum"), bukan gate/blocker baru. Toggle-nya `Setting` biasa (pola sama `AttendanceSettingEnum`) supaya gampang dimatikan/dinyalain tanpa ubah kode.

**Desain — sengaja TIDAK menyentuh `ShiftSwapRequestService::store()`/`createApprovalSnapshot()` sama sekali** (snapshot & alur approval tetap langsung dibuat pas submit, persis kode sekarang, zero perubahan ke jalur itu) — tanggapan rekan cuma nempel sebagai 2 kolom info + 1 aksi kecil yang berjalan PARALEL, independen dari approval engine:

1. **Migration**: 2 kolom nullable baru di `shift_swap_requests` — `target_response` (`enum('accepted','declined')` nullable) dan `target_responded_at` (`timestamp` nullable). TIDAK perlu ubah kolom `status` yang sudah ada (tidak ada state baru, `status` tetap murni cerminan approval snapshot seperti sekarang).
2. **Setting**: 1 case baru `AttendanceSettingEnum::ASK_TARGET_RESPONSE_TUKAR_SHIFT = 'ask_target_response_tukar_shift'` (type `BOOLEAN`, default `'false'`) — cuma ngatur APAKAH UI tanggapan ditampilkan/target dinotif, bukan ngatur approval sama sekali. Sengaja tidak ditambah ke UI `AttendanceSettingsPage` di PR ini (nyalain via 1 baris `Setting` insert dulu, toggle UI-nya belakangan kalau perlu).
3. **1 method baru di `ShiftSwapRequestService`**: `recordTargetResponse(ShiftSwapRequests $request, User $actor, string $response)` — `abort_unless($actor->id === $request->target_id)`, validasi `$response` in `['accepted','declined']`, update `target_response`+`target_responded_at`. Tidak menyentuh `status`, tidak memanggil `ApprovalFlowService` apapun — murni catatan.
4. **`store()`**: kalau setting ON, sesudah bikin snapshot (baris yang sudah ada, tidak diubah urutannya), kirim 1 Notification opsional ke target (`ShiftSwapTargetResponseRequested`, database+WebPush, pola sama notifikasi lain) — target boleh buka & kasih tanggapan kapan saja, atau diamkan saja, approval tetap jalan normal.
5. **UI — komponen/card KHUSUS sendiri** (instruksi user: bukan ditumpuk jadi tombol kecil di halaman detail, biar simple & gampang dipakai langsung tanpa pindah halaman):
   - **File baru** `app/Livewire/ShiftSwapTargetResponseCard.php` + view — pola persis `PendingApprovalsCard` tapi jauh lebih simple (1 query doang, tidak ada bulk/approval-engine sama sekali): query `ShiftSwapRequests::where('target_id', Auth::id())->whereNull('target_response')->with(['requester', 'reqSchedule.shiftType'])->get()`. Tiap baris = 1 card kecil: nama pemohon, tanggal+shift yang mau ditukar, 2 tombol besar "Setuju"/"Tolak" langsung di card itu (`wire:click="respond($id, 'accepted')"`/`respond($id, 'declined')`) — manggil `ShiftSwapRequestService::recordTargetResponse()` langsung, tanpa modal/tanpa pindah halaman, mirip pola `approveEnrollment()`/`rejectEnrollment()` yang sudah ada di `PendingApprovalsCard` (aksi 1-klik).
   - Query di komponen ini DICEK dulu ke setting: kalau `ASK_TARGET_RESPONSE_TUKAR_SHIFT` OFF, `getPendingResponsesProperty()` langsung balikin collection kosong (card tidak pernah render) — konsisten dengan closing note di atas ("setting OFF = tidak ada satupun perilaku yang berubah"). Kalau ON, baru query `target_id = Auth::id() AND target_response IS NULL`.
   - Ditaruh di Dashboard (`home-cards.blade.php`) — sejajar/dekat widget "Butuh Persetujuan Anda" (`PendingApprovalsCard`), tampil kalau ada baris yang perlu direspon (`$this->pendingResponses->isNotEmpty()`), gak perlu permission baru (cukup `hasShiftSchedule()` — kalau bukan karyawan shift, gak mungkin jadi target tukar shift juga).
   - `ShiftSwapRequestDetail` tetap tampilkan badge status "Rekan: Setuju/Menolak/Belum menanggapi" (read-only info buat requester & approver), tapi AKSI menanggapi (klik Setuju/Tolak) cuma ada di card khusus ini, bukan di halaman detail.

**Kalau setting OFF (default)**: tidak ada notifikasi ke target, tidak ada tombol tanggapan, halaman detail sama persis kayak tanpa fitur ini — alur approval 100% seperti kode hari ini, tidak ada satupun perilaku yang berubah.

## Bagian B — Riwayat & Detail Pengajuan Sendiri

**File baru:**
- `app/Livewire/Presensi/MyShiftSwapRequests.php` + view — list, mirip `app/Livewire/MyAttendanceRequests.php` (query `ShiftSwapRequests::where('requester_id', Auth::id())`, custom card mobile + table desktop, paginate).
- `app/Livewire/Presensi/ShiftSwapRequestDetail.php` + view — detail + approve/reject/cancel, mirip persis `app/Livewire/AttendanceRequestDetail.php`:
  - `mount(ShiftSwapRequests $record)`: load `['approvalSnapshot.activity','approvalHistories']`, gate `canAccess()` = requester ATAU target ATAU `ApprovalPermissionService::hasPermission($snapshot->id, $user)` ATAU `canBrowseAllCompanies()`.
  - `getCanApproveProperty()` → `ApprovalPermissionService::canApprove($snapshot->id, Auth::user())`.
  - `confirmAction()` → `ApprovalFlowService::advanceStep($record->approvalSnapshot, Auth::id(), $note, $action)`, sama persis pola `AttendanceRequestDetail::confirmAction()`.
  - Timeline → `ApprovalTimelineService::build($record)` + `<x-approval-history-list :histories="$record->approvalHistories" />` (component sudah ada, reusable, dipakai 3 modul lain).

  **Penting:** `ApprovalTimelineService::build()` generik tapi HARDCODE akses `$request->user` (baris 23 & 41 file itu) — `ShiftSwapRequests` model cuma punya `requester()`, bukan `user()`. Tambah 1 relasi alias di `app/Models/Shift/ShiftSwapRequests.php`:
  ```php
  public function user() { return $this->belongsTo(User::class, 'requester_id'); }
  ```
  (sejajar `requester()` yang sudah ada, cuma alias nama biar konsisten sama service generik — jangan ganti nama `requester()` yang sudah dipakai di tempat lain).

## Bagian C — Masuk ke "Butuh Persetujuan Anda"

**Edit `app/Livewire/PendingApprovalsCard.php`:**
- Di `buildRequests()`, tambah blok baru (pola sama persis blok `training_request`/`attendance_request`):
  ```php
  $shiftSwapRequests = collect();
  if ($this->wants('shift_swap_request')) {
      $swapIds = $permissionService
          ->pendingApprovalsFor(Auth::user(), ApprovalModuleEnum::SWAP_SHIFT_REQUEST->value)
          ->pluck('snapshot.module_id')->filter()->values();

      $shiftSwapRequests = ShiftSwapRequests::with(['requester', 'target', 'reqSchedule.shiftType', 'targetSchedule.shiftType'])
          ->whereIn('id', $swapIds)
          ->whereHas('requester')
          ->get()
          ->map(fn ($r) => (object) [
              'id' => $r->id,
              'dispatch' => 'shift_swap_request',
              'bulk_supported' => true,
              'module' => 'shift_swap_request',
              'badge' => 'blue',
              'label' => 'Tukar Shift',
              'icon' => 'fa-right-left',
              'tile' => 'neutral',
              'keterangan' => $r->reqSchedule?->shiftType?->name . ' (' . $r->req_date->translatedFormat('d M') . ') ' .
                  ($r->targetSchedule ? '↔ ' . $r->targetSchedule->shiftType?->name . ' (' . $r->target_date->translatedFormat('d M') . ')' : '→ diserahkan'),
              'user_name' => $r->requester->name,
              'created_at' => $r->created_at,
              'url' => route('tukar-shift.show', ['record' => $r->id]),
              'modal' => false,
              'external' => false,
          ]);
  }
  ```
  Sambungkan ke `$requests = $attendanceRequests->concat(...)->concat($shiftSwapRequests)->...`.
- Di `approveOne()`, tambah match arm:
  ```php
  'shift_swap_request' => app(ApprovalFlowService::class)->advanceStep(
      ShiftSwapRequests::findOrFail($row->id)->approvalSnapshot,
      Auth::id(),
      $note,
      'approved',
  ),
  ```
- Tambah `use App\Models\Shift\ShiftSwapRequests;` di import.

Tidak perlu ubah halaman manapun yang RESTRICT `$only` (Dashboard/MyStaud pakai `null` = semua modul, otomatis ke-include).

## Bagian D — Permission, Routes, Nav

**`app/Enum/CustomPermissionEnum.php`:** 1 case baru `TUKAR_SHIFT_SAYA = 'tukar_shift_saya'` (pola sama `PENGAJUAN_ABSENSI_SAYA`) + `->label()` match arm. **Tidak perlu** bikin `APPROVE_TUKAR_SHIFT` — dicek: `APPROVE_PENGAJUAN_ABSENSI` yang jadi acuan pola itu ternyata TIDAK dicek di kode manapun (cuma didaftarkan di enum, dead permission) — approve sudah digerbangi murni lewat `ApprovalPermissionService::canApprove()`/`hasPermission()` (org hierarchy + Master Approval Flow), tidak butuh permission Shield tambahan. Jangan replikasi pola mati itu.

**`routes/web.php`** (dalam grup middleware yang sama dengan `presensi.jadwal-saya`) — cuma 2 route (form ajukan sudah jadi modal di Dashboard, bukan halaman):
```php
Route::get('/tukar-shift', MyShiftSwapRequests::class)->name('tukar-shift');
Route::get('/tukar-shift/{record}', ShiftSwapRequestDetail::class)->name('tukar-shift.show');
```

**`config/portal-pages.php`** — 2 entry baru:
```php
'tukar-shift' => ['label' => 'Tukar Shift', 'permission' => CustomPermissionEnum::TUKAR_SHIFT_SAYA->value, 'guard' => null],
'tukar-shift.show' => ['label' => 'Detail Tukar Shift', 'permission' => CustomPermissionEnum::TUKAR_SHIFT_SAYA->value, 'guard' => null],
```
(Approver yang bukan requester tetap lolos lewat `canAccess()` di komponen — sama pola `ManpowerRequestDetailPage` yang sudah dicatat di README, gerbang blanket permission di depan + `canAccess()` di dalam buat kasus lain.)

**Nav** — `resources/views/components/nav.blade.php`, tambah 1 nav-item baris 1 (sejajar "Pengajuan Absensi"), gated `$canAccessTukarShift` (dihitung di `layout.blade.php` array `$navGates`: `auth()->user()->can(TUKAR_SHIFT_SAYA) && auth()->user()->hasShiftSchedule()`) + padanan mobile di `pwa-bottom-nav.blade.php` sheet "Penugasan" kalau relevan (opsional, ikuti pola `canAccessCoachingCounseling` yang baru dikerjakan hari ini).

## Bagian E — Test

- `tests/Feature/Presensi/ShiftSwapRequestModalTest.php` — submit sukses (mode schedule & off_day) dari komponen modal, validasi gagal (beda bulan, bentrok, shift tidak kompatibel — reuse skenario yang sudah ada di `tests/Feature/Shift/ShiftSwapRequestServiceTest.php`, cuma lewat Livewire bukan Service langsung), modal tertutup setelah sukses & tetap terbuka kalau gagal.
- `tests/Feature/Presensi/MyShiftSwapRequestsTest.php` — list cuma nampilin punya sendiri.
- `tests/Feature/Presensi/ShiftSwapRequestDetailTest.php` — akses ditolak buat orang tidak terkait, approve beneran nge-swap jadwal (assert `ScheduleShiftAssignment` ke-update), reject nandain status.
- `tests/Feature/PendingApprovalsCardTest.php` (kalau ada existing test filenya, tambah case baru) — modul `shift_swap_request` muncul buat approver yang berhak.
- `tests/Feature/Shift/ShiftSwapTargetResponseTest.php` — setting OFF (default): `store()` perilakunya identik kode sekarang (regresi-guard, snapshot langsung dibuat). Setting ON: snapshot TETAP langsung dibuat sama seperti OFF (buktikan approval tidak tergerbang), `recordTargetResponse()` cuma update kolom info tanpa menyentuh snapshot/status, approver tetap bisa `advanceStep()` approve/reject terlepas dari `target_response` masih null atau sudah diisi apa, orang selain target ditolak manggil `recordTargetResponse()`.
- `tests/Feature/Presensi/ShiftSwapTargetResponseCardTest.php` — setting OFF: card kosong/tidak render walau ada pengajuan target. Setting ON: card cuma nampilin pengajuan yang `target_id`-nya user login & `target_response` masih null, klik "Setuju"/"Tolak" langsung update kolom tanpa reload halaman, baris yang sudah direspon hilang dari card.

## Langkah eksekusi

0. Simpan dokumen plan ini ke `planning/2026-08-16-fitur-tukar-shift.md` (konvensi project, sejajar `planning/2026-08-15-adopsi-dcms-ke-hris.md` dkk) sebelum mulai coding.
1. Bagian A (modal) → A2 (tanggapan rekan + card) → B (list & detail) → C (PendingApprovalsCard) → D (permission/routes/nav) → E (test), urutan ini karena tiap bagian belakangan bergantung ke Model/Service yang disentuh bagian sebelumnya.

## Verifikasi

1. `php artisan test --filter=ShiftSwap` — semua test baru + `ShiftSwapRequestServiceTest.php` lama tetap hijau.
2. Manual: login user shift (mis. Ahmad Ripaini), buka Dashboard → di bawah widget "Jadwal Shift Saya" klik tombol "Tukar Shift" → modal terbuka → submit → cek muncul di `/tukar-shift`.
3. Login user lain yang jadi approver (harus ada Master Approval Flow modul Tukar Shift buat company itu) → cek muncul di widget "Butuh Persetujuan Anda" (Dashboard/MyStaff) → approve → cek `ScheduleShiftAssignment` kedua user beneran ketukar (lewat Tinker atau lihat kalender masing-masing).
4. Update catatan README.md § Known Gaps yang bilang modul ini "orphan" — sudah tidak akurat setelah PR ini (dan sudah tidak akurat sejak 2026-08-14 sebenarnya).
