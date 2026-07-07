# Kesetaraan Role/Jabatan Lintas Company (per 2026-07-04)

## Pertanyaan

11 role dasar (lihat [`struktur-role-dan-dashboard.md`](struktur-role-dan-dashboard.md)) di-seed sama persis ke semua company, tapi kenyataan di lapangan tidak semua role itu ada di semua company — mis. "HC Supervisor" ada di holding tapi di rumah sakit yang ada malah "Kepala Ruangan", "Kasi Rawat Jalan", dsb. Apakah ada tabel/mekanisme yang menyamakan kesetaraan level jabatan antar company?

## Jawaban: TIDAK ADA tabel kesetaraan resmi

Role (`roles` — Spatie) dan Jabatan (`m_job_positions`) itu dua sumbu yang berbeda dan **tidak ada satupun yang di-mapping lintas company lewat FK**:

- **Role** — cuma untuk hak akses sistem. Tiap company punya baris `roles` sendiri (84 baris utk 8 company), nama sama tapi row & permission count beda-beda (lihat catatan bug "Super Admin" ambigu di file sebelumnya).
- **Jabatan (`m_job_positions`)** — struktur organisasi riil per company. Field `pangkat_id`, `golongan_id`, `kelas_id` juga **company-scoped** (`m_pangkats`, `m_golongans`, `m_kelas` masing-masing punya `company_id` sendiri, di-set otomatis dari `Auth::user()->company_id` saat create). Tidak ada tabel pusat yang bilang "Pangkat X di company A = Pangkat Y di company B".

## Yang berfungsi SEBAGAI kesetaraan de facto: Golongan (angka) + Pangkat (nama tier)

Dicek langsung ke data (`m_pangkats`, `m_golongans` per company):

| Golongan | SGG (holding) | RSU Banjarbaru/Barabai (RS) |
|---|---|---|
| 6 | President Direktur PT. SGG | Direktur RS |
| 5 | Manager PT. SGG | Manager RS |
| 4 | Supervisor PT. SGG | Supervisor RS |
| 3 | Koordinator PT. SGG | Koordinator RS |
| 2 | Staff PT. SGG | Staff RS |
| 1 | Pelaksana PT. SGG | Pelaksana RS |

Kedua company (dan semua company yang sudah diisi datanya) pakai **6 tier golongan yang sama urutannya**, cuma beda suffix nama ("PT. SGG" vs "RS"). Ini yang secara praktis jadi "alat penyetara" grade jabatan antar company — **tapi ini murni konvensi penamaan/penomoran manual**, bukan relasi database. Tidak ada yang mencegah satu company punya 5 tier atau urutan beda besok.

Contoh nyata dari data RSU Syifa Medika Banjarbaru (`golongan`/`pangkat` sebenarnya, dicek via tinker):
- **HC Supervisor** (holding) = golongan **4**, pangkat **Supervisor PT. SGG**
- Padanan setara di RS (golongan 4, pangkat **Supervisor RS**): **Kasi Keperawatan**, **Kasubag. Kepegawaian & Diklat**, **Kasubag. Hukum Humas & Promkes**, **Kepala Instalasi Rawat Jalan**, **Kepala Instalasi Farmasi RS**, **Kepala IPSRS**
- **Kepala Ruangan** (IGD/NICU/Poliklinik/dst) di RS ternyata **golongan 3 (Koordinator RS)** — **satu tier di bawah** Supervisor, setara dengan level "Koordinator PT. SGG" di holding (mis. IT Supervisor Staff, Finance Analyst Staff), **bukan** dengan HC Supervisor.

Jadi asumsi "Kepala Ruangan ≈ Supervisor" itu **tidak akurat** menurut struktur golongan yang sudah diinput — Kepala Ruangan itu setingkat Koordinator, bukan Supervisor. Yang setingkat Supervisor di RS justru role "Kasi ..." dan "Kepala Instalasi ...".

## Kelas (`m_kelas`) — ada tapi nyaris tidak dipakai

`m_kelas` (skala 1-12 di RS, 1-15 di holding, lebih granular dari golongan) cuma terisi di **6 dari 141** baris jabatan total lintas semua company. Secara praktis bisa dianggap belum dipakai sebagai alat kesetaraan.

## `job_evaluation_score` & `workload_score` — kolom disiapkan, belum dipakai

Ada di `m_job_positions` sebagai field skor evaluasi jabatan (harusnya jadi ukuran objektif buat bandingkan bobot jabatan lintas company/lintas nama), tapi **0 dari semua jabatan** yang terisi. Ini kemungkinan fitur yang direncanakan (analisis jabatan/job evaluation) tapi belum dieksekusi datanya.

## Kesimpulan praktis

1. **Belum ada tabel kesetaraan lintas company.** Kalau butuh logic yang tergantung level (misal approval routing lintas company, atau dashboard model Level×Domain di file sebelumnya), jangan pakai nama Role atau nama Jabatan sebagai sumber kebenaran — pakai **`golongan` (angka)**, itu satu-satunya sumbu yang konsisten urutan & rentangnya antar company sejauh ini.
2. **Golongan bukan jaminan formal** — masih string/angka bebas per company, rawan drift kalau HR nambah company baru dengan skema beda. Kalau mau dijadikan andalan sistem (bukan cuma insight manual), perlu tabel `grade_levels` global (mis. 1-6 dengan label baku) yang di-reference oleh `m_golongans` tiap company, supaya "golongan 4 di company manapun = Supervisor-level" itu constraint, bukan konvensi.
3. **`job_evaluation_score`/`workload_score` kosong** — kalau nanti mau kesetaraan yang lebih presisi (bukan cuma 6 tier kasar), ini kolom yang paling make sense diisi & dipakai, tapi butuh proses job evaluation dulu (belum ada di scope sekarang).
