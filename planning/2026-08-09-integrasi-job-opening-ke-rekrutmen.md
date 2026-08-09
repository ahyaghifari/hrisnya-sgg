# Planning: Integrasi Job Opening HRIS → Sistem Rekrutmen (2026-08-09)

Status: **sisi HRIS sudah dibangun & dites, nunggu endpoint Rekrutmen jadi.** Lanjutan dari diskusi SSO (`2026-08-08-sso-lintas-sistem.md`) — sekarang fokus ke satu titik nyata: lowongan yang dibuka di HRIS perlu muncul di sistem Rekrutmen (port 8001, database `sgg_rekrutmen`, Laravel juga).

## 0. Keputusan final (sudah diimplementasi di sisi HRIS)

- **Arah**: HRIS push ke Rekrutmen (bukan Rekrutmen polling)
- **Transport**: HTTP, `PUT {REKRUTMEN_API_URL}/integrations/hris/job-openings/{hris_job_opening_id}` — PUT dipilih supaya upsert idempotent (create kalau belum ada, update kalau sudah). `REKRUTMEN_API_URL` sudah termasuk prefix `/api` (mis. `http://localhost:8001/api`), bukan cuma domain root.
- **Auth**: Bearer token statis, `REKRUTMEN_API_TOKEN` di `.env` kedua sisi
- **Sync mode**: SYNCHRONOUS (blocking), **bukan queue** — instruksi eksplisit user, tiap Simpan/Publish/Tutup di HRIS langsung coba kirim saat itu juga
- **Trigger**: `saveDraft()`, `publish()`, `close()` — 3-3nya, bukan cuma publish
- **Gagal sync**: NON-FATAL — aksi di HRIS tetap sukses, kegagalan kirim (endpoint belum ada/down/timeout) cuma dicatat log (`storage/logs/laravel.log`, prefix `RekrutmenSyncService`), tidak membatalkan simpan/publish/tutup

**Sudah dibangun & dites** (`app/Services/Job/RekrutmenSyncService.php`, dipanggil dari `JobOpeningService`):
- Config kosong (`.env` `REKRUTMEN_API_URL`/`REKRUTMEN_API_TOKEN` belum diisi) → sync di-skip otomatis, tidak error — dikonfirmasi lewat tes langsung (`saveDraft()` sukses, log skip muncul).
- Begitu Rekrutmen selesai bikin endpoint-nya dan 2 env var itu diisi, sync langsung aktif tanpa perlu ubah kode lagi.

## 1. Temuan dari cek langsung ke `sgg_rekrutmen`

Dicek via PDO ke `127.0.0.1:3306` database `sgg_rekrutmen` (host sama dengan `hris_db`, bukan server terpisah).

**Tabel `companies` SUDAH ada** (kontra info awal "belum aku tambahkan" — mungkin maksudnya belum lengkap isinya, bukan skemanya):

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint unsigned PK | ID lokal Rekrutmen, BUKAN ULID HRIS |
| `hris_code` | varchar(10), unique | **Ini penghubungnya** — cocok ke `m_companies.code` HRIS (format 4 digit, `0000`/`0001`/dst) |
| `management_mode` | enum('free','hris') | **Kunci penting** — cuma company `management_mode='hris'` yang harusnya sinkron dari HRIS. Sekarang baru "Syifa Global Group" (holding) yang `hris`, sisanya `free` (dikelola mandiri di Rekrutmen, tidak nyambung HRIS) |
| `logo_path`, `hero_image_path`, `brand_color`, `email`, `phone` | — | Data tampilan publik yang HRIS **tidak punya** — Rekrutmen kelola sendiri, bukan disinkron dari HRIS |

Isi saat ini (6 dari 8 company HRIS sudah ada di sini, `hris_code` sudah cocok):
```
0000 Syifa Global Group          management_mode=hris
0001 RSU Syifa Medika Banjarbaru management_mode=free
0002 RSU Syifa Medika Barabai    management_mode=free
0003 Syifa Global Med            management_mode=free
0004 Syifa Global Lestari Banjarbaru management_mode=free
0006 Lerina Inn                  management_mode=free
```
(Syifa Global Lestari Surabaya `0005` & Syifa Agro Natura `0007` belum ada baris — sesuai kata user, masih nyusul.)

**Tabel `job_openings` Rekrutmen** — sudah ada, tapi:
- `company_id` FK ke `companies.id` **lokal** (bigint), bukan ULID HRIS — jadi mapping company HARUS lewat `hris_code`, bukan `company_id` langsung
- Belum ada kolom penghubung balik ke `job_openings.id` HRIS (tidak ada `hris_job_opening_id` atau semacamnya) — kalau HRIS kirim data lagi (update), Rekrutmen tidak punya cara tahu "ini row yang mana yang harus ditimpa"
- Belum ada `max_applicants`/`application_deadline` (baru ditambahkan di HRIS sesi ini)

## 2. Skema yang diusulkan

### 2.1 Kolom baru di HRIS (SUDAH dikerjakan sesi ini)
`job_openings` (HRIS): `max_applicants` (int, nullable), `application_deadline` (date, nullable) — diisi manual di form Edit Lowongan Kerja, dipakai Rekrutmen buat auto-tutup penerimaan lamaran.

### 2.2 Kolom yang perlu ditambah di Rekrutmen (belum dikerjakan — punya user)
`job_openings` (Rekrutmen) perlu tambahan:
- `hris_job_opening_id` — char(26) nullable unique, ULID `job_openings.id` HRIS. Kunci upsert idempotent (`updateOrCreate` by kolom ini), bukan bikin baris baru tiap sync.
- `max_applicants` — int nullable (mirror dari HRIS)
- `application_deadline` — date nullable (mirror dari HRIS)

`job_openings.company_id` tetap FK lokal — cara resolve-nya: `Companies::where('hris_code', $hrisCompanyCode)->first()`.

### 2.3 Bentuk data yang dikirim HRIS → Rekrutmen (payload, belum terikat transport)

Saat `JobOpeningService::publish()` dipanggil di HRIS (dan company-nya `management_mode='hris'` di sisi Rekrutmen):

```json
{
  "hris_job_opening_id": "01kzkc6m6pna6p5jy3cwtrtkq0",
  "hris_company_code": "0000",
  "title": "IT R&D",
  "quota": 2,
  "max_applicants": null,
  "application_deadline": null,
  "description": "<html dari Trix>",
  "requirements": "<html dari Trix>",
  "is_active": true
}
```

Field `company_name` yang sudah ada di tabel Rekrutmen (denormalized) diisi dari `title`/nama company hasil resolve `hris_code` di sisi Rekrutmen, bukan dikirim HRIS — supaya nama selalu konsisten sama data Rekrutmen sendiri kalau berubah.

## 2.4 Arah baru: HRIS narik jumlah pelamar dari Rekrutmen (2026-08-09, sesi lanjutan)

Rekrutmen udah punya 231 data pelamar tes. Butuh cara HRIS nampilin jumlah pelamar per lowongan
dari Rekrutmen — beda arah dari §2.3 (yang itu HRIS → Rekrutmen, ini Rekrutmen → HRIS).

**Kontrak** (didesain di sisi HRIS, dibangun di sisi Rekrutmen):
```
POST /api/integrations/hris/job-openings/applicant-counts
Header: Authorization: Bearer {token}
Body:   {"ids": ["01k...", "01k...", ...]}   <- hris_job_opening_id, batch (bukan 1-per-request)
Response: {"counts": {"01k...": 12, "01k...": 45}}
```

Dipakai di halaman List Lowongan Kerja (`JobOpeningsPage`) — 1 panggilan batch per halaman
(bukan N+1 per baris), kolom baru "Pelamar (Rekrutmen)" di samping "Pelamar (HRIS)" yang lama
(data lokal `CandidateJobApplications`, sengaja tidak digabung jadi 1 angka — dua sumber beda).
Non-fatal sama seperti push — gagal/endpoint belum ada, kolom nampilin `-`, halaman tetap jalan.

Kode: `RekrutmenSyncService::getApplicantCounts()`.

## 2.5 Arah baru: Daftar pelamar (paginated+search) & export Excel (2026-08-09, sesi lanjutan 2)

Halaman Detail Lowongan Kerja (`JobOpeningDetailPage`) butuh 2 hal lagi, dengan constraint eksplisit dari user: **jangan pernah narik seluruh pelamar (bisa 500-1000+) ke memory HRIS sekaligus** — baik buat ditampilkan maupun buat export.

**Keputusan**: Rekrutmen yang generate Excel-nya (bukan HRIS narik semua data lalu build xlsx sendiri) — HRIS jadi proxy tipis buat tombol download, tidak pernah pegang data mentah pelamar dalam jumlah besar.

### a) List pelamar — server-side pagination + search
```
GET /integrations/hris/job-openings/{hris_job_opening_id}/applicants?page=1&per_page=20&search=budi
Header: Authorization: Bearer {token}
```
Response:
```json
{
  "data": [
    {
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "phone": "0812xxxxxxx",
      "applied_at": "2026-08-01",
      "status": "screening"
    }
  ],
  "meta": { "current_page": 1, "last_page": 50, "total": 1000, "per_page": 20 }
}
```
`per_page` default 20 kalau tidak dikirim. `search` cocok ke nama (minimal), field lain (email/status) opsional kalau Rekrutmen mau dukung. Field response boleh disesuaikan Rekrutmen (nama kolom apa yang tersedia di `candidates`/`job_applications` mereka) — kontrak minimal yang HRIS butuh cuma `name`, `applied_at`, `status`; `email`/`phone` nice-to-have buat ditampilkan.

**Sisi HRIS**: `JobOpeningDetailPage` (atau dipecah jadi komponen Livewire terpisah khusus daftar pelamar) simpan state `page`+`search`, panggil `RekrutmenSyncService::getApplicants($hrisJobOpeningId, $page, $search)` tiap ganti halaman/ketik cari — TIDAK pernah nyimpen lebih dari 1 halaman (20 baris) di memory sekaligus.

### b) Export Excel — Rekrutmen generate & stream, HRIS proxy
```
GET /integrations/hris/job-openings/{hris_job_opening_id}/applicants/export?search=budi
Header: Authorization: Bearer {token}
```
Response: **binary file** (`Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `Content-Disposition: attachment; filename="..."`) — bukan JSON. Rekrutmen yang query SEMUA baris yang cocok filter (tanpa limit halaman) dan generate xlsx-nya sendiri (Laravel Excel/`fputcsv` streaming, terserah Rekrutmen), lalu stream balik.

**Sisi HRIS**: butuh 1 route/Controller BARU (bukan Livewire component method — file download butuh full HTTP response, bukan AJAX morph). Alurnya: user klik "Export Excel" → hit route HRIS `GET /kepegawaian/lowongan/{record}/pelamar/export` → controller HRIS panggil `Http::withToken($token)->get($rekrutmenExportUrl)` → **stream ulang response-nya persis apa adanya** ke browser user (proxy tipis, bukan baca-lalu-tulis-ulang) pakai `response()->streamDownload()` atau relay header+body mentah. HRIS tidak pernah parse/pegang isi file, cuma nerusin bytes.

### Ringkasan kenapa didesain gini
- HRIS gak pernah nampung >20 baris pelamar di memory buat list (server-side pagination penuh di Rekrutmen)
- HRIS gak pernah nampung 1000 baris buat export (Rekrutmen generate file, HRIS cuma nerusin stream)
- Rekrutmen juga gak dihajar query "ambil semua kolom semua kandidat" berulang-ulang dari sisi HRIS — beban query export tetap 1x jalan pas diminta, bukan per-scroll/per-halaman kayak kalau HRIS yang narik manual

## 3. Yang BELUM diputuskan

- **Arah kandidat balik** — kalau kandidat melamar di Rekrutmen, apa lamarannya perlu balik ke `CandidateJobApplications` HRIS juga, atau Rekrutmen sepenuhnya independen buat data pelamar (cuma job opening yang disinkron satu arah)?
- **Company `management_mode='free'`** — 5 dari 6 company yang ada sekarang `free`, artinya HRIS **tidak boleh** push job opening buat company itu (mereka kelola manual di Rekrutmen). Belum diputuskan siapa yang nolak: HRIS cek dulu `management_mode` sebelum push (butuh baca tabel Rekrutmen dari HRIS — cross-boundary lagi, HRIS jadi tau struktur internal Rekrutmen), atau Rekrutmen yang terima semua payload masuk tapi diam-diam abaikan/tolak untuk company `free` (lebih bersih, tanggung jawab validasi ada di penerima). **Belum diimplementasi salah satunya** — sekarang HRIS push apa adanya ke endpoint manapun tanpa cek `management_mode` sama sekali.

## 4. Sisa Kerjaan (di sisi Rekrutmen, bukan repo ini)

1. Bangun endpoint `PUT /integrations/hris/job-openings/{hris_job_opening_id}` (di bawah prefix `/api` yang sudah ada), terima payload di §2.3, upsert by `hris_job_opening_id`, resolve `company_id` lokal dari `hris_company_code` via `companies.hris_code`
2. Bangun endpoint `POST /integrations/hris/job-openings/applicant-counts` (§2.4) — terima `{"ids": [...]}`, balikin `{"counts": {id: jumlah}}`, hitung dari tabel `job_applications` Rekrutmen `where job_opening_id in (...)` (perlu join lewat `hris_job_opening_id` kalau `job_applications.job_opening_id` merujuk PK lokal, bukan langsung `hris_job_opening_id`)
3. Cek `Authorization: Bearer {token}` cocok `REKRUTMEN_API_TOKEN` di kedua endpoint
4. Bangun endpoint `GET /integrations/hris/job-openings/{id}/applicants` (§2.5a) — pagination + search server-side, JANGAN balikin semua baris sekaligus
5. Bangun endpoint `GET /integrations/hris/job-openings/{id}/applicants/export` (§2.5b) — generate & stream file xlsx langsung (bukan JSON), query semua baris cocok filter tanpa limit halaman
6. Putuskan §3 (gating `management_mode`, arah data kandidat balik)
7. `REKRUTMEN_API_URL`/`REKRUTMEN_API_TOKEN` sudah diisi di `.env` HRIS — begitu endpoint di atas jadi, langsung aktif tanpa ubah kode HRIS lagi
