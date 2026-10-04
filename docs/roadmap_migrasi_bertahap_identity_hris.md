# Roadmap Migrasi Bertahap Identity & Data HRIS (Parallel Tables)

Dokumen ini adalah **turunan konkret** dari `roadmap_refactor_identity_hris.md`. Dokumen
itu menjelaskan arah dan prinsip; dokumen ini menjelaskan **cara mengerjakannya pelan-pelan**
berdasarkan hasil audit kode (4 Oktober 2026), bukan tebakan struktur.

## Tujuan

Membangun struktur identitas yang rapi — `employees → employee_*` (persons sudah dilebur ke employees) — **di samping**
struktur lama, tanpa menyentuh tabel lama, lalu memindahkan fitur satu per satu dengan urutan
dari yang paling aman ke yang paling berisiko.

```text
                 TABEL LAMA (tetap jalan, tidak diubah)
   users ── user_profiles ── user_job_positions ── user_contracts ...
     │              │                  │
     │       sinkron satu arah (lama → baru), idempotent
     ▼              ▼                  ▼
   employees ─── employee_identities/addresses/documents/careers ...
                 TABEL BARU (dibangun pelan-pelan)
```

Yang **tidak** dilakukan dokumen ini: mengubah, merename, atau menghapus tabel lama; SSO/Keycloak
(ditahan, lihat bagian 12); membuat dokter (bisa kapan saja setelah Fase 1, lihat bagian 11).

---

# 0. Status Terkini (diperbarui 5 Oktober 2026)

Bagian ini ringkasan posisi pekerjaan. Rinciannya ada di bagian bernomor di bawah; bagian yang masih
menyebut `persons`/`person_*` adalah catatan sejarah sebelum peleburan.

| Pekerjaan | Status |
|---|---|
| Tabel `employees` + `employee_identities/addresses/documents/careers` sebagai salinan | ✅ selesai (bagian 3) |
| `identity:backfill` + observer sinkron satu arah (users/user_profiles/jabatan/lampiran → employees) | ✅ selesai (bagian 3, 6) |
| `identity:verify` (parity check) | ✅ selesai (bagian 7) |
| Peleburan `persons` ke `employees`, `users.person_id` dibuang | ✅ selesai (migration `2026_10_27_100000`) |
| Approval membaca jabatan dari employees (`EmployeeContext`) | ✅ selesai (Slice 0, bagian 8) |
| Pembacaan `name`/`nip`/`company_id` dari employees (accessor di `User`) | ✅ selesai (bagian 8) |
| Query ke scope employees, penulisan lewat mutator, audit pindah ke `Employee`, foto pindah ke `users.profile_picture` | ✅ di kode (rilis transisi, commit `6919baa2`); **status deploy/migrate di produksi belum tercatat di sini, isi saat dikonfirmasi** |
| Pencabutan kolom `users.name/nip/company_id` | ⏸ disiapkan di `database/migrations-pending/`, **belum aktif** (rilis kedua, bagian 8) |
| `identity:purge-users` (hapus permanen akun demo + salinannya) | ✅ selesai, sudah dipakai untuk akun demo (bagian 5b) |
| Fase 0 (audit data: NIK kosong/ganda, selisih jabatan, jabatan aktif ganda) | ❓ hasilnya belum tercatat; **wajib sebelum Slice C dan unique NIK** |
| Slice A (izin/cuti/lembur), B (jadwal/absensi), C (layar edit dibalik), D (payroll) | ⏳ belum dimulai |
| `employee_identities.no_nik` unique | ⏳ menunggu data bersih |
| Dokter (`doctors`) | ⏳ belum |
| SSO/Keycloak | ⏸ ditahan (bagian 12) |

**Yang berubah sejak revisi dokumen sebelumnya:**

- Dua akun Super Admin kini juga punya baris `employees` (dulu dikecualikan), supaya kolom `users.name/nip/company_id`
  bisa dicabut. Login NIP mereka baru bekerja setelah `identity:backfill` (login email tidak terpengaruh).
- `employees.profile_picture` dibuang; foto adalah atribut akun di `users.profile_picture` (bagian 8).
- Pekerjaan UI Beranda (5 Oktober) mulai memakai data ini, tanpa mengubah alur identitas:
  - Welcome card membaca foto dari `User::profilePhotoPath()` dan nama panggilan dari `user_profiles.nickname`
    (dipakai kalau 3 sampai 15 huruf, selain itu nama lengkap).
  - Modal "Nama panggilan" (`Dashboard::saveNickname`, 3 sampai 12 huruf) **menulis `user_profiles.nickname` lewat
    model**, jadi observer memicu sinkron ke `employees.nickname`. Ini jalur tulis nickname kedua selain form Data Diri;
    tetap sumbernya `user_profiles` (edit ganda belum dibalik). Test: `DashboardNicknameTest`.
  - Pilihan mode nama (lengkap/panggilan) hanya disimpan di `localStorage` browser, bukan database.
  - Edit foto dari Beranda sempat dibuat lalu **dicabut**; foto hanya diubah dari Profil Saya
    (`HandlesProfilePhotoUpload`, `abort_unless` pemilik akun).
  - Kolom baru `m_companies.logo` (logo perusahaan di welcome card) tidak terkait identitas karyawan.

**Langkah berikutnya, berurutan:**

1. Konfirmasi rilis transisi sudah ter-deploy: `migrate`, `identity:backfill`, `identity:verify` bersih, `queue:restart`.
2. Selesaikan Fase 0 (bagian 5) dan putuskan data yang bertentangan bersama HC; baru pasang unique NIK.
3. Rilis kedua: aktifkan pencabutan kolom `users.name/nip/company_id` setelah rilis transisi stabil beberapa hari.
4. Slice A, lalu B (bagian 8), satu rilis per slice. Slice C dan D menyusul sesuai bagian 8.

---

# 1. Hasil Audit Singkat (Dasar Keputusan)

## Ukuran sistem

| Hal | Jumlah |
|---|---|
| Migration | 561 |
| Model | 229 |
| File test | 267 (60 payroll, 82 absensi/presensi) |
| Model dengan `user_id` | 56 |
| Deklarasi FK ke `users` | ± 87 |
| Pemakaian `nip` di `app/` + `resources/` | 342 |
| Pemakaian `->name` user | ± 1.100 |

## Nama tabel sebenarnya

Dokumen roadmap awal menyebut `users_data_diri`, `users_jabatan`, `users_riwayat_*`. **Tabel itu
tidak ada.** Yang ada:

```text
users                     akun (+ nip, name, company_id, soft delete, keycloak_sub)
user_profiles             1:1 dengan users — data pribadi DAN data kepegawaian bercampur
user_job_positions        riwayat jabatan (pakai start/end/status → "jabatan aktif")
user_contracts            kontrak
user_transitions (+ _details, _attachments)   mutasi/perubahan status
user_orientations, user_documents, user_profile_documents
user_hr_balances          saldo cuti/izin
user_koperasi, user_notification_preferences
```

## Temuan paling penting

1. **`user_profiles` sudah campuran Person + Employee.** Isinya:
   - Pribadi: NIK, KK, NPWP, tempat/tanggal lahir, gender, agama, alamat, kontak, foto,
     `marital_status`, rekening bank.
   - Kepegawaian: `job_position_id`, `pangkat_id`, `kelas_id`, `employee_type_id`,
     `joining_date`, `tmt_tetap`.
   (Kolom `golongan_id`, `org_structure_id`, `business_unit_id`, `contract_end_date`, dan
   `bank_id` sudah dihapus lewat migration sebelumnya; organisasi kini diturunkan dari jabatan,
   tanggal akhir kontrak ada di `user_contracts`/`user_transitions`. Daftar lengkap kolom saat
   ini ada di bagian 3.)
   Payroll membaca tabel ini intens (`employee_type_id` ±10x, `joining_date` ±7x, pangkat/kelas).
   Artinya **membelah `user_profiles` adalah bagian paling berisiko** dari seluruh refactor.
2. **Jabatan punya dua sumber.** `user_profiles.job_position_id` (dipakai ±8 tempat) dan
   `user_job_positions` aktif (`activeJobPosition`, dipakai ±56 tempat). Tidak ada mekanisme
   sinkron yang ditemukan. **Datanya kemungkinan sudah berbeda** — harus dicek sebelum migrasi.
3. **NIP adalah kunci gabung, bukan sekadar atribut.** `access_logs.employee_id` = `users.nip`
   (pipeline mesin absen, ±20 file), login (email atau NIP), import payroll Excel, pencarian
   di modul Persuratan, pembuatan NIP otomatis di `User::creating`.
4. **ID user (ULID) dipakai sebagai kunci di mana-mana**, termasuk tabel polimorfik (role,
   permission, token Sanctum, notifikasi, push subscription) dan dua DB lain (`recruitment`,
   `persuratan` lewat `hris_user_id`, tanpa FK). Selama `users.id` tidak berubah, semua itu aman.
5. **Global scope `non_karyawan`** menyaring 4 ULID yang di-hardcode dari setiap `User::query()`
   (`User::NON_KARYAWAN_IDS`; 2 di antaranya tetap boleh punya payroll lewat
   `PAYROLL_ELIGIBLE_EXECUTIVE_IDS`). Pemetaan `employees` harus memperlakukan ini dengan sengaja.
6. **Jaring pengaman test punya lubang.** Test jalan di SQLite memori, produksi MySQL. Dan ada
   test yang **sudah gagal sebelum refactor**: `DashboardTest` (5), `UbahAkunLoginTest` (5),
   `Auth/LoginTest` (1, role Super Admin duplikat). Harus dicatat sebagai baseline.

---

# 2. Prinsip Utama

```text
Tambah → Salin → Verifikasi → Pindah satu fitur → Ulangi
```

1. **Tabel lama tidak disentuh** (tidak ada ALTER, rename, drop) sampai fase cleanup.
2. **`users.id` dan `users.nip` tidak pernah diubah atau dipindah** sampai tahap terakhir.
3. **Data baru = salinan dari data lama.** Tidak ada input manual ke tabel baru di fase awal.
4. **Satu arah dulu** (lama → baru). Arah baru dibalik per modul, hanya saat layar edit modul
   itu dipindahkan (bagian 6).
5. **Tidak ada fitur yang dipindah sebelum parity check-nya nol selisih** (bagian 7).
6. **Satu fitur per rilis.** Jangan memindahkan dua modul dalam satu deploy.
7. **Payroll dipindah paling akhir** dan hanya dengan test snapshot hasil (bagian 9).

---

# 3. Skema Tabel Baru (Versi 1)

Tahap awal sengaja **kecil**. Tabel lain menyusul saat modulnya dipindah.

> **Perubahan arah (peleburan, migration `2026_10_27_100000_merge_persons_into_employees.php`):**
> tidak ada lagi entitas `Person`. Data pribadi menjadi kolom `employees`, `person_identities` /
> `person_addresses` / `person_documents` menjadi `employee_identities` / `employee_addresses` /
> `employee_documents`, dan `users.person_id` dibuang (tautan akun cukup `employees.user_id`).
> Dokter menjadi baris `employees` dengan tipe karyawan tersendiri (membalik catatan roadmap awal
> "jangan jadikan dokter karyawan palsu"); orang yang bukan karyawan dan bukan akun belum punya
> tempat (bisa ditambah sebagai tabel lain bila perlu, mis. tanggungan keluarga). Tidak ada data
> yang dipindah: semua isinya salinan, jadi tabel `persons*` dibuang lalu `identity:backfill`
> mengisi tabel baru dari `users` + `user_profiles`. Bagian lain dokumen ini yang masih menyebut
> `persons`/`person_*` adalah catatan sejarah sebelum peleburan.

## Karyawan: data pribadi, identitas, alamat, lampiran (SUDAH DIBANGUN)

Semuanya **salinan** dari `users` + `user_profiles` — tabel lama tetap sumber dan tetap diedit
seperti biasa (edit ganda dihindari dengan observer, bukan dengan mengubah halaman).

```text
employees   (kolom data pribadi, di samping kolom kepegawaian di bagian berikut)
  nama_lengkap (= users.name), nickname, gender, marital_status, place_of_birth, date_of_birth,
  religion_id, phone, whatsapp, email_pribadi
  ← kontak dilipat dulu (1:1); jadi tabel sendiri kalau butuh 1:N (kontak darurat)
  ← foto profil TIDAK di sini: atribut akun di users.profile_picture (bagian 8)

employee_identities  (1:1 dengan employees)
  no_nik, no_kk, npwp, no_bpjs_kes, no_bpjs_ket          ← NIK BELUM unique (bagian 5)
  bank_name, bank_account_number, bank_account_name      ← rekening ikut di sini (sensitif, 1:1)

employee_documents   (1:N, unique per employee_id + type) ← lampiran KTP/KK/STR/SIP/RKK
  type (string apa adanya, label lama dulu bebas ketik), file_path
  file_path SAMA dengan user_profile_documents: file terenkripsi di disk privat TIDAK dipindah,
  pembukaan tetap lewat route lampiran lama (dekripsi + cek izin).
  STR/SIP ikut disalin: karyawan nakes juga memilikinya, bukan cuma dokter.
  (user_documents = surat resmi terbitan admin; tidak ada kode yang memakainya - dicek isinya
  di DB dulu sebelum diputuskan, bukan sumber lampiran KTP/KK.)

employee_addresses   (1:N, unique per employee_id + type)
  type ('utama' dulu — form HRIS belum membedakan KTP/domisili), is_primary,
  country, province, city, district, village, address, postal_code, rt, rw
```

**Cara sinkron:** `App\Services\Identity\EmployeeSyncService` (satu fungsi, dipakai bersama)
dipanggil oleh `EmployeeSyncObserver` (`#[ObservedBy]` di `User`, `UserProfiles`,
`UserProfileDocument`, `UserJobPositions`) tiap model disimpan atau dihapus, dan oleh
`php artisan identity:backfill [--dry-run] [--user=NIP]` (nama lama `identity:backfill-employees`
tetap jalan sebagai alias). Dicek `identity:verify`.

- **`users.nip`, `users.name`, `users.company_id`: pembacanya sudah dipindah ke employees, pencabutan kolom
  disiapkan tapi belum aktif** (bagian 8, rilis kedua). Catatan inventarisasi sebelum pemindahan (Okt 2026): `name` ±680 titik (±133 query perlu ditulis ulang),
  `nip` ±310 (±110 query/kunci), `company_id` ±560 yang **356 di antaranya adalah pagar akses akun
  yang sedang login dan TIDAK boleh dipindah ke employees** (akun tanpa baris employees
  kehilangan scope perusahaannya = kebocoran data lintas perusahaan). Sesi/token tidak terpengaruh
  karena berkunci `users.id`.
- Kegagalan salin hanya dilaporkan ke log; menyimpan profil tidak ikut gagal.
- **Celah:** update/hapus massal tidak memicu event model (`$user->profile()->update()` lewat
  relasi, `UserProfiles::where()->update()`). `UserRepository::updateProfileUser` memanggil
  sinkron sendiri untuk jalur itu. Jangan menulis profil lewat update massal tanpa memanggil
  `EmployeeSyncService`.

## Karyawan: `employees`, `employee_careers` (SUDAH DIBANGUN)

Migration `2026_10_26_110000_create_employees_tables.php`. Salinan: tabel lama
tetap sumber, belum dibaca fitur apa pun.

```text
employees   (kondisi SAAT INI)
  id, user_id (unique → users; tautan akun ↔ karyawan, jembatan ke fitur lama),
  nip (salinan users.nip), company_id, employee_type_id,
  job_position_id, pangkat_id, kelas_id      ← boleh null (PHK mengosongkan ketiganya)
  joining_date, tmt_tetap,
  status (aktif | nonaktif ← users.is_active + soft delete)

employee_careers   (RIWAYAT perubahan jabatan, dari user_job_positions)
  id, employee_id, user_job_position_id (unique, kunci sinkron), job_position_id,
  pangkat_id, kelas_id (snapshot), assigned_by, start_date, end_date, status, source
```

Keputusan:
- Pangkat dan kelas ikut di `employees` (kondisi sekarang) dan di `employee_careers` (snapshot).
  Karena itu `employee_grades` terpisah **tidak jadi diperlukan**. Pangkat/kelas memang ikut
  jabatan (`UserTransitionService` mengambilnya dari jabatan baru saat promosi/mutasi).
- **Riwayat pangkat/kelas hanya bisa direkonstruksi sebagian.** `user_job_positions` tidak
  mencatatnya. Baris riwayat lama sengaja NULL (tidak menebak dari jabatan sekarang); hanya
  baris aktif yang berisi snapshot, dan snapshot yang sudah tersimpan tidak ditimpa saat baris
  ditutup.
- **Dua sumber jabatan tidak ditutupi.** `employees.job_position_id` datang dari
  `user_profiles`, riwayat dari `user_job_positions`. Selisihnya dilaporkan `identity:verify`
  (sudah dibangun, lihat bagian 7).
- 2 akun Super Admin (`User::NON_KARYAWAN_IDS` dikurangi `PAYROLL_ELIGIBLE_EXECUTIVE_IDS`)
  **kini juga mendapat baris `employees`** (revisi 5 Oktober: dulu dikecualikan dan namanya tetap di `users.name`).
  Alasannya, kolom `users.name/nip/company_id` tidak bisa dicabut kalau ada akun tanpa sumber di employees. Mereka tetap
  tersaring dari `User::query()` oleh scope `non_karyawan`. Direktur dan Sekretaris Direktur tetap karyawan karena masih
  ada payroll.

**Cara sinkron:** `EmployeeSyncService` dipanggil `EmployeeSyncObserver` (di `User`,
`UserProfiles`, `UserJobPositions`) dan oleh `php artisan identity:backfill-employees
[--dry-run] [--user=NIP]`. Jalankan **setelah** `identity:backfill`.

**Celah (update/hapus massal tidak memicu event):**
`$user->jobPositions()->where(...)->delete()` di `EditKaryawan` & `CreateKaryawan`, dan
`->update()` di `UserTransitionService` (termination). Rapikan dengan menjalankan ulang
`identity:backfill-employees`; `identity:verify` yang akan melaporkan selisihnya.

## Menyusul (tidak di Versi 1)

| Tabel | Sumber lama | Kapan |
|---|---|---|
| `employee_contracts` | `user_contracts` | saat modul kontrak dipindah |
| `employee_bank_accounts` (riwayat ganti rekening, 1:N) | kolom bank di `employee_identities` | kalau butuh riwayat rekening |
| `employee_transitions` | `user_transitions*` | saat modul mutasi dipindah |
| `doctors` | — (baru) | kapan saja setelah Fase 1 |

> Organisasi **tidak** disalin ke `employees` (kolom `org_structure_id` di profil sudah dihapus
> 2026-09-20). Organisasi diturunkan dari jabatan aktif (`job_position → org_structure`), seperti
> perilaku sekarang (`AttendaceReqService`: `jobPosition->org_structure_id`).

## Pemetaan Kolom `user_profiles` (Kondisi Saat Ini)

Dari `UserProfiles::$fillable` dan migration terkini. Model ini memakai `LogsFieldAuditChanges`
(perubahan field dicatat ke audit log) — observer sinkron tidak boleh memicu audit ganda.

| Kolom lama | Tujuan | Catatan |
|---|---|---|
| `users.name` | `employees.nama_lengkap` | |
| `nickname`, `gender`, `marital_status`, `place_of_birth`, `date_of_birth`, `religion_id` | `employees` (kolom) | |
| `profile_picture` (json) | `users.profile_picture` | Atribut akun, bukan data kepegawaian; `employees.profile_picture` dibuang (bagian 8) |
| `no_nik`, `no_kk`, `npwp` | `employee_identities` | NIK/NPWP disimpan plaintext sekarang |
| `no_bpjs_kes`, `no_bpjs_ket` | `employee_identities` | |
| `country`, `province`, `city`, `district`, `village`, `address`, `postal_code`, `rt`, `rw` | `employee_addresses` (`type` = utama) | |
| `user_profile_documents` (`label`, `file_path`) | `employee_documents` (`type`, `file_path`) | Baris disalin, file tidak dipindah |
| `phone`, `whatsapp`, `email_pribadi` | `employees` (kolom) | `users.email` = email akun, tetap di `users` |
| `employee_type_id`, `joining_date`, `tmt_tetap` | `employees` | |
| `job_position_id`, `pangkat_id`, `kelas_id` | `employees` (kondisi sekarang) + snapshot di `employee_careers` | Payroll membaca pangkat/kelas dari tabel lama sampai slice payroll |
| `bank_name`, `bank_account_number`, `bank_account_name` | `employee_identities` | Dibaca payroll dari tabel lama sampai slice payroll |
| `job_position_id` | **tidak disalin** | Duplikat sumber jabatan; pakai `user_job_positions` |
| `users.is_active` + soft delete | `employees.status` | |
| `users.email`, `password`, `fcm_token`, `keycloak_sub` | tetap di `users` | Data akun |

---

# 4. Peta Status Tabel

```text
🟢 Aman disentuh     🟡 Perlu migrasi     🔴 Jangan disentuh dulu
```

| Area | Status | Catatan |
|---|---|---|
| `users` | 🔴 | Kunci di seluruh sistem. Tidak diubah. |
| `user_profiles` | 🟡 | Dibaca (disalin), tidak diubah. |
| `user_job_positions` | 🟡 | Dibaca (disalin), tidak diubah. |
| `attendance_requests` + detail | 🟢 | `user_id` tidak berubah. Hanya cara membaca data karyawan yang pindah. |
| Jadwal / `access_logs` / absensi | 🟡 | Join NIP tetap. Pindah setelah izin/cuti/lembur. |
| Payroll (semua) | 🔴 | Terakhir. Hanya dengan test snapshot. |
| Persuratan (DB lain) | 🟢 | `hris_user_id` tidak berubah. |
| Rekrutmen (DB lain) | 🟢 | Tidak terkait user. |
| Auth/login/Sanctum/role | 🔴 | Tidak disentuh. |

---

# 5. Fase 0 — Persiapan & Pembersihan Data

**Sebelum satu tabel pun dibuat.**

```text
□ Backup database produksi (dan catat cara restore)
□ Siapkan staging dengan salinan data produksi (anonymized jika perlu)
□ Catat baseline test gagal yang sudah ada (DashboardTest, UbahAkunLoginTest, LoginTest)
□ Bereskan test baseline yang gagal ATAU tandai eksplisit "bukan karena refactor"
□ Jalankan test absensi + payroll di MySQL staging (bukan hanya SQLite) dan catat hasilnya
□ Query: hitung user tanpa user_profiles
□ Query: hitung NIK kosong / NIK ganda antar user
□ Query: bandingkan user_profiles.job_position_id vs jabatan aktif user_job_positions
□ Query: hitung user dengan >1 jabatan aktif, atau 0 jabatan aktif
□ Query: hitung user soft-deleted dan akun di NON_KARYAWAN_IDS
```

Hasil query dicatat di dokumen terpisah (`HRIS DATABASE MAP`). **Selisih jabatan dan NIK ganda
diputuskan oleh HC/Admin** — kode tidak boleh menebak mana yang benar.

> **Status (5 Oktober 2026):** hasil Fase 0 belum tercatat di dokumen ini. Fase 1 dan sebagian slice sudah
> dikerjakan lebih dulu, jadi Fase 0 sekarang berfungsi sebagai **syarat sebelum Slice C dan unique NIK**, bukan
> sebelum tabel dibuat. `identity:verify` melaporkan sebagian temuannya (selisih jabatan, jabatan aktif ganda).

## 5b. Hapus akun demo — `identity:purge-users` (SUDAH DIBANGUN)

`php artisan identity:purge-users {nip*} [--force] [--nullify-approvals] [--also=tabel.kolom]`. Menghapus **permanen**
user (mis. akun demo) beserta salinan employees dan data approval-nya. Tanpa `--force` hanya melapor (dry-run).

- NIP harus ada semua; akun non-karyawan/eksekutif (`User::NON_KARYAWAN_IDS`) ditolak.
- User yang punya penghalang **dilewati** dan dilaporkan beserta alasannya, sisanya tetap dihapus; pengecekan
  diulang sampai stabil (melewati satu user bisa membuat user lain ikut bermasalah).
- Penghalang: FK ke `users` tanpa cascade yang berisi baris (kecuali diizinkan `--also`), pengajuan yang sudah
  diproses orang lain, dan aktivitas approval pada pengajuan orang lain (kecuali `--nullify-approvals`).
- Satu transaksi; user dihapus lewat query langsung supaya observer tidak membuat ulang baris employees.
- Test: `PurgeUsersTest`. Jalankan `--force` hanya setelah laporan dry-run dibaca dan backup dibuat.

---

# 6. Fase 1 — Fondasi (Tidak Ada Fitur yang Berubah)

**Status: ✅ selesai.** Catatan sejarah: tahap awal membuat entitas `Person` terpisah; sudah dilebur ke `employees`
(bagian 3), jadi butir `Person` di bawah tidak berlaku lagi.

```text
✅ Migration: employees (+ employee_identities, employee_addresses, employee_documents), employee_careers
✅ Model + relasi: Employee, EmployeeCareer, dst. (Person sudah dilebur)
✅ Relasi tambahan di User: employee()  (kini juga dimuat otomatis lewat $with)
✅ Command: identity:backfill  (--dry-run, --user=NIP, idempotent)
✅ Observer satu arah di model lama (UserProfiles, UserJobPositions, User, UserProfileDocument) → sinkron ke tabel baru
✅ Command: identity:verify  (bagian 7)
✅ Test: backfill dua kali tidak menghasilkan duplikat (EmployeeSyncTest)
✅ Test: observer menyinkronkan perubahan profil/jabatan (EmployeeSyncTest)
```

## Aturan `identity:backfill`

- Idempotent: kunci pencarian `employees.user_id`; jalan dua kali = tidak
  ada duplikat.
- `--dry-run` menampilkan jumlah yang akan dibuat/diperbarui dan **daftar konflik**, tanpa menulis.
- NIK ganda atau kosong **dilaporkan, tidak membuat proses gagal**. `employee_identities.no_nik` baru diberi
  constraint unik setelah data bersih.
- Berjalan dalam transaksi per user supaya satu data buruk tidak menggagalkan semuanya.
- Tidak pernah menulis ke tabel lama.
- Tidak menyalin password, token, atau data yang terenkripsi payroll.

## Sinkron satu arah

```text
user_profiles / user_job_positions / users   (sumber, diedit seperti biasa)
        │  observer (saved/deleted)
        ▼
employees / employee_*   (salinan, hanya ditulis oleh sinkron)
```

Selama fase ini **tabel baru read-only bagi aplikasi**. Kalau observer gagal, kejadian dicatat
ke log dan `identity:verify` yang menangkap selisihnya — alur edit data lama tidak boleh ikut
gagal.

---

# 7. Parity Check — `identity:verify` (SUDAH DIBANGUN)

`php artisan identity:verify [--user=NIP] [--limit=15]` — hanya membaca, exit code 1 kalau ada
selisih, keluarannya cuma jumlah dan NIP. Dimensi yang dicek: `employees` (data pribadi,
identitas & rekening, alamat, lampiran; baris ada, data kepegawaian, akun
non-karyawan tidak punya employee), jabatan (paling banyak satu aktif di riwayat; profil =
riwayat aktif), dan jumlah `employee_careers` vs `user_job_positions`. Selisih diperbaiki dengan
`identity:backfill`; selisih jabatan profil-vs-riwayat adalah
data lama yang diputuskan HC.

Ini jaring pengaman utama. Command membandingkan **nilai dari jalur lama vs jalur baru** untuk
semua karyawan dan melaporkan selisih.

| Dimensi | Jalur lama | Jalur baru |
|---|---|---|
| Perusahaan | `users.company_id` | `employees.company_id` |
| Tanggal masuk | `user_profiles.joining_date` | `employees.joining_date` |
| Tipe karyawan | `user_profiles.employee_type_id` | `employees.employee_type_id` |
| Jabatan aktif | `user->activeJobPosition` | `employee->activeJobPosition` |
| Organisasi | `jobPosition->org_structure_id` | sama, via jabatan baru |
| Atasan (jabatan induk) | parent dari jabatan aktif | sama, via jabatan baru |
| Status aktif | `users.is_active` + soft delete | `employees.status` |

```text
Output: ringkasan jumlah cocok / selisih + daftar NIP yang berbeda per dimensi.
Exit code ≠ 0 kalau ada selisih → bisa dipasang di CI/cron.
```

**Aturan:** sebuah fitur **tidak boleh dipindah** sebelum dimensi yang dibaca fitur itu
menunjukkan **nol selisih** di staging dan produksi.

---

# 8. Urutan Migrasi Fitur (Slice)

Setiap slice = "fitur ini membaca data karyawan lewat model baru, bukan `user->profile`".
**Tabel fiturnya sendiri tidak berubah** (`user_id` tetap).

## Slice Pembacaan — `name`, `nip`, `company_id` dari employees (SUDAH DIPINDAH lewat accessor)

`User` memuat relasi `employee` otomatis (`protected $with = ['employee']`: satu query per batch, bukan
per baris, termasuk saat dimuat lewat relasi seperti `$payroll->user`) dan **menimpa pembacaan kolom**
lewat accessor `getNameAttribute` / `getNipAttribute` / `getCompanyIdAttribute`:
`$user->name` = `employees.nama_lengkap`, `$user->nip` = `employees.nip`, `$user->company_id` =
`employees.company_id`, dan jatuh balik ke kolom `users.*` untuk akun tanpa baris employees (dua Super
Admin) atau salinan yang belum terisi. Fallback itu yang membuat pagar akses `company_id` (±356 titik)
tidak fail-open untuk akun tanpa employee. Plus `initials()` (inisial avatar) dan `full_name` (alias).
Dengan begitu semua pembacaan PHP/Blade (±1.000 titik) beralih tanpa edit per file.

**Yang TIDAK beralih (tetap membaca kolom users):** query SQL (`where`/`orderBy`/`pluck`/`select`/join,
`Auth::attempt`), dirty-tracking Eloquent, dan penulisan. Itu disengaja: login, pencocokan mesin absen,
import Excel, dan pencarian memakai `users.nip`/`users.name` yang masih disinkron sebagai cermin.

**Aturan:** kode yang butuh nilai SUMBER `users.*` (`EmployeeSyncService`, `identity:verify`) WAJIB memakai
`rawName()` / `rawNip()` / `rawCompanyId()`, bukan `$user->name`, supaya salinan tidak disalin/dibandingkan
dari dirinya sendiri. Jangan memanggil `$this->name` di dalam accessor (rekursi).

**Risiko yang diterima:** pembacaan kini bergantung pada salinan. Kalau sinkron gagal (update massal,
error yang hanya dilog), tampilan menampilkan nilai lama dan **form edit yang mengisi dari `$record->name`
bisa menulis balik nilai lama ke `users.name`**. `identity:verify` (membandingkan nilai mentah) yang
menangkapnya - jalankan berkala. Inisial avatar dihitung dari format nama, jadi `nama_lengkap` harus
berformat sama dengan `users.name`.

**Belum:** ±130 query yang memakai kolom `name`/±110 query dan kunci `nip` bila ingin ditulis ulang agar
membaca `employees` (tanpa manfaat fungsional selama cermin disinkron), dan membalik arah penulisan
(edit langsung ke employees lalu dicerminkan ke users).

## Slice Penulisan, Audit, Foto, dan Pencabutan Kolom (rilis transisi)

- **Query:** semua query yang membaca `users.name|nip|company_id` (±250 titik) ditulis ulang ke employees lewat scope
  `User::whereNip|whereNipLike|whereCompany|searchName|searchNameOrNip|orderByName|orderByNip`. Login NIP lewat
  `KaryawanAwareUserProvider::retrieveByCredentials` -> `employees.nip`. Dua akun Super Admin juga punya baris
  employees. Terbukti: 38 file regresi identik dengan baseline `HEAD` di mode transisi dan di skema akhir.
- **Penulisan:** mutator `name`/`nip`/`company_id` di `User` menahan nilai ke employees (`EmployeeSyncObserver` menyimpannya
  saat `saved`) dan, selama kolom users ada, juga menulis kolom itu (cermin). Penulis lama tidak diubah.
  `User::legacyColumns()` mendeteksi skema otomatis (override: env `IDENTITY_LEGACY_USER_COLUMNS`, `config/identity.php`).
- **Audit:** perubahan nama/NIP/perusahaan dicatat `Employee` dengan kunci `{user_id}:name|nip|company_id` yang sama dengan
  catatan lama (riwayat Data Diri tetap satu aliran); `User` hanya mengaudit `email` dan `is_active`.
- **Foto profil:** `users.profile_picture` (atribut akun), `User::profilePhotoPath()` menormalkan bentuk data lama;
  `employees.profile_picture` dibuang (migration `2026_10_29_100000`). `user_profiles.profile_picture` tetap sebagai sumber
  lama sampai cleanup.
- **Pencabutan kolom (BELUM aktif):** `database/migrations-pending/2026_10_28_100000_drop_name_nip_company_id_from_users_table.php`
  (lihat README di folder itu). Aktifkan sebagai rilis kedua setelah rilis transisi stabil, `identity:backfill` selesai, dan
  `identity:verify` bersih. `up()` menolak bila data tidak bersih; `down()` memulihkan kolom dari employees (teruji).
- **Urutan rilis transisi:** backup -> deploy + `migrate` berbarengan (selang antara deploy dan migrate membuat foto tidak tampil)
  -> `identity:backfill` SEGERA (baris employees dua Super Admin baru dibuat di sini; sebelum itu login NIP mereka gagal, login
  email tetap jalan) -> `identity:verify` -> `queue:restart`.

## Slice 0 — Approval Management (SUDAH DIPINDAH, dikerjakan lebih dulu dari Slice A)

Approval hanya membaca **jabatan pengaju/penyetuju** (dan organisasi/departemen turunannya) dari
`user_profiles.job_position_id`; penyetuju tipe `role` dan `user` tidak bergantung pada data
kepegawaian. Enam titik baca (`ApprovalSnapshotRepository` 2, `ApprovalPermissionService` 5) kini
lewat `App\Services\Identity\EmployeeContext` (jabatan, organisasi, departemen dari `employees`,
**jatuh balik ke `user_profiles`** untuk akun tanpa baris employees: Super Admin/Direktur, dokter
non-karyawan, atau salinan belum tersinkron). Karena approval membaca profil (bukan riwayat),
selisih jabatan profil-vs-riwayat yang tersisa tidak memengaruhi slice ini. Rollback = ubah isi
`EmployeeContext`. Catatan: `ApprovalPermissionService::getDeniedReason` sebelumnya membaca
`$user->job_position_id` yang tidak ada di model `User`; kini memakai nilai yang benar (hanya
memengaruhi teks alasan penolakan, bukan siapa yang boleh menyetujui).

## Slice A — Pengajuan Izin, Cuti, Lembur

Alasan dipilih: pembacaan data karyawan di fitur ini sempit.

| Yang dibaca | Lokasi sekarang |
|---|---|
| `company_id` (setting, kuota) | `AttendaceReqService` |
| `joining_date` (syarat masa kerja cuti) | `AttendaceReqService:401, :506` |
| Jabatan aktif + organisasi (pilih pengganti) | `AttendaceReqService:195-205` |
| Jabatan induk pengaju (step atasan approval) | `ApprovalFlowService:441` |

```text
□ Parity check dimensi: perusahaan, tanggal masuk, jabatan aktif, organisasi, atasan = 0 selisih
□ Tambah satu titik akses (mis. Employee::forUser($user)) — fitur membaca lewat sana
□ Ganti pembacaan di AttendaceReqService dan ApprovalFlowService
□ Test absensi/pengajuan yang ada tetap hijau (tanpa diubah)
□ Test baru: hasil identik untuk user dengan/tanpa jabatan, dengan/tanpa joining_date
□ Rilis ke staging → verifikasi manual alur ajukan → setujui → batalkan
□ Rilis ke produksi, pantau log observer & identity:verify beberapa hari
```

## Slice B — Jadwal, Absensi, Rekap

```text
□ Pindahkan ScheduleResolver / penentuan jadwal user ke model baru
□ NIP tetap dari users.nip untuk join access_logs (tidak diubah)
□ Test: NormalShiftScanMatrix, FlexibleScheduleSync, ExtraShiftScanScenario, dst. tetap hijau
□ Bandingkan hasil rekap satu periode lama vs baru (snapshot)
```

## Slice C — Layar Edit Kepegawaian (arah sinkron dibalik)

Baru di sini layar edit data karyawan menulis ke **tabel baru**, dan tabel lama diisi oleh
sinkron balik (kebalikan fase 1) **selama modul lain masih membaca tabel lama**.

```text
□ Pindahkan form Data Diri → employees (+ employee_identities, employee_addresses)
□ Pindahkan form Jabatan → employees + employee_careers (satu sumber jabatan, dua sumber lama disatukan)
□ Balik arah observer untuk modul ini saja
□ identity:verify tetap dijalankan di kedua arah
```

## Slice D — Payroll (terakhir)

```text
□ Pangkat/kelas sudah ada di employees & employee_careers; rekening di employee_identities
□ Test snapshot: generate payroll satu periode dari jalur lama dan jalur baru,
  bandingkan SETIAP baris payroll_items — harus identik sampai ke sen
□ Pindahkan calculator satu per satu (GajiPokok, Tunjangan, BPJS, ...), bukan sekaligus
□ Jangan ubah enkripsi PayrollEncrypted
```

Tidak ada slice yang dikerjakan paralel dengan slice lain.

---

# 9. Strategi Rilis & Rollback

Karena tabel lama tidak disentuh, **rollback = kembalikan pembacaan ke jalur lama**. Setiap slice
dibuat bisa dikembalikan dengan satu commit/flag.

```text
□ Setiap slice di branch sendiri, satu rilis, satu PR
□ Titik akses tunggal (Employee::forUser) → mengganti sumber cukup di satu tempat
□ Pertahankan jalur lama sampai slice stabil minimal satu siklus payroll
□ Jika identity:verify menemukan selisih setelah rilis → kembalikan slice, perbaiki data, ulangi
```

---

# 10. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Salinan tertinggal dari data lama | Fitur baru menampilkan data basi | Observer + `identity:verify` terjadwal |
| Dua sumber jabatan berbeda | Atasan/organisasi salah | Fase 0: bandingkan & putuskan; backfill pakai `user_job_positions` |
| NIK ganda/kosong | Backfill gagal atau salah gabung | Dilaporkan, unik ditunda |
| Akun `NON_KARYAWAN_IDS` | Masuk/tidak masuk `employees` salah | Aturan eksplisit di backfill, dites |
| Observer gagal | Alur edit data lama terganggu | Observer tidak melempar exception; gagal = log |
| Test SQLite lolos tapi MySQL gagal | Bug lolos ke produksi | Jalankan test kritis di MySQL staging |
| Test baseline sudah gagal | Regresi tersamar | Catat baseline di Fase 0 |
| Payroll berubah diam-diam | Uang | Slice D terakhir + snapshot per baris |

---

# 11. Dokter (Terpisah dari Slice di Atas)

Kebutuhan dokter tidak harus menunggu migrasi selesai. Setelah Fase 1:

```text
□ Tabel doctors (employee_id, nomor dokter, spesialisasi, SIP, STR, status)
□ Dokter = baris employees (tipe karyawan "Dokter") + doctors; bukan entitas Person terpisah
□ Dokter yang juga karyawan lain tetap satu baris employees
```

Kolom kepegawaian di `user_profiles` semuanya nullable, sehingga dokter non-karyawan tetap bisa
punya `users` tanpa data karyawan. Kalau kebutuhan dokter mendesak sebelum Fase 1 selesai,
alternatif sementara yang tanpa refactor: tabel `doctors` yang menunjuk `user_id`.

---

# 12. SSO / Keycloak — Ditahan

Per 4 Oktober 2026 tombol "Masuk dengan SSO" disembunyikan dari halaman login. Yang **tidak**
dihapus: route `sso.redirect`/`sso.callback`, `KeycloakController`, kolom `users.keycloak_sub`,
panel `/keycloak`, dan sinkron grup. Pencocokan SSO memakai `users.nip`
(`preferred_username`), jadi **NIP tetap di `users` selama migrasi** agar SSO bisa dinyalakan
lagi tanpa penyesuaian. Mengaktifkan kembali SSO = mengembalikan pembatas "atau" + tombol di
`resources/views/auth/login.blade.php`.

---

# 13. Aturan Keamanan

1. Backup sebelum setiap migration produksi; coba dulu di staging.
2. Migration hanya aditif; semua `down()` bisa menghapus tabel baru tanpa efek ke tabel lama.
3. Jangan log NIK, NPWP, rekening, atau password. Command verify menampilkan NIP saja.
4. Jangan menyalin password atau token ke tabel baru.
5. Batasi akses tabel `employees` (dan employee_*) dengan permission yang sama seperti data pribadi saat ini.
6. Jangan mengubah enkripsi payroll.
7. Jangan menghapus tabel lama sebelum seluruh dependensinya terverifikasi (Fase Cleanup).

---

# 14. Hal yang Jangan Dilakukan

```text
❌ ALTER / rename / drop tabel lama selama slice berjalan
❌ Memindahkan users.nip atau mengubah users.id
❌ Memindahkan dua modul dalam satu rilis
❌ Input manual ke tabel baru sebelum arah sinkron dibalik
❌ Memasang unique pada employee_identities.no_nik sebelum data bersih
❌ Menebak jabatan mana yang benar saat dua sumber berbeda
❌ Memindahkan payroll tanpa test snapshot per baris
❌ Menyentuh login/Sanctum/role/permission
❌ Menjalankan backfill di produksi tanpa --dry-run lebih dulu
```

---

# 15. Cleanup (Jauh di Depan)

Hanya setelah semua slice stabil minimal beberapa siklus payroll:

```text
□ Cari seluruh pemakaian kolom legacy (user_profiles.*, user_job_positions)
□ Tandai deprecated, ubah ke jalur baru yang tersisa
□ Hapus observer sinkron
□ Pertimbangkan menghapus kolom/tabel legacy hanya jika benar-benar tidak dipakai
□ Pertimbangkan memindahkan NIP ke employees (butuh slice sendiri karena join access_logs)
```

---

# 16. Ringkasan Urutan

Posisi per 5 Oktober 2026: Fase 1, Slice 0 (approval), Slice Pembacaan, dan Slice Penulisan/Audit/Foto **sudah
dikerjakan**; pencabutan kolom `users.*` menunggu rilis kedua; Fase 0 (audit data) belum tercatat; Slice A sampai D
belum dimulai.

```text
FASE 0  Persiapan, backup, bersihkan data (jabatan ganda, NIK)   ← belum tercatat; syarat Slice C & unique NIK
   ↓
FASE 1  Tabel baru + backfill + observer + identity:verify   ✅ selesai (tidak ada fitur berubah)
   ↓
SLICE 0 Approval, Pembacaan name/nip/company_id, Penulisan/Audit/Foto   ✅ di kode (rilis transisi)
   ↓
RILIS 2 Cabut kolom users.name/nip/company_id   ⏸ migrations-pending, setelah rilis transisi stabil
   ↓
SLICE A Izin / Cuti / Lembur
   ↓
SLICE B Jadwal / Absensi / Rekap
   ↓
SLICE C Layar edit Kepegawaian (arah sinkron dibalik)
   ↓
SLICE D Payroll (snapshot per baris)
   ↓
CLEANUP

(paralel, terpisah)  Dokter — setelah Fase 1
(ditahan)            SSO / Keycloak
```

---

# 17. Langkah Berikutnya

(Versi awal bagian ini berisi "kerjakan Fase 0 sebelum membuat migration". Migration Fase 1 sudah dibuat dan
dipakai, jadi urutannya direvisi 5 Oktober 2026.)

1. **Konfirmasi rilis transisi di produksi**: backup, `migrate`, `identity:backfill` segera (baris employees dua
   Super Admin dibuat di sini), `identity:verify` bersih, `queue:restart`. Catat tanggalnya di bagian 0.
2. **Fase 0**: lima query audit (user tanpa profil, NIK kosong/ganda, selisih jabatan, jabatan aktif ganda/kosong,
   akun non-karyawan) dan baseline test di MySQL staging. Bawa hasilnya ke HC/Admin untuk memutuskan data yang
   bertentangan. Setelah bersih, pasang unique `employee_identities.no_nik`.
3. **Rilis kedua**: pindahkan migration pencabutan kolom dari `database/migrations-pending/` setelah rilis transisi
   stabil beberapa hari (syarat lengkap ada di README folder itu).
4. **Slice A**, satu rilis, parity check dimensinya nol selisih lebih dulu.

**Jangan memindahkan Slice C (arah sinkron dibalik) atau memasang unique NIK sebelum hasil Fase 0 ada.**
