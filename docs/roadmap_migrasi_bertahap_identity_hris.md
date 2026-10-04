# Roadmap Migrasi Bertahap Identity & Data HRIS (Parallel Tables)

Dokumen ini adalah **turunan konkret** dari `roadmap_refactor_identity_hris.md`. Dokumen
itu menjelaskan arah dan prinsip; dokumen ini menjelaskan **cara mengerjakannya pelan-pelan**
berdasarkan hasil audit kode (4 Oktober 2026), bukan tebakan struktur.

## Tujuan

Membangun struktur identitas yang rapi — `persons → employees → employee_*` — **di samping**
struktur lama, tanpa menyentuh tabel lama, lalu memindahkan fitur satu per satu dengan urutan
dari yang paling aman ke yang paling berisiko.

```text
                 TABEL LAMA (tetap jalan, tidak diubah)
   users ── user_profiles ── user_job_positions ── user_contracts ...
     │              │                  │
     │       sinkron satu arah (lama → baru), idempotent
     ▼              ▼                  ▼
   persons ─── employees ─── employee_careers ...
                 TABEL BARU (dibangun pelan-pelan)
```

Yang **tidak** dilakukan dokumen ini: mengubah, merename, atau menghapus tabel lama; SSO/Keycloak
(ditahan, lihat bagian 12); membuat dokter (bisa kapan saja setelah Fase 1, lihat bagian 11).

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

## Orang: `persons`, `person_identities`, `person_addresses` (SUDAH DIBANGUN)

Migration `2026_10_26_100000_create_persons_tables.php`. Semuanya **salinan** dari `users` +
`user_profiles` — tabel lama tetap sumber dan tetap diedit seperti biasa (edit ganda dihindari
dengan observer, bukan dengan mengubah halaman).

```text
persons
  id (ulid), user_id (nullable, unique → users), nama_lengkap (= users.name), nickname,
  gender, marital_status, place_of_birth, date_of_birth, religion_id, profile_picture (json),
  phone, whatsapp, email_pribadi          ← kontak dilipat dulu (1:1), jadi person_contacts
                                            kalau butuh 1:N (kontak darurat)

person_identities  (1:1 dengan persons)
  no_nik, no_kk, npwp, no_bpjs_kes, no_bpjs_ket          ← NIK BELUM unique (bagian 5)
  bank_name, bank_account_number, bank_account_name      ← rekening ikut di sini (sensitif, 1:1)

person_documents   (1:N, unique per person_id + type) ← lampiran KTP/KK/STR/SIP/RKK
  type (string apa adanya, label lama dulu bebas ketik), file_path
  file_path SAMA dengan user_profile_documents: file terenkripsi di disk privat TIDAK dipindah,
  pembukaan tetap lewat route lampiran lama (dekripsi + cek izin).
  STR/SIP ikut disalin: karyawan nakes juga memilikinya, bukan cuma dokter.
  (user_documents = surat resmi terbitan admin; tidak ada kode yang memakainya - dicek isinya
  di DB dulu sebelum diputuskan, bukan sumber lampiran KTP/KK.)

person_addresses   (1:N, unique per person_id + type)
  type ('utama' dulu — form HRIS belum membedakan KTP/domisili), is_primary,
  country, province, city, district, village, address, postal_code, rt, rw
```

**Cara sinkron:** `App\Services\Identity\PersonSyncService` (satu fungsi, dipakai bersama)
dipanggil oleh `PersonSyncObserver` (`#[ObservedBy]` di `User` dan `UserProfiles`) tiap model
disimpan, dan oleh `php artisan identity:backfill [--dry-run] [--user=NIP]`.

- **`users.person_id` (SUDAH DIBANGUN)**: tautan akun → orang, nullable & unique, migration
  `2026_10_26_130000_add_person_id_to_users_table.php`. Diisi `PersonSyncService` lewat query
  langsung (bukan `$user->save()`, supaya tidak memicu observer lagi) dan oleh
  `identity:backfill`; dicek `identity:verify`. Relasi `User::person()` dan `User::employee()`
  sudah ada tapi belum dipakai fitur apa pun. `persons.user_id` tetap sebagai jembatan sampai
  cleanup. **`users.nip` dan `users.name` belum dicabut** - itu baru setelah pembacanya (±342
  pemakaian `nip`, ±1.100 `name`, login, join `access_logs`) dipindah; sesi/token tidak
  terpengaruh karena berkunci `users.id`, tapi pembacaan langsung kolom itu akan error.
- Kegagalan salin hanya dilaporkan ke log; menyimpan profil tidak ikut gagal.
- **Celah:** update massal tidak memicu event model (`$user->profile()->update()` lewat
  relasi, `UserProfiles::where()->update()`). `UserRepository::updateProfileUser` sudah
  diganti ke `$user->profile->update()`. Jangan menulis profil lewat update massal.
- Perubahan baru: update profil lewat jalur itu kini juga memicu audit field
  (`LogsFieldAuditChanges`) yang sebelumnya terlewat.

## Karyawan: `employees`, `employee_careers` (SUDAH DIBANGUN)

Migration `2026_10_26_110000_create_employees_tables.php`. Salinan seperti `persons*`: tabel lama
tetap sumber, belum dibaca fitur apa pun.

```text
employees   (kondisi SAAT INI)
  id, person_id → persons, user_id (unique → users; jembatan ke fitur lama),
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
  (belum dibangun).
- 2 akun Super Admin (`User::NON_KARYAWAN_IDS` dikurangi `PAYROLL_ELIGIBLE_EXECUTIVE_IDS`)
  mendapat `persons` tapi **tidak** mendapat `employees`. Direktur dan Sekretaris Direktur tetap
  karyawan karena masih ada payroll.

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
| `person_bank_accounts` (riwayat ganti rekening, 1:N) | kolom bank di `person_identities` | kalau butuh riwayat rekening |
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
| `users.name` | `persons.nama_lengkap` | |
| `nickname`, `gender`, `marital_status`, `place_of_birth`, `date_of_birth`, `religion_id` | `persons` | |
| `profile_picture` (json) | `persons.foto` | |
| `no_nik`, `no_kk`, `npwp` | `person_identities` | NIK/NPWP disimpan plaintext sekarang |
| `no_bpjs_kes`, `no_bpjs_ket` | `person_identities` | |
| `country`, `province`, `city`, `district`, `village`, `address`, `postal_code`, `rt`, `rw` | `person_addresses` (`type` = utama) | |
| `user_profile_documents` (`label`, `file_path`) | `person_documents` (`type`, `file_path`) | Baris disalin, file tidak dipindah |
| `phone`, `whatsapp`, `email_pribadi` | `persons` | `users.email` = email akun, tetap di `users` |
| `employee_type_id`, `joining_date`, `tmt_tetap` | `employees` | |
| `job_position_id`, `pangkat_id`, `kelas_id` | `employees` (kondisi sekarang) + snapshot di `employee_careers` | Payroll membaca pangkat/kelas dari tabel lama sampai slice payroll |
| `bank_name`, `bank_account_number`, `bank_account_name` | `person_identities` | Dibaca payroll dari tabel lama sampai slice payroll |
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

---

# 6. Fase 1 — Fondasi (Tidak Ada Fitur yang Berubah)

```text
□ Migration: persons
□ Migration: employees
□ Migration: employee_careers
□ Model + relasi: Person, Employee, EmployeeJobPosition
□ Relasi tambahan di User: person(), employee()  (hanya tambah, tidak mengubah yang ada)
□ Command: identity:backfill  (--dry-run, --user=NIP, idempotent)
□ Observer satu arah di model lama (UserProfiles, UserJobPositions, User) → sinkron ke tabel baru
□ Command: identity:verify  (bagian 7)
□ Test: backfill dua kali tidak menghasilkan duplikat
□ Test: observer menyinkronkan perubahan profil/jabatan
```

## Aturan `identity:backfill`

- Idempotent: kunci pencarian `employees.user_id` / `persons.user_id`; jalan dua kali = tidak
  ada duplikat.
- `--dry-run` menampilkan jumlah yang akan dibuat/diperbarui dan **daftar konflik**, tanpa menulis.
- NIK ganda atau kosong **dilaporkan, tidak membuat proses gagal**. `persons.nik` baru diberi
  constraint unik setelah data bersih.
- Berjalan dalam transaksi per user supaya satu data buruk tidak menggagalkan semuanya.
- Tidak pernah menulis ke tabel lama.
- Tidak menyalin password, token, atau data yang terenkripsi payroll.

## Sinkron satu arah

```text
user_profiles / user_job_positions / users   (sumber, diedit seperti biasa)
        │  observer (saved/deleted)
        ▼
persons / employees / employee_careers  (salinan, hanya ditulis oleh sinkron)
```

Selama fase ini **tabel baru read-only bagi aplikasi**. Kalau observer gagal, kejadian dicatat
ke log dan `identity:verify` yang menangkap selisihnya — alur edit data lama tidak boleh ikut
gagal.

---

# 7. Parity Check — `identity:verify` (SUDAH DIBANGUN)

`php artisan identity:verify [--user=NIP] [--limit=15]` — hanya membaca, exit code 1 kalau ada
selisih, keluarannya cuma jumlah dan NIP. Dimensi yang dicek: `persons` (baris, data pribadi,
identitas & rekening, alamat, lampiran), `employees` (baris, data kepegawaian, akun
non-karyawan tidak punya employee), jabatan (paling banyak satu aktif di riwayat; profil =
riwayat aktif), dan jumlah `employee_careers` vs `user_job_positions`. Selisih diperbaiki dengan
`identity:backfill` + `identity:backfill-employees`; selisih jabatan profil-vs-riwayat adalah
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

## Slice A — Pengajuan Izin, Cuti, Lembur (pertama)

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
□ Pindahkan form Data Diri → persons
□ Pindahkan form Jabatan → employees + employee_careers (satu sumber jabatan, dua sumber lama disatukan)
□ Balik arah observer untuk modul ini saja
□ identity:verify tetap dijalankan di kedua arah
```

## Slice D — Payroll (terakhir)

```text
□ Pangkat/kelas sudah ada di employees & employee_careers; rekening di person_identities
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
□ Tabel doctors (person_id, nomor dokter, spesialisasi, SIP, STR, status)
□ Dokter non-karyawan = persons + user + doctors (tanpa employees)
□ Dokter yang juga karyawan = persons + user + employees + doctors
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
5. Batasi akses tabel `persons`/`employees` dengan permission yang sama seperti data pribadi saat ini.
6. Jangan mengubah enkripsi payroll.
7. Jangan menghapus tabel lama sebelum seluruh dependensinya terverifikasi (Fase Cleanup).

---

# 14. Hal yang Jangan Dilakukan

```text
❌ ALTER / rename / drop tabel lama selama slice berjalan
❌ Memindahkan users.nip atau mengubah users.id
❌ Memindahkan dua modul dalam satu rilis
❌ Input manual ke tabel baru sebelum arah sinkron dibalik
❌ Memasang unique pada persons.nik sebelum data bersih
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

```text
FASE 0  Persiapan, backup, bersihkan data (jabatan ganda, NIK)
   ↓
FASE 1  Tabel baru + backfill + observer + identity:verify   (tidak ada fitur berubah)
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

# 17. Langkah Pertama

1. Kerjakan **Fase 0**: backup, baseline test, dan lima query audit (user tanpa profil, NIK
   kosong/ganda, selisih jabatan, jabatan aktif ganda/kosong, akun non-karyawan).
2. Bawa hasil query ke HC/Admin untuk memutuskan data yang bertentangan.
3. Baru buat migration Fase 1 di branch terpisah.

**Jangan membuat migration sebelum hasil Fase 0 ada.**
