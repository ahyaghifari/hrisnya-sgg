# Dokumentasi TTE Non-Tersertifikasi & QR Verification pada HRIS

## 1. Tujuan

Dokumen ini menjadi acuan awal untuk merancang fitur:
- Pembuatan dokumen resmi dari HRIS
- Penomoran surat otomatis
- Persetujuan dokumen
- Tanda Tangan Elektronik (TTE)
- TTE non-tersertifikasi
- QR Code untuk verifikasi dokumen
- Audit trail
- Validasi keaslian dan integritas dokumen

> Catatan: Ini adalah acuan teknis dan pemahaman regulasi, bukan pendapat hukum. Untuk dokumen yang memiliki konsekuensi hukum atau harus diterima pihak eksternal, tetap perlu validasi legal/compliance perusahaan.

---

## 2. Konsep Dasar

### QR Code

QR Code adalah media untuk mengakses informasi. QR bukan otomatis merupakan TTE.

Contoh:

```text
QR Code
   |
   v
https://hris.company.id/verify/8F92A7C19D2E
   |
   v
Halaman Verifikasi
```

### TTE

TTE menghubungkan dokumen, identitas penandatangan, persetujuan penandatangan, data pembuatan TTE, dan integritas dokumen.

### Sistem Verifikasi

Sistem verifikasi digunakan untuk mengecek apakah dokumen dan tanda tangan masih valid.

---

## 3. Dasar Hukum Utama

Regulasi utama: **PP Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik.**

Pasal yang relevan:
- Pasal 59
- Pasal 60
- Pasal 61
- Pasal 62
- Pasal 64

---

## 4. Pasal 59 Ayat (3)

Pasal 59 ayat (3) merupakan dasar utama mengenai kekuatan hukum TTE.

Secara substansi, TTE mempunyai kekuatan hukum dan akibat hukum yang sah apabila memenuhi persyaratan yang ditentukan.

Persyaratan tersebut antara lain:

1. Data pembuatan TTE terkait hanya dengan Penanda Tangan.
2. Data pembuatan TTE pada saat penandatanganan berada dalam kuasa Penanda Tangan.
3. Perubahan terhadap TTE setelah penandatanganan dapat diketahui.
4. Perubahan terhadap informasi elektronik terkait TTE setelah penandatanganan dapat diketahui.
5. Terdapat cara untuk mengidentifikasi Penanda Tangan.
6. Terdapat cara untuk menunjukkan bahwa Penanda Tangan memberikan persetujuan terhadap informasi elektronik terkait.

### Implikasi untuk HRIS

Sistem tidak cukup hanya menyediakan tombol:

```text
Tanda Tangan
```

Sistem harus dapat menunjukkan:

```text
Siapa yang menandatangani
        +
Apa yang ditandatangani
        +
Kapan ditandatangani
        +
Bagaimana persetujuan diberikan
        +
Bagaimana integritas dokumen dijaga
```

---

## 5. Pasal 60 — TTE Tersertifikasi dan Tidak Tersertifikasi

Pasal 60 membedakan:

```text
TTE
├── TTE Tersertifikasi
└── TTE Tidak Tersertifikasi
```

Pasal 60 ayat (1) menjelaskan fungsi TTE sebagai alat autentikasi/verifikasi identitas Penanda Tangan serta untuk menjamin keutuhan dan keautentikan informasi elektronik.

Pasal 60 ayat (2) menyatakan bahwa TTE terdiri dari TTE tersertifikasi dan TTE tidak tersertifikasi.

---

## 6. Pasal 60 Ayat (3) — TTE Tersertifikasi

TTE tersertifikasi harus memenuhi persyaratan yang ditentukan, termasuk:
- memenuhi Pasal 59 ayat (3);
- menggunakan Sertifikat Elektronik yang diterbitkan oleh PSrE Indonesia;
- menggunakan perangkat pembuat TTE tersertifikasi.

---

## 7. Pasal 60 Ayat (4) — TTE Tidak Tersertifikasi

TTE tidak tersertifikasi dibuat **tanpa menggunakan jasa PSrE Indonesia**.

Artinya, regulasi mengenal TTE yang tidak menggunakan layanan PSrE.

Namun:

```text
Tanpa PSrE
≠
Tanpa keamanan
≠
Tanpa autentikasi
≠
Tanpa persetujuan
≠
Tanpa integritas
```

Persyaratan TTE lainnya tetap harus diperhatikan.

---

## 8. BSrE vs PSrE

Istilah regulasi yang perlu digunakan adalah:

**PSrE = Penyelenggara Sertifikasi Elektronik**

BSrE merupakan salah satu penyelenggara dalam ekosistem sertifikasi elektronik pemerintah.

Jadi jangan menggunakan asumsi:

```text
TTE sah = harus BSrE
```

Lebih tepat:

```text
TTE tersertifikasi
        |
        v
menggunakan layanan PSrE yang sesuai
```

Sedangkan:

```text
TTE tidak tersertifikasi
        |
        v
tidak menggunakan jasa PSrE Indonesia
```

---

## 9. Pasal 61 — Data Pembuatan TTE

Pasal 61 mengatur data pembuatan TTE, termasuk hubungan data pembuatan dengan Penanda Tangan dan data verifikasi.

Dalam HRIS, konsepnya dapat berupa:

```text
User
 |
 +-- Signing Key
       |
       +-- Private Key
       +-- Public Key
       +-- Status
       +-- Created At
       +-- Revoked At
```

### Keamanan private key

Jangan menyimpan private key plaintext di database tanpa perlindungan.

Lebih baik:

```text
HRIS
 |
 +-- Signing Key Reference
          |
          v
Secure Key Storage / Secret Management
```

---

## 10. Pasal 62 — Proses Penandatanganan

Pasal 62 berkaitan dengan proses penandatanganan, termasuk:
- validitas data verifikasi;
- penguasaan data pembuatan TTE;
- pemahaman Penanda Tangan terhadap informasi yang ditandatangani;
- mekanisme yang menunjukkan persetujuan;
- penggunaan data pembuatan TTE;
- waktu penandatanganan;
- kemampuan mendeteksi perubahan terhadap TTE dan informasi elektronik.

### Implementasi UX yang disarankan

```text
User login
   |
   v
Buka dokumen
   |
   v
Preview dokumen
   |
   v
User membaca
   |
   v
Konfirmasi:
"Saya setuju dan menandatangani dokumen ini"
   |
   v
Re-authentication / PIN / MFA
   |
   v
Hash + Signature + Timestamp
   |
   v
Verification Code + QR
   |
   v
Signed Document
```

---

## 11. Pasal 64 Ayat (3) — Verifikasi

Pasal 64 ayat (3) relevan dengan verifikasi informasi elektronik yang telah ditandatangani.

Verifikasi dapat dilakukan dengan memeriksa data verifikasi TTE untuk mengetahui perubahan terhadap informasi elektronik yang ditandatangani.

Dalam HRIS:

```text
Dokumen
   |
   v
Hash
   |
   v
Signature
   |
   v
Verification
```

---

## 12. QR Verification

QR sebaiknya menjadi pintu masuk ke sistem verifikasi.

Contoh:

```text
PDF
 |
 +-- Nomor: 001/ST/HR/IX/2026
 +-- Penandatangan: Direktur
 +-- Tanggal: 28-09-2026
 +-- QR Code
          |
          v
https://hris.company.id/verify/8F92A7C19D2E
```

Halaman verifikasi dapat menampilkan:

```text
STATUS       : VALID
NOMOR        : 001/ST/HR/IX/2026
JENIS        : Surat Tugas
PENANDATANGAN: Nama Penandatangan
JABATAN      : Direktur
DITANDATANGANI: 28-09-2026
```

---

## 13. Jangan Menggunakan QR Statis Per User

Tidak disarankan:

```text
Ahmad
  |
  +-- QR-AHMAD
```

yang kemudian digunakan pada semua dokumen.

QR harus terikat dengan dokumen/signature tertentu.

Contoh:

```text
Document ID:
DOC-2026-000123

Verification Code:
8F92A7C19D2E

Verification URL:
https://hris.company.id/verify/8F92A7C19D2E
```

Dengan demikian QR dapat menghubungkan:

```text
Penandatangan
      +
Dokumen tertentu
      +
Tindakan penandatanganan
```

---

## 14. Struktur Database

### document_types

```text
id
code
name
requires_signature
requires_approval
active
created_at
updated_at
```

Contoh code:

```text
surat_tugas
surat_peringatan
surat_undangan
sertifikat_iht
berita_acara
surat_keterangan
surat_mutasi
```

### documents

```text
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

Status:

```text
draft
pending_approval
approved
pending_signature
signed
revoked
cancelled
```

### document_approvals

```text
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

Approval dan signature sebaiknya dipisahkan.

### document_signatures

```text
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

`signer_name` dan `signer_position` dapat disimpan sebagai snapshot histori.

### document_verifications

```text
id
document_id
signature_id
verification_code
status
expires_at
created_at
updated_at
```

### signing_keys

```text
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

Private key tidak disarankan disimpan plaintext.

---

## 15. Audit Trail

Catat event penting:

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
User       : Ahmad
Event      : document_signed
Document   : DOC-2026-000123
Timestamp  : 2026-09-28 10:30:22
IP Address : ...
User Agent : ...
```

---

## 16. Hash Dokumen

Hash dapat digunakan untuk mendeteksi perubahan.

```text
PDF
 |
 v
SHA-256
 |
 v
A1B2C3D4...
```

Jika isi berubah, hash berubah.

```text
Dokumen asli   -> ABC123
Dokumen berubah -> XYZ789
```

---

## 17. Masalah QR dan Hash PDF

Hindari desain sederhana:

```text
1. Generate PDF
2. Hash PDF
3. Sign hash
4. Tambahkan QR ke PDF
```

Karena setelah QR ditambahkan:

```text
PDF berubah
```

sehingga hash PDF juga berubah.

### Pendekatan yang lebih terstruktur

Tentukan payload yang ditandatangani terlebih dahulu:

```text
Signed Payload
----------------
document_number
document_type
document_content_hash
signer_id
signer_name
signer_position
signed_at
```

Kemudian:

```text
Signed Payload
      |
      v
Hash
      |
      v
Digital Signature
```

QR mengarah ke data verifikasi:

```text
QR
 |
 v
Verification Code
 |
 v
HRIS
 |
 +-- Signed Payload
 +-- Signature
 +-- Document Hash
 +-- Signer
 +-- Timestamp
 +-- Status
```

Format/canonicalization payload harus ditentukan secara konsisten pada implementasi final.

---

## 18. Arsitektur Signature Provider

Agar HRIS tidak terkunci pada satu metode:

```php
interface SignatureProvider
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
SignatureProvider
       |
       +-- InternalSignatureService
       |
       +-- CertifiedSignatureService
```

Dengan demikian:

```text
HRIS
 |
 v
SignatureService
 |
 +------------------------+
 |                        |
 v                        v
Internal TTE          PSrE Integration
```

---

## 19. Tiga Level Implementasi

### Level 1 — QR Verification

Tujuan: memastikan dokumen memang diterbitkan oleh HRIS/perusahaan.

Komponen:

```text
PDF
+
Nomor Dokumen
+
QR
+
Verification Page
```

Ini adalah mekanisme verifikasi dokumen dan jangan otomatis disebut TTE hanya karena terdapat QR.

### Level 2 — TTE Tidak Tersertifikasi

Target: mekanisme TTE internal yang dirancang memenuhi persyaratan TTE sesuai regulasi.

Komponen:

```text
User Authentication
+
Explicit Consent
+
Signing Key
+
Digital Signature
+
Document Hash
+
Timestamp
+
Audit Trail
+
Verification
+
Revocation
```

Dasar yang perlu diperhatikan terutama:

```text
PP 71/2019
Pasal 59
Pasal 60
Pasal 61
Pasal 62
Pasal 64
```

### Level 3 — TTE Tersertifikasi

Target: dokumen yang membutuhkan TTE tersertifikasi.

```text
HRIS
 |
 v
Signature Provider
 |
 v
PSrE
 |
 v
Certified Electronic Signature
```

---

## 20. Perbandingan

| Aspek | QR Verification | TTE Non-Tersertifikasi | TTE Tersertifikasi |
|---|---|---|---|
| QR | Ya | Dapat digunakan | Dapat digunakan |
| Verifikasi dokumen | Ya | Ya | Ya |
| Identitas signer | Dapat ditampilkan | Harus dirancang | Menggunakan mekanisme sertifikasi |
| Explicit consent | Tidak cukup hanya QR | Diperlukan | Diperlukan |
| Integritas | Perlu mekanisme | Perlu | Perlu |
| Digital signature | Tidak wajib | Bagian desain TTE | Ya |
| PSrE | Tidak | Tidak | Ya |
| Cocok untuk internal | Ya | Ya | Ya |
| Kebutuhan eksternal | Tergantung | Tergantung | Tergantung persyaratan dokumen |

---

## 21. Flow HRIS

```text
Create Draft
     |
     v
Approval Workflow
     |
     v
All Approval Done?
     |
    Yes
     |
     v
Generate Final PDF
     |
     v
Request Signature
     |
     v
Signer Review PDF
     |
     v
Explicit Consent
     |
     v
Authentication
     |
     v
Hash / Sign
     |
     v
Timestamp / Audit
     |
     v
Verification Code
     |
     v
Generate QR
     |
     v
Signed Document
```

---

## 22. Status Dokumen

Disarankan:

```text
DRAFT
  |
  v
PENDING_APPROVAL
  |
  +---- REJECTED
  |
  v
APPROVED
  |
  v
PENDING_SIGNATURE
  |
  v
SIGNED
  |
  +---- REVOKED
```

Dokumen yang sudah signed sebaiknya tidak diedit langsung.

Jika berubah:

```text
Signed Document
      |
      v
Create New Version
      |
      v
New Approval
      |
      v
New Signature
```

---

## 23. Revocation

Status dapat berupa:

```text
VALID
REVOKED
INVALID
EXPIRED
```

QR dokumen lama tetap dapat dipindai dan sistem menampilkan status terbaru.

Contoh:

```text
STATUS DOKUMEN
---------------
Nomor : 001/ST/HR/IX/2026
Status: REVOKED

Alasan:
Dokumen digantikan oleh dokumen baru.
```

---

## 24. Keamanan Minimum

### Authentication
Signer harus dapat diidentifikasi.

### Re-authentication
Dokumen penting dapat menggunakan password, PIN, OTP, atau MFA.

### Private Key
Private key harus dilindungi.

### Authorization
Hanya signer yang ditentukan yang dapat menandatangani.

### Audit Log
Tindakan penting dicatat.

### Hash
Gunakan mekanisme integritas dokumen.

### Revocation
Sediakan mekanisme pembatalan/revokasi.

### Backup
Data signature dan verification harus memiliki backup yang aman.

---

## 25. Hal yang Jangan Dilakukan

### Jangan:

```text
QR = TTE
```

### Jangan:

```text
QR Ahmad digunakan untuk semua dokumen Ahmad
```

### Jangan:

```text
User login
→ otomatis dianggap setuju
```

### Jangan:

```text
Private key disimpan plaintext
```

### Jangan:

```text
Dokumen sudah signed
→ PDF bebas diedit
```

### Jangan:

```text
Hash PDF
→ tambahkan QR
→ anggap hash tetap sama
```

---

## 26. Checklist Implementasi

### Document Management
- [ ] Document type
- [ ] Automatic numbering
- [ ] Document status
- [ ] Document version
- [ ] Final PDF generation

### Approval
- [ ] Approval sequence
- [ ] Approver
- [ ] Approval status
- [ ] Rejection
- [ ] Approval timestamp

### Signature
- [ ] Signer identification
- [ ] Explicit consent
- [ ] Re-authentication
- [ ] Signing key
- [ ] Digital signature
- [ ] Document hash
- [ ] Timestamp
- [ ] Signature status

### Verification
- [ ] Unique verification code
- [ ] QR generation
- [ ] Verification endpoint
- [ ] Document status
- [ ] Signer information
- [ ] Hash verification
- [ ] Signature verification

### Security
- [ ] Private key protection
- [ ] Authorization
- [ ] Audit trail
- [ ] Revocation
- [ ] Backup
- [ ] Access logging

---

## 27. Kesimpulan

Arsitektur yang fleksibel untuk HRIS:

```text
                  HRIS
                   |
                   v
            Document Management
                   |
          +--------+--------+
          |                 |
          v                 v
      Approval          Signature
                            |
                 +----------+----------+
                 |                     |
                 v                     v
       Internal TTE              Certified TTE
       / Non-certified               / PSrE
                 |
                 v
          Verification
                 |
                 v
                QR
```

Dasar hukum utama:

```text
PP 71 Tahun 2019
│
├── Pasal 59 ayat (3)
│   └── Persyaratan TTE
│
├── Pasal 60
│   ├── TTE tersertifikasi
│   └── TTE tidak tersertifikasi
│
├── Pasal 61
│   └── Data pembuatan TTE
│
├── Pasal 62
│   └── Proses penandatanganan dan persetujuan
│
└── Pasal 64 ayat (3)
    └── Verifikasi informasi elektronik yang ditandatangani
```

> **Poin utama:** QR Code bukan sumber keabsahan TTE. QR adalah sarana verifikasi. Keabsahan dan kekuatan pembuktian TTE bergantung pada keseluruhan proses identifikasi penandatangan, kontrol atas data pembuatan TTE, persetujuan, integritas dokumen, tanda tangan, waktu penandatanganan, dan mekanisme verifikasi.

Untuk HRIS, desain sebaiknya mendukung:

```text
Internal / Non-certified TTE
            +
Certified TTE melalui PSrE
```

sehingga perusahaan dapat memilih mekanisme sesuai kebutuhan dokumen dan persyaratan administratifnya.

---

## 28. Referensi Regulasi

- PP Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik.
- JDIH Kementerian Komunikasi dan Digital Republik Indonesia.
- Ketentuan mengenai Tanda Tangan Elektronik pada Pasal 59–64 PP 71/2019.

> Sebelum sistem digunakan sebagai mekanisme penandatanganan dokumen resmi perusahaan, lakukan review terhadap regulasi terbaru dan validasi dengan legal/compliance perusahaan.
