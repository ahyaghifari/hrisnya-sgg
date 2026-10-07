# Doctor Management Domain — Technical Specification

## Tujuan

Spesifikasi ini mendefinisikan Doctor Management yang dikelola melalui panel HRIS, sementara data domain dokter disimpan pada Doctor DB terpisah.

### Prinsip
- HRIS = administration/provisioning gate dan UI administrasi.
- SSO/identity provider = authentication dan SSO.
- Doctor DB = master data dokter, dokumen profesional, license, dan credentialing.
- Sistem lain tidak membuat akun dokter secara mandiri.
- Jangan membuat tabel `persons` hanya untuk menyatukan identitas.
- Jangan membuat foreign key lintas database; gunakan application-level reference/ID.

---

## Arsitektur

```text
                         +------------------+
                         |       HRIS       |
                         |    Admin Panel   |
                         +--------+---------+
                                  |
                         Doctor Management API
                                  |
                                  v
                         +------------------+
                         |    Doctor DB     |
                         | doctors          |
                         | specialties      |
                         | doctor_hospitals |
                         | documents        |
                         | licenses         |
                         | credentialing    |
                         +------------------+

                         +------------------+
                         |     SSO/identity provider     |
                         | Authentication   |
                         |       / SSO      |
                         +--------+---------+
                                  |
                   +--------------+--------------+
                   v              v              v
                  HRIS           WBS           Audit
```

### Kepemilikan data

| Data | Master |
|---|---|
| User/account HRIS | HRIS |
| Employee | HRIS |
| Struktur organisasi | HRIS |
| Authentication/password | SSO/identity provider |
| Data dokter | Doctor DB |
| Spesialisasi | Doctor DB |
| Penempatan dokter di RS | Doctor DB |
| SIP/STR/STR-KT | Doctor DB |
| Dokumen dokter | Doctor DB |
| Riwayat credentialing | Doctor DB |

---

## User dan Doctor

Satu identity dapat mempunyai konteks HRIS dan Doctor.

```text
                    SSO/identity provider Identity
                           |
                     external_identity_user_id
                           |
             +-------------+-------------+
             |                           |
            HRIS                     Doctor DB
             |                           |
           User                        Doctor
             |
         Employee (optional)
```

- Dokter yang juga employee dapat mempunyai `Employee` dan `Doctor`.
- Dokter mitra tidak wajib menjadi employee payroll.
- `external_identity_id` menjadi identity teknis lintas sistem.
- Jangan menggunakan nama sebagai identity.

---

# Database Doctor DB

## 1. `doctors`

```sql
CREATE TABLE doctors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    nip VARCHAR(50) NULL,
    gelar_depan VARCHAR(100) NULL,
    gelar_belakang VARCHAR(100) NULL,
    tempat_lahir VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    jenis_kelamin ENUM('L', 'P') NULL,
    nik VARCHAR(32) NULL,
    agama VARCHAR(50) NULL,
    external_identity_user_id VARCHAR(255) NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY doctors_external_identity_user_id_unique (external_identity_user_id),
    INDEX doctors_nip_index (nip),
    INDEX doctors_nama_index (nama),
    INDEX doctors_status_index (status)
);
```

Aturan:
- `nama` tanpa gelar.
- Gelar disimpan terpisah.
- `nip` nullable karena tidak semua dokter adalah employee.
- `external_identity_id` nullable sampai akun SSO dibuat/link.
- NIK adalah data sensitif.

## 2. `specialties`

```sql
CREATE TABLE specialties (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    kode VARCHAR(50) NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY specialties_nama_unique (nama)
);
```

## 3. `doctor_specialties`

```sql
CREATE TABLE doctor_specialties (
    doctor_id BIGINT UNSIGNED NOT NULL,
    specialty_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (doctor_id, specialty_id),
    INDEX doctor_specialties_specialty_index (specialty_id)
);
```

Satu dokter boleh mempunyai banyak specialty, tetapi hanya satu specialty utama.

## 4. `doctor_hospitals`

Dokter dapat bekerja/praktik di beberapa RS. Karena itu jangan taruh `rumah_sakit_id` langsung di `doctors`.

```sql
CREATE TABLE doctor_hospitals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    rumah_sakit_id BIGINT UNSIGNED NOT NULL,
    tanggal_mulai DATE NULL,
    tanggal_selesai DATE NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX doctor_hospitals_doctor_index (doctor_id),
    INDEX doctor_hospitals_rs_index (rumah_sakit_id),
    INDEX doctor_hospitals_status_index (status)
);
```

`rumah_sakit_id` adalah application-level reference, bukan FK lintas database.

---

# Dokumen Dokter

## 5. `doctor_documents`

Untuk dokumen umum:

```sql
CREATE TABLE doctor_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,
    document_number VARCHAR(150) NULL,
    issued_at DATE NULL,
    expired_at DATE NULL,
    status ENUM('aktif', 'nonaktif', 'expired') NOT NULL DEFAULT 'aktif',
    notes TEXT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX doctor_documents_doctor_index (doctor_id),
    INDEX doctor_documents_type_index (document_type),
    INDEX doctor_documents_expired_index (expired_at)
);
```

Jenis minimal:

```text
LAMARAN
CV
IJAZAH_DOKTER
IJAZAH_SPESIALIS
IJAZAH_SUBSPESIALIS
TRANSKRIP
SERTIFIKAT_KOMPETENSI
SERTIFIKAT_PELATIHAN
BUKU_REKENING
KTP
NPWP
```

Dokumen asli tetap disimpan sebagai file masing-masing. Jangan menghapus histori hanya karena ada dokumen baru.

---

# SIP / STR / STR-KT

## 6. `doctor_licenses`

SIP, STR, dan STR-KT perlu struktur khusus karena mempunyai nomor dan masa berlaku.

```sql
CREATE TABLE doctor_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    jenis ENUM('SIP', 'STR', 'STR_KT') NOT NULL,
    nomor VARCHAR(150) NULL,
    tanggal_terbit DATE NULL,
    tanggal_berlaku DATE NULL,
    tanggal_berakhir DATE NULL,
    doctor_document_id BIGINT UNSIGNED NULL,
    status ENUM('aktif', 'akan_expired', 'expired', 'nonaktif') NOT NULL DEFAULT 'aktif',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX doctor_licenses_doctor_index (doctor_id),
    INDEX doctor_licenses_jenis_index (jenis),
    INDEX doctor_licenses_expired_index (tanggal_berakhir)
);
```

Contoh:

```text
Doctor License
- jenis: SIP
- nomor: SIP-00123
- tanggal_terbit: 2026-01-01
- tanggal_berakhir: 2027-01-01
- file: SIP-00123.pdf
```

---

# Reminder Masa Berlaku

## 7. `document_expiry_reminders`

Reminder default:

```text
90 hari
60 hari
30 hari
14 hari
7 hari
1 hari
```

```sql
CREATE TABLE document_expiry_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_license_id BIGINT UNSIGNED NOT NULL,
    reminder_days INT NOT NULL,
    reminder_date DATE NOT NULL,
    sent_at TIMESTAMP NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY expiry_reminder_unique (doctor_license_id, reminder_days),
    INDEX expiry_reminders_date_index (reminder_date),
    INDEX expiry_reminders_status_index (status)
);
```

Laravel Scheduler menjalankan proses harian:

```text
Check active licenses
        |
        v
Check tanggal_berakhir
        |
        v
Create/check reminder
        |
        v
Send notification ketika jatuh tempo
        |
        v
Mark sent
```

Jangan mengirim reminder yang sama berulang kali.

Setelah `tanggal_berakhir` terlewati, status license menjadi `expired`.

---

# Credentialing

Credentialing adalah proses bisnis terpisah. Rangkaian dokumen berikut memang dijadikan **satu PDF final**:

```text
Permohonan kredensial dari direktur
        -> Undangan kredensial
        -> Berkas RKK
        -> Absensi kredensial
        -> Surat rekomendasi kredensial
        -> SK SPK
        -> 1 PDF final
```

## 8. `credentialing_cases`

```sql
CREATE TABLE credentialing_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id BIGINT UNSIGNED NOT NULL,
    rumah_sakit_id BIGINT UNSIGNED NOT NULL,
    nomor VARCHAR(150) NULL,
    jenis VARCHAR(50) NULL,
    tanggal_pengajuan DATE NULL,
    status ENUM(
        'draft',
        'pengajuan',
        'verifikasi',
        'kredensial',
        'rekomendasi',
        'sk_spk',
        'selesai',
        'ditolak'
    ) NOT NULL DEFAULT 'draft',
    file_pdf VARCHAR(500) NULL,
    tanggal_selesai DATE NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX credentialing_doctor_index (doctor_id),
    INDEX credentialing_rs_index (rumah_sakit_id),
    INDEX credentialing_status_index (status)
);
```

Satu `credentialing_case` menghasilkan satu PDF final:

```text
credentialing_case
        |
        +-- file_pdf
                |
                v
        credentialing-2026-001.pdf
```

Jika bisnis mengharuskan sumber setiap tahap tetap disimpan untuk audit, file sumber boleh disimpan sebagai attachment internal, tetapi struktur utama tetap `credentialing_cases` + satu PDF final.

Satu dokter dapat memiliki banyak riwayat credentialing:

```text
Doctor #25
├── Credentialing 2024 -> final.pdf
├── Credentialing 2026 -> final.pdf
└── Credentialing 2028 -> final.pdf
```

---

# Relasi

```text
doctors
  |
  +--< doctor_specialties >-- specialties
  |
  +--< doctor_hospitals
  |
  +--< doctor_documents
  |
  +--< doctor_licenses
  |       |
  |       +-- doctor_documents
  |
  +--< credentialing_cases
```

Cardinality:

```text
Doctor 1 : N Specialty
Doctor 1 : N Hospital
Doctor 1 : N Document
Doctor 1 : N License
Doctor 1 : N Credentialing Case
```

---

# Panel HRIS

Admin tetap mengelola semuanya melalui HRIS.

```text
HRIS
├── Dokter
│   ├── Daftar Dokter
│   ├── Tambah Dokter
│   ├── Detail Dokter
│   ├── Spesialisasi
│   ├── Penempatan RS
│   ├── Dokumen
│   ├── SIP / STR
│   └── Credentialing
└── User & Access
    └── User / SSO
```

HRIS tidak perlu mengekspos bahwa data berada di database berbeda.

Detail dokter dapat menggunakan tab:

```text
Data Diri | Penempatan | Spesialisasi | Dokumen | SIP/STR | Credentialing | User & SSO
```

---

# API Doctor DB

HRIS berkomunikasi melalui API/service layer, bukan query langsung ke Doctor DB.

Contoh endpoint internal:

```text
GET    /api/doctors
POST   /api/doctors
GET    /api/doctors/{id}
PUT    /api/doctors/{id}
DELETE /api/doctors/{id}

GET    /api/doctors/{id}/documents
POST   /api/doctors/{id}/documents

GET    /api/doctors/{id}/licenses
POST   /api/doctors/{id}/licenses

GET    /api/doctors/{id}/credentialing
POST   /api/doctors/{id}/credentialing
```

Gunakan authentication antar-service. Endpoint tidak boleh publik.

---

# User Provisioning

Ketika admin membuat dokter dari HRIS:

```text
Admin HRIS
   |
   v
Create Doctor
   |
   v
Doctor DB
   |
   +--> Create/Link HRIS User
   |
   +--> Provision/Link SSO/identity provider
   |
   v
Store external_identity_user_id
```

Jika SSO/identity provider identity sudah ada, lakukan link, bukan membuat identity baru tanpa pengecekan.

Jika username SSO/identity provider menggunakan NIP, NIP tetap business identifier; identity teknis tetap `sub` / `external_identity_id`.

---

# Integrasi Employee HRIS

Jika dokter juga employee:

```text
HRIS employees
- id
- user_id
- doctor_id
```

`doctor_id` adalah application-level reference ke Doctor DB, bukan foreign key lintas database.

Contoh:

```text
HRIS
Employee #100
  doctor_id = 25

Doctor DB
Doctor #25
  nama = dr. Ahmad
```

Jika dokter bukan employee, Doctor tetap dapat memiliki HRIS User dan SSO bila diperlukan.

---

# Keamanan

Data sensitif:

```text
NIK
KTP
NPWP
Nomor rekening
SIP
STR
Ijazah
Dokumen credentialing
```

Minimal:
- Authorization berbasis role/permission.
- File disimpan di private storage.
- Jangan menggunakan URL publik langsung untuk file sensitif.
- Download/preview harus melalui authorization.
- Audit log untuk upload, replace, download, dan delete.
- Jangan tampilkan NIK/NPWP/rekening penuh pada daftar dokter.
- Validasi MIME type dan ukuran file.
- Gunakan nama file storage yang aman/acak.
- Backup database dan file harus berjalan bersama.

---

# Urutan Implementasi

## Phase 1 — Doctor Master

Buat:

```text
doctors
specialties
doctor_specialties
doctor_hospitals
```

Implementasikan CRUD dokter, specialty, dan penempatan RS.

## Phase 2 — Document Management

Buat `doctor_documents` dan implementasikan upload, preview, download, metadata, serta status.

## Phase 3 — License

Buat `doctor_licenses` untuk SIP, STR, dan STR-KT.

## Phase 4 — Reminder

Buat `document_expiry_reminders` dan Laravel Scheduler.

## Phase 5 — Credentialing

Buat `credentialing_cases`, histori credentialing, workflow status, dan PDF final.

## Phase 6 — SSO / User Linking

Integrasikan HRIS -> SSO/identity provider -> Doctor DB untuk create/link identity.

---

# Acceptance Criteria

Implementasi dianggap sesuai apabila:

- [ ] Admin dapat CRUD dokter dari panel HRIS.
- [ ] Data dokter tersimpan di Doctor DB.
- [ ] Satu dokter dapat mempunyai banyak specialty.
- [ ] Satu dokter dapat mempunyai banyak rumah sakit.
- [ ] Dokumen umum dapat di-upload dan dikelola.
- [ ] SIP dapat memiliki nomor dan tanggal berlaku/berakhir.
- [ ] STR dan STR-KT dapat dikelola.
- [ ] Sistem mendeteksi dokumen yang akan expired.
- [ ] Reminder tidak dikirim dua kali untuk periode yang sama.
- [ ] Dokumen expired berubah status dengan benar.
- [ ] Satu dokter dapat memiliki banyak credentialing case.
- [ ] Satu credentialing case menghasilkan satu PDF final.
- [ ] Riwayat credentialing tidak tertimpa.
- [ ] Admin tidak perlu membuka database Doctor DB secara langsung.
- [ ] User dokter dapat di-link ke SSO/identity provider.
- [ ] `external_identity_id` digunakan sebagai identity reference.
- [ ] Tidak ada foreign key langsung antara HRIS DB dan Doctor DB.
- [ ] File sensitif tidak dapat diakses tanpa authorization.

---

# Prinsip Final

> **Satu panel administrasi (HRIS), satu identity provider (SSO/identity provider), dan satu domain master dokter (Doctor DB).**
>
> Dokter dikelola melalui HRIS, tetapi data profesional dokter tetap dimiliki Doctor DB. Credentialing disimpan sebagai histori proses, dengan satu PDF final untuk setiap proses credentialing. SIP/STR/STR-KT memiliki expiry tracking dan reminder otomatis.
