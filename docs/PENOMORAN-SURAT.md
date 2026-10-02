# Dokumentasi Sistem Penomoran Surat Otomatis

Dokumen ini menjelaskan cara kerja penomoran surat otomatis di sistem lama (`audit.syifaglobalgroup.com`), untuk dijadikan acuan migrasi ke sistem baru.

## 1. Format Nomor Surat

```
{nomor_urut:3digit}/{kode_jenis_surat}.{kode_unit}/{kode_perusahaan}/{bulan_romawi}/{tahun}
```

Contoh nyata dari data: `001/A.2/SGG/VII/2025`

| Bagian | Arti | Contoh |
|---|---|---|
| `001` | Nomor urut, padding 3 digit (`%03d`) | 001 |
| `A` | Kode jenis surat | Kebijakan/Peraturan |
| `2` | Kode unit | Recruiter & Benefit |
| `SGG` | Kode perusahaan | PT Syifa Global Group |
| `VII` | Bulan dalam angka romawi (dari tanggal surat) | Juli |
| `2025` | Tahun (dari tanggal surat) | 2025 |

Nomor urut **tidak reset per bulan**, hanya reset per **perusahaan + tahun** (lihat bagian 3).

Sumber: [generatenumber.php](../persuratan/generatenumber.php)

```php
$nomor_final = sprintf('%03d', $nomor_urut) . "/{$kode_j}.{$kode_u}/{$kode_p}/{$bulan}/{$tahun}";
```

### 1.1 Urutan format sama untuk semua jenis surat — tidak ada pengecualian

Satu fungsi ini dipakai untuk **semua** jenis surat tanpa terkecuali (SK, Surat Tugas, Surat Perintah, Berita Acara, Sertifikat, dst) — dipanggil dari `proses_input_surat.php`, `proses_input_surat_auditee.php`, dan `proses_generate_massal.php`. Tidak ada percabangan `if ($jenis === 'SP') { ... }` di manapun. Urutannya selalu **nomor / kode_jenis.kode_unit / kode_perusahaan / bulan / tahun**.

⚠️ **Temuan dari peninjauan dokumen nyata**: ditemukan pola urutan yang **berbeda** pada dokumen Surat Perintah (SP) — kode unit tampak lebih dulu dari kode jenis (`kode_unit.kode_jenis`), berkebalikan dari Berita Acara dan jenis lain yang konsisten dengan urutan di atas. Karena polanya konsisten berulang (bukan acak), ini kemungkinan besar **bukan salah ketik**, melainkan konvensi asli perusahaan yang memang berbeda per jenis surat — dan sistem ini tidak pernah mengakomodasinya (satu format dipaksakan untuk semua jenis).

Pendukung dugaan ini: di dump data yang ada, **tidak ada satupun baris `surat` berjenis Surat Perintah** yang tercatat pernah dibuat lewat sistem — mengindikasikan SP kemungkinan selama ini dibuat manual (template terpisah) dan nomornya hanya dicatat ke sistem apa adanya, bukan digenerate oleh `generate_letter_number()`.

**Untuk sistem baru:** perlu dikonfirmasi ke pemilik SOP persuratan apakah tiap jenis surat memang punya pola urutan kode sendiri-sendiri. Kalau iya, sistem baru butuh pemetaan pola per `jenis_surat` (bukan satu template string global seperti sekarang).

## 2. Tabel Referensi Kode

### 2.1 Kode Perusahaan — `kode_surat_perusahaan`
| id_perusahaan | nama_perusahaan | kode |
|---|---|---|
| 1 | PT Syifa Global Group | `SGG` |
| 2 | RSU Syifa Medika Banjarbaru | `RSUSM` |
| 3 | RSU Syifa Medika Barabai | `RSUSM-BRB` |

Catatan: tabel `perusahaan` punya 6 baris (4=Koperasi Syifa Medika Persada, 5=Syifa Global Med, 6=Syifa Global Lestari) tapi **belum punya kode surat** — kalau dipakai bikin surat, `generate_letter_number()` akan gagal (`throw new Exception("Kode Perusahaan tidak ditemukan.")`).

### 2.2 Kode Unit — `kode_surat_unit`
Relasi ke `unit.id`, bukan ke nama unit langsung. Contoh isi (id_unit → kode):

| id_unit | kode |
|---|---|
| 35 | 1 |
| 36 | 2 |
| 37 | 3 |
| 6 | 3A |
| 7 | 3B |
| 38 | 3C |
| 10 | 3D |
| 15 | 6 |
| 30 | 18 |
| 33, 34 | 5 |
| 39 | 6 |

Catatan: kode unit **bukan unik** (unit 15 dan 39 sama-sama `6`; unit 33 dan 34 sama-sama `5`) — dan tidak semua unit terdaftar (tabel `unit` punya puluhan baris, tabel kode ini cuma 12). Kalau unit belum punya kode → gagal generate juga.

### 2.3 Jenis & Kode Surat — `jenis_surat`

Tabel real (26 baris total, per screenshot data live — lebih lengkap dari draf awal dokumen ini):

| id | kode | nama_jenis |
|---|---|---|
| 3 | F | Surat Perintah |
| 4 | A | Kebijakan / Peraturan / PP |
| 5 | B | Pedoman / Panduan Pelaksanaan |
| 6 | C | Surat Edaran / Pengumuman |
| 7 | D | Internal Memo |
| 9 | E | Surat Keputusan |
| 10 | G | Surat Tugas |
| 11 | H | Surat Undangan |
| 12 | I | Surat Kuasa |
| 13 | J | Berita Acara |
| 14 | K | Surat Perjanjian / MoU |
| 15 | L | Surat Permohonan Barang / Jasa |
| 16 | PKWT | Perjanjian Kerja Waktu Tertentu |
| 17 | PKWTT | Perjanjian Kerja Waktu Tidak Tentu |
| 18 | CERT | Sertifikat |
| 19 | M | Surat Balasan |
| 20 | N | Surat Tanda Terima |
| 21 | O | Surat Pengantar |
| 22 | SP | Surat Peringatan |
| 23 | P | Surat Rekomendasi |
| 24 | MCU | Surat MCU |
| 25 | SOP | Standard Operational Procedure |
| 26 | FRM | Formulir |
| 27 | Q | Surat Keterangan |
| 28 | SPKK | Surat Penugasan Kerja Klinik |
| 29 | SKK | Surat Keterangan Kesehatan |

Catatan: `id=8` tidak ada (kemungkinan pernah dihapus) — sudah dikonfirmasi 26/26 baris tertampil, tidak ada baris tersembunyi lain.

⚠️ **Konflik kode dengan temuan bagian 1.1**: kode `SP` di tabel ini dipakai untuk **Surat Peringatan** (id 22) — bukan Surat Perintah, yang kodenya `F` (id 3). Temuan "SP" di bagian 1.1 (urutan kode terbalik) merujuk ke istilah **Surat Perintah**, dan kode aslinya di sistem adalah `F`, bukan `SP`. Perlu dicek ulang dokumen fisik yang jadi acuan temuan 1.1 untuk pastikan jenis surat mana sebenarnya yang polanya terbalik itu.

Ketiga tabel referensi ini dikelola manual via CRUD admin di [manage_penomoran_surat.php](../admin/manage_penomoran_surat.php) + [api_penomoran_actions.php](../admin/api/api_penomoran_actions.php). Tidak ada validasi keunikan kode di level aplikasi maupun constraint DB — insert/update lewat `save_item()` generik, tanpa cek duplikat.

## 3. Counter Nomor Urut — `nomor_surat_counter`

Tabel ini **tidak ada di dump SQL** ([audit_syifa_global_db.sql](../AUDIT%20V6%20AURA%20THEME/audit_syifa_global_db.sql), per 5 Juli 2025) — ditambahkan setelahnya di DB live. Struktur yang dipakai kode (inferred dari query):

```sql
CREATE TABLE nomor_surat_counter (
  id_perusahaan INT NOT NULL,
  tahun INT NOT NULL,
  nomor_terakhir INT NOT NULL,
  PRIMARY KEY (id_perusahaan, tahun)   -- perlu UNIQUE key ini agar ON DUPLICATE KEY UPDATE bekerja
);
```

**Key point (sistem lama): counter di-scope per `(id_perusahaan, tahun)` saja — TIDAK per unit atau per jenis surat.** Artinya semua jenis surat dan semua unit di satu perusahaan pada tahun yang sama berbagi satu urutan nomor global.

> **Keputusan untuk sistem baru:** counter tetap di-scope per `(id_perusahaan, tahun)` saja — sama seperti sistem lama. Unit dan jenis surat **tidak** ikut jadi bagian scope; semua jenis surat & unit di satu perusahaan pada tahun yang sama tetap berbagi satu urutan nomor global. Bulan juga sengaja tidak dijadikan bagian scope (dibahas & ditolak — nomor kontinu per tahun lebih gampang diaudit ketimbang reset per bulan). Row lock (`SELECT ... FOR UPDATE`) tetap wajib dipertahankan saat generate nomor.

### 3.1 Alur pembuatan nomor (single) — [proses_input_surat.php](../persuratan/proses_input_surat.php)

1. `BEGIN TRANSACTION`
2. `SELECT nomor_terakhir FROM nomor_surat_counter WHERE id_perusahaan=? AND tahun=? FOR UPDATE` — row lock, mencegah race condition saat 2 user input barengan.
3. Kalau belum ada baris → `nomor_terakhir = 0`.
4. `nomor_urut_baru = nomor_terakhir + 1`.
5. Format nomor via `generate_letter_number(...)`.
6. `INSERT INTO surat (...)`.
7. `INSERT ... ON DUPLICATE KEY UPDATE nomor_terakhir = nomor_urut_baru` — commit counter baru.
8. `COMMIT`.

### 3.2 Alur generate massal (reservasi nomor draft) — [proses_generate_massal.php](../persuratan/proses_generate_massal.php)

Fitur untuk mencadangkan N nomor surat sekaligus (maks 50) sebagai draft, dipakai user role `pj, manager, direktur, admin, sekretaris, manager_audit`:

1. Lock counter sama seperti di atas (`FOR UPDATE`).
2. Loop `$jumlah` kali: nomor urut naik satu-satu, tiap iterasi insert baris `surat` dengan `status='Draft'` dan judul placeholder `[DRAFT] Dicadangkan pada {tanggal jam}`.
3. Setelah loop, counter diupdate sekali ke nilai akhir.
4. Semua dalam satu transaksi — kalau gagal di tengah, rollback total (tidak ada nomor bocor/hilang).

### 3.3 Regenerasi nomor saat edit — [proses_edit_surat.php](../persuratan/proses_edit_surat.php)

- Nomor surat **hanya di-regenerate** kalau `id_jenis_surat` atau `tanggal_surat` berubah.
- Nomor urutnya sendiri **tidak berubah** — diambil ulang dari nomor lama (`explode('/', $old_surat['nomor_surat'])[0]`), lalu diformat ulang dengan kode jenis/bulan/tahun yang baru.
- Counter global **tidak disentuh** saat edit (karena tidak mengambil nomor urut baru).
- Kode perusahaan & unit tidak bisa diubah lewat form edit ini (tetap pakai `id_perusahaan`/`id_unit` lama).

## 4. Tipe Surat Masuk vs Keluar

Tabel `surat` punya kolom `tipe_surat` enum `('masuk','keluar')`.
- **Surat keluar** → pakai sistem penomoran otomatis di atas.
- **Surat masuk** → **tidak** pakai format otomatis. Nomornya cuma string bebas format `[MASUK] Dari: {pengirim} | {tanggal}` (lihat data contoh: `[MASUK] Dari: Polresta Banjarbaru | 02-07-2025`, di [proses_input_surat_masuk.php](../persuratan/proses_input_surat_masuk.php)).

## 5. Integrasi Eksternal (SiPOS) — Sistem Penomoran Kedua yang Berbeda

Ada endpoint terpisah, [get_next_po.php](../get_next_po.php), dipakai sistem PO eksternal ("SiPOS") untuk minta preview nomor PO berikutnya. **Ini bukan bagian dari alur di atas** dan punya cara kerja sendiri, format berbeda: `{nomor}/O.3D/SGG/{bulan_romawi}/{tahun}` (kode unit & perusahaan hardcoded `O.3D`/`SGG`, bukan dari tabel referensi).

⚠️ Catatan untuk migrasi — jangan bawa pola ini apa adanya:
- Nomor urut dihitung dari `SELECT po_number ... ORDER BY id DESC LIMIT 1` lalu di-parse manual, **tanpa row lock** → rawan race condition/nomor dobel kalau 2 request bersamaan.
- Endpoint ini cuma "preview", tidak mengklaim/mengunci nomor — kalau SiPOS request 2x sebelum PO pertama disimpan, keduanya bisa dapat nomor yang sama.
- Auth pakai token hardcoded di source (`Bearer SGG_SECRET_KEY_2026_XYZ`) + `Access-Control-Allow-Origin: *`.

## 6. Dari Mana Kode Perusahaan & Unit Ditentukan

Ini sering disalahpahami sebagai "otomatis dari lokasi/profil user" — kenyataannya **campuran**, dan salah satunya murni manual tanpa validasi. Sumber: [input_surat.php](../persuratan/input_surat.php).

| Kode | Ditentukan oleh | Detail |
|---|---|---|
| **Perusahaan** | Role `sekretaris` → **terkunci otomatis** dari `users.id_perusahaan` miliknya sendiri, dropdown `disabled`. Role `admin`/`manager_audit` → **bebas pilih** perusahaan manapun. | [input_surat.php:30-39](../persuratan/input_surat.php#L30-L39) |
| **Departemen & Unit** | **Selalu dropdown manual**, bertingkat (perusahaan → departemen → unit), untuk **semua role** termasuk sekretaris. Tidak ada logika yang mengunci ke unit/departemen milik user yang login. | [input_surat.php:97-104](../persuratan/input_surat.php#L97-L104) |
| **Jenis surat** | Selalu dropdown manual, semua role. | — |

Konsekuensi: **tidak ada validasi bahwa unit yang dipilih benar-benar relevan dengan surat itu** (mis. unit tempat tugas pembuat surat, atau unit tujuan surat). Sekretaris di perusahaan A bebas memilih unit manapun di perusahaan A saat membuat surat — salah pilih tidak dicegah sistem.

### 6.1 Kasus surat lintas-entitas (mis. SP dari holding untuk orang di unit bisnis anak perusahaan)

Tabel `surat` **tidak punya kolom terpisah untuk "penerbit" vs "tujuan/penerima"** — hanya satu set `id_perusahaan`/`id_departemen`/`id_unit` ([lihat skema](../AUDIT%20V6%20AURA%20THEME/audit_syifa_global_db.sql)). Artinya kalau surat diterbitkan holding tapi ditujukan ke orang di unit bisnis lain:

- Sistem **tidak bisa merepresentasikan keduanya sekaligus** — pembuat surat harus pilih salah satu (kode holding atau kode unit bisnis) sebagai dasar nomor, dan tidak ada aturan di kode yang menentukan mana yang seharusnya dipakai.
- Ini keputusan manual per-orang saat mengisi form, sehingga rawan tidak konsisten antar pembuat surat yang berbeda.
- Field `perihal`/`deskripsi` (teks bebas) adalah satu-satunya tempat info "surat ini untuk siapa" bisa dicatat manual — tapi tidak mempengaruhi nomor surat.

**Untuk sistem baru:** perlu diputuskan eksplisit dengan pemilik SOP — apakah nomor surat selalu memakai kode **penerbit** (konvensi paling umum di kebanyakan organisasi), atau perlu dua kolom relasi terpisah (penerbit + tujuan) kalau memang praktiknya mensyaratkan itu. Ini keputusan desain skema, bukan sekadar migrasi data 1:1 dari sistem lama — sistem lama sendiri belum punya konsepnya.

## 7. Ringkasan untuk Desain Sistem Baru

Yang perlu dibawa/diperbaiki saat migrasi:

1. **Data referensi** yang harus dipindah: `perusahaan` + `kode_surat_perusahaan`, `unit` + `kode_surat_unit`, `jenis_surat`. Lengkapi dulu yang masih kosong (3 perusahaan & sebagian besar unit belum punya kode).
2. **Constraint keunikan** kode yang belum ada di sistem lama — sebaiknya ditambahkan di sistem baru (unique per `id_perusahaan`, unique per `id_unit`, unique per `kode_surat` jenis).
3. **Scope counter**: **dipertahankan sama seperti sistem lama** — per `(perusahaan, tahun)`, global lintas unit & jenis surat. Sempat dipertimbangkan dipecah per jenis surat dan/atau per bulan, tapi diputuskan tetap 1 urutan global per perusahaan per tahun (lihat bagian 3) — migrasi data counter jadi 1:1, gak perlu strategi pemetaan ulang.
4. **Row lock (`SELECT ... FOR UPDATE`) dalam transaksi** wajib dipertahankan di sistem baru manapun — ini yang bikin penomoran lama tidak dobel walau diakses bersamaan.
5. **Surat masuk tidak ikut format otomatis** — kalau sistem baru mau seragamkan, ini perubahan behavior, bukan migrasi murni.
6. **Endpoint SiPOS (`get_next_po.php`) perlu ditulis ulang**, bukan dipindah langsung: tambahkan row lock + pindahkan token rahasia ke config/env, bukan hardcoded di source.
7. **Konfirmasi ke pemilik SOP: apakah pola urutan kode memang berbeda per jenis surat** (temuan SP terbalik, lihat bagian 1.1) — kalau iya, sistem baru butuh pemetaan pola per jenis, bukan satu template global.
8. **Putuskan relasi penerbit vs tujuan surat** (bagian 6.1) — sistem lama tidak punya kolom terpisah untuk ini; kalau surat lintas-entitas (holding ↔ unit bisnis) memang butuh mencatat keduanya, ini perubahan skema baru, bukan migrasi 1:1.
9. **Kunci kode perusahaan/unit ke profil user yang login**, bukan dropdown bebas tanpa validasi seperti sekarang (bagian 6) — supaya tidak bergantung ke kedisiplinan manual pembuat surat.
