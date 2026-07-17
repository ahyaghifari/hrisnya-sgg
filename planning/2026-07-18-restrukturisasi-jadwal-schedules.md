# Planning: Restrukturisasi "Shift" → "Jadwal" (Schedules) — Fixed/Flexible/Shift (2026-07-18)

Status: **Tahap 1 (fondasi: migration + model) dieksekusi di database LOKAL, 2026-07-18.** Tahap 2 (adaptasi Filament Resource/Form/Table/Widget + Services yang masih baca tabel lama) **BELUM dikerjakan** — dipisah eksplisit karena skalanya lebih besar dari dugaan awal (30 file tersentuh, beberapa Table class Filament ~300 baris). Lanjutan dari `knowledge/markdown/rencana-integrasi-hikvision-dan-pola-jadwal.md` (§5, pertanyaan terbuka "rename `m_shifts` → `m_schedules`?" — sekarang dijawab: ya, plus dipecah jadi beberapa tabel, bukan cuma rename).

**Ringkasan Tahap 1 (selesai)**:
- 9 tabel baru dibuat — **murni ADDITIVE**, `m_shifts`/`shift_schedule_assignments`/`shift_templates`/`shift_template_days` **TIDAK didrop/disentuh**, semua Filament & Service lama tetap baca tabel lama seperti biasa, tidak ada yang rusak.
- Data existing (4 `m_shifts`, 116 `shift_schedule_assignments`) sudah di-copy ke struktur baru — 4 shift digabung jadi 1 `schedules` "Jadwal Shift Default" (dikonfirmasi user). 1 bug data lama ditemukan & dibetulkan saat migrasi: "Shift Malam" (20:00-08:00) punya `is_overnight=0` yang salah, dibetulkan jadi `1` di `schedule_shift_types`.
- 2 baris `shift_templates` lama ternyata sudah soft-deleted — otomatis tidak ikut ter-copy (benar, bukan bug).
- 9 model Eloquent baru dibuat (`Schedule`, `ScheduleShiftType`, `ScheduleFixedDay`, `ScheduleFlexibleDay`, `ScheduleAssignment`, `ScheduleShiftAssignment`, `ScheduleShiftTemplate`, `ScheduleShiftTemplateDay`, `ScheduleShiftTemplateAssignment`), relasi disanity-check via tinker.
- Permission baru `CustomPermissionEnum::SISTEM_ABSENSI` ditambah & di-assign ke `ahyaghifari288@gmail.com` (di luar rencana awal dokumen ini, diminta user saat eksekusi).
- 2 penyesuaian teknis dari rencana awal: FK `org_structure_id` mengarah ke `m_org_structures` (bukan `org_structures`, nama tabel asli beda dari asumsi §2.1), dan kolom `schedule_shift_template_id` di `schedule_shift_template_assignments` dipersingkat jadi `shift_template_id` (nama constraint MySQL kena limit 64 karakter kalau dipanjangkan).

## 1. Latar Belakang

Rencana awal (`ScheduleType` enum FIXED/FLEXIBLE/SHIFT ditambah sebagai 1 kolom di `m_shifts`) **tidak cukup** — dari contoh nyata fingerspot.io (`knowledge/absensi/*.png`), Jadwal Fixed & Flexible punya struktur **7 baris (Senin-Minggu)** per jadwal, sedangkan Jadwal Shift punya struktur **N kode shift** (Pagi/Siang/Malam/dst) per jadwal — 2 bentuk yang tidak bisa dipaksa masuk 1 tabel flat seperti `m_shifts` sekarang. Assignment ke user juga beda: Fixed/Flexible cukup 1x assign (berlaku otomatis tiap hari sesuai aturan harinya), Shift butuh assign per-tanggal (roster, karena rotasinya berubah-ubah, lihat `CONTOH JADWAL SHIFT 2.png`).

**Keputusan user**: restrukturisasi total (bukan tambal), sekaligus hilangkan prefix `m_` untuk cluster tabel ini (preferensi user, bukan konvensi seluruh project — tabel `m_*` lain TIDAK ikut berubah, di luar scope ini).

## 2. Skema Tabel (4 rename + 5 baru + 2 FK-only, total 11 tabel tersentuh)

### 2.1 `schedules` (ganti `m_shifts`) — header/master jadwal

```php
Schema::create('schedules', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->string('name');                    // "JAM KERJA NORMAL", "Manager", "JADWAL NS 4B"
    $table->string('schedule_type');            // ScheduleType::FIXED|FLEXIBLE|SHIFT
    $table->foreignUlid('org_structure_id')->nullable()->constrained('org_structures')->nullOnDelete(); // level "departemen" (lihat §3 hikvision doc)
    $table->string('color')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`org_structure_id` dipindah dari `m_shifts` — tetap dipakai buat assignment 3-level (Perusahaan/Departemen/Perorangan) yang sudah direncanakan di dokumen Hikvision §3. `code` (dulu ada di `m_shifts`) **tidak dipindah ke sini** — kode (`P`/`S`/`M`) sekarang levelnya per-baris di `schedule_shift_types` (§2.4), bukan per-jadwal.

### 2.2 `schedule_fixed_days` — 7 baris per `schedules` (tipe FIXED)

```php
Schema::create('schedule_fixed_days', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
    $table->unsignedTinyInteger('day_of_week');  // 0=Minggu .. 6=Sabtu (pola Carbon::dayOfWeek)
    $table->boolean('is_off_day')->default(false);
    $table->time('start_time')->nullable();      // Masuk
    $table->unsignedSmallInteger('break_minutes')->default(0); // Istirahat
    $table->time('end_time')->nullable();        // Pulang
    $table->unsignedSmallInteger('overtime_before_min')->default(0);
    $table->unsignedSmallInteger('overtime_before_break')->default(0);
    $table->unsignedSmallInteger('overtime_before_max')->default(0);
    $table->unsignedSmallInteger('overtime_after_min')->default(0);
    $table->unsignedSmallInteger('overtime_after_break')->default(0);
    $table->unsignedSmallInteger('overtime_after_max')->default(0);
    // "Pengaturan Lanjutan" (§2.5, sama struktur di 3 tabel: ini, flexible_days, shift_types)
    $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
    $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
    $table->unsignedTinyInteger('counted_work_days')->default(1);
    $table->unsignedSmallInteger('max_work_minutes')->nullable();
    $table->unsignedSmallInteger('half_day_threshold_minutes')->default(0);
    $table->unsignedSmallInteger('absent_threshold_minutes')->default(0);
    $table->timestamps();
    $table->unique(['schedule_id', 'day_of_week']);
});
```

### 2.3 `schedule_flexible_days` — 7 baris per `schedules` (tipe FLEXIBLE)

```php
Schema::create('schedule_flexible_days', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
    $table->unsignedTinyInteger('day_of_week');
    $table->boolean('is_off_day')->default(false);
    $table->unsignedSmallInteger('break_minutes')->default(0);
    $table->unsignedSmallInteger('min_work_minutes')->default(0);   // ganti "MIN jam kerja"
    $table->unsignedSmallInteger('max_work_minutes')->default(0);   // ganti "MAX jam kerja"
    $table->unsignedSmallInteger('overtime_break_minutes')->default(0);
    $table->unsignedSmallInteger('overtime_min_minutes')->default(0);
    $table->unsignedSmallInteger('overtime_max_minutes')->default(0);
    // "Pengaturan Lanjutan" — sama 8 kolom seperti §2.2 (checkin/checkout buffer TIDAK relevan
    // di sini karena flexible sudah punya jendela absensi sendiri di level schedule, lihat §2.1
    // catatan; late/early_leave_tolerance juga tidak relevan karena flexible tidak punya jam
    // masuk/pulang tetap — TAPI tetap disediakan kolomnya biar 1 struktur konsisten dgn §2.2/§2.4,
    // nilainya default 0/diabaikan kalau tidak relevan untuk tipe ini).
    $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
    $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
    $table->unsignedTinyInteger('counted_work_days')->default(1);
    $table->unsignedSmallInteger('max_work_minutes_cap')->nullable(); // beda nama dari max_work_minutes di atas (itu target, ini CAP absolut)
    $table->unsignedSmallInteger('half_day_threshold_minutes')->default(0);
    $table->unsignedSmallInteger('absent_threshold_minutes')->default(0);
    $table->timestamps();
    $table->unique(['schedule_id', 'day_of_week']);
});
```

**Catatan jendela absensi Flexible** ("Jam mulai absensi 06:00" / "Jam akhir absensi 23:59" di `CONTOH JADWAL FLEXIBEL.png`) — itu levelnya **1 jadwal, bukan per hari**. Ditaruh di `schedules` sebagai 2 kolom nullable (`flexible_checkin_from`, `flexible_checkin_until`), relevan cuma kalau `schedule_type = FLEXIBLE`. **Perlu dikonfirmasi ke user** apakah memang selalu 1 jendela untuk semua hari, atau bisa beda per hari juga (dari screenshot cuma 1 jendela di atas tabel, jadi diasumsikan per-jadwal — tapi tetap tanya sebelum eksekusi, jangan tebak).

### 2.4 `schedule_shift_types` (ganti isi `m_shifts` lama) — N kode per `schedules` (tipe SHIFT)

```php
Schema::create('schedule_shift_types', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
    $table->string('code');           // P, S, M, P1, ...
    $table->string('name');           // Pagi, Siang, Malam, Pagi Karu, ...
    $table->time('start_time');       // Masuk
    $table->unsignedSmallInteger('break_minutes')->default(0);
    $table->time('end_time');         // Pulang
    $table->boolean('is_overnight')->default(false); // ganti deteksi manual end<start yang sudah ada
    $table->unsignedSmallInteger('overtime_before_min')->default(0);
    $table->unsignedSmallInteger('overtime_before_break')->default(0);
    $table->unsignedSmallInteger('overtime_before_max')->default(0);
    $table->unsignedSmallInteger('overtime_after_min')->default(0);
    $table->unsignedSmallInteger('overtime_after_break')->default(0);
    $table->unsignedSmallInteger('overtime_after_max')->default(0);
    // "Pengaturan Lanjutan" — sama 8 kolom §2.2
    $table->unsignedSmallInteger('checkin_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('checkout_buffer_minutes')->default(0);
    $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
    $table->unsignedSmallInteger('early_leave_tolerance_minutes')->default(0);
    $table->unsignedTinyInteger('counted_work_days')->default(1);
    $table->unsignedSmallInteger('max_work_minutes')->nullable();
    $table->unsignedSmallInteger('half_day_threshold_minutes')->default(0);
    $table->unsignedSmallInteger('absent_threshold_minutes')->default(0);
    $table->string('color')->nullable();  // dipindah dari `schedules` lama kalau warnanya per-kode, bukan per-jadwal (cek data existing dulu)
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['schedule_id', 'code']);
});
```

### 2.5 Field "Pengaturan Lanjutan" — 8 kolom, sama di 3 tabel (§2.2/§2.3/§2.4)

Diambil apa adanya dari `KONFIGURASI JADWAL FIXED/FLEXIBLE/SHIFT.png` (identik strukturnya di ketiga tipe):

| Kolom | Label UI | Arti |
|---|---|---|
| `checkin_buffer_minutes` | Batas mulai absensi masuk (menit) | Berapa menit SEBELUM jam masuk, scan sudah bisa dianggap valid |
| `checkout_buffer_minutes` | Batas akhir absensi pulang (menit) | Berapa menit SESUDAH jam pulang, scan masih dianggap valid |
| `late_tolerance_minutes` | Toleransi terlambat (menit) | Telat di bawah ini TIDAK dihitung telat sama sekali |
| `early_leave_tolerance_minutes` | Toleransi pulang cepat (menit) | Sama, buat pulang cepat |
| `counted_work_days` | Dihitung kerja Hari | Biasanya 1; kalau 2 berarti 1 hari absen dihitung 2 hari kerja (kasus khusus) |
| `max_work_minutes` | Maksimal Durasi Kerja (menit) | Cap — durasi kerja di atas ini dipotong ke nilai ini |
| `half_day_threshold_minutes` | Hadir setengah hari jika durasi kerja kurang dari (menit) | Di bawah ini → 0.5 hari kerja |
| `absent_threshold_minutes` | Dihitung tidak hadir jika durasi kerja kurang dari (menit) | Di bawah ini → 0 hari kerja (alpha) |

**Ini murni skema (kolom disediakan) — logika PEMAKAIAN 8 field ini di `computeMetricsFromCheckTimes()` TIDAK termasuk sesi restrukturisasi ini**, lihat §6.

### 2.6 `schedule_assignments` — assign Fixed/Flexible, dual-target: user ATAU jabatan (baru)

Fingerspot assign Fixed/Flexible langsung ke **jabatan** (`m_job_positions`, bukan cuma user), karena aturannya jarang berubah & biasanya berlaku ke semua orang di jabatan itu. Tapi tetap perlu bisa override ke 1 user spesifik (exception personal). Jadi 1 tabel, 2 kemungkinan target — **tepat 1 dari 2 kolom `user_id`/`job_position_id` harus terisi, tidak boleh keduanya/tidak ada sama sekali** (divalidasi di level aplikasi, bukan CHECK constraint DB — lebih mudah dites & pesan errornya lebih jelas ke admin):

```php
Schema::create('schedule_assignments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
    $table->foreignUlid('job_position_id')->nullable()->constrained('m_job_positions')->cascadeOnDelete();
    $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete();
    $table->date('effective_from');
    $table->date('effective_until')->nullable(); // NULL = berlaku terus sampai diganti
    $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

Beda total dari assignment Shift — **tidak per-tanggal**, cukup 1 baris per target (dengan riwayat kalau jadwalnya pernah ganti, lewat `effective_from`/`effective_until`). Resolusi "jadwal aktif user X hari ini": cari assignment `user_id = X` yang aktif (rentang tanggal cocok) dulu — **kalau ketemu, menang** (override personal); kalau tidak ada, baru fallback cari assignment `job_position_id = jabatan_user_X` yang aktif.

**Field "Pengaturan Lanjutan" TIDAK ada di tabel ini** — itu tetap di `schedule_fixed_days`/`schedule_flexible_days` (§2.2/§2.3), karena aturannya melekat ke jadwalnya (`schedules`), bukan ke siapa yang di-assign.

### 2.7 `schedule_shift_assignments` (ganti `shift_schedule_assignments`) — roster harian, tipe SHIFT

```php
Schema::create('schedule_shift_assignments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('company_id')->constrained('m_companies')->cascadeOnDelete();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignUlid('shift_type_id')->constrained('schedule_shift_types')->cascadeOnDelete(); // ganti shift_id
    $table->date('date');
    $table->string('notes')->nullable();
    $table->string('schedule_status')->nullable(); // kolom lama, arti/pemakaiannya dicek ulang saat implementasi
    $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->unique(['user_id', 'date']);
});
```

### 2.8 `schedule_shift_templates` (ganti `shift_templates`) — pola rotasi, tipe SHIFT

Sudah ada sebelumnya (`ShiftTemplates`+`ShiftTemplateDays`, dipakai `GenerateScheduleFromTemplate.php`) — bukan tabel baru, cuma direnamed + di-scope ulang ke `schedules` (bukan cuma `company_id`) supaya jelas 1 template rotasi itu punya kode-kode dari 1 grup jadwal tertentu:

```php
Schema::create('schedule_shift_templates', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('schedule_id')->constrained('schedules')->cascadeOnDelete(); // BARU — dulu cuma company_id
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedSmallInteger('cycle_length');
    $table->timestamps();
    $table->softDeletes();
});
```

### 2.9 `schedule_shift_template_days` (ganti `shift_template_days`)

```php
Schema::create('schedule_shift_template_days', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('schedule_shift_template_id')->constrained('schedule_shift_templates')->cascadeOnDelete();
    $table->unsignedSmallInteger('day_number'); // 1..cycle_length
    $table->foreignUlid('shift_type_id')->nullable()->constrained('schedule_shift_types')->cascadeOnDelete(); // ganti shift_id
    $table->boolean('is_off_day')->default(false);
    $table->timestamps();
});
```

### 2.10 `schedule_shift_template_assignments` (baru) — keanggotaan rotasi PERMANEN, biar nyambung lintas bulan

**Masalah yang diselesaikan tabel ini**: kalau "generate roster" selalu minta admin isi `start_date` manual tiap kali (pola `GenerateScheduleFromTemplate.php` existing), rotasi akan **RESET tiap kali digenerate ulang per periode** (misal per bulan) — padahal seharusnya nyambung terus dari bulan sebelumnya, cuma beda "hari ke berapa di siklus" pas dilihat dari tanggal manapun. Solusinya: simpan **1 titik jangkar (anchor) permanen per orang**, tidak dihitung ulang tiap generate.

```php
Schema::create('schedule_shift_template_assignments', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignUlid('shift_template_id')->constrained('schedule_shift_templates')->cascadeOnDelete(); // dipersingkat dari schedule_shift_template_id, kena limit nama constraint MySQL (64 char) di tabel ini
    $table->date('anchor_date');       // tanggal yang dianggap "hari ke-1" siklus — TIDAK BERUBAH walau digenerate berkali-kali
    $table->date('effective_until')->nullable(); // NULL = masih aktif; diisi kalau orangnya pindah rotasi/keluar grup
    $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**Cara kerja generate per periode** (menggantikan alur `GenerateScheduleFromTemplate.php` yang minta `start_date` manual tiap kali):
1. Admin pilih "Periode Penjadwalan" (bulan, bukan rentang tanggal bebas — sesuai pola fingerspot `CONTOH JADWAL SHIFT 2.png`).
2. Sistem ambil semua `schedule_shift_template_assignments` yang aktif di bulan itu (`anchor_date <= akhir_bulan` DAN (`effective_until IS NULL` ATAU `effective_until >= awal_bulan`)).
3. Untuk tiap baris & tiap tanggal di bulan itu: `hari_ke = (tanggal - anchor_date) % cycle_length + 1` (posisi dihitung dari `anchor_date` masing-masing orang, BUKAN dari tanggal 1 bulan yang di-generate — inilah yang bikin rotasi nyambung mulus lintas bulan).
4. Upsert hasil ke `schedule_shift_assignments` (§2.7) — **TAPI cuma `insertOrIgnore`, JANGAN overwrite baris yang sudah ada** (lihat §2.11).

**Rotasi grup (beberapa orang bergilir bersama, contoh diskusi: 4 orang gilir Libur→Pagi→Malam→Sore)** — dicapai dengan cara yang sama, cuma `anchor_date` tiap orang digeser konsisten sejumlah "1 langkah siklus" dari orang berikutnya (bukan struktur tabel yang beda). Tidak perlu tabel/kolom tambahan buat kasus ini.

**Fitur UI menyusul (di luar scope skema ini, dicatat sebagai usulan)**: "buat grup rotasi" — admin pilih N orang + urutkan + 1 template, sistem otomatis hitung & isi `anchor_date` tiap orang (geser 1 langkah masing-masing) tanpa admin hitung manual. Ini cuma kemudahan UI, tidak mengubah struktur tabel di atas.

### 2.11 Aturan generate: `insertOrIgnore`, JANGAN PERNAH overwrite

Auto-generate (baik dari §2.10 maupun tombol manual `GenerateScheduleFromTemplate` yang sudah ada) **wajib** cuma mengisi tanggal yang **belum ada baris assignment-nya sama sekali** di `schedule_shift_assignments`. Kalau baris utk `user_id`+`date` itu sudah ada — dilewati, tidak disentuh. Alasan: baris yang sudah ada bisa jadi hasil **tukar shift** (`shift_swap_requests`, fitur existing) atau **koreksi manual admin** — kalau di-timpa ulang oleh job generate bulan berikutnya, perubahan itu hilang diam-diam. Pola ini sudah dikenal di kode existing (`CreateScheduleShift.php` punya toggle "Timpa Jadwal yang Ada" — override manual boleh pilih menimpa, tapi auto-generate dari template/anchor defaultnya WAJIB `insertOrIgnore`).

## 3. Migrasi Data (`m_shifts` lama → struktur baru)

**Tidak destruktif tanpa perpindahan data** — semua isi `m_shifts` (4 baris lokal) jadi kandidat tipe SHIFT (karena strukturnya memang shift-code, bukan day-of-week):

1. Untuk tiap baris `m_shifts` lama: buat 1 `schedules` baru (`schedule_type = 'shift'`, `name` = nama shift lama atau nama group kalau ada — **perlu keputusan user**: apakah 4 shift lama (Pagi/Siang/Malam/dst) digabung jadi 1 `schedules` "Jadwal Shift Default" dengan 4 `schedule_shift_types`, atau tiap shift lama jadi `schedules` terpisah 1-kode? **Kemungkinan besar opsi pertama** (gabung 1 grup) sesuai pola fingerspot, tapi WAJIB dikonfirmasi user sebelum eksekusi — bisa jadi tiap company perlu 1 grup per departemen/shift-set, tidak otomatis 1 grup semua).
2. Tiap baris `m_shifts` → 1 baris `schedule_shift_types` (copy `code`/`name`/`start_time`/`end_time`/`is_overnight`/`color`), FK ke `schedules` hasil langkah 1.
3. `shift_schedule_assignments.shift_id` → `schedule_shift_assignments.shift_type_id` (mapping 1:1 by `m_shifts.id` → `schedule_shift_types.id` hasil langkah 2).
4. `shift_overtime_costs.shift_id` → `shift_type_id` (mapping sama).
5. `shift_template_days.shift_id` → `shift_type_id` (mapping sama).
6. `shift_swap_requests.req_schedule_id`/`target_schedule_id` (FK ke assignment lama) → repoint ke `schedule_shift_assignments` hasil migrasi (ID assignment TIDAK berubah kalau migrasi cukup UPDATE kolom FK-nya saja tanpa re-generate ULID, jadi baris ini kemungkinan tidak perlu disentuh — perlu verifikasi nama tabel assignment-nya di migration `shift_swap_requests` apakah FK constraint-nya explicit ke `shift_schedule_assignments` atau cuma UUID polos tanpa constraint).
7. Drop tabel lama (`m_shifts`) + rename tabel (`shift_schedule_assignments`→`schedule_shift_assignments` bisa pakai `Schema::rename()` kalau strukturnya cukup dekat, atau create-baru+migrate+drop kalau kolomnya berubah banyak — keduanya valid, pilih saat implementasi berdasar seberapa besar bedanya).

**Prinsip keamanan** (sama seperti payroll Fase 1): migration data + migration schema + kode baru **1 paket, 1 deploy** — tidak ada window `m_shifts` sudah hilang tapi kode masih baca dia.

## 4. Kode yang Kena Dampak (butuh diubah, ditemukan lewat grep `shift_id`/`ShiftScheduleAssignments`/`MasterShift`)

- `app/Models/Shift/MasterShift.php` → jadi `Schedule` + `ScheduleShiftType` (2 model baru, `MasterShift` lama dihapus).
- `app/Models/Shift/ShiftScheduleAssignments.php` → `ScheduleShiftAssignment` (rename + FK `shift_type_id`).
- `app/Models/Shift/ShiftOvertimeCosts.php` — update relasi FK `shift_id` → `shift_type_id`, model TIDAK perlu rename.
- `app/Models/Shift/ShiftSwapRequests.php` — cek ulang FK `req_schedule_id`/`target_schedule_id`, pastikan tetap nyambung ke `schedule_shift_assignments` hasil migrasi (§3 poin 6), model TIDAK perlu rename.
- `app/Models/Shift/ShiftTemplates.php`/`ShiftTemplateDays.php` → `ScheduleShiftTemplate`/`ScheduleShiftTemplateDay` (rename ikut tabel, §2.8/§2.9), tambah relasi baru ke `ScheduleShiftTemplateAssignment` (§2.10).
- Model baru `ScheduleShiftTemplateAssignment` (§2.10) + job/action generate bulanan (`GenerateScheduleFromTemplate.php` dirombak total: dari "isi start_date manual" jadi "pilih Periode Penjadwalan (bulan) → baca anchor permanen → insertOrIgnore", lihat §2.10-§2.11).
- `app/Services/Payroll/Calculators/LemburCalculator.php` — method `processDetail()` (branch shift) ganti query `ShiftScheduleAssignments`/`ShiftOvertimeCosts` ke model baru.
- `app/Services/Attendance/AttendanceImportService.php` — `computeMetricsFromCheckTimes()` baca `$shift->start_time`/`end_time`/`is_overnight` dari `ScheduleShiftType` (bukan lagi `MasterShift`), TAPI logic tolerance/buffer BELUM disambungkan (§6, di luar scope).
- `app/Filament/Resources/ScheduleShifts/*`, `ShiftTemplates/*`, `UserShiftSchedules/*`, `Widgets/ShiftCalendarWidget.php` — semua reference model/tabel lama, perlu diaudit satu-satu saat implementasi (bukan cuma rename import, beberapa query eksplisit ke nama tabel).
- Permission Shield — `shield:generate` ulang setelah Resource baru untuk `Schedule`/`ScheduleShiftType`/`ScheduleAssignment` dibuat.

**Catatan**: daftar ini dari grep awal, BUKAN hasil audit baca-kode-penuh — saat eksekusi, baca tiap file yang disebut sebelum mengubah (pola yang sudah dipakai konsisten di sesi payroll kemarin), jangan asumsi cuma rename import selesai.

## 5. Yang TIDAK termasuk dokumen ini

- Resource/UI baru untuk kelola `schedules`+tabel anak (mengganti Resource `MasterShift`/`ShiftTemplates` lama) — desain UI-nya menyusul, dokumen ini fokus skema dulu.
- Halaman "Periode Penjadwalan" (dropdown bulan, pola `CONTOH JADWAL SHIFT 2.png`) yang memanggil generate dari §2.10 — juga UI, menyusul.
- Fitur "buat grup rotasi" (auto-hitung `anchor_date` bergeser buat N orang sekaligus) — disebut di §2.10 sebagai usulan, belum didesain detail.
- Assignment 3-level (Perusahaan/Departemen/Perorangan, `org_structure_id`) untuk Fixed/Flexible — kolomnya sudah disediakan di `schedules` (§2.1), dan sekarang assignment jabatan (§2.6) sudah menutupi level "Departemen/Jabatan" — logic resolusi presedensi (User > Jabatan > default company) sudah dijelaskan di §2.6, tapi implementasi kodenya belum ditulis.
- Job terjadwal (cron) yang otomatis jalanin generate tiap awal bulan — §2.10 baru desain data & logic-nya, belum jadi job Laravel Scheduler beneran.
- Integrasi Hikvision (`access_logs` → mapping `employee_id`↔`user_id`) — workstream terpisah, lihat dokumen Hikvision.
- Halaman "Kantor" (masih pertanyaan terbuka dari diskusi awal, belum dijawab).

## 6. Yang Sengaja Ditunda (bukan lupa)

**Logika pemakaian 8 field "Pengaturan Lanjutan" di `computeMetricsFromCheckTimes()`** — skemanya (§2.5) disiapkan sesi ini, tapi menyambungkannya ke perhitungan status hadir/telat/setengah-hari/alpha yang sekarang masih exact-minute tanpa toleransi, adalah **perubahan logic terpisah** dari restrukturisasi tabel. Digabung sekaligus berisiko bug tercampur antara "salah pindah data" vs "salah logic baru" — lebih aman 2 tahap: (1) beres-beres tabel dulu (dokumen ini), (2) baru logic toleransi jalan di atas tabel yang sudah rapi.

## 7. Pertanyaan yang Wajib Dijawab Sebelum Eksekusi

1. 4 baris `m_shifts` existing (Pagi/Siang/Malam/dst) — digabung jadi 1 `schedules` grup, atau tetap terpisah per shift (tiap jadi `schedules` 1-kode)?
2. Jendela absensi Flexible (`flexible_checkin_from`/`until`) — 1 per jadwal (seperti asumsi §2.3) atau bisa beda per hari?
3. `color` di `m_shifts` lama — dipakai per shift individual (pindah ke `schedule_shift_types`, seperti diasumsikan §2.4) atau sebenarnya mau jadi warna per `schedules` grup (pindah ke §2.1)? Perlu cek data existing dulu.

## 8. Referensi
- `knowledge/markdown/rencana-integrasi-hikvision-dan-pola-jadwal.md` — latar belakang & keputusan awal yang direvisi dokumen ini.
- `knowledge/absensi/*.png` — 7 screenshot fingerspot.io yang jadi rujukan bentuk tabel.
- `app/Models/Shift/*.php`, `app/Services/Attendance/AttendanceImportService.php`, `app/Services/Payroll/Calculators/LemburCalculator.php` — kode existing yang kena dampak.
