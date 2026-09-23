# SP (Surat Peringatan) & Merit Point — planning HRIS

> Dibuat 2026-09-23, direvisi 2026-09-23 (putaran kedua setelah feedback user). Acuan awal:
> `knowledge/markdown/hris-sp-dan-merit-point-demo.md` (dokumen generik, ditulis tanpa lihat kode —
> dipakai sebagai referensi kebutuhan bisnis, BUKAN dicontek literal skema/nama kolomnya). Planning
> ini yang jadi rujukan implementasi, sudah disesuaikan ke convention project nyata (lihat §0).
>
> **STATUS: DRAFT, BELUM DISETUJUI.** Belum ada kode yang ditulis. Menunggu review user.

---

## 0. Penyesuaian dari dokumen acuan ke convention project

| Di dokumen acuan | Di project ini | Alasan |
|---|---|---|
| `employee_id` | `user_id` | Project tidak punya tabel `employees`, employee = `users` (lihat `coachings.user_id`). Sudah dikonfirmasi user: 1 orang = 1 akun, tidak ada kebutuhan pisah tabel identitas/karyawan. |
| `employee_sp` (singular) | `employee_sps` | Semua tabel project plural (`coachings`, `attendance_devices`). |
| `source_type` sebagai string bebas | PHP backed enum `implements HasLabel` | Pola `CoachingStatus`/`AttendanceReqStatus` — dapat label & warna otomatis di Filament gratis. |
| SP1/SP2 durasi 90/180 hari **hardcode** | Setting per company (`PembinaanSettingEnum`, pola `KepegawaianSettingEnum`) | Project selalu bikin angka bisnis begini jadi Setting company-scoped (`Setting::resolve()`), bukan konstanta di kode. Default tetap 90/180 kalau belum diisi. |
| Auth "ikuti permission existing" (generik) | Filament Shield auto-gen `ViewAny:EmployeeSp` dkk, gerbang modul **gabung ke `SISTEM_PEMBINAAN_KARYAWAN`** (dikonfirmasi user, §2.1) | Pola persis Coaching (`CoachingPolicy` + `SISTEM_PEMBINAAN_KARYAWAN`). |
| Notifikasi tidak dibahas detail | `App\Notifications\*` + `NotificationTypeEnum` + `NotificationPreferenceService` (WebPush + database) | Infra sudah ada & dipakai Coaching, tinggal ikut. |
| "Employee response" cukup 1 kolom nullable | Tetap dipakai apa adanya (`employee_response`, `response_date`) di `employee_sps` — **BUKAN** ditambahkan ke Coaching | Coaching sudah sengaja dibuat satu arah tanpa tanggapan karyawan (2026-09-22). SP itu entity beda, boleh punya tanggapan karyawan sendiri. |
| Trigger otomatis dari Attendance | **Ditunda seluruhnya** (dikonfirmasi user: "sp_recommendations tidak perlu dulu yang otomatis") | Belum ada aturan bisnis konkret. Jalur manual (dari Coaching / HR langsung) dibangun duluan, attendance-trigger jadi fase terpisah nanti. |
| Balance/status dihitung on-the-fly murni | **Ada tabel cache `user_hr_balances`** (dikonfirmasi user, lihat §3.5) | Permintaan eksplisit user untuk performa tampilan widget Karir — bukan cuma ikut prinsip lama "hitung dari data". |

---

## 1. Coaching: Tindak Lanjut + section "Tindakan Lanjutan"

Dua kebutuhan baru di `CoachingDetailPage`, dua-duanya independen satu sama lain:

1. **Tindak Lanjut** — evaluasi coach soal kondisi karyawan (tetap seperti draft awal, 2 pilihan,
   dikonfirmasi user).
2. **Tindakan Lanjutan** (nama beda, jangan tertukar) — section collapsible baru berisi aksi
   konkret yang bisa diambil coach: **beri SP** (dibangun sekarang) dan **beri Merit Point manual**
   (didokumentasikan di sini, **belum dibangun** — lihat §1.3).

### 1.1 Tindak Lanjut (evaluasi kondisi)

Field baru di `coachings`, isi cuma boleh `coach_by`:

| kolom | tipe | catatan |
|---|---|---|
| `follow_up_status` | string nullable | enum `CoachingFollowUpStatus`: `IMPROVED` (sudah membaik) \| `NEEDS_FOLLOW_UP` (butuh coaching lanjutan) — **tetap 2 pilihan**, dikonfirmasi user |
| `follow_up_note` | text nullable | catatan coach soal kondisi terkini |
| `follow_up_at` | timestamp nullable | kapan evaluasi diisi |

Opsional, sekadar catatan kondisi — **TIDAK lagi jadi syarat/gerbang buat aksi SP** (beda dari draft
pertama). Kalau `NEEDS_FOLLOW_UP`, UI cukup kasih tombol pintasan "Buat Coaching Lanjutan" (prefill
`CreateCoachingPage` dengan `user_id` sama) — bukan pemicu SP.

### 1.2 Tindakan Lanjutan — beri SP

**Perubahan penting dari draft pertama:** SP TIDAK lagi menunggu `follow_up_status =
NEEDS_FOLLOW_UP`. Section "Tindakan Lanjutan" tersedia di `CoachingDetailPage` **sejak coaching
pertama kali dibuat**, kapan saja, independen dari status tindak lanjut (dikonfirmasi user).

```
CoachingDetailPage (coach_by yang login)
└─ ▸ Tindakan Lanjutan (collapsible, default tertutup)
     └─ [ Rekomendasikan SP ]
          → form: level SP + alasan (dari sp_reasons) + catatan
          → bikin sp_recommendations (source_type=COACHING, source_id=coachings.id, decision=pending)
          → HR tetap review (lihat §2.4) - coach TIDAK bisa terbitkan SP sendiri
```

Kenapa section terpisah dari kartu "Bukti"/info lain di halaman yang sama: aksi ini beda sifat
(mengubah data lain, butuh konfirmasi/form), collapsible-default-tertutup supaya halaman detail
tidak penuh untuk coaching yang tidak butuh tindakan apa pun (mayoritas kasus).

### 1.3 Tindakan Lanjutan — beri Merit Point manual (DIDOKUMENTASIKAN, BELUM DIBANGUN)

User ingin section yang sama nantinya juga punya `[ Beri Merit Point ]` — coach bisa kasih
poin manual langsung dari record coaching (mis. karyawan yang tadinya bermasalah sekarang membaik,
kasih reward). Ini kemungkinan butuh approval juga (sama seperti SP), tapi **untuk fase sekarang
TIDAK dibangun** — cukup SP dulu di section ini (keputusan eksplisit user). Ditulis di sini supaya
tidak hilang dari rencana, bukan berarti bagian dari scope MVP.

Kalau nanti dibangun, pertanyaan yang perlu dijawab duluan: approval-nya lewat jalur sama dengan
`sp_recommendations` (butuh tabel serupa `merit_point_recommendations`?) atau langsung tercatat
tapi ditandai "menunggu review" di `merit_point_transactions` itu sendiri? **Belum diputuskan,
jangan asumsikan saat waktunya tiba.**

### 1.4 Migration

```php
Schema::table('coachings', function (Blueprint $table) {
    $table->string('follow_up_status')->nullable()->after('improvement_due_date');
    $table->text('follow_up_note')->nullable()->after('follow_up_status');
    $table->timestamp('follow_up_at')->nullable()->after('follow_up_note');
});
```

---

## 2. SP (Surat Peringatan)

### 2.1 Keputusan

| Topik | Keputusan | Alasan |
|---|---|---|
| Recommendation sebagai entity terpisah? | **Tetap ada** (`sp_recommendations`) — HR butuh satu tempat review sebelum SP resmi terbit, walau sumbernya sekarang cuma manual (Coaching/HR langsung), bukan otomatis. | Approval tetap dibutuhkan (dikonfirmasi user: "manual ini sepertinya butuh approval"). |
| Modul/gerbang | **Gabung `SISTEM_PEMBINAAN_KARYAWAN`** — dikonfirmasi user, bukan gerbang baru. | SP adalah kelanjutan Coaching, satu tim HC yang sama pegang keduanya. |
| Master alasan (`sp_reasons`) | Dibuat, sesuai dokumen acuan §1.2. | — |
| Durasi SP1/SP2 | Setting company-scoped (`PembinaanSettingEnum`, §0), default 90/180 hari. SP3 selalu `end_date = NULL`. | — |
| Status SP (aktif/expired) | Dihitung dari tanggal secara live untuk keperluan BISNIS (validasi, list "SP aktif" di HR) — TAPI juga di-cache ke `user_hr_balances` khusus buat tampilan cepat di widget Karir (lihat §3.5, ini bukan sumber kebenaran). | Live-calc tetap jadi source of truth; cache cuma buat 1 widget yang butuh gampang dibaca. |
| Tanggapan karyawan | 1 kolom `employee_response` + `response_date` di `employee_sps`. | — |
| Halaman HR | Filament Resource `EmployeeSpResource` (list + create + view), pola persis `CoachingResource`. | — |
| Halaman karyawan | Livewire portal baru `SuratPeringatanPage` (list) + detail (lihat SP aktif, isi tanggapan) — route baru, bukan ditumpuk ke halaman Coaching Saya. | SP itu legal/formal, harus kelihatan sebagai bagian tersendiri di navigasi. |

### 2.2 Tabel

#### `sp_reasons`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `code` | string unique | `LATE_ATTENDANCE`, `UNAUTHORIZED_ABSENCE`, dst |
| `name` | string | label Indonesia |
| `is_active` | boolean default true | |
| timestamps | | |

Seed awal (persis dokumen acuan §1.2): Keterlambatan, Absen Tanpa Keterangan, Tidak Mengikuti
Instruksi, Pelanggaran SOP, Perilaku Tidak Pantas.

#### `sp_recommendations`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `user_id` | foreignUlid → `users`, cascadeOnDelete | |
| `company_id` | foreignUlid → `m_companies`, cascadeOnDelete | |
| `sp_level` | string | enum `SpLevel`: `SP1`\|`SP2`\|`SP3` (saran awal, HR boleh ubah saat review) |
| `reason_id` | foreignUlid → `sp_reasons`, restrictOnDelete | |
| `source_type` | string | enum `SpSourceType`: `COACHING`\|`MANUAL` (`ATTENDANCE` ditambahkan nanti kalau fase attendance-trigger dikerjakan, lihat §5) |
| `source_id` | ulid nullable | id record sumber (`coachings.id` kalau `COACHING`), tanpa FK polymorphic |
| `reason_note` | text | catatan/detail kejadian |
| `decision` | string nullable | `pending`\|`approved`\|`rejected`, default `pending` |
| `reviewed_by` | foreignUlid → `users` nullable | |
| `reviewed_at` | timestamp nullable | |
| `employee_sp_id` | foreignUlid → `employee_sps` nullable | terisi kalau `decision = approved` |
| timestamps | | |

Index: `(company_id, decision)`, `(user_id)`.

#### `employee_sps`

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `user_id` | foreignUlid → `users`, cascadeOnDelete | |
| `company_id` | foreignUlid → `m_companies`, cascadeOnDelete | |
| `sp_level` | string | enum `SpLevel` |
| `reason_id` | foreignUlid → `sp_reasons`, restrictOnDelete | |
| `source_type` | string | enum `SpSourceType` |
| `source_id` | ulid nullable | |
| `reason_note` | text | |
| `start_date` | date | |
| `end_date` | date nullable | null = SP3 (tidak pernah expired) |
| `employee_response` | text nullable | |
| `response_date` | timestamp nullable | |
| `issued_by` | foreignUlid → `users`, restrictOnDelete | HR yang menerbitkan |
| `issued_at` | timestamp | |
| timestamps | | |

Index: `(user_id)`, `(company_id, start_date, end_date)`.

SP aktif (dipakai bisnis/validasi, tetap live-query, BUKAN dari cache):
```sql
start_date <= CURRENT_DATE AND (end_date IS NULL OR end_date >= CURRENT_DATE)
```

### 2.3 Sumber rekomendasi

**Dari Coaching** (§1.2): tombol "Rekomendasikan SP" di section Tindakan Lanjutan, tersedia kapan
saja sejak coaching dibuat — tidak lagi digerbang status tindak lanjut.

**Manual**: HR buat langsung dari `EmployeeSpResource`, tanpa lewat `sp_recommendations`
(`Create:EmployeeSp` langsung ke `employee_sps`, `source_type=MANUAL`).

**Dari Attendance**: **DITUNDA sepenuhnya**, tidak dikerjakan di fase ini (dikonfirmasi user). Lihat
§5 kalau nanti mau dibuka lagi.

### 2.4 Review HR

List `sp_recommendations` dengan `decision=pending`, tombol Setujui/Tolak:
- **Setujui** → transaksi: bikin `employee_sps` (isi level+reason+source dari recommendation,
  `start_date` = hari ini kecuali HR override, `end_date` dihitung dari Setting), update
  `sp_recommendations.decision=approved` + `employee_sp_id`, refresh `user_hr_balances` (§3.5),
  kirim notifikasi ke karyawan (`SpIssued`).
- **Tolak** → `sp_recommendations.decision=rejected`, tidak ada `employee_sps` yang dibuat.

---

## 3. Merit Point (tetap berstatus DEMO)

### 3.1 Toggle per company

**Baru** (dikonfirmasi user: "setiap company nggak diterapkan juga merit point"). Setting boolean
company-scoped, `PembinaanSettingEnum::MERIT_POINT_ENABLED` (pola `SettingTypeEnum::BOOLEAN`,
modul baru `SettingModuleEnum::PEMBINAAN` menaungi setting SP + Merit Point sekaligus, ganti nama
dari usulan draft pertama `SpSettingEnum`/`MeritPointSettingEnum` terpisah — satu modul "Pembinaan"
lebih match gerbang `SISTEM_PEMBINAAN_KARYAWAN` yang juga digabung).

Semua UI Merit Point (widget di tab Karir, menu rule, tombol beri poin) **disembunyikan total**
kalau `MERIT_POINT_ENABLED = false` untuk company itu — bukan cuma disabled/abu-abu. Default
**false** (opt-in per company, bukan otomatis aktif untuk semua).

### 3.2 Tabel

#### `merit_point_rules`
`id` ulid, `company_id` **wajib diisi, tidak nullable** (dikonfirmasi user: per company, bukan
global), `type` (enum `MeritPointType`: `REWARD`\|`PENALTY`\|`CONTRIBUTION`), `name`, `points`
(signed int), `trigger_type` (enum `MeritPointTriggerType`: `AUTOMATIC`\|`MANUAL`), `is_active`,
timestamps.

#### `merit_point_transactions`
`id` ulid, `user_id` FK cascadeOnDelete, `rule_id` FK nullable restrictOnDelete, `points` (signed
int, snapshot — tidak ikut berubah walau rule-nya diedit belakangan), `reason` text, `source_type`
string nullable, `source_id` ulid nullable, `created_by` FK nullable, `created_at` (tanpa
`updated_at` — ledger tidak pernah diedit).

Index: `(user_id)`.

### 3.3 Seed demo

Persis dokumen acuan §6, ditandai jelas sebagai demo (komentar + mungkin suffix nama), dibuat untuk
1 company contoh saja (karena sekarang `company_id` wajib per rule) — bukan disebar ke semua
company otomatis (konsisten §3.1: opt-in).

### 3.4 Alur tetap 3 cara (dokumen acuan §8), tidak berubah

Automatic Rule / Manual pakai Rule / Manual Bebas — tidak ada penyesuaian dari draft pertama.

### 3.5 Tabel cache `user_hr_balances`

**Baru, permintaan eksplisit user.** 1 baris per user, isi ringkasan SP + Merit Point saat ini,
dibaca widget Karir tanpa perlu agregasi ulang tiap render.

| kolom | tipe | catatan |
|---|---|---|
| `id` | ulid PK | |
| `user_id` | foreignUlid → `users`, unique, cascadeOnDelete | 1:1 dengan user |
| `merit_point` | unsigned tinyint, default 0 | cache `clamp(SUM(merit_point_transactions.points), 0, 100)` |
| `active_sp_level` | string nullable | cache level SP aktif TERTINGGI saat baris ini terakhir di-refresh |
| `active_sp_id` | foreignUlid → `employee_sps` nullable | pointer ke baris `employee_sps` yang jadi sumber `active_sp_level` |
| `updated_at` | timestamp | kapan cache terakhir diperbarui |

**Ini CACHE, bukan sumber kebenaran** — sengaja ditulis eksplisit di sini karena beda dari prinsip
lama project ("hitung dari data, jangan simpan status", dipakai di Cuti/Kontrak/SP §2.2). Konsekuensi
yang harus disadari:

- `merit_point` selalu akurat karena cuma berubah lewat event eksplisit (transaksi baru) — di-refresh
  tiap kali `merit_point_transactions` baru dibuat, tidak ada celah basi.
- `active_sp_level` **BISA BASI** — SP kadaluarsa murni karena tanggal lewat (`end_date < today`),
  TANPA ada event/write apa pun. Kalau tidak ada job yang jalan, baris ini bisa terus nunjukin SP
  yang sebenarnya sudah expired kemarin.
- **Mitigasi**: job terjadwal harian (scheduler `bootstrap/app.php`, jalan sekali semalam, murah -
  cuma query per user yang punya `active_sp_id` terisi) yang recompute `active_sp_level`/
  `active_sp_id` dari `employee_sps` live. Ditambah refresh eager tiap kali `employee_sps` baru
  dibuat (§2.4).
- Semua VALIDASI/KEPUTUSAN BISNIS (mis. cek SP aktif buat aturan lain nanti) **HARUS** query live
  ke `employee_sps`, **TIDAK BOLEH** baca dari `user_hr_balances` — tabel ini murni buat tampilan.

### 3.6 UI — widget di tab Karir (Profil Saya)

**Bukan tab baru.** Digabung ke tab "Karir" yang sudah ada (`EmployeeAttendanceStatistics`, label
"Karir" untuk diri sendiri / "Pekerjaan" untuk lihat orang lain) — sesuai instruksi user, bersebelahan:

```
┌─────────────────────┐  ┌─────────────────────┐
│   MERIT POINT        │  │   SURAT PERINGATAN   │
│                       │  │                       │
│    ╭─────────╮        │  │        0              │
│   │  ● 100  │  (gauge  │  │   (atau badge SP1/2/3 │
│    ╰─────────╯   hijau │  │    kalau ada yg aktif)│
│      berkilau)         │  │                       │
└─────────────────────┘  └─────────────────────┘
```

- Kiri: Merit Point, indikator lingkaran/gauge bergaya "cairan"/glow (nilai tinggi = hijau
  berkilau, makin rendah = warna makin redup/kearah amber-merah — bahasa visual "kesehatan").
- Kanan: SP aktif — `0` polos kalau bersih (netral, tidak menakutkan), badge SP1/SP2/SP3 berwarna
  eskalatif (kuning→oranye→merah) kalau ada yang aktif.
- **Section ini otomatis hilang** kalau company tidak mengaktifkan `MERIT_POINT_ENABLED` — tapi SP
  tetap tampil sendirian (SP bukan bagian dari toggle Merit Point, dua fitur independen).
- **Tombol riwayat** (dijawab §5.2): masing-masing widget (Merit Point & SP) punya ikon kecil
  "riwayat" di pojok card — buka riwayat lengkap (Merit Point: semua `merit_point_transactions`
  user; SP: semua `employee_sps` user termasuk yang sudah expired, bukan cuma yang aktif). Bentuk
  tampilan riwayat (modal/halaman terpisah) belum diputuskan, tentukan pas build UI.
- Detail visual (gradient, animasi, dsb) dikerjakan di fase build UI, bukan bagian planning ini —
  pas waktunya, load skill `dataviz`/`frontend-design` dulu sebelum nulis widget-nya biar
  konsisten sama palet & prinsip desain project.

---

## 4. Yang TIDAK dikerjakan sekarang (non-goals)

Ikut dokumen acuan §16, plus:
- **Beri Merit Point manual dari section Tindakan Lanjutan Coaching** — didokumentasikan (§1.3),
  belum dibangun.
- **Trigger SP otomatis dari Attendance** — didokumentasikan (§2.3), ditunda total.
- **Approval untuk transaksi Merit Point** — belum ada konsepnya sama sekali, nunggu §1.3 jelas dulu.
- **UI khusus buat coach "menutup" coaching** — cukup isi Tindak Lanjut, coaching tidak punya status
  "closed"/"open" terpisah (YAGNI, ikuti keputusan 2026-09-22: Coaching tanpa status).
- **Auto-reject SP recommendation kalau kadaluarsa** — HR yang tidak review tetap menumpuk di
  `pending`, tidak ada job pembersih otomatis.

---

## 5. Pertanyaan terbuka — masih perlu dijawab

1. **Trigger otomatis dari Attendance** (kalau/kapan dibuka lagi) — aturan konkretnya apa, dihitung
   per-bulan-kalender atau rolling window. Sengaja tidak diputuskan di fase ini.
2. ~~SP karyawan boleh lihat riwayat SP yang sudah expired, atau cuma yang aktif?~~ **DIJAWAB
   (2026-09-23)**: karyawan boleh lihat riwayat lengkap (termasuk expired), bukan cuma yang aktif.
   Kedua widget (Merit Point & SP) di tab Karir dapat tombol ikon kecil "riwayat" (bukan tombol
   teks besar) yang buka daftar riwayat — Merit Point → riwayat `merit_point_transactions` milik
   user, SP → riwayat `employee_sps` milik user (termasuk yang sudah `end_date` lewat). Detail
   halaman/modal riwayat (modal vs halaman terpisah) dikerjakan di fase build UI (§3.6), bukan
   diputuskan di planning ini.
3. **Job refresh `user_hr_balances`** (§3.5) — jadwal harian cukup, atau perlu lebih sering? Dan
   apakah butuh tombol "Refresh Manual" di admin buat jaga-jaga kalau job sempat gagal jalan.
4. **Beri Merit Point dari Tindakan Lanjutan Coaching** (§1.3) — kapan mau dibangun, dan alur
   approval-nya seperti apa (ikut pola `sp_recommendations`, atau beda).

---

## 6. Urutan eksekusi (kalau disetujui)

1. Migration + model + enum: SP (`sp_reasons`, `sp_recommendations`, `employee_sps`), Coaching
   follow-up (§1.4), `user_hr_balances` (§3.5), Setting `PembinaanSettingEnum` (durasi SP + toggle
   Merit Point) — base data layer, tanpa UI dulu.
2. `sp_reasons` seed + `EmployeeSpResource` (Filament, HR bikin SP manual langsung) — jalur paling
   sederhana duluan, bisa didemokan cepat.
3. Section "Tindakan Lanjutan" (SP saja) + Tindak Lanjut di `CoachingDetailPage`.
4. Halaman review `sp_recommendations` (HC).
5. Portal karyawan `SuratPeringatanPage` (lihat SP aktif + isi tanggapan).
6. Notifikasi (`SpIssued`, dst) + sinkronisasi `user_hr_balances` di titik-titik terkait (SP
   disetujui) + job refresh harian.
7. Merit Point: migration rules+transactions (kalau belum ikut langkah 1), toggle setting, seed
   demo, UI tambah transaksi (HC), sinkronisasi `user_hr_balances` tiap transaksi baru.
8. Widget tab Karir (Merit Point + SP bersebelahan) — **load skill `dataviz`/`frontend-design`
   dulu** sebelum membangun, baru gabung Merit Point + SP dari `user_hr_balances`.
9. (Ditunda, fase terpisah nanti) Trigger otomatis Attendance → `sp_recommendations`, dan Beri
   Merit Point manual dari Tindakan Lanjutan Coaching.

Test minimal ikut dokumen acuan §15 (SP), ditambah:
- Tindak Lanjut Coaching cuma bisa diisi `coach_by`.
- Rekomendasikan SP dari Coaching tersedia SEJAK coaching dibuat, TIDAK bergantung
  `follow_up_status`.
- `user_hr_balances.merit_point` selalu sinkron dengan `SUM(merit_point_transactions.points)`
  ter-clamp setelah tiap transaksi.
- `user_hr_balances.active_sp_level` ter-refresh saat SP baru disetujui, DAN saat job harian jalan
  untuk SP yang sudah lewat `end_date`.
- Merit Point Rule wajib `company_id`, tidak bisa dibuat global.
- Widget Karir tidak tampil section Merit Point kalau `MERIT_POINT_ENABLED=false`.
