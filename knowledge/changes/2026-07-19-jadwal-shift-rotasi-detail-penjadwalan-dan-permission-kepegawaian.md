# Perubahan: Jadwal Shift — Template Rotasi, Detail Penjadwalan, Export Excel, dan Permission Kepegawaian (2026-07-19)

Lanjutan dari Restrukturisasi Jadwal (Shift) — lihat `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` untuk latar belakang Tahap 1/2. Sesi ini fokus ke **Tahap B/C**: cara isi roster shift per karyawan per tanggal, lewat 2 mode (template rotasi otomatis atau manual satu-satu), plus 1 perbaikan kecil di sistem permission navigasi.

## Ringkasan Status Akhir

- **Tab "Template Shift"** (pola rotasi berulang per karyawan) dan **"Detail Penjadwalan"** (grid roster harian per periode + generate dari template) — selesai dibangun dan bisa dipakai.
- **Export Excel** untuk Detail Penjadwalan — selesai, warna sel di file persis sama dengan tampilan di browser.
- **Permission `sistem_kepegawaian`** — ditambahkan, menu navigasi "Kepegawaian" (Izin/Cuti/Lembur) sekarang punya gate sendiri (sebelumnya menumpang `sistem_absensi`).
- Restrukturisasi Jadwal Tahap 2 (edit/delete jadwal, Tukar Shift) **masih belum dikerjakan** — sudah dicatat sebagai gap di dokumen sesi sebelumnya, tidak disentuh sesi ini.

## 1. Desain Ulang Skema Template — 3 Tabel Jadi 1

Tahap 1 restrukturisasi sudah bikin 3 tabel (`schedule_shift_templates`, `schedule_shift_template_days`, `schedule_shift_template_assignments`) tapi belum ada kode/data yang pakai. User minta disederhanakan drastis lewat beberapa iterasi:

1. Gabung jadi 1 tabel `schedule_shift_rotations` (`schedule_id`, `user_id`, `shift_type_id` nullable, `off_day`, `day` 1-based, `created_by`) — **tanpa entity "Template" terpisah**, tanpa `cycle_length` tersimpan. Cycle length dihitung otomatis dari `COUNT` baris `day` milik 1 user (`ScheduleShiftRotation::dayNumberFor()`).
2. Kolom `anchor_date` yang awalnya ada di tabel ini **dihapus lagi** — user: "kok ada tanggal jangkar si, kan ini template aja". Tanggal mulai rotasi ditentukan **saat generate**, bukan bagian dari template (parameter runtime di `dayNumberFor()`, bukan kolom DB).
3. `schedules.use_template` (boolean) ditambahkan sebagai saklar — tapi aktivasinya **sengaja tidak ada di form Create/Edit Jadwal**, cuma lewat tombol Aktifkan/Batalkan di tab Template Shift sendiri.

3 model lama (`ScheduleShiftTemplate`, `ScheduleShiftTemplateDay`, `ScheduleShiftTemplateAssignment`) dihapus total, diganti 1 model `ScheduleShiftRotation`.

## 2. Tab "Template Shift" — Grid Rotasi

`App\Livewire\Presensi\ScheduleShiftRotationTab` + `schedule-shift-rotation-tab.blade.php`. Desain final (setelah beberapa iterasi dari draft awal "per-karyawan side panel"): **1 grid tunggal** — baris = nama karyawan, kolom = "Hari 1..N" yang bisa ditambah/kurangi lewat tombol (jumlah hari sama untuk semua karyawan, bukan per-orang), sel = `<select>` kode shift atau "Libur", 1 tombol Simpan untuk semua baris sekaligus.

- Kalau `use_template` belum aktif: tampil banner + tombol "Aktifkan Template Rotasi".
- Kalau aktif: grid + tombol "Tambah/Kurangi Hari" + "Batalkan Penggunaan Template".
- Opsi "Libur" sengaja ditaruh **paling bawah** di dropdown (bukan pertama).
- Keterangan/legend kode shift ditampilkan di atas grid.

## 3. Tab "Detail Penjadwalan" — Roster Harian + Generate

`App\Livewire\Presensi\ScheduleShiftRosterTab` + `schedule-shift-roster-tab.blade.php`. Grid karyawan × tanggal (1 bulan penuh, `<input type="month">` buat pilih periode), tiap sel `<select>` kode shift/"L"/kosong dengan `wire:change` langsung tersimpan ke `ScheduleShiftAssignment` (manual override, selalu boleh menimpa — beda dari generate).

**Kolom `off_day`** ditambahkan ke `schedule_shift_assignments` (`shift_type_id` jadi nullable) setelah 3x bolak-balik keputusan:
1. Awalnya mau disimpan eksplisit.
2. User bilang cukup disimpulkan dari baris kosong (tanpa kolom baru) — sempat diimplementasi & migration di-rollback.
3. User berubah pikiran lagi ("eh tapi jadi ngga konsisten dong") — kolom `off_day` dipasang permanen, konsisten dengan `schedule_shift_rotations` yang memang sudah menyimpan `off_day` eksplisit sejak awal.

Perilaku akhir yang disepakati: **kosong (tidak ada baris sama sekali) ≠ Libur.** Sel kosong tampil netral (belum diisi), sel Libur harus dipilih eksplisit dari dropdown (opsi `value="off"`, tampil huruf "L", `off_day=true, shift_type_id=null` di DB). Sempat salah implementasi (kosong ikut dianggap "L" bawaan) — diperbaiki setelah user tegur, dan hasil generate uji coba yang keburu tersimpan dengan asumsi lama **dihapus (52 baris)** supaya bisa digenerate ulang dengan logic yang benar.

**Generate dari Template** (`generateFromTemplate()`): admin pilih tanggal mulai + "mulai dari hari ke-berapa di template" (rotasi tidak selalu mulai dari hari 1), lalu isi 1 periode penuh. Pakai `firstOrCreate` (insertOrIgnore) — **tidak pernah menimpa** baris yang sudah ada (manual/tukar shift), sesuai aturan lama di planning §2.11. Baris `off_day=true` di rotasi ikut dibuatkan baris eksplisit di roster (bukan di-skip), konsisten dengan keputusan §ini.

Bug kritis yang sempat terjadi & diperbaiki: generate mengisi dari tanggal 1 walau user pilih mulai dari tanggal 19 (loop tidak skip tanggal sebelum anchor) — sudah di-fix (`if ($date->lt($anchor)) continue;`), dan 124 baris hasil generate salah dari insiden ini sudah diverifikasi & dihapus sebelumnya.

## 4. Warna Kode Shift — `color` + `font_color`, Solid Tanpa Adaptasi Tema

`schedule_shift_types` sekarang punya 2 kolom warna: `color` (background, sudah ada sebelumnya, baru dipakai UI sesi ini) dan **`font_color`** (baru, kolom terpisah, dipilih manual per shift lewat 2 color-picker di form Create/Edit).

Iterasi desain warna (beberapa kali berubah arah atas permintaan user):
1. Awal: solid hex langsung → pecah di dark mode.
2. Diperbaiki pakai `color-mix(in srgb, {color} X%, var(--surface))` supaya otomatis adaptif ke tema.
3. **User balik arah lagi**: warna & warna font harus dipakai **persis** seperti yang diisi di form — solid, tidak di-opacity/color-mix, tidak ikut tema gelap/terang sama sekali (karena warna+font sudah dipilih berpasangan supaya kontras, tidak butuh adaptasi otomatis).
4. Untuk sel "Libur" (bukan warna pilihan user, warna sistem `#dc2626`) — dipudarkan dikit (`color-mix(in srgb, #dc2626 55%, white)`, font `#7f1d1d`) supaya beda kesan dari kode shift asli tapi tetap jelas.

Diterapkan konsisten di 3 tempat: tabel read-only tab Jam Kerja, grid tab Template Shift, grid tab Detail Penjadwalan.

**Catatan salah paham yang sempat terjadi**: instruksi "background default putih font hitam, tidak berubah tema" awalnya diterapkan ke seluruh baris tabel form Create/Edit (kode shift) — ternyata yang dimaksud user bukan itu (row form tetap ikut tema `var(--surface-alt)` seperti semula), sudah di-revert.

## 5. Export Excel — Detail Penjadwalan

`App\Exports\Presensi\ScheduleShiftRosterExport` (pola FromArray + WithStyles + WithColumnWidths + WithTitle, mengikuti konvensi `AttendanceHistoryExport` yang sudah ada) + method `ScheduleShiftRosterTab::exportExcel()`. Isi file: baris judul (nama jadwal), baris keterangan (legend kode shift + "L=Libur"), lalu grid karyawan × tanggal — tiap sel diwarnai **persis sama** dengan warna `color`/`font_color` milik shift type-nya (bukan dihitung ulang), sel Libur pakai warna pudar yang sama seperti di UI. Tombol "Export Excel" ditaruh di sebelah label periode/bulan (kiri), terpisah dari form Generate (kanan) yang sengaja dibuat lebih ringkas/kecil.

## 6. Permission `sistem_kepegawaian`

Ditemukan saat user minta menu navigasi "Kepegawaian" (dropdown Izin/Cuti/Lembur di topnav) dikasih permission sendiri — sebelumnya menu ini menumpang gate `sistem_absensi` di `nav.blade.php` (kemungkinan bug lama, bukan disengaja). Ditambahkan:

- Case baru `CustomPermissionEnum::SISTEM_KEPEGAWAIAN = 'sistem_kepegawaian'` (label "Sistem Kepegawaian").
- `nav.blade.php`: variabel `$canAccessKepegawaian` terpisah, dropdown Kepegawaian dipindah keluar dari blok `@if ($canAccessAbsensi)` jadi blok sendiri.
- `CustomPermissionsSeeder` dijalankan ulang supaya baris permission-nya ada di DB (`php artisan db:seed --class=CustomPermissionsSeeder`).

**Belum ada role yang otomatis dapat permission ini** — perlu diaktifkan manual per role lewat `/admin/roles` (checkbox "Sistem Kepegawaian") supaya menu tetap muncul buat role yang sebelumnya bisa akses lewat `sistem_absensi`.

## Yang Masih Perlu Dikerjakan

1. **Assign `sistem_kepegawaian` ke role yang relevan** lewat `/admin/roles` — tanpa ini, menu "Kepegawaian" hilang dari semua role sampai dicentang manual (§6).
2. **Detail Penjadwalan untuk jadwal `use_template = false` (murni manual) belum diverifikasi/dites** — sesi ini fokus & dites lewat jalur `use_template = true` (isi Template Shift → Generate). Grid & `setCell()` di tab Detail Penjadwalan seharusnya tetap jalan tanpa rotasi (tombol Generate memang disembunyikan kalau `use_template` false), tapi belum ada pengujian nyata untuk skenario jadwal shift yang dari awal sengaja tidak pakai template sama sekali.
3. **Fitur Tukar Shift masih belum ada sama sekali** — baik sisi karyawan minta tukar maupun sisi atasan approve, termasuk cara tukar shift "disuntikkan" ke roster (`ScheduleShiftAssignment`) tanpa tertimpa generate berikutnya. Ini bagian dari gap Restrukturisasi Jadwal Tahap 2 yang sudah dicatat sebelumnya (lihat `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` §5), ditegaskan lagi di sini karena langsung relevan dengan tab Detail Penjadwalan yang baru dibangun sesi ini.
4. Restrukturisasi Jadwal Tahap 2 lainnya (edit/delete jadwal) — gap lama, belum disentuh sesi ini.
5. Belum ada test otomatis baru untuk generate-dari-template atau export Excel — verifikasi sejauh ini manual lewat tinker/browser.

## Referensi
- `planning/2026-07-18-restrukturisasi-jadwal-schedules.md` — spek awal Restrukturisasi Jadwal, termasuk gap Tahap 2.
- `knowledge/changes/2026-07-19-revisi-absensi-deploy-production-dan-bug-role-permission.md` — sesi sebelumnya di hari yang sama (revisi Absensi, deploy production, bug preload Role/Permission).
