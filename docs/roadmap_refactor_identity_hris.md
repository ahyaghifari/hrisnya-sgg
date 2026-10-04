# Roadmap Refactor Identity & Data HRIS

## Tujuan

HRIS saat ini sudah production dan digunakan oleh:

- Absensi di 2 unit bisnis
- Payroll
- Data kepegawaian
- Modul HR lainnya
- Seluruh pegawai sudah memiliki akun HRIS

Namun struktur saat ini masih mencampurkan beberapa konsep:

- akun/login (`users`)
- identitas orang
- data karyawan
- data jabatan
- riwayat kepegawaian

Kebutuhan baru juga muncul karena **dokter** akan masuk ke HRIS, sementara dokter tidak selalu merupakan karyawan dan data yang dibutuhkan lebih sederhana.

Target refactor:

```text
                         ┌──────────────┐
                         │   Keycloak   │
                         │ Auth / SSO   │
                         └──────┬───────┘
                                │
                                ▼
                         ┌──────────────┐
                         │     USER     │
                         │    Account   │
                         └──────┬───────┘
                                │
                                ▼
                         ┌──────────────┐
                         │    PERSON    │
                         │     Orang    │
                         └──────┬───────┘
                                │
                    ┌───────────┴───────────┐
                    ▼                       ▼
              ┌───────────┐           ┌───────────┐
              │ EMPLOYEE  │           │  DOCTOR   │
              │ Karyawan  │           │  Dokter   │
              └─────┬─────┘           └───────────┘
                    │
        ┌───────────┼────────────┐
        ▼           ▼            ▼
     Payroll     Absensi      Kepegawaian
```

---

# 1. Prinsip Utama

## Jangan melakukan big-bang migration

Karena HRIS sudah berjalan, **jangan langsung membongkar tabel `users` dan memindahkan semua data**.

Gunakan pola:

```text
Expand
  ↓
Migrate
  ↓
Switch
  ↓
Contract
```

Artinya:

1. Tambahkan struktur baru.
2. Migrasikan/copy data secara bertahap.
3. Pindahkan kode aplikasi secara bertahap.
4. Setelah benar-benar stabil, baru pertimbangkan menghapus struktur lama.

---

# 2. Kondisi Saat Ini

Kemungkinan struktur saat ini kurang lebih:

```text
users
├── name
├── nip
├── email
├── password
└── ...

users_data_diri
├── user_id
├── NIK
├── tempat_lahir
├── tanggal_lahir
└── ...

users_jabatan
├── user_id
├── jabatan_id
└── ...

users_riwayat_*
└── user_id
```

Masalah utamanya:

```text
USER = AKUN + ORANG + SEBAGIAN DATA KARYAWAN
```

Ini masih bisa berjalan selama semua user adalah karyawan.

Namun ketika dokter masuk:

```text
Dokter
├── punya nama
├── bisa punya NIP
├── punya akun
├── punya SIP/STR
├── punya spesialisasi
└── belum tentu karyawan
```

maka model lama mulai sulit dikembangkan.

---

# 3. Konsep Target

Pisahkan empat konsep:

## Person

> Siapa manusia tersebut?

## User

> Apakah orang tersebut memiliki akun untuk login?

## Employee

> Apakah orang tersebut merupakan karyawan?

## Doctor

> Apakah orang tersebut merupakan dokter?

Secara konsep:

```text
PERSON
   │
   ├── USER
   │
   ├── EMPLOYEE
   │
   └── DOCTOR
```

Satu orang dapat memiliki lebih dari satu status.

Contoh:

```text
Person
Dr. Budi
│
├── User
├── Employee
└── Doctor
```

Sedangkan dokter non-karyawan:

```text
Person
Dr. Andi
│
├── User
└── Doctor
```

---

# 4. Tahap 1 — Audit Database Existing

**Jangan langsung membuat migration.**

Petakan terlebih dahulu seluruh database.

Buat dokumen:

```text
HRIS DATABASE MAP
```

Minimal petakan:

| Tabel | Fungsi | Relasi | Modul |
|---|---|---|---|
| `users` | Akun + data dasar | - | Hampir semua |
| `users_data_diri` | Data pribadi | users | Kepegawaian |
| `users_jabatan` | Jabatan | users | Kepegawaian/Payroll |
| `users_riwayat_*` | Riwayat | users | Kepegawaian |
| tabel payroll | Penggajian | users/employee | Payroll |
| tabel attendance | Presensi | users/employee | Absensi |

Beri status:

```text
🟢 Aman disentuh
🟡 Perlu migrasi
🔴 Jangan disentuh dulu
```

Payroll dan absensi sebaiknya dianggap **🔴 sampai dependensinya benar-benar dipahami**.

### Yang perlu dicari

- Semua foreign key ke `users`
- Semua model Laravel yang menggunakan `User`
- Query payroll
- Query absensi
- Query lembur
- Query cuti
- Export Excel
- Slip gaji
- Notification
- PDF
- Report
- Audit log
- Job/queue
- Event/listener
- Scheduled command

Tujuannya:

> Mengetahui semua tempat yang bergantung pada struktur `users` sebelum mengubahnya.

---

# 5. Tahap 2 — Membuat `persons`

Buat tabel baru:

```text
persons
--------------------
id
nik
nama_lengkap
tempat_lahir
tanggal_lahir
jenis_kelamin
created_at
updated_at
```

Jangan memindahkan data lama dulu.

Tabel ini awalnya hanya menjadi **fondasi identity person**.

---

# 6. Tahap 3 — Hubungkan `users` ke `persons`

Tambahkan:

```text
users
--------------------
id
person_id
email
username
password
...
```

Awalnya:

```text
person_id = NULL
```

Kemudian lakukan mapping.

Contoh:

```text
users
id   name
10   Muhammad Ahya
```

Buat:

```text
persons
id   nama_lengkap
100  Muhammad Ahya
```

Kemudian:

```text
users
id   person_id
10   100
```

Relasi:

```text
USER 10
  │
  └── PERSON 100
```

---

# 7. Jangan Hapus `users.name` dan `users.nip` Dulu

Untuk menjaga sistem lama tetap aman:

```text
users
├── name              ← legacy
├── nip               ← legacy
├── person_id         ← baru
└── ...
```

Sistem lama masih boleh menggunakan:

```php
$user->name
$user->nip
```

sementara sistem baru mulai menggunakan:

```php
$user->person->nama_lengkap
```

Tetapkan bahwa:

> `persons` menjadi source of truth baru untuk identitas orang.

Namun field legacy tetap dipertahankan sampai seluruh dependency berhasil dimigrasikan.

---

# 8. Tahap 4 — Membuat `employees`

Setelah `persons` stabil, buat:

```text
employees
--------------------
id
person_id
nip
company_id
department_id
unit_id
subunit_id
position_id
status
tanggal_masuk
tanggal_keluar
created_at
updated_at
```

Relasi:

```text
Person
  │
  └── Employee
```

### Kenapa NIP berada di Employee?

Karena NIP adalah identitas dalam konteks kepegawaian.

Bukan identitas universal seseorang.

Dengan demikian:

```text
Person
├── NIK
└── Nama

Employee
└── NIP
```

---

# 9. Tahap 5 — Pisahkan Jabatan dan Riwayat

Jabatan tidak seharusnya dianggap sebagai atribut permanen `User`.

Target:

```text
Employee
   │
   ├── current_position
   │
   └── employment_histories
          ├── Programmer
          ├── Senior Programmer
          └── Supervisor
```

Riwayat harus mempertahankan histori perubahan.

Contoh:

```text
employment_histories
--------------------
id
employee_id
position_id
company_id
department_id
unit_id
subunit_id
start_date
end_date
status
```

Struktur detail perlu disesuaikan dengan model HRIS yang sudah ada.

---

# 10. Tahap 6 — Membuat `doctors`

Buat entitas dokter terpisah:

```text
doctors
--------------------
id
person_id
doctor_number
specialization_id
sip_number
str_number
status
created_at
updated_at
```

Relasi:

```text
Person
  │
  └── Doctor
```

Dokter non-karyawan:

```text
Person
  ├── User
  └── Doctor
```

Dokter yang juga karyawan:

```text
Person
  ├── User
  ├── Employee
  └── Doctor
```

Dengan model ini tidak perlu membuat:

```text
users_dokter
```

yang memaksa dokter mengikuti struktur karyawan.

---

# 11. Aturan Relasi yang Disarankan

## Person

```text
1 orang = 1 person
```

## User

```text
1 person = 0 atau 1 user
```

Tidak semua orang harus punya akun.

## Employee

```text
1 person = 0 atau 1 employee
```

## Doctor

```text
1 person = 0 atau 1 doctor
```

Tetapi:

```text
1 person
├── employee
└── doctor
```

boleh.

---

# 12. Tahap 7 — Compatibility Layer Laravel

Jangan langsung mengubah seluruh controller.

Target relasi Laravel:

```text
User
  -> person()
  -> employee()
  -> doctor()

Person
  -> user()
  -> employee()
  -> doctor()

Employee
  -> person()

Doctor
  -> person()
```

Struktur domain dapat mulai diarahkan menjadi:

```text
app/
├── Domain/
│   ├── Person/
│   ├── Employee/
│   ├── Doctor/
│   ├── Identity/
│   └── Organization/
```

Tujuannya agar code baru tidak lagi bergantung langsung pada struktur legacy.

---

# 13. Tahap 8 — Regression Test

Sebelum employee production mulai dipindahkan, pastikan fungsi penting tetap menghasilkan hasil yang sama.

Prioritas:

### Sangat penting

- Login
- Absensi masuk
- Absensi pulang
- Keterlambatan
- Izin
- Cuti
- Lembur
- Payroll
- Slip gaji
- Perubahan status employee
- Export payroll

### Penting

- Notification
- PDF
- Report
- Dashboard
- Export data

Tujuan utama:

> Refactor identity tidak boleh mengubah hasil payroll dan absensi.

---

# 14. Tahap 9 — Migrasi Data Existing

Gunakan script/command khusus.

Contoh konsep:

```text
users
   ↓
find/create person
   ↓
attach person_id
   ↓
find/create employee
   ↓
attach employee data
```

Buat proses yang **idempotent**.

Artinya jika dijalankan dua kali:

```text
php artisan migrate:identity
```

tidak menghasilkan person/employee duplikat.

Sebaiknya memiliki:

```text
--dry-run
```

sehingga bisa melihat hasil sebelum benar-benar menulis data.

---

# 15. Tahap 10 — SSO / Keycloak

Setelah model Person/User/Employee mulai stabil, baru integrasikan Keycloak.

Target:

```text
Person
   │
   ▼
User
   │
   └── keycloak_user_id
             │
             ▼
         Keycloak
```

Tambahkan:

```text
users
--------------------
id
person_id
keycloak_user_id
sso_enabled
...
```

Keycloak menjadi sumber:

- Password
- Authentication
- Identity
- Session
- SSO
- Token

HRIS menjadi sumber:

- Person
- Employee
- Doctor
- Organization
- Data HR

---

# 16. Migrasi SSO Secara Bertahap

Jangan langsung mematikan login HRIS.

Gunakan:

```text
sso_enabled = false
```

untuk user lama yang belum dimigrasikan.

Alurnya:

```text
                   HRIS
                    │
             SSO enabled?
              /          \
            YA            TIDAK
            │               │
            ▼               ▼
        Keycloak        Login lama
            │
            ▼
          HRIS
```

User yang sudah siap:

```text
Aktifkan SSO
      ↓
Create/link Keycloak identity
      ↓
User set password SSO
      ↓
sso_enabled = true
```

Setelah seluruh user berhasil dimigrasikan dan sistem stabil, login lokal HRIS dapat dipensiunkan.

---

# 17. Password

Jangan membuat dua sumber password.

Hindari:

```text
HRIS password
      ↕
Keycloak password
```

Target:

```text
Keycloak
└── satu-satunya password
```

Setelah SSO aktif:

```text
HRIS
 └── tidak lagi menjadi sumber authentication password
```

User tetap merasa memiliki:

> satu akun + satu password untuk semua sistem.

---

# 18. Struktur Organisasi

Masalah lain yang perlu diselesaikan setelah identity stabil:

```text
HRIS
├── perusahaan
├── departemen
├── unit
└── subunit

SILAPOR
├── perusahaan
├── departemen
├── unit
└── subunit

DCMS
├── perusahaan
├── departemen
├── unit
└── subunit

Asset
├── perusahaan
├── departemen
├── unit
└── subunit
```

Target:

> HRIS menjadi source of truth untuk Employee dan Organization.

Sistem lain boleh mempunyai local copy/reference untuk kebutuhan performa dan query.

Namun jangan masing-masing menjadi master.

---

# 19. Sinkronisasi Organisasi

Pola yang disarankan:

```text
                    HRIS
             Organization Master
                     │
             Sync / Event / API
                     │
          ┌──────────┼──────────┐
          ▼          ▼          ▼
       SILAPOR      DCMS       ASSET
```

Hindari setiap request melakukan:

```text
DCMS
 ↓
request HRIS
 ↓
ambil department
```

Gunakan API/sync/event sesuai kebutuhan.

Gunakan identifier/code organisasi yang stabil.

Contoh:

```text
Company Code
Department Code
Unit Code
Subunit Code
```

---

# 20. Struktur Arsitektur Akhir

Target akhir:

```text
                    ┌──────────────┐
                    │   KEYCLOAK   │
                    │              │
                    │ Authentication
                    │ Password     │
                    │ SSO          │
                    │ Session      │
                    │ Token        │
                    └──────┬───────┘
                           │
                           │ Identity
                           ▼
                    ┌──────────────┐
                    │     USER     │
                    │    Account   │
                    └──────┬───────┘
                           │
                           ▼
                    ┌──────────────┐
                    │    PERSON    │
                    │     Orang    │
                    └──────┬───────┘
                           │
                 ┌─────────┴─────────┐
                 ▼                   ▼
          ┌─────────────┐     ┌─────────────┐
          │  EMPLOYEE   │     │    DOCTOR   │
          │  Karyawan   │     │   Dokter    │
          └──────┬──────┘     └─────────────┘
                 │
       ┌─────────┼───────────┐
       ▼         ▼           ▼
    Payroll    Absensi    Kepegawaian
```

---

# 21. Roadmap Implementasi

## Phase 1 — Audit

```text
□ Audit tabel users
□ Audit users_*
□ Audit foreign key
□ Audit model Laravel
□ Audit payroll dependency
□ Audit attendance dependency
□ Audit query/report/export
□ Audit job/event/notification
```

## Phase 2 — Person

```text
□ Create persons
□ Add users.person_id
□ Buat mapping existing user → person
□ Validasi tidak ada duplicate
□ Validasi semua user memiliki person
```

## Phase 3 — Employee

```text
□ Create employees
□ Mapping employee existing
□ Pisahkan NIP dari user
□ Mapping organization
□ Mapping position
□ Mapping employment status
```

## Phase 4 — Compatibility

```text
□ Tambahkan relasi Laravel
□ Buat service/domain layer
□ Pertahankan field legacy
□ Refactor modul secara bertahap
```

## Phase 5 — Doctor

```text
□ Create doctors
□ Person ↔ Doctor
□ Doctor ↔ User
□ Doctor profile
□ Specialization
□ STR/SIP
□ Doctor yang juga employee
```

## Phase 6 — SSO

```text
□ Setup Keycloak
□ Add keycloak_user_id
□ Add sso_enabled
□ Provisioning user
□ Link existing account
□ Migrasi bertahap
□ Login HRIS via Keycloak
□ Login aplikasi lain via Keycloak
```

## Phase 7 — Organization

```text
□ Tentukan organization master
□ Tentukan company code
□ Tentukan department code
□ Tentukan unit code
□ Tentukan subunit code
□ Buat API
□ Buat sync/event
□ Integrasikan SILAPOR
□ Integrasikan DCMS
□ Integrasikan Asset
```

## Phase 8 — Cleanup

Hanya dilakukan setelah stabil:

```text
□ Cari seluruh penggunaan field legacy
□ Hapus dependency lama
□ Hapus kolom legacy jika aman
□ Hapus tabel legacy jika benar-benar tidak digunakan
```

---

# 22. Prinsip Keamanan

Karena HRIS menyimpan payroll dan data pribadi:

1. Backup database sebelum migration.
2. Test migration di staging.
3. Jangan melakukan migration langsung di production.
4. Gunakan transaction jika memungkinkan.
5. Buat migration yang reversible jika memungkinkan.
6. Jangan log password.
7. Jangan memindahkan plaintext password.
8. Jangan mengubah encryption payroll tanpa kebutuhan.
9. Audit akses terhadap data Person/Employee.
10. Jangan menghapus tabel lama sebelum seluruh dependency diverifikasi.

---

# 23. Urutan Kerja yang Paling Aman

Urutan final:

```text
AUDIT
  ↓
DATABASE MAP
  ↓
PERSON
  ↓
USER ↔ PERSON
  ↓
EMPLOYEE
  ↓
COMPATIBILITY LAYER
  ↓
REGRESSION TEST
  ↓
MIGRATE EXISTING EMPLOYEES
  ↓
DOCTOR
  ↓
KEYCLOAK / SSO
  ↓
ORGANIZATION MASTER
  ↓
INTEGRASI SISTEM LAIN
  ↓
LONG-TERM CLEANUP
```

---

# 24. Hal yang Jangan Dilakukan

Jangan:

```text
❌ Rename users menjadi persons langsung
❌ Hapus users.name langsung
❌ Hapus users.nip langsung
❌ Pindahkan payroll sekaligus
❌ Pindahkan attendance sekaligus
❌ Migrasi semua sistem sekaligus
❌ Membuat password HRIS dan Keycloak tersinkron dua arah
❌ Membuat dokter sebagai employee palsu
❌ Membuat tabel user terpisah untuk setiap tipe orang
❌ Membuat semua aplikasi menjadi master organisasi sendiri
```

Lebih baik:

```text
✅ Tambahkan struktur baru
✅ Mapping data lama
✅ Pertahankan compatibility
✅ Migrasi modul satu per satu
✅ Regression test
✅ Migrasi SSO bertahap
✅ Jadikan HRIS master employee/organization
✅ Jadikan Keycloak master authentication
```

---

# 25. Langkah Pertama yang Harus Dilakukan Sekarang

**Jangan coding migration dulu.**

Kumpulkan:

1. Struktur tabel `users`
2. Daftar seluruh tabel `users_*`
3. Migration Laravel yang berkaitan dengan user/employee
4. Model `User`
5. Model yang berkaitan dengan employee
6. Struktur tabel payroll
7. Struktur tabel attendance
8. Relasi/foreign key yang menggunakan `users.id`

Dari data tersebut, buat **mapping konkret**:

```text
Tabel lama
    ↓
Tetap
    ↓
Pindah ke persons
    ↓
Pindah ke employees
    ↓
Pindah ke doctors
    ↓
Tidak perlu dipindah
```

**Ini harus menjadi pekerjaan pertama sebelum refactor dimulai.**
