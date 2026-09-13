# Redesign Oncall & Middle - catatan buat lanjut besok

Konteks: dibahas 2026-09-13/14, dipicu screenshot rotasi jadwal nyata (OC muncul di
siklus tetap P,P,OC,S,S,OC,M,M,N/A,L - bukan dipanggil dadakan pas libur) dan tabel
konfigurasi Middle per unit (RCP Rajal/Eksekutif/Ranap, jam beda-beda, salah satu
ditandai "fleksibel").

**Catatan tambahan (2026-09-14)**: dicek ulang pola penjadwalan shift yang jalan
sekarang - Middle ternyata JARANG muncul di rotasi nyata. Konsisten sama pemaknaan
"Middle = shift cover jam sibuk/tambahan, bukan slot gantian rata ke semua orang
kayak Pagi/Siang/Malam" - tidak mengubah keputusan desain (Middle tetap assignable
langsung, fixed calculation, exclude dari Extra Shift), cuma konfirmasi pemakaiannya
memang jarang, bukan tanda ada yang salah di desainnya.

**Catatan tambahan #1b (2026-09-14)**: dicek juga unit LAIN (TTK Rajal/Apoteker,
farmasi) - pola pakai Middle di situ JUSTRU KONSISTEN, tiap Sabtu, dibagi rata ke
semua orang (2x sebulan per orang) - beda BANGET dari RCP Ranap (resepsionis) yang
jarang/gak rata. Kesimpulan: frekuensi & pola pakai Middle itu MURNI beda per unit
tergantung kebutuhan operasional masing2 (farmasi butuh Middle rutin tiap Sabtu,
resepsionis cuma situasional) - sistem TIDAK PERLU ngatur/maksa pola tertentu, cukup
sediakan Middle sebagai shift type assignable biasa, admin tiap unit bebas pakai
sesuai kebutuhan mereka sendiri-sendiri. Tidak ada perubahan desain dari catatan ini,
cuma konfirmasi tambahan bahwa variasi pemakaian ini WAJAR/diharapkan, bukan anomali.

**Catatan tambahan #2 (2026-09-14)**: dicek data presensi asli bulan Juli - hari OC
kebanyakan NOL scan sama sekali (gak dipanggil, dianggap wajar bukan Alpha), TAPI ada
1 hari (Minggu 2026-07-26) yang beneran discan: masuk 07:39, pulang 11:53 (~4 jam 14
menit doang, bukan durasi penuh 7-8 jam kayak shift biasa). Ini justru NGUATIN
(bukan mengubah) desain `calculateOncall()` yang udah dibangun - durasi kerja Oncall
emang gak tetap/gak ditarget jam tertentu, `early_leave_minutes` sengaja selalu 0
(gak ada konsep "pulang cepat" buat Oncall). 2 hal yang perlu tetap ditindaklanjuti:
1. Nol-scan di hari OC = wajar, JANGAN ketandain Alpha/pelanggaran di rekap/laporan
   (lihat poin di bawah, masih perlu solusi konkret - kemungkinan exception khusus
   pas cross-check roster vs attendance_records buat shift_type=oncall).
2. Durasi kerja Oncall yang pendek/gak tentu itu SAH, tidak perlu validasi
   "minimal berapa jam" - `calculateOncall()` sekarang memang tidak mengecek itu,
   sudah benar, tidak perlu ubah apa-apa di kalkulasinya.

**Contoh ke-2** (karyawan lain, Selasa 2026-07-07, juga OC): masuk **11:01**, pulang
16:41 (~5 jam 40 menit). Jam masuk beda JAUH dari contoh pertama (07:39 vs 11:01) -
konfirmasi Oncall memang TIDAK punya jam mulai baku sama sekali (bukan cuma durasi
yang bebas). `checkin_buffer_minutes` buat Oncall di lapangan kemungkinan disetting
sangat lebar (atau `late_tolerance_minutes` besar) supaya jam masuk berapa pun tetap
wajar - perlu dicek nanti pas setup data asli, bukan perlu perubahan kode.

## Kesimpulan (belum dieksekusi, tinggal konfirmasi ulang besok lalu jalan)

**Oncall bukan buat Extra Shift sama sekali** - itu asumsi awal yang salah (dibangun
2026-09-13 berdasar instruksi "Oncall cuma boleh diajukan pas hari itu libur"). Yang
bener: Oncall itu **shift roster biasa, assignable langsung di jadwal/rotasi**, sama
kayak Pagi/Siang/Malam/Middle - bedanya cuma cara HITUNGNYA fleksibel (target durasi),
bukan jendela jam ketat. Anggotanya dikasih fleksibilitas tapi tetap harus hadir kalau
diperlukan (standby terjadwal, bukan dipanggil dadakan).

**Middle** boleh `shift_type` dikosongin aja (gak perlu enum `ShiftType::MIDDLE`) -
excludenya dari Extra Shift kejadian otomatis karena `null` gak pernah match
`from_shift_type`/`to_shift_type` manapun di `ExtraShiftRule`. Lebih simpel dari
approach yang sekarang (enum eksplisit + filter manual di beberapa tempat).

**Oncall TETAP butuh `shift_type='oncall'` eksplisit** (gak boleh dikosongin kayak
Middle) - karena string ini jadi SAKLAR buat kode nyata:
- `ShiftScheduleCalculator::calculateOne()` - cabang `calculateOncall()` (kalkulasi
  fleksibel) di-trigger dari cek `$config->shift_type === ShiftType::ONCALL->value`.
- Kalau kosong, Oncall jatuh ke kalkulasi FIXED biasa - fitur utamanya hilang.

## Yang perlu diubah besok

1. **Hapus** filter exclude Oncall dari dropdown roster & rotasi
   (`schedule-shift-roster-tab.blade.php`, `schedule-shift-rotation-tab.blade.php` -
   cari `!= \App\Enum\ShiftType::ONCALL->value` / `$assignableShiftTypes`) - biar
   Oncall assignable langsung kayak shift lain.
2. **Hapus** `ExtraShiftAssignment::syncOncallRoster()` / `revertOncallRoster()` +
   `booted()` hook-nya - mekanisme "Extra Shift Oncall nimpa roster" gak relevan lagi
   kalau Oncall udah assignable langsung dari awal.
3. **Hapus** Oncall dari opsi `ExtraShiftRulePage` (kalau ada baris `from=libur,
   to=oncall` yang udah kepakai di data, perlu diputuskan mau di-migrate/dihapus).
4. **Hapus** filter `whereDoesntHave('shiftType', shift_type=oncall)` di
   `AccessLogScheduleReader`/`ShiftAttendanceReader` (`find()`,
   `findForEntries()`, `findForIncrementalDates()`, `extraShiftAssignmentsFor()`)
   yang sengaja exclude Oncall dari extra_shift lookup - kalau Oncall udah gak
   pernah lewat Extra Shift lagi, `ExtraShiftAssignment` dengan shift_type=oncall
   seharusnya gak akan pernah ada lagi, tapi filter ini aman dibiarkan/dihapus,
   perlu ditinjau ulang.
5. Migrasi/hapus `ShiftType::MIDDLE` enum? (ATAU tetap dipertahankan kalau ternyata
   laporan butuh bedain kategori Middle secara eksplisit - masih didiskusikan,
   BELUM final). Kalau dihapus: cari semua referensi `ShiftType::MIDDLE` (
   `ExtraShiftRulePage`, dropdown filter roster/rotasi) dan balikin ke behavior
   "shift_type kosong = otomatis exclude Extra Shift".
6. Cek ulang test yang kena dampak: `ExtraShiftOncallOverwritesRosterTest` (bakal
   dihapus/diganti total kalau syncOncallRoster dihapus), test manapun yang assert
   Oncall gak assignable di roster (perlu dibalik expectednya).
7. Perlu tanya user: baris `ExtraShiftAssignment` lama (kalau sudah ada data
   production) dengan `shift_type_id` mengarah ke Oncall - gimana nasibnya pas
   migrasi ke desain baru (biarin sebagai histori, atau di-backfill ke
   `schedule_shift_assignments` langsung)?

**Catatan tambahan #3 (2026-09-14)**: user sendiri notice OC itu ujung-ujungnya kerasa
kaya "libur" (default kosong, gak wajib presensi) - tapi beda dari Libur murni karena
tetap berstatus "siaga" (bisa dipanggil, jam/durasi bebas total kalau dipanggil). Bukan
`off_day=true` biasa (itu beneran libur, gak ada ekspektasi apa-apa), tapi juga bukan
shift kerja wajib (gak ada kewajiban presensi/jam tetap). Mungkin butuh STATUS KE-3 di
sistem (bukan sekadar shift_type biasa) - "standby/siaga", beda dari off_day DAN beda
dari shift kerja normal. Belum final, user bilang bisa disesuaikan lain waktu karena
"mungkin dia sangat sebebas itu" - taruh sebagai opsi desain buat didiskusikan lagi,
jangan buru-buru diimplementasi sebagai shift_type biasa kalau ternyata butuh konsep
status roster baru.

## Belum diputuskan / perlu konfirmasi ulang besok

- Apakah Middle enum dihapus total atau dipertahankan buat kebutuhan laporan?
- Apakah Oncall reaktif (dipanggil dadakan pas libur, desain lama) masih dibutuhkan
  SAMA SEKALI sebagai jalur terpisah, atau benar-benar dihapus total diganti jalur
  terjadwal doang?
