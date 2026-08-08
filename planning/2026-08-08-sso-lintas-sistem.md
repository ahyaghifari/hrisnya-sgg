# Planning: SSO Lintas Sistem (HRIS sebagai Identity Provider) — 2026-08-08

Status: **diskusi awal, belum ada keputusan final, belum mulai eksekusi.** Dokumen ini rekap hasil ngobrol soal kebutuhan SSO, bukan rencana yang siap jalan.

## 1. Latar Belakang

Perusahaan punya beberapa sistem terpisah, masing-masing login sendiri-sendiri:
- **HRIS** (project ini) — data karyawan paling lengkap & akurat (aktif/nonaktif, company, role)
- **Rekrutmen** — sedang didevelop sendiri, jalan di port 8001, Laravel juga
- **DCMS** — sistem dokumen
- **Silapor** — punya rekan kerja lain, belum tau stack teknisnya apa

User (developer HRIS + Rekrutmen) mulai kerasa butuh SSO — user capek/rawan salah kalau tiap sistem punya akun & password sendiri-sendiri.

## 2. Dua Opsi Arsitektur

| | Opsi 1: HRIS jadi provider (Laravel Passport) | Opsi 2: IdP terpisah (Keycloak/Authentik) |
|---|---|---|
| Siapa jalanin IdP | HRIS sendiri, nempel di codebase yang sama | Service terpisah, khusus itu doang |
| Maintenance | Kamu urus sendiri (Passport = library, bukan produk jadi) | Fitur enterprise (MFA, admin UI, federation) sudah ada, tinggal konfigurasi |
| Kalau HRIS down | Login SEMUA sistem ikut kena (app bisnis + IdP satu proses) | Insiden terpisah — Keycloak down beda dari HRIS down |
| Cocok buat | Skala kecil (3-4 sistem), tim kecil, belum mau nambah infra | Skala lebih besar, banyak sistem, butuh isolasi kegagalan |

**Jalan tengah yang muncul di diskusi**: pisahin fisik — service auth kecil (Keycloak, atau custom) yang **baca data dari HRIS** (API/sync/event), tapi jalan sebagai proses sendiri. HRIS tetap pemilik data identitas, tapi bukan yang nanganin traffic login — kalau HRIS lagi deploy/maintenance, service auth tetap hidup asal data terakhir sudah ke-sync.

**Belum diputuskan** opsi mana yang dipakai — masih tahap menimbang.

## 3. Kenapa HRIS Ragu Cocok Jadi Provider Sekarang

- HRIS masih sering direstrukturisasi berat (migrasi Hak Akses ke permission granular baru selesai beberapa hari terakhir, redesign payroll Fase 2 belum commit, belum ada test/CI gate — lihat README § Known Gaps)
- Naikin beban HRIS jadi identity provider buat 3+ sistem lain nambah risiko pas fondasinya sendiri masih goyang
- **Rekomendasi sesi ini**: beresin dulu migrasi permission/Hak Akses HRIS sendiri sampai stabil, baru pasang lapisan SSO di atasnya. Bangun IdP di atas fondasi yang masih berubah-ubah = kerja dobel kalau strukturnya berubah lagi.

## 4. Mekanisme (kalau opsi Passport/OAuth2 dipilih)

### 4.1 Alur login (Authorization Code flow)
1. User buka sistem satelit (mis. Rekrutmen) → klik "Login pakai HRIS" → redirect ke `HRIS/oauth/authorize`
2. Login HRIS (kalau belum) → approve scope yang diminta client
3. Redirect balik bawa `code` → sistem satelit tukar ke `/oauth/token` → dapat access token
4. Sistem satelit panggil `/oauth/userinfo` (custom endpoint, Passport tidak menyediakan otomatis) → dapat klaim: id, nip, name, email, company_id, is_active, role

### 4.2 JIT Provisioning (akun otomatis, belum pernah daftar)
- User pertama kali login via SSO → sistem satelit `firstOrCreate` user lokal by `hris_user_id` (BUKAN by email — email bisa berubah, ULID `hris_user_id` tetap)
- Role default user baru: paling minim (viewer), admin assign lebih belakangan
- Kalau ada user lama (dibuat manual sebelum SSO) dengan email sama → perlu logic matching/link, bukan create baru — celah duplikasi kalau tidak ditangani

### 4.3 Sumber kebenaran field identitas
- **Email/nama**: ikut HRIS, read-only di sistem satelit — kalau diizinkan edit lokal, next login/refresh token bisa ketiban balik data HRIS lama, bikin bingung user
- **`hris_user_id`**: kunci matching utama, immutable
- **Password lokal**: boleh beda per sistem (lihat 4.4)

### 4.4 Wajib set password lokal sebagai fallback (ide dari diskusi ini)
- Begitu akun auto-provisioned (`password` masih null) → **paksa** halaman "Atur Password" sebelum lanjut pakai app (middleware, bukan skip-able)
- Tujuan: kalau HRIS/SSO down, user masih bisa masuk lewat email+password lokal di sistem satelit itu
- **Risiko yang harus dijaga**: jalur password lokal ini independen dari status aktif/nonaktif di HRIS — kalau karyawan resign, jalur SSO otomatis ke-block, tapi password lokal TIDAK otomatis ikut nonaktif kecuali ada sync/webhook terpisah yang cek status HRIS berkala. Tanpa ini, orang resign masih bisa login pakai password lama di sistem satelit — celah nyata, wajib ditangani sebelum fitur ini dianggap selesai.
- UI harus jelas label "Password [nama sistem]" beda dari "Password HRIS", biar user gak ketuker

### 4.5 Token & revoke
- Access token umur pendek (jam-an), refresh token lebih panjang (hari/minggu) — jangan default Passport yang bisa kepanjangan
- **Revoke token Passport** wajib dihubungkan ke titik yang sama dengan gate `is_active=false` yang sudah ada di HRIS (`AuthService`/`LoginController`) — supaya nonaktifkan karyawan juga langsung mutus akses SSO-nya, bukan cuma blokir login form biasa

## 5. Hal Lain yang Belum Dibahas Detail (perlu digali kalau lanjut)

- **Single Logout (SLO)** — logout di 1 sistem tidak otomatis logout di sistem lain (masing-masing pegang token/session sendiri). Perlu diputuskan apa ini kebutuhan nyata atau bisa diskip di fase awal.
- **Audit log terpusat** — HRIS jadi satu-satunya titik yang tahu semua otorisasi keluar (client mana, kapan). Bukan otomatis ada dari Passport, perlu dibangun manual kalau mau.
- **Koordinasi non-teknis** — Silapor punya tim beda (rekan kerja user), belum tahu stack-nya. Perlu koordinasi jadwal & siapa pegang implementasi OAuth client di sisi mereka kalau bukan Laravel.

## 6. Urutan Eksekusi yang Disarankan (kalau nanti mulai)

1. Stabilkan dulu fondasi HRIS sendiri (Hak Akses, payroll) — lihat § 3
2. Pilot di **1 sistem dulu** — Rekrutmen (port 8001) paling masuk akal: punya sendiri, masih development, Laravel juga (Socialite lebih gampang)
3. Baru rollout ke DCMS/Silapor kalau pola sudah terbukti jalan di pilot

## 7. Keputusan Terbuka

- [ ] Opsi 1 (Passport di HRIS) atau Opsi 2 (IdP terpisah baca dari HRIS)?
- [ ] SLO dibutuhkan di fase awal atau bisa nunggu?
- [ ] Password lokal fallback: wajib di semua sistem satelit, atau opsional per sistem?
- [ ] Kapan mulai — nunggu fondasi HRIS stabil dulu (rekomendasi), atau ada urgensi lebih cepat?
