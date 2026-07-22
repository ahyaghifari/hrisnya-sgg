# Manajemen Notifikasi — Referensi Teknis (2026-07-22)

Dokumen ini murni referensi teknis: **jenis notifikasi apa saja yang ada, kapan tiap jenis dikirim, siapa penerimanya, dan bagaimana user bisa mematikannya** — dari pembacaan kode langsung, bukan asumsi. Untuk detail mesin approval yang jadi sumber sebagian besar notifikasi ini (siapa approver, bagaimana step naik), lihat dokumen terpisah [`manajemen-persetujuan.md`](manajemen-persetujuan.md) — dokumen ini fokus ke sisi notifikasinya saja.

## Ringkasan

Semua notifikasi di project ini (per 2026-07-22) berasal dari 1 sumber: **alur approval** (cuti/izin/lembur, dan modul lain yang lewat `ApprovalFlowService`). Belum ada notifikasi dari modul lain (LMS, Kanban, dst) — jadi cakupan `NotificationTypeEnum` sengaja masih kecil (4 case), didesain gampang ditambah nanti tanpa migration baru.

Ada 2 jalur pengiriman berbeda yang **tidak simetris** (temuan penting, lihat §5):
- **Jalur "approver pertama"** (submit-time) — `NotificationService`, masih pakai `DatabaseNotification::create()` mentah, **cuma channel `database`**, tidak lewat WebPush.
- **Jalur lainnya** (approver berikutnya di chain, dan status berubah ke requester) — pakai class `Illuminate\Notifications\Notification` asli (`ApprovalNeedsAction`, `RequestStatusChanged`), channel `database` **+** `WebPushChannel`.

## 1. Jenis Notifikasi (`App\Enum\NotificationTypeEnum`)

| Case | Ke siapa | Kapan dikirim |
|---|---|---|
| `APPROVAL_NEEDS_ACTION` | Approver yang sedang giliran | (a) saat pengajuan baru disubmit → approver step 1; (b) saat approver sebelumnya approve & step naik → approver step berikutnya |
| `APPROVAL_APPROVED` | Requester (pembuat pengajuan) | Pengajuan disetujui sampai selesai (step terakhir/`is_final`) |
| `APPROVAL_REJECTED` | Requester | Pengajuan ditolak salah satu approver (di step manapun) |
| `APPROVAL_CANCELLED` | Requester | Pengajuan dibatalkan sendiri oleh requester |

`label()` dan `description()` di enum ini yang ditampilkan di halaman Pengaturan Notifikasi (§4) — bukan cuma dekorasi, jadi kalau tambah case baru, isi 2 method itu juga.

## 2. Jalur Pengiriman

### 2.1 Submit pengajuan → approver pertama (`APPROVAL_NEEDS_ACTION`, database-only)

File: `app/Services/Attendance/AttendaceReqService.php` (`storeLeaveRequest`/`storePermissionRequest`/`storeOvertimeRequest`) → panggil salah satu dari:
- `NotificationService::sendLeaveRequestNotification()`
- `NotificationService::sendPermissionRequestNotification()`
- `NotificationService::sendOvertimeRequestNotification()`

Ketiganya (`app/Services/Notification/NotificationService.php`) polanya sama: insert langsung ke tabel `notifications` (`DatabaseNotification::create()`), **bukan** lewat `$user->notify(new SomeNotification())` — jadi **tidak pernah lewat WebPush**, cuma muncul di lonceng notifikasi in-app. Isinya lebih kaya dari jalur lain (nama jenis cuti/izin/lembur spesifik, tanggal, jam), karena datanya sudah tersedia di titik ini (`AttendaceReqService` yang bangun request detail-nya).

Sebelum insert, tiap method sekarang cek dulu (baris ~11-18 dst): `if (! $this->preferenceService->isEnabled($approver, NotificationTypeEnum::APPROVAL_NEEDS_ACTION)) return;` — kalau approver matikan preferensi ini, tidak ada baris `notifications` sama sekali yang dibuat (bukan cuma disembunyikan).

### 2.2 Step approval naik → approver berikutnya (`APPROVAL_NEEDS_ACTION`, database+WebPush)

File: `app/Services/Approval/ApprovalFlowService.php::advanceStep()`, cabang `if ($next)` (step masih ada lanjutannya, belum final). Setelah `approval_activities`/`approval_snapshots` di-update ke step baru:

```php
$snapshot->refresh();
$nextApprover = $this->notificationService->getFirstApproverFromSnapshot($snapshot);
if ($nextApprover) {
    $requesterName = User::find($snapshot->created_by)?->name ?? 'Seseorang';
    $nextApprover->notify(new ApprovalNeedsAction($snapshot->module_type, $snapshot->module_id, $requesterName));
}
```

`NotificationService::getFirstApproverFromSnapshot($snapshot)` **namanya menyesatkan** — bukan cuma "approver pertama", sebenarnya generik: resolve 1 user dari `$snapshot->activity` **apa adanya saat dipanggil** (baca `approver_type`/`approver_ref_id`/`scope_type`). Karena dipanggil SETELAH activity di-update ke step baru, otomatis yang di-resolve adalah approver step BARU, bukan step lama — makanya bisa dipakai ulang di sini tanpa tulis logic baru.

`App\Notifications\ApprovalNeedsAction` (baru, pola sama persis `RequestStatusChanged`): channel `database`+`WebPushChannel`, isi generik (`"{label modul} dari {requester} memerlukan persetujuan Anda"` — labelnya cuma "Pengajuan absensi"/"Pengajuan tugas"/"Pengajuan tukar shift", tidak sedetail jalur 2.1 karena `ApprovalFlowService` module-agnostic, tidak tahu ini leave/permission/overtime spesifik).

**Konsekuensi desain**: approver step 1 dan approver step 2+ dapat REDAKSI notifikasi yang beda (yang pertama lebih detail, yang berikutnya lebih generik) — bukan bug, tapi trade-off supaya `ApprovalFlowService` tidak perlu tahu detail per-modul.

### 2.3 Status berubah → requester (`APPROVAL_APPROVED`/`REJECTED`/`CANCELLED`, database+WebPush)

File: sama `ApprovalFlowService.php`, 3 titik (`advanceStep()` cabang reject, cancel, dan final-approve):
```php
User::find($snapshot->created_by)?->notify(
    new RequestStatusChanged($snapshot->module_type, $snapshot->module_id, 'approved'|'rejected'|'cancelled', $actorName)
);
```
`App\Notifications\RequestStatusChanged` — channel `database`+`WebPushChannel`.

## 3. Gating Preferensi (`via()`)

Kedua notification class asli (`ApprovalNeedsAction`, `RequestStatusChanged`) menaruh pengecekan preferensi di `via()`, **bukan** di titik pemanggilan — jadi kalau dimatikan, method `via()` return `[]` (array kosong), Laravel **tidak membuat job/baris apapun sama sekali** untuk notifiable itu (bukan dibuat lalu disembunyikan).

```php
public function via(object $notifiable): array
{
    if (! app(NotificationPreferenceService::class)->isEnabled($notifiable, NotificationTypeEnum::APPROVAL_NEEDS_ACTION)) {
        return [];
    }
    return ['database', WebPushChannel::class];
}
```

`RequestStatusChanged::via()` mapping status→enum dulu (`approved`→`APPROVAL_APPROVED`, dst) baru cek.

Jalur 2.1 (submit-time) beda pola — karena bukan lewat class `Notification` asli, pengecekannya ditaruh manual di awal tiap method `NotificationService::sendXRequestNotification()` (lihat §2.1), bukan di `via()` (karena tidak ada `via()` di situ).

## 4. Preferensi per User (`user_notification_preferences`)

| Kolom | Keterangan |
|---|---|
| `user_id` | FK `users`, cascade delete |
| `notification_type` | string, isi `NotificationTypeEnum::value` |
| `enabled` | boolean, default `true` |

Model: `App\Models\UserNotificationPreference`. Unique `(user_id, notification_type)`.

**Default opt-out, bukan opt-in**: kalau tidak ada baris untuk kombinasi user+type tertentu, `NotificationPreferenceService::isEnabled()` return `true` (lihat kode di bawah) — supaya semua user existing (termasuk yang login sebelum fitur ini di-deploy) tetap dapat semua notifikasi seperti biasa sampai mereka sendiri yang matikan.

```php
// app/Services/Notification/NotificationPreferenceService.php
public function isEnabled(User $user, NotificationTypeEnum $type): bool
{
    $preference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_type', $type->value)
        ->first();

    return $preference?->enabled ?? true;
}
```

### Halaman Pengaturan Notifikasi

`App\Livewire\Pwa\NotificationSettingsPage`, route `/akun/notifikasi` (nama `akun.notifikasi`), link ditaruh di halaman Akun (`resources/views/livewire/pwa/akun.blade.php`, baris dekat "Ubah Kata Sandi").

**Pola simpan: batch, bukan langsung per-klik.** `mount()` load semua preferensi ke `public array $selections` (state lokal), checkbox pakai `wire:model="selections.{type}"` (cuma ubah state di browser/component, belum nulis DB), tombol **Simpan** eksplisit yang baru panggil `save()` → loop semua case, `NotificationPreferenceService::setEnabled()` per jenis. Klik checkbox 1x tidak langsung tersimpan sampai klik Simpan.

## 5. Temuan Penting / Catatan (Known Gaps)

1. **Jalur submit-time (§2.1) tidak lewat WebPush** — approver pertama cuma dapat notifikasi in-app (lonceng), tidak ada push ke device, beda dari approver step berikutnya (§2.2) yang dapat push. Bukan bug yang ditemukan tak sengaja — sengaja tidak diubah pas fitur "kabari approver berikutnya" dibangun, supaya redaksi kaya per-tipe (nama jenis cuti/izin/lembur) di jalur lama tidak regresi. Kalau mau disamakan (submit-time juga lewat WebPush), `NotificationService::sendXRequestNotification()` perlu direfactor jadi class `Notification` asli.
2. **`getFirstApproverFromSnapshot()` cuma resolve 1 user**, bahkan untuk `approver_type=role`/`job_position` yang bisa match banyak user (`->first()` di kode) — kalau 1 role dipegang 3 orang, cuma 1 yang dapat notifikasi (yang pertama ketemu query), bukan ketiganya. Konsisten dari dulu (bukan regresi dari perubahan ini), tapi relevan diketahui kalau approval flow suatu company/role punya banyak pemegang.
3. **Notifikasi butuh queue worker jalan** — `ApprovalNeedsAction` dan `RequestStatusChanged` sama-sama `implements ShouldQueue`, dan `.env` lokal set `QUEUE_CONNECTION=database`. Kalau tidak ada `php artisan queue:work` (atau supervisor setara) yang jalan terus-menerus di server, notifikasi cuma menumpuk di tabel `jobs`, tidak pernah terkirim — **bukan gagal senyap yang gampang ketahuan**, karena baris `attendance_requests`/`approval_snapshots` tetap berhasil ter-update seperti biasa, cuma notifikasinya yang macet.
4. **Subscription Web Push tidak terikat jenis notifikasi** — `PushSubscriptionController::store()` (dipanggil dari tombol "Aktifkan" di banner home-cards.blade.php) cuma simpan `endpoint`+`keys` browser user, generik untuk SEMUA notifikasi WebPush apapun. User yang sudah subscribe SEBELUM fitur notifikasi baru ditambahkan otomatis ikut menerima notifikasi jenis baru begitu di-deploy — tidak perlu klik "Aktifkan" ulang.
5. **Preferensi default enabled untuk semua orang**, termasuk yang login sebelum halaman Pengaturan Notifikasi ada — tidak ada migrasi data/seeding row awal, murni mengandalkan default `?? true` di `isEnabled()`.

## 6. Referensi File Kode

- `app/Enum/NotificationTypeEnum.php` — vocabulary jenis notifikasi
- `app/Models/UserNotificationPreference.php` — model preferensi
- `app/Services/Notification/NotificationPreferenceService.php` — `isEnabled()`/`setEnabled()`
- `app/Services/Notification/NotificationService.php` — jalur submit-time (§2.1), juga rumah `getFirstApproverFromSnapshot()`
- `app/Notifications/ApprovalNeedsAction.php` — jalur approver berikutnya (§2.2)
- `app/Notifications/RequestStatusChanged.php` — jalur status ke requester (§2.3)
- `app/Services/Approval/ApprovalFlowService.php` — titik pemanggilan §2.2 dan §2.3 (`advanceStep()`)
- `app/Livewire/Pwa/NotificationSettingsPage.php` + `resources/views/livewire/pwa/notification-settings-page.blade.php` — halaman setting
- `database/migrations/2026_07_22_122841_create_user_notification_preferences_table.php`
- `manajemen-persetujuan.md` — referensi mesin approval yang jadi sumber semua notifikasi ini
