# Presensi — Restrukturisasi Jadwal (Schedules) & Pola Tabel List.js (2026-07-17/18)

Rangkuman 1 sesi besar yang gesernya dari pembahasan payroll ke modul **Presensi/Absensi**. Ada 2 hal yang saling lepas tapi dikerjakan bareng: (1) restrukturisasi total "Shift" → "Jadwal" (`schedules`), dan (2) pola baru "tabel cepat" pakai List.js yang dipakai ke beberapa halaman existing sekalian.

## 1. Latar Belakang

Diskusi dimulai dari `knowledge/markdown/rencana-integrasi-hikvision-dan-pola-jadwal.md` (analisis sebelumnya) yang menyimpulkan: `m_shifts` perlu direname jadi konsep "Jadwal" yang lebih luas (Fixed/Flexible/Shift, niru fingerspot.io), bukan cuma shift rotasi. Rencana awal itu ("tambah kolom `schedule_type` ke `m_shifts`") ternyata **tidak cukup** — Fixed/Flexible punya struktur 7-hari-per-jadwal yang tidak muat di 1 baris flat.

User memberi 7 screenshot fingerspot.io asli (`knowledge/absensi/*.png` — CONTOH/KONFIGURASI JADWAL FIXED/FLEXIBEL/SHIFT) sebagai referensi bentuk & alur UI yang jadi acuan detail desain. Rencana lengkapnya ditulis di [`planning/2026-07-18-restrukturisasi-jadwal-schedules.md`](../../planning/2026-07-18-restrukturisasi-jadwal-schedules.md) — baca dokumen itu untuk skema kolom lengkap tiap tabel, dokumen ini cuma ringkasan status + link.

## 2. Tahap 1 — Fondasi (Migration + Model), SUDAH dieksekusi ke DB lokal

**Prinsip**: murni **ADDITIVE**. `m_shifts`, `shift_schedule_assignments`, `shift_templates`, `shift_template_days` (tabel lama) **TIDAK disentuh/didrop sama sekali** — Filament & service lama (LemburCalculator, AttendanceImportService, dst) tetap baca tabel lama seperti biasa, tidak ada yang rusak. Cutover (Tahap 2 sesungguhnya) belum dikerjakan.

### 9 tabel baru
| Tabel | Model | Isi |
|---|---|---|
| `schedules` | `Schedule` | Header jadwal — nama, `schedule_type` (fixed/flexible/shift), unit/divisi, jendela absensi Flexible |
| `schedule_shift_types` | `ScheduleShiftType` | Kode shift (Pagi/Siang/Malam, dst) per `schedules` tipe Shift — ganti isi baris `m_shifts` lama |
| `schedule_fixed_days` | `ScheduleFixedDay` | 7 baris/hari per `schedules` tipe Fixed |
| `schedule_flexible_days` | `ScheduleFlexibleDay` | 7 baris/hari per `schedules` tipe Flexible |
| `schedule_assignments` | `ScheduleAssignment` | User **ATAU** jabatan ↔ `schedules` (Fixed/Flexible), dual-target |
| `schedule_shift_assignments` | `ScheduleShiftAssignment` | Roster harian tipe Shift — ganti `shift_schedule_assignments` |
| `schedule_shift_templates` | `ScheduleShiftTemplate` | Pola rotasi tipe Shift — ganti `shift_templates` |
| `schedule_shift_template_days` | `ScheduleShiftTemplateDay` | Ganti `shift_template_days` |
| `schedule_shift_template_assignments` | `ScheduleShiftTemplateAssignment` | Keanggotaan rotasi **permanen** per user (`anchor_date`), biar generate per-bulan tidak reset siklus — genuinely baru, tidak ada padanan lama |

### Data existing sudah dipindah (copy, bukan move)
4 baris `m_shifts` lama (semua company sama) digabung jadi 1 `schedules` "Jadwal Shift Default" + 4 `schedule_shift_types`. 116 baris `shift_schedule_assignments` ikut di-copy. 1 bug data lama ditemukan & dibetulkan saat migrasi: "Shift Malam" (20:00-08:00) `is_overnight=0` yang salah, dibetulkan jadi `1`.

### Lain-lain Tahap 1
- Permission baru `CustomPermissionEnum::SISTEM_ABSENSI` — sudah di-assign ke `ahyaghifari288@gmail.com`.
- 8 field "Pengaturan Lanjutan" (toleransi telat/pulang cepat, buffer scan, dihitung kerja, maks durasi, ambang setengah-hari/alpha) sudah jadi kolom di `schedule_fixed_days`/`schedule_flexible_days`/`schedule_shift_types`, tapi **skema doang** — logic pemakaiannya di `computeMetricsFromCheckTimes()` (perhitungan absensi aktual) **belum disambungkan**, sengaja ditunda biar tidak campur bug "salah pindah data" vs "salah logic baru".

## 3. Tahap 2 (sebagian) — UI Halaman Jadwal, "pelan-pelan"

### 3.1 Halaman List — `/presensi/jadwal` (`SchedulesPage`)
List `schedules` per company, filter company + cari (nama/tipe/unit), pagination — semua pakai pola List.js (§4). Tombol "+ Tambah Jadwal" ke halaman terpisah (bukan modal, atas permintaan eksplisit user).

### 3.2 Halaman Tambah — `/presensi/jadwal/tambah` (`CreateSchedulePage`)
Wizard 2 langkah:
1. **Pilih tipe** — 3 kartu ikon besar (pakai gambar `public/assets/jadwal-{fixed,flexible,shift}.png`), radio + deskripsi singkat tiap tipe.
2. **Isi detail**:
   - Header: Nama Jadwal (input besar model "judul dokumen"), Perusahaan. **Unit/Divisi sengaja dihapus** dari form ini — dipindah ke step assignment terpisah nanti (`schedule_assignments`, belum dibangun).
   - **Fixed/Flexible**: tabel 7 hari (Senin→Minggu tampilan, data tetap `day_of_week` 0=Minggu Carbon-style) niru `CONTOH JADWAL FIXED/FLEXIBEL.png` — Minggu default Libur. Kolom Jam Kerja (zona tint navy) vs Lembur (transparan) dibedakan visual, lebar kolom rata semua (bukan sebagian dilebihkan). Baris Libur meng-collapse 6/9 kolom datanya jadi 1 strip merah "Libur — tidak ada jam kerja atau lembur" (bukan sederet input abu-abu kosong).
   - **Shift**: sengaja belum ada tabel kode shift ("shift nanti dulu", eksplisit ditunda) — cuma isi header, pesan "menyusul".
   - **Kolom Aksi per hari** — 2 tombol:
     - **Rincian** (ikon info): **hover tooltip murni CSS+JS** (bukan klik, bukan Livewire roundtrip) menampilkan ringkasan jam yang BENERAN DIHITUNG (bukan cuma angka menit mentah) — niru persis `KONFIGURASI JADWAL FIXED.png`: "Batas mulai bisa scan masuk 05:30" (= jam masuk − buffer), "Dianggap lembur akhir dari jam 18:00 sampai 20:00" (= jam pulang + rentang menit lembur), dst. Baris lembur awal/akhir otomatis sembunyi kalau memang tidak dikonfigurasi (max=0). Tooltip pakai `position:fixed` + delegated `mouseover` listener di `app.js` (§4.4) supaya tidak ke-clip pembungkus tabel yang `overflow-x:auto`.
     - **Pengaturan Lanjutan** (ikon slider): modal beneran (perlu `wire:click`, bukan hover) — panel ringkasan krem (niru warna cream KONFIGURASI screenshot) + 8 input field 2 kolom. Field "Maksimal Durasi Kerja" beda nama kolom Fixed (`max_work_minutes`) vs Flexible (`max_work_minutes_cap`, karena Flexible sudah pakai `max_work_minutes` buat field Jam Kerja biasa) — ditangani `CreateSchedulePage::maxDurationField()`.
   - Checkbox "Libur" pakai `.custom-checkbox` (sama seperti halaman Komponen Gaji payroll), aksen merah.
   - Simpan → `DB::transaction()`: bikin 1 `schedules` + 7 baris `schedule_fixed_days`/`schedule_flexible_days` sekaligus.

### 3.3 Yang BELUM dikerjakan (menyusul)
- Tabel kode Shift + rotasi (`schedule_shift_types`, `schedule_shift_templates`) di UI — masih 100% kosong, tidak ada halaman kelola sama sekali.
- Halaman/tombol "Kelola" di `SchedulesPage` masih disabled — belum ada halaman EDIT jadwal yang sudah ada (baru bisa CREATE).
- Assignment user/jabatan ke jadwal (`schedule_assignments`) — tidak ada UI sama sekali.
- Logic pemakaian 8 field "Pengaturan Lanjutan" di perhitungan absensi aktual (`computeMetricsFromCheckTimes()`).
- Cutover Filament lama (`MasterDataShiftResource`, `ScheduleShiftResource`, `ShiftTemplateResource`, dst — 30 file per audit planning doc) ke struktur baru — sistem lama & baru **masih hidup berdampingan**, belum ada yang di-drop.
- Fitur "generate roster per periode" (baca `schedule_shift_template_assignments.anchor_date`, isi `schedule_shift_assignments` otomatis tiap bulan) — modelnya sudah ada, job/UI-nya belum.

## 4. Pola Baru: Tabel "Cepat" dengan List.js (dipicu keluhan latensi Livewire)

Selama sesi ini user merasa loading Livewire (roundtrip server tiap ketik di kolom cari / pindah halaman) terasa lambat. Solusi yang disepakati: **List.js** (headless, ~2KB, tanpa maksa style — beda dari DataTables.net/Tabulator yang bawa CSS sendiri, ditolak karena bakal "perang" lawan style existing).

### 4.1 Prinsip pembagian kerja
- **Company/kategori/periode** (apapun yang benar-benar ganti QUERY DATABASE) → tetap lewat Livewire, itu memang butuh request server.
- **Cari teks, sortir, pindah halaman, filter kategori/status** (begitu data sudah di layar) → **List.js di browser, instan, 0 request** ke server.

### 4.2 Konvensi markup (dipakai ulang di semua halaman)
```html
<div id="xxx-list" data-list-table data-list-value-names="name,nip,..." data-list-page-size="15" wire:ignore.self>
    <input class="search" ...>                          <!-- bukan wire:model, murni HTML -->
    <table class="table-wrap">                            <!-- kalau .list cuma <tbody>, bukan wrapper visualnya sendiri -->
        <th class="sort" data-sort="name">...</th>       <!-- sortir gratis -->
        <tbody class="list">
            <tr><td><span class="name">...</span></td></tr>   <!-- class = value-name buat dicari -->
        </tbody>
    </table>
    <div class="list-empty-state" style="display:none">Tidak ada hasil.</div>
    <button class="list-prev">...</button>
    <ul class="pagination"></ul>
    <button class="list-next">...</button>
</div>
```
Filter tambahan (kategori/status, dsb) pakai `data-list-filter-key`/`data-list-filter-value` di `<select>`/`<button>`, dan `data-filter-<key>` di tiap baris data — di-AND-kan otomatis lewat `wireDeclarativeFilters()`.

Livewire cuma perlu **dispatch event `list-table:refresh`** (dari `updatedCompanyId()` dkk) tiap kali company/periode berubah — List.js `reIndex()` otomatis nyinkronin ulang ke baris baru yang Livewire ganti.

### 4.3 Semua logic ada di `resources/js/app.js`
`initListTables()`, `wirePrevNextButtons()` (List.js **tidak** ada tombol prev/next bawaan, cuma nomor halaman — ditambah manual pakai `list.show(i, page)`), `wireDeclarativeFilters()`, `wireEmptyState()` (pinter bedain `.table-wrap` vs `.list` sendiri buat pembungkus visualnya), `refreshListTables()`.

### 4.4 Isu yang ditemukan & solusinya
- **Search harus di DALAM container** yang sama dengan `.list`/`.sort`/`.pagination` — List.js cuma nyari elemen dalam scope container-nya. Kesalahan pertama: search ditaruh di luar container, jadi tidak berfungsi sama sekali.
- **Pagination List.js polos** (`<li><a>1</a></li>`) — 0 style bawaan, harus di-styling manual (`.pagination li a` dkk di `app.css`).
- **Tooltip/absolute-positioned element di dalam container `overflow-x:auto`** ke-clip — solusinya `position:fixed` + JS hitung posisi dari `getBoundingClientRect()` (lihat §3.2 Rincian), bukan `position:absolute` biasa.

### 4.5 Halaman yang sudah dikonversi
- **`PayrollsPage`** (`/keuangan/payroll`) — search dihapus dari Livewire, pagination server dihapus (load semua sekali).
- **`TrainingList`** (`/pelatihan`) — search + filter kategori (select) + filter status ikut/belum ikut (3 tombol) semua jadi client-side, termasuk kombinasi (AND) filter kategori+status sekaligus.
- **`MyEmployees`** (`/my-staff`, desktop) — **Filament Table dicabut total**, diganti tabel custom + List.js, kolom dipertahankan persis (foto, nama+email, NIP, departemen, jabatan+role, perusahaan, aksi Lihat/Edit). Mobile (card manual) tidak disentuh.
- **`SchedulesPage`** (`/presensi/jadwal`) — dibangun baru langsung pakai pola ini.

### 4.6 Komponen reusable baru
`<x-company-filter :companies="$this->companies" wire:model.live="companyId" />` (`resources/views/components/company-filter.blade.php`) — dipakai di Payroll, MyStaff, dan Jadwal, mengganti `<select>` company yang tadinya diketik ulang di tiap halaman.

## 5. File Penting
- Planning skema restrukturisasi: [`planning/2026-07-18-restrukturisasi-jadwal-schedules.md`](../../planning/2026-07-18-restrukturisasi-jadwal-schedules.md) (kolom lengkap tiap tabel, §7B dst untuk detail keputusan).
- Screenshot referensi UI: `knowledge/absensi/*.png`.
- Model baru: `app/Models/Shift/Schedule*.php` (9 file).
- Migration: `database/migrations/2026_07_18_1000{00..900}_*.php`.
- UI Jadwal: `app/Livewire/Presensi/{SchedulesPage,CreateSchedulePage}.php` + view di `resources/views/livewire/presensi/`.
- Pola List.js: `resources/js/app.js` (semua `wire*`/`init*` function), `resources/css/app.css` (`.pagination`, `.custom-checkbox`, `.btn-ghost:disabled`).

## 6. Belum Dikerjakan / Perlu Diputuskan Sesi Berikutnya
1. UI kode Shift + rotasi + assignment user/jabatan (§3.3) — bagian terbesar yang masih kosong.
2. Cutover 30 file Filament lama ke struktur baru (lihat planning doc §4) — sistem lama & baru masih duplikat/berdampingan.
3. Logic 8 field "Pengaturan Lanjutan" belum dipakai di `computeMetricsFromCheckTimes()` — skema sudah siap, logic-nya belum.
4. Integrasi Hikvision (`access_logs` → mapping `employee_id`↔`user_id`) — workstream terpisah, lihat `rencana-integrasi-hikvision-dan-pola-jadwal.md`.
5. Halaman "Kelola" jadwal yang sudah ada (edit) — baru bisa Create, belum ada Edit.
6. Ada 1 file migration kosong tak dikenali (`database/migrations/2026_07_17_211204_add_performance_indexes_for_search_and_filters.php`, `up()`/`down()` isinya kosong) — **bukan dari sesi ini**, perlu ditanyakan ke user sebelum commit (isi/hapus/biarkan).
