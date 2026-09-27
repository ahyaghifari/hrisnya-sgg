# Dokumentasi Arsitektur Tanda Tangan Elektronik & QR Verification HRIS

**Status:** Draft Analisis  
**Tujuan:** Menjadi acuan desain fitur dokumen elektronik, persetujuan, tanda tangan elektronik internal, dan verifikasi dokumen pada HRIS.

---

## 1. Latar Belakang

HRIS akan menghasilkan berbagai dokumen administratif perusahaan, antara lain:

- Surat Tugas
- Surat Peringatan
- Surat Undangan
- Surat Keterangan
- Surat Mutasi
- Surat Pengangkatan
- Berita Acara
- Sertifikat In House Training
- Dokumen administratif HR lainnya

Sebagian dokumen tersebut membutuhkan persetujuan dan/atau tanda tangan pejabat perusahaan.

Sistem yang direncanakan menggunakan:

1. **Document Management**
2. **Approval Workflow**
3. **Electronic Signature**
4. **QR Code Verification**
5. **Audit Trail**
6. **Document Integrity Check menggunakan hash**
7. **Cryptographic Digital Signature** untuk mekanisme TTE internal

Dokumentasi ini membedakan antara:

- QR Code sebagai alat verifikasi
- Tanda tangan elektronik
- TTE tidak tersertifikasi
- TTE tersertifikasi melalui PSrE

---

# 2. Konsep Utama

## 2.1 QR Code Bukan Tanda Tangan Itu Sendiri

QR Code sebaiknya tidak dianggap sebagai sumber utama keabsahan dokumen.

Contoh QR:

```text
https://hris.company.id/verify/8F92A7C19D2E
```

QR hanya menjadi pintu masuk menuju sistem verifikasi.

Ketika QR dipindai, HRIS melakukan:

```text
QR
 ↓
Verification Code
 ↓
HRIS
 ↓
Document
 ↓
Signature
 ↓
Hash Verification
 ↓
Signer Identity
 ↓
Status
```

Dengan demikian QR berfungsi sebagai **verification mechanism**, bukan sebagai pengganti cryptographic signature.

---

# 3. Tujuan Sistem

Sistem diharapkan dapat menjawab pertanyaan berikut:

### 3.1 Siapa yang menandatangani?

```text
Nama
Jabatan
User HRIS
```

### 3.2 Kapan ditandatangani?

```text
signed_at
```

### 3.3 Dokumen apa yang ditandatangani?

```text
document_id
document_number
document_type
```

### 3.4 Apakah dokumen berubah setelah ditandatangani?

Dilakukan menggunakan hash:

```text
SHA-256(document)
```

### 3.5 Apakah signature valid?

Signature diverifikasi menggunakan public key.

### 3.6 Apakah dokumen masih berlaku?

Status signature dan/atau dokumen diperiksa.

### 3.7 Apakah dokumen dapat diverifikasi oleh pihak luar?

Ya, melalui endpoint publik:

```text
GET /verify/{verification_code}
```

Endpoint tersebut tidak perlu memberikan akses ke HRIS internal.

---

# 4. Arsitektur Konseptual

```text
                         HRIS
                          |
                          v
                  +---------------+
                  |    Document    |
                  |    Creation    |
                  +-------+-------+
                          |
                          v
                  +---------------+
                  |    Approval    |
                  |    Workflow    |
                  +-------+-------+
                          |
                          v
                  +---------------+
                  |   Signature    |
                  |    Process     |
                  +-------+-------+
                          |
                 +--------+--------+
                 |                 |
                 v                 v
           Document Hash     Digital Signature
                 |                 |
                 +--------+--------+
                          |
                          v
                  +---------------+
                  |  Final PDF     |
                  |  + QR Code     |
                  +-------+-------+
                          |
                          v
                  /verify/{code}
                          |
                          v
                  +---------------+
                  | Verification  |
                  |    Service     |
                  +---------------+
```

---

# 5. Rekomendasi Struktur Database

Struktur awal yang direkomendasikan:

```text
document_types
documents
document_approvals
document_signatures
document_verifications
signing_keys
```

Selain tabel tersebut, sistem sebaiknya menggunakan tabel `users` yang sudah ada pada HRIS.

---

# 6. Tabel `document_types`

Menyimpan jenis dokumen yang dapat dibuat oleh HRIS.

## Struktur

```text
document_types
-----------------------------
id
code
name
requires_signature
requires_approval
active
created_at
updated_at
```

## Contoh

| code | name | requires_signature | requires_approval |
|---|---|---:|---:|
| surat_tugas | Surat Tugas | 1 | 1 |
| surat_peringatan | Surat Peringatan | 1 | 1 |
| surat_undangan | Surat Undangan | 1 | 0 |
| sertifikat_iht | Sertifikat IHT | 1 | 0 |
| berita_acara | Berita Acara | 1 | 1 |

Tujuannya agar engine dokumen dan signature dapat digunakan oleh banyak modul.

---

# 7. Tabel `documents`

Merupakan tabel utama seluruh dokumen yang dibuat HRIS.

## Struktur

```text
documents
--------------------------------
id
document_type_id
document_number
title
file_path
file_hash
hash_algorithm
status
created_by
created_at
updated_at
```

## Field penting

### `file_hash`

Hash file final.

Rekomendasi:

```text
SHA-256
```

Contoh:

```text
7f91a8c1d...
```

Hash digunakan untuk mendeteksi perubahan terhadap file setelah proses signing.

### `status`

Contoh:

```text
draft
pending_approval
approved
pending_signature
signed
revoked
cancelled
```

---

# 8. Tabel `document_approvals`

Menyimpan proses persetujuan dokumen.

Approval dan signature harus dipisahkan.

Contoh workflow:

```text
Staff HR
   |
   v
HR Supervisor
   |
   v
HR Manager
   |
   v
Direktur
```

## Struktur

```text
document_approvals
--------------------------------
id
document_id
sequence
approver_id
status
approved_at
rejected_at
notes
created_at
updated_at
```

## Status

```text
pending
approved
rejected
cancelled
```

Approval bukan berarti otomatis menjadi signature.

---

# 9. Tabel `document_signatures`

Menyimpan data signature.

## Struktur

```text
document_signatures
--------------------------------
id
document_id
signer_id
signer_name
signer_position
signature_type
algorithm
document_hash
signature_data
signed_at
status
created_at
updated_at
```

## Field

### `signer_id`

Referensi ke user HRIS yang melakukan signing.

### `signer_name`

Snapshot nama saat signing.

Disarankan tetap disimpan walaupun data user berubah.

### `signer_position`

Snapshot jabatan saat signing.

Contoh:

```text
HR Manager
```

### `signature_type`

Contoh:

```text
internal
certified
```

Untuk tahap awal:

```text
internal
```

### `algorithm`

Contoh:

```text
RSA-SHA256
```

atau algoritma lain yang dipilih berdasarkan implementasi cryptography.

### `document_hash`

Hash file/dokumen pada saat signing.

### `signature_data`

Hasil cryptographic signature.

Data dapat disimpan dalam format Base64 jika diperlukan.

### `status`

Contoh:

```text
valid
revoked
invalid
expired
```

---

# 10. Tabel `document_verifications`

QR Code sebaiknya tidak menyimpan seluruh informasi dokumen.

QR hanya berisi identifier yang aman untuk digunakan sebagai verification lookup.

## Struktur

```text
document_verifications
--------------------------------
id
document_id
signature_id
verification_code
status
expires_at
created_at
updated_at
```

## Contoh

```text
verification_code:
8F92A7C19D2E
```

QR:

```text
https://hris.company.id/verify/8F92A7C19D2E
```

## Status

```text
active
revoked
expired
```

Untuk dokumen resmi yang tidak memiliki masa berlaku, `expires_at` dapat bernilai `NULL`.

---

# 11. Tabel `signing_keys`

Tabel ini digunakan untuk mengelola pasangan cryptographic key.

```text
signing_keys
--------------------------------
id
user_id
key_type
algorithm
public_key
private_key_reference
status
created_at
revoked_at
```

## Catatan keamanan

**Private key tidak disarankan disimpan plaintext di database.**

Jangan melakukan:

```text
private_key = "-----BEGIN PRIVATE KEY-----..."
```

secara langsung dalam database biasa.

Lebih baik:

```text
HRIS
 |
 +-- Public Key
 |      |
 |      +-- Database
 |
 +-- Private Key
        |
        +-- Secure Storage / Secret Manager
```

Jika infrastruktur belum memiliki secret manager, private key setidaknya harus:

- encrypted at rest
- tidak muncul pada log
- tidak dikirim ke frontend
- tidak dikembalikan melalui API biasa
- akses dibatasi berdasarkan service/role
- memiliki mekanisme rotation/revocation

---

# 12. Relasi Database

```text
document_types
      |
      | 1:N
      v
documents
      |
      +----------------------+
      |                      |
      | 1:N                  | 1:N
      v                      v
document_approvals     document_signatures
                             |
                             | 1:N / 1:1
                             v
                    document_verifications


users
  |
  +------ documents.created_by
  |
  +------ document_approvals.approver_id
  |
  +------ document_signatures.signer_id
  |
  +------ signing_keys.user_id
```

---

# 13. Alur Pembuatan Dokumen

Contoh Surat Tugas.

```text
1. HR membuat Surat Tugas
          |
          v
2. HRIS membuat draft
          |
          v
3. Approval workflow
          |
          v
4. Semua approval selesai
          |
          v
5. Generate PDF FINAL
          |
          v
6. Hitung SHA-256
          |
          v
7. User berwenang melakukan signing
          |
          v
8. Generate cryptographic signature
          |
          v
9. Simpan signature
          |
          v
10. Generate verification code
          |
          v
11. Generate QR Code
          |
          v
12. Embed QR ke PDF final
          |
          v
13. Simpan final document
          |
          v
14. Status = signed
```

---

# 14. Prinsip Penting: Sign Setelah Dokumen Final

Jangan melakukan:

```text
Sign
 ↓
Edit PDF
 ↓
Tambah QR
```

jika signature dibuat terhadap keseluruhan file sebelum QR.

Karena perubahan terhadap file setelah signature dapat membuat hash/signature tidak lagi cocok.

Salah satu pendekatan desain adalah:

```text
Document Content
      |
      v
Canonical / Signing Representation
      |
      v
Hash
      |
      v
Signature
      |
      +---- Verification Metadata
      |
      v
Final PDF
      |
      +---- QR
```

Implementasi teknis harus menentukan dengan jelas **bagian mana yang menjadi signed payload** dan bagaimana perubahan setelah signing dideteksi.

Jangan sekadar menghitung hash dari PDF yang kemudian diubah lagi tanpa desain canonicalization yang jelas.

---

# 15. Mekanisme Cryptographic Signature

Konsep:

```text
Document
    |
    v
SHA-256
    |
    v
Document Hash
    |
    v
Private Key
    |
    v
Digital Signature
```

Untuk verifikasi:

```text
Document
    |
    v
SHA-256
    |
    v
Current Hash
    |
    +----------------+
                     |
Stored Signature <--+
                     |
                     v
                 Public Key
                     |
                     v
              Signature Valid?
```

Jika file berubah:

```text
Original Hash != Current Hash
```

maka dokumen dapat ditandai:

```text
INVALID / MODIFIED
```

---

# 16. QR Verification

QR Code tidak perlu menyimpan:

```text
Nama
Jabatan
Nomor dokumen
Signature
Private key
```

QR cukup berisi:

```text
verification URL
```

Contoh:

```text
https://hris.company.id/verify/8F92A7C19D2E
```

Ketika user membuka URL:

```text
GET /verify/{verification_code}
```

server mengambil:

```text
document
signature
verification
```

kemudian menampilkan informasi yang aman untuk publik.

---

# 17. Contoh Halaman Verifikasi

```text
========================================

          DOKUMEN VALID

========================================

Jenis Dokumen
Surat Tugas

Nomor Dokumen
ST/HR/0092/2026

Judul
Surat Tugas Budi Santoso

Penandatangan
Ahmad

Jabatan
HR Manager

Tanggal Tanda Tangan
27 September 2026

Status Signature
VALID

Status Dokumen
TIDAK BERUBAH

========================================

Dokumen diterbitkan oleh
PT XYZ

Verification ID
8F92A7C19D2E

========================================
```

Jangan menampilkan informasi HRIS internal yang tidak diperlukan.

---

# 18. Public Verification API / Web

Contoh endpoint:

```text
GET /verify/{verification_code}
```

Endpoint bersifat publik.

Tidak membutuhkan login.

Tetapi endpoint hanya memberikan data verifikasi.

Contoh response:

```json
{
    "valid": true,
    "document": {
        "number": "ST/HR/0092/2026",
        "type": "Surat Tugas",
        "title": "Surat Tugas Budi Santoso"
    },
    "signer": {
        "name": "Ahmad",
        "position": "HR Manager"
    },
    "signature": {
        "status": "valid",
        "signed_at": "2026-09-27T14:30:22+08:00",
        "algorithm": "RSA-SHA256"
    }
}
```

---

# 19. Audit Trail

Selain tabel di atas, sistem sebaiknya memiliki audit trail.

Jika HRIS sudah mempunyai mekanisme audit log, gunakan mekanisme tersebut.

Minimal aktivitas berikut tercatat:

```text
document_created
document_updated
approval_requested
document_approved
document_rejected
signature_requested
document_signed
verification_created
document_revoked
document_cancelled
```

Contoh:

```text
actor_id
action
document_id
timestamp
ip_address
user_agent
metadata
```

Audit trail penting untuk menunjukkan histori proses.

---

# 20. Revoke / Pembatalan Dokumen

Dokumen yang sudah signed tidak sebaiknya dihapus secara fisik.

Contoh:

```text
Dokumen
ST/HR/0092/2026

Status:
REVOKED
```

Verification page:

```text
⚠ DOKUMEN TIDAK BERLAKU

Dokumen ini pernah diterbitkan,
tetapi statusnya telah dicabut.

Tanggal pencabutan:
27 September 2026

Alasan:
Terdapat perubahan penugasan.
```

Dengan demikian QR lama tetap dapat ditemukan tetapi menunjukkan status yang benar.

---

# 21. Jangan Menggunakan QR Statis Per User

Hindari desain:

```text
Ahmad → QR-AHMAD
```

yang kemudian dapat ditempel ke dokumen apa pun.

Lebih baik:

```text
Document A
   |
   +-- Signature A
   |
   +-- Verification Code A

Document B
   |
   +-- Signature B
   |
   +-- Verification Code B
```

Dengan demikian QR terikat kepada dokumen/signature tertentu.

---

# 22. Perbandingan Dua Model

## Model Lemah

```text
User
 |
 +-- QR pribadi
       |
       v
Mahasiswa / HR
       |
       v
Tempel QR ke dokumen
```

Masalah:

- QR dapat disalin
- sulit membuktikan persetujuan terhadap dokumen tertentu
- tidak mengikat signature dengan isi dokumen
- tidak mendeteksi perubahan dokumen dengan sendirinya

## Model yang Direkomendasikan

```text
Document
   |
   v
Approval
   |
   v
Signer Authentication
   |
   v
Document Hash
   |
   v
Cryptographic Signature
   |
   v
Verification Code
   |
   v
QR
```

Keuntungan:

- signature terikat dengan dokumen
- dapat memeriksa integritas
- dapat mengetahui penandatangan
- dapat mengetahui waktu signing
- dapat melakukan revocation
- dapat diverifikasi pihak luar
- audit trail lebih jelas

---

# 23. TTE Internal vs TTE Tersertifikasi

Sistem harus membedakan dua jenis penggunaan.

## Internal Signature

Digunakan untuk kebutuhan internal perusahaan.

Contoh:

- Surat Tugas internal
- Undangan internal
- Sertifikat internal
- Surat administrasi HR
- Berita acara internal

Model:

```text
HRIS
 +
Cryptographic Signature
 +
QR Verification
 +
Audit Trail
```

## Certified Electronic Signature

Digunakan ketika kebutuhan bisnis/administratif mengharuskan TTE tersertifikasi.

Model:

```text
HRIS
   |
   v
PSrE
   |
   v
Certified Electronic Signature
```

HRIS sebaiknya dibuat dengan abstraction sehingga kedua model dapat digunakan.

---

# 24. Signature Provider Abstraction

Disarankan membuat interface/service:

```text
SignatureService
```

Contoh konsep:

```php
interface SignatureService
{
    public function sign(
        string $payload,
        Signer $signer
    ): SignatureResult;

    public function verify(
        string $payload,
        SignatureResult $signature
    ): bool;
}
```

Implementasi:

```text
InternalSignatureService
CertifiedSignatureService
```

Sehingga:

```text
HRIS
 |
 +-- InternalSignatureService
 |
 +-- CertifiedSignatureService
```

Modul dokumen tidak perlu mengetahui detail cryptography/provider.

---

# 25. Contoh Laravel Service Architecture

```text
app/
├── Domain/
│   └── Documents/
│       ├── Models/
│       │   ├── Document.php
│       │   ├── DocumentType.php
│       │   ├── DocumentApproval.php
│       │   ├── DocumentSignature.php
│       │   ├── DocumentVerification.php
│       │   └── SigningKey.php
│       │
│       ├── Services/
│       │   ├── DocumentService.php
│       │   ├── ApprovalService.php
│       │   ├── SignatureService.php
│       │   ├── VerificationService.php
│       │   └── QrCodeService.php
│       │
│       └── Contracts/
│           └── SignatureProvider.php
```

Struktur aktual dapat disesuaikan dengan arsitektur HRIS yang sudah ada.

---

# 26. Security Requirements

Minimal:

- Private key tidak boleh dikirim ke frontend.
- Private key tidak boleh ditampilkan pada log.
- Signature harus dilakukan server-side atau signing service.
- Verification code harus sulit ditebak.
- Jangan menggunakan incremental ID sebagai verification code.
- Gunakan random cryptographically secure token.
- Dokumen signed tidak boleh diedit tanpa membuat signature baru.
- Semua perubahan status harus diaudit.
- Dokumen revoked tidak boleh ditampilkan sebagai valid.
- Endpoint verification tidak boleh membocorkan data HR yang sensitif.
- Gunakan HTTPS.
- Batasi akses signing berdasarkan role/permission.
- Gunakan re-authentication/step-up authentication untuk tindakan signing jika diperlukan.
- Pertimbangkan key rotation dan key revocation.

---

# 27. Verification Code

Jangan gunakan:

```text
/verify/1001
```

jika `1001` hanya merupakan ID database.

Lebih baik:

```text
/verify/8F92A7C19D2E...
```

atau UUID/random token.

Contoh:

```text
verification_code = random_bytes(...)
```

Harus menggunakan random generator yang cryptographically secure.

---

# 28. Status Dokumen

Contoh state machine:

```text
draft
  |
  v
pending_approval
  |
  +------> rejected
  |
  v
approved
  |
  v
pending_signature
  |
  v
signed
  |
  +------> revoked
```

Dokumen tidak boleh kembali ke `draft` setelah `signed`.

Jika isi berubah:

```text
signed document
      |
      v
new revision
      |
      v
approval ulang
      |
      v
sign ulang
```

---

# 29. Versioning

Untuk dokumen yang memungkinkan revisi, pertimbangkan:

```text
documents
document_versions
```

Jika versi dokumen penting, struktur dapat dikembangkan menjadi:

```text
documents
    |
    +-- document_versions
             |
             +-- document_signatures
             |
             +-- document_verifications
```

Contoh:

```text
Document: ST/HR/0092/2026

Version 1
Status: revoked

Version 2
Status: signed
```

Untuk implementasi awal, versioning dapat ditunda jika kebutuhan belum ada.

---

# 30. Rekomendasi MVP

Untuk tahap pertama, tidak perlu langsung membangun seluruh sistem kompleks.

MVP:

```text
1. document_types
2. documents
3. document_signatures
4. document_verifications
5. audit_logs
```

Kemudian:

```text
6. document_approvals
```

Setelah workflow approval stabil:

```text
7. signing_keys
8. cryptographic signature
```

Kemudian:

```text
9. Certified Signature Provider
```

jika kebutuhan sudah ada.

---

# 31. Rekomendasi Implementasi Bertahap

## Phase 1

Document engine:

```text
Document
PDF
Document Number
File Storage
Hash
Status
```

## Phase 2

Approval:

```text
Approval Workflow
Approver
Sequence
Approval History
```

## Phase 3

Internal signing:

```text
Signer
Private/Public Key
SHA-256
Digital Signature
```

## Phase 4

QR verification:

```text
Verification Code
QR Generator
Public Verification Page
```

## Phase 5

Security hardening:

```text
Audit Log
Key Rotation
Revocation
Re-authentication
Rate Limit
Secure Key Storage
```

## Phase 6

External certified TTE:

```text
PSrE Integration
```

---

# 32. Contoh Use Case: Surat Tugas

```text
HR membuat surat
       |
       v
Generate draft
       |
       v
HR Manager approve
       |
       v
Direktur approve
       |
       v
Generate final PDF
       |
       v
Hash PDF
       |
       v
Direktur Sign
       |
       v
Generate signature
       |
       v
Generate verification code
       |
       v
Generate QR
       |
       v
Final document
```

Verifikasi:

```text
Scan QR
   |
   v
Public Verification Page
   |
   +-- Document exists
   +-- Signer exists
   +-- Signature valid
   +-- Hash valid
   +-- Status valid
```

---

# 33. Contoh Use Case: Sertifikat In House Training

Sertifikat dapat dibuat secara batch.

```text
Training
   |
   +-- Participant A
   +-- Participant B
   +-- Participant C
   +-- Participant D
```

Setiap sertifikat:

```text
Certificate A
  |
  +-- unique document ID
  +-- unique verification code
  +-- signature
  +-- QR

Certificate B
  |
  +-- unique document ID
  +-- unique verification code
  +-- signature
  +-- QR
```

Dengan demikian pihak luar dapat memverifikasi sertifikat tanpa login HRIS.

---

# 34. Hal yang Harus Dihindari

Jangan membuat sistem hanya:

```text
PDF
 +
gambar tanda tangan
 +
QR Code
```

karena gambar tanda tangan mudah disalin dan QR tidak otomatis membuktikan bahwa signer menyetujui dokumen tersebut.

Hindari:

```text
User → QR pribadi → semua dokumen
```

Gunakan:

```text
Document
   +
Signer
   +
Hash
   +
Signature
   +
Verification Code
```

---

# 35. Pertimbangan Legal

Dokumentasi ini adalah desain teknis, bukan pendapat hukum.

Dalam kerangka hukum Indonesia, perlu dibedakan:

1. **Tanda tangan elektronik**
2. **Tanda tangan elektronik tersertifikasi**
3. **Tanda tangan elektronik tidak tersertifikasi**
4. **QR Code sebagai mekanisme verifikasi**

PP 71 Tahun 2019 mengatur penyelenggaraan sistem dan transaksi elektronik serta persyaratan TTE.

TTE tersertifikasi menggunakan Sertifikat Elektronik dari PSrE Indonesia dan memiliki persyaratan tersendiri.

TTE tidak tersertifikasi juga dikenal dalam kerangka regulasi, tetapi kekuatan pembuktian dan penerimaannya harus dianalisis berdasarkan mekanisme yang digunakan serta konteks dokumennya.

Karena itu sistem internal tidak boleh mengklaim:

> "Semua dokumen otomatis sah untuk semua keperluan hanya karena memiliki QR."

Untuk kebutuhan tertentu yang mensyaratkan TTE tersertifikasi, HRIS harus dapat menggunakan provider/PSrE yang sesuai.

---

# 36. Keputusan Arsitektur yang Direkomendasikan

Untuk HRIS:

```text
                    DOCUMENT ENGINE
                           |
             +-------------+-------------+
             |                           |
        INTERNAL SIGN               CERTIFIED SIGN
             |                           |
       Cryptographic                  PSrE
       Signature
             |
             v
       QR Verification
             |
             v
       Public Verify
```

Dokumen internal menggunakan internal signing jika sesuai kebutuhan.

Dokumen yang membutuhkan sertifikasi menggunakan PSrE.

Kedua jenis signature sebaiknya menggunakan interface yang sama agar modul dokumen tidak bergantung pada provider tertentu.

---

# 37. Kesimpulan

Struktur yang direkomendasikan:

```text
document_types
       |
       v
documents
   |
   +----------> document_approvals
   |
   +----------> document_signatures
                       |
                       v
              document_verifications

users
  |
  +----------> approvals
  +----------> signatures
  +----------> signing_keys
```

Prinsip utama:

```text
QR ≠ Signature
QR = Verification Entry Point

Signature = Cryptographic / Electronic Signing Mechanism

Hash = Document Integrity

Approval = Business Authorization

Audit Log = Evidence of Process

PSrE = Option for Certified Electronic Signature
```

Dengan model ini, HRIS tidak mengunci seluruh sistem pada satu provider TTE, tetapi tetap memiliki fondasi untuk:

- TTE internal
- QR verification
- audit trail
- document integrity
- approval workflow
- revocation
- future PSrE integration

---

# 38. Referensi Regulasi

Regulasi utama yang perlu dianalisis lebih lanjut oleh tim legal/IT:

- Undang-Undang Nomor 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik beserta perubahan-perubahannya.
- Peraturan Pemerintah Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik.
- Ketentuan teknis terkait Penyelenggara Sertifikasi Elektronik dan tanda tangan elektronik yang berlaku.

Sumber resmi regulasi dapat diperiksa melalui JDIH Kementerian Komunikasi dan Digital:

https://jdih.komdigi.go.id/

---

# 39. Open Questions untuk Project HRIS

Sebelum implementasi final, project perlu menentukan:

- Apakah signature internal akan digunakan hanya untuk dokumen internal?
- Siapa saja yang boleh melakukan signing?
- Apakah satu dokumen dapat memiliki banyak signer?
- Apakah signer menggunakan akun HRIS atau autentikasi tambahan?
- Apakah private key bersifat per-user atau per-organization?
- Di mana private key akan disimpan?
- Apakah perlu key rotation?
- Apakah dokumen dapat direvoke?
- Apakah dokumen memiliki masa berlaku?
- Apakah perlu document versioning?
- Apakah verification page dapat diakses publik?
- Data apa yang boleh ditampilkan pada verification page?
- Dokumen apa yang wajib menggunakan TTE tersertifikasi?
- PSrE mana yang akan digunakan jika diperlukan?
- Apakah perusahaan memerlukan kebijakan internal mengenai penggunaan TTE?
- Apakah tim legal perlu melakukan review sebelum fitur digunakan untuk dokumen resmi?

---

**Dokumen ini dimaksudkan sebagai baseline arsitektur untuk analisis dan diskusi project HRIS. Implementasi cryptographic signature dan keputusan legal perlu divalidasi kembali sebelum digunakan sebagai mekanisme resmi perusahaan.**
