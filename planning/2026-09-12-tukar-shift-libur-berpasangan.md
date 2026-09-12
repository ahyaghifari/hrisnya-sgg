# Tukar Shift ke Rekan yang Libur — pertukaran berpasangan (reciprocal)

> **STATUS 2026-09-12: DIEKSEKUSI.** Migration jalan, service (`resolveSlot`/`move_over_off_day`/
> `assertReciprocalOffEligible`), UI modal (mode ke-3 "Rekan sedang libur"), `storeOffDay()` lama
> dihapus. 26 test baru/diubah, semua hijau (`ShiftSwapRequestServiceTest` 18, `ShiftSwapRequestModalTest`
> 8) + 42 test regresi terkait tukar shift semua hijau. Tidak ada deviasi dari planning.

> Lanjutan dari [planning/2026-09-10-approval-sub-module-izin-cuti-lembur.md] (tidak terkait
> langsung) dan perubahan hari ini di `ShiftSwapRequestService` (jalur konflik tukar-tanggal
> jadi warning-only, bukan auto Extra Shift lagi — lihat commit hari ini). Fitur ini BEDA
> kasus: bukan soal shift-vs-shift yang bentrok, tapi shift-KERJA ditukar dengan LIBUR rekan.
>
> **Masalah.** `ShiftSwapRequestService::storeOffDay()` sudah ada tapi (a) **tidak pernah
> dipakai UI mana pun** (dead code — modal cuma kirim `swap_mode: 'schedule'`), dan (b) **satu
> arah**: rekan yang libur diminta kerja gantiin pemohon, tapi pemohon tidak memberi apa pun
> balik — rekan kehilangan jatah libur tanpa kompensasi. `validateOffDayRules()`-nya juga
> **kebalik**: syaratnya rekan TIDAK PUNYA baris sama sekali (N/A/kosong), padahal yang
> seharusnya boleh justru rekan yang barisnya ADA dengan `off_day=true` (Libur beneran).
>
> **Yang diminta user**: kalau mau tukar shift ke rekan yang sedang **Libur** (bukan N/A/kosong),
> pemohon WAJIB "membayar" balik satu hari liburnya sendiri di tanggal lain — supaya jatah libur
> kedua pihak tetap sama sebelum & sesudah tukar.

---

## 0. Keputusan

| Topik | Keputusan | Alasan |
|---|---|---|
| Mekanisme dasar | **Bukan fitur baru dari nol** — reuse `storeScheduleSwap` (mode tanggal-beda) yang sudah ada. `req_schedule_id` = shift pemohon (A) di D1 yang diserahkan; `target_schedule_id` = shift rekan (B) di D2 yang diambil alih A. Efeknya: A off di D1 (gantinya B kerja), B off di D2 (gantinya A kerja) — otomatis "libur ditukar libur". | Mesin approval + eksekusi (`executeApprovedSwap`) sudah lengkap, tidak perlu ditulis ulang. |
| `storeOffDay()` (satu arah) | **Dipensiunkan/dihapus.** Tidak pernah dipakai UI, dan konsepnya (gratis, tidak berpasangan) bertentangan dengan aturan baru ini. | Dead code + salah arah (lihat bug `validateOffDayRules` di atas). |
| Definisi "Libur" yang SAH buat kandidat rekan (D1) | **WAJIB** baris `schedule_shift_assignments` beneran ada untuk (user, tanggal) dengan `off_day = true`. **N/A/kosong (tidak ada baris sama sekali) TIDAK BOLEH.** Query LANGSUNG ke tabel, **JANGAN** lewat `ScheduleResolver::resolveForUserDate()` / `ResolvesDayScheduleSummary` — resolver itu ikut membaurkan "Libur" hasil proyeksi pola fixed/flexible (`ScheduleFixedDay`/`ScheduleFlexibleDay`) yang BUKAN roster shift beneran. | Instruksi user eksplisit: "libur ditukar dengan libur, ngga bisa libur ditukar dengan off_day [proyeksi]/kosong". Lihat `ScheduleResolver.php:32-34` vs `:61+` untuk beda dua sumber ini. |
| D2 (kompensasi dari A) | Tanggal lain milik **B** yang **sedang B kerjakan beneran** (`shift_type_id` NOT NULL, `off_day = false`) — A pilih salah satu dari situ, A akan mengambil alih shift itu. | Supaya A benar-benar "membayar" — B dapat gantinya libur di D2. |
| D1 ≠ D2 | **Wajib divalidasi** (belum ada pengecekan ini di mana pun sekarang). | Tidak mungkin B dan A tukar-menukar status kerja/libur pada tanggal yang sama. |
| Baris Libur B di D1 saat eksekusi | **Bukan konflik** — boleh "ditimpa": dihapus lalu baris A pindah kepemilikan ke B (lihat §2, mode baru `move_over_off_day`). Ini PERBAIKAN GENERIK ke `resolveSlot()`, bukan cuma buat fitur ini — berlaku ke SEMUA tukar-tanggal-beda: busy row ber-`off_day=true` selalu boleh ditimpa, cuma busy row berisi shift KERJA beneran yang jadi 'conflict'. | `resolveSlot()` sekarang (hasil perubahan hari ini) menganggap SEMUA baris existing (termasuk Libur) sebagai 'conflict' (warning, no-op) — itu salah untuk kasus Libur, harus tetap bisa jalan. |
| Konflik NYATA (bukan off_day) di D1 atau D2 | **Blok keras** (throw, tolak submit / gagalkan approval) — BEDA dari tukar-tanggal biasa yang sekarang warning-only. | Kalau dibiarkan warning-only seperti kasus umum, salah satu sisi bisa diam-diam tidak jadi pindah (mode `'conflict'` = no-op) sementara sisi lain tetap jalan → pemohon untung, rekan rugi (persis masalah yang mau dihindari). |
| Cara bedakan "harus keras" vs "boleh warning-only" | Kolom baru **`swap_kind`** (`shift_swap_requests`, default `'schedule'`) — nilai `'reciprocal_off'` untuk fitur ini. `executeApprovedSwap()`/`resolveDifferentDateExecution()` terima flag `strict` diturunkan dari `swap_kind`. | Perlu disimpan (persisted) karena eksekusi baru jalan pas approval final — jauh setelah submit, tidak bisa infer ulang dari data lain. |
| Kompatibilitas jenis shift (pagi/siang/malam) | **Tetap berlaku** — D2 A ambil alih shift APA ADANYA milik B (bukan nuker jenis), tapi tetap tunduk `validateScheduleSwapRules()` (alasan physically sama: jangan sampai orang kebiasaan malam tiba-tiba kepaksa masuk pagi). | Konsisten kebijakan yang sudah ada, tidak ada alasan dikecualikan. |
| Bulan sama | Tetap berlaku (`validateScheduleSwapRules`). | Sudah aturan umum tukar shift, tidak spesifik ke fitur ini. |

---

## 1. Migration

`shift_swap_requests` tambah kolom:

```php
Schema::table('shift_swap_requests', function (Blueprint $table) {
    $table->string('swap_kind', 20)->default('schedule')->after('target_schedule_id');
});
```

Baris lama otomatis `'schedule'` (perilaku sekarang, tidak berubah).

---

## 2. `ShiftSwapRequestService` — perbaikan generik `resolveSlot()`

File: `app/Services/Shift/ShiftSwapRequestService.php`

```php
/** @return array{mode: 'move'|'move_over_off_day'|'conflict', busy?: ScheduleShiftAssignment, warning?: string} */
private function resolveSlot(?ScheduleShiftAssignment $busy, ?User $incomingOwner, string $conflictMessagePrefix, bool $strict = false): array
{
    if (! $busy) {
        return ['mode' => 'move'];
    }

    if ($busy->off_day) {
        // Libur beneran (baris ada, off_day=true) BUKAN konflik - boleh ditimpa siapa pun.
        return ['mode' => 'move_over_off_day', 'busy' => $busy];
    }

    if ($strict) {
        throw new \InvalidArgumentException(
            "{$conflictMessagePrefix} ({$busy->shiftType?->name}) - tukar libur berpasangan butuh tanggal ini benar-benar kosong/libur di kedua belah pihak."
        );
    }

    return ['mode' => 'conflict', 'busy' => $busy, 'warning' => /* pesan sama seperti sekarang */];
}
```

`resolveDifferentDateExecution()` terima param `bool $strict = false`, diteruskan ke 2 pemanggilan `resolveSlot()`.

`applySlotResolution()` tambah cabang:

```php
if ($slot['mode'] === 'move_over_off_day') {
    $slot['busy']->delete(); // baris Libur ditimpa
    $originalSchedule->update(['user_id' => $incomingUserId]);
    return;
}
```

`executeApprovedSwap()` — baca `$swapRequest->swap_kind === 'reciprocal_off'` untuk set `$strict = true` saat panggil `resolveDifferentDateExecution()`. Kalau `strict` dan ternyata muncul konflik nyata baru (berubah sejak submit) → exception naik ke `ApprovalFlowService::advanceStep()` → approval gagal dengan pesan jelas (pola try/catch yang sudah ada di `ShiftSwapRequestDetail::confirmAction()`), TIDAK partial-execute.

---

## 3. Validasi submit — `storeScheduleSwap()` (mode baru)

Tambah parameter/percabangan berdasar `swap_kind`:

1. **Kandidat B (halaman pilih rekan)** — query baru, TIDAK reuse `getTargetCandidatesProperty()` yang ada (itu cari yang KERJA). Kandidat di sini:
   ```php
   ScheduleShiftAssignment::where('off_day', true)
       ->whereDate('date', $reqDate)
       ->where('user_id', '!=', Auth::id())
       // + filter sesama jadwal shift yang sama seperti kandidat biasa (myScheduleId())
       ->with('user')->get();
   ```
2. **D1 ≠ D2** — validasi baru eksplisit, pesan: "Tanggal kompensasi tidak boleh sama dengan tanggal shift yang diserahkan."
3. **Kandidat D2 (shift B yang diambil-alih A)** — muncul SETELAH B dipilih:
   ```php
   ScheduleShiftAssignment::where('user_id', $targetUserId)
       ->where('off_day', false)->whereNotNull('shift_type_id')
       ->whereDate('date', '!=', $reqDate)
       ->where('date', '>=', now()->addDays(max(1, $minLeadDays)))
       ->get();
   ```
4. **Validasi keras pra-submit** (fail-fast, sebelum `ShiftSwapRequests::create()`): panggil `resolveDifferentDateExecution($reqSchedule, $targetSchedule, strict: true)` — kalau ada slot 'conflict' asli (bukan off_day), langsung throw, jangan lanjut bikin snapshot approval.
5. Field baru dikirim ke `store()`: `'swap_kind' => 'reciprocal_off'`, `'req_schedule_id'`, `'target_schedule_id'` (shift B di D2) — struktur data SAMA PERSIS `storeScheduleSwap` biasa, cuma `swap_kind` beda dan asal kandidatnya beda.

`assertNoPendingSwapOnSlots` dan `assertWithinSettingLimits` (lead days, kuota) tetap jalan apa adanya, tidak berubah.

---

## 4. Hapus `storeOffDay()`

- `ShiftSwapRequestService::storeOffDay()` + `validateOffDayRules()` dihapus.
- `store()`: cabang `if ($mode === 'off_day')` dihapus (tinggal `'schedule'` dengan sub-kind `'reciprocal_off'`/`'schedule'` lewat `$data['swap_kind']`).
- Test `test_off_day_swap_transfers_shift_to_target_after_approval` (di `ShiftSwapRequestServiceTest.php`) dihapus, diganti test baru §6.

---

## 5. UI — `ShiftSwapRequestModal`

Tambah pilihan mode ke-3 di samping "same"/"different" yang sudah ada: **"Rekan sedang libur"**.

Alur form:
1. Pilih tanggal shift sendiri (D1) — sama seperti sekarang.
2. Toggle "Rekan sedang libur, saya tukar dengan libur saya" → tampilkan dropdown kandidat rekan dari query §3.1 (yang Libur di D1, BUKAN yang kerja).
3. Setelah rekan dipilih → dropdown kedua **"Tanggal shift {rekan} yang Anda ambil alih"** dari query §3.3.
4. Preview: "Anda: Libur {D1} (gantinya {rekan} kerja) — Anda kerja {D2} menggantikan {rekan} (gantinya {rekan} libur)."
5. Submit → `store(['swap_mode' => 'schedule', 'swap_kind' => 'reciprocal_off', 'req_schedule_id' => ..., 'target_schedule_id' => ...])`.

`conflictWarnings()`/banner warning yang dibuat hari ini TIDAK relevan di mode ini (jalur ini blok keras, bukan warning) — banner cukup ditampilkan untuk mode "different" biasa.

---

## 6. Test

File: `tests/Feature/Shift/ShiftSwapRequestServiceTest.php` (tambahan, bukan file baru)

1. **Happy path**: A kerja D1, B `off_day=true` D1, B kerja D2 (`off_day=false`). Submit `swap_kind=reciprocal_off` → approve → assert: baris A-D1 sekarang `user_id=B` (baris Libur B lama di D1 terhapus), baris B-D2 sekarang `user_id=A`.
2. **B kandidat tapi N/A** (tidak ada baris sama sekali di D1) → tidak boleh muncul di kandidat (test di level query candidate / Livewire component, bukan service — service sendiri technically masih bisa terima kalau dipaksa lewat ID, tapi UI tidak pernah menyodorkannya. Kalau mau dikeraskan di service juga, tambah assert eksplisit "target harus off_day=true" di `storeScheduleSwap` saat `swap_kind=reciprocal_off`).
3. **D1 == D2** → `InvalidArgumentException` saat submit.
4. **A ternyata sudah ada shift lain (bukan libur) di D2** → `InvalidArgumentException` saat submit (strict, bukan warning).
5. **Race condition**: submit sukses (D2 kosong), lalu sebelum approval final A keburu dapat shift lain beneran di D2 → approval final harus **gagal** (exception naik, status tetap `in_review`, tidak ada perubahan baris), bukan partial-execute.
6. **B Libur di D1 tapi TERNYATA sudah dihapus/diubah** (misal B keburu di-assign shift beneran) sebelum approval final → re-check di `executeApprovedSwap` mendeteksi bukan lagi `off_day=true` → strict → approval gagal.

---

## 7. Yang sengaja TIDAK dikerjakan

- Tidak menyentuh mode "same date" / "different date" biasa selain perbaikan generik `move_over_off_day` (§2) — itu genuinely bug fix yang menguntungkan kedua mode, bukan scope creep.
- Tidak ada UI admin terpisah untuk lihat rekap "tukar libur" — cukup tampil di riwayat tukar shift biasa (`swap_kind` bisa ditambah sebagai kolom info kalau nanti perlu filter).
- Tidak retroaktif mengubah data lama (`storeOffDay` belum pernah dipanggil oleh siapa pun, aman dihapus tanpa migrasi data).
