# Alur Penandatanganan Dokumen HRIS

## 1. Tujuan

Dokumentasi ini menjelaskan alur standar penandatanganan dokumen HRIS untuk Berita Acara, Surat Peringatan (SP), Surat Tugas, Surat Keterangan, dan dokumen HR lainnya.

Konsep utama:

```text
Draft
  ↓
Review
  ↓
FIX / LOCK CONTENT
  ↓
Multi-Signature
  ↓
Finalisasi
  ↓
Hash + QR Verification
  ↓
Immutable Document
```

Jika isi dokumen perlu diubah setelah signed, dokumen lama **tidak diedit**. Sistem membuat versi baru dan proses tanda tangan dilakukan kembali.

---

## 2. Prinsip Utama

### FIX / LOCK

`FIX` berarti isi dokumen sudah final dan dikunci.

Setelah FIX:

```text
❌ Tidak boleh mengubah isi
❌ Tidak boleh mengganti data dokumen
❌ Tidak boleh mengubah data yang memengaruhi isi dokumen
```

Namun proses tanda tangan masih berlangsung.

### FINAL / SIGNED

`FINAL` berarti seluruh tanda tangan yang diwajibkan sudah selesai.

Contoh:

```text
Pembuat       ✓
Mengetahui    ✓
Karyawan      ✓
```

Setelah semua selesai:

```text
Generate Final Document
        ↓
Hash
        ↓
QR Verification
        ↓
Immutable
```

---

## 3. Alur Keseluruhan

```text
                 ┌──────────────────┐
                 │  BUAT DOKUMEN    │
                 └────────┬─────────┘
                          ↓
                       DRAFT
                          ↓
                 ┌──────────────────┐
                 │ REVIEW / EDIT    │
                 └────────┬─────────┘
                          ↓
                 ┌──────────────────┐
                 │   FIX / LOCK     │
                 │      CONTENT     │
                 └────────┬─────────┘
                          ↓
              ┌────────────────────────┐
              │ Isi dokumen tidak dapat│
              │       diedit lagi      │
              └───────────┬────────────┘
                          ↓
                 TANDA TANGAN #1
                     PEMBUAT
                          ↓
                 TANDA TANGAN #2
                   MENGETAHUI
                          ↓
                 TANDA TANGAN #3
                    KARYAWAN
                          ↓
              ┌────────────────────────┐
              │   SEMUA SIGNED?        │
              └───────────┬────────────┘
                          │
                         YES
                          ↓
              ┌────────────────────────┐
              │   FINALISASI DOKUMEN   │
              │    PDF + HASH + QR     │
              └───────────┬────────────┘
                          ↓
              ┌────────────────────────┐
              │ FINAL / IMMUTABLE       │
              │ Tidak dapat diedit      │
              └────────────────────────┘
```

---

## 4. Tahap 1 — Draft

Pembuat membuat dokumen melalui HRIS.

Contoh SP:

```text
Nomor       : SP-001/HR/2026
Nama        : Ahmad
Jenis SP    : SP 1
Alasan      : Pelanggaran ...
Tanggal     : 03 Oktober 2026
```

Status:

```text
DRAFT
```

Pada tahap ini dokumen masih dapat diedit dan diperbaiki.

---

## 5. Tahap 2 — Review

Pembuat atau pihak berwenang memeriksa:

- isi dokumen;
- data karyawan;
- tanggal;
- alasan;
- nomor dokumen;
- pihak penandatangan;
- urutan penandatangan.

Jika ada kesalahan:

```text
REVIEW
  ↓
Kembali ke DRAFT
```

---

## 6. Tahap 3 — FIX / LOCK CONTENT

Jika isi sudah benar, pembuat memilih:

```text
FIX DOKUMEN
```

Sistem kemudian:

1. menyimpan snapshot/version dokumen;
2. membuat hash konten/dokumen;
3. mengubah status menjadi `FIXED`;
4. mengunci isi dokumen;
5. membuat proses signing.

Contoh:

```text
Version : 1
Status  : FIXED
Hash    : A8F91C...
```

Setelah FIX:

```text
DRAFT
   ↓
FIXED
   ↓
Tidak dapat diedit
```

---

## 7. Mengapa FIX Harus Sebelum Tanda Tangan?

Karena seluruh penandatangan harus menandatangani **isi dokumen yang sama**.

```text
Dokumen FIXED
      │
      ├── Pembuat tanda tangan
      │
      ├── Mengetahui tanda tangan
      │
      └── Karyawan tanda tangan
```

Jangan menggunakan pola:

```text
Pembuat TTD
   ↓
Edit dokumen
   ↓
Mengetahui TTD
   ↓
Edit lagi
   ↓
Karyawan TTD
```

Setelah FIX, perubahan isi harus dihentikan.

---

## 8. Tahap 4 — Multi-Signature

Setelah dokumen FIXED, proses tanda tangan dilakukan sesuai urutan.

Contoh:

```text
Signer 1
Pembuat
   ↓
Signer 2
Mengetahui
   ↓
Signer 3
Karyawan
```

Status:

```text
Pembuat       ✓ SIGNED
Mengetahui    ⏳ WAITING
Karyawan      ⏳ WAITING
```

Kemudian:

```text
Pembuat       ✓ SIGNED
Mengetahui    ✓ SIGNED
Karyawan      ⏳ WAITING
```

Terakhir:

```text
Pembuat       ✓ SIGNED
Mengetahui    ✓ SIGNED
Karyawan      ✓ SIGNED
```

---

## 9. Authentication Saat Tanda Tangan

Flow yang disarankan:

```text
Signer login
    ↓
Buka dokumen
    ↓
Review dokumen
    ↓
Konfirmasi persetujuan
    ↓
Re-authentication
    ↓
Password / PIN / MFA
    ↓
Signature Process
```

Contoh:

```text
[✓] Saya telah membaca dan menyetujui
    dokumen ini dan bermaksud
    menandatanganinya secara elektronik.

[ TANDA TANGANI ]
```

Password/PIN/MFA merupakan bagian dari autentikasi/otorisasi proses signing, bukan otomatis merupakan TTE itu sendiri.

---

## 10. Tahap 5 — Semua Signature Selesai

Sistem mengecek:

```text
Required Signers
        ↓
Apakah semuanya SIGNED?
```

Jika:

```text
Pembuat       ✓
Mengetahui    ✓
Karyawan      ✓
```

maka:

```text
ALL SIGNED
```

Jika masih ada signer:

```text
Pembuat       ✓
Mengetahui    ✓
Karyawan      ⏳
```

dokumen belum final.

---

## 11. Tahap 6 — Finalisasi Dokumen

Setelah semua signature selesai:

```text
ALL SIGNED
    ↓
Generate Final Document
    ↓
Hash
    ↓
Simpan Signature Metadata
    ↓
Generate Verification Code
    ↓
Generate QR
    ↓
FINAL
```

Status:

```text
SIGNED / FINAL
```

---

## 12. Final Document

Dokumen final menjadi artefak yang tidak boleh diedit.

Contoh:

```text
SP-001-2026.pdf
```

Metadata:

```text
Status    : FINAL
Version   : 1
Hash      : A8F91C...
Signed At : 2026-10-03 10:30:00
```

Dokumen final tidak boleh:

```text
❌ diedit
❌ diganti isinya
❌ diubah tanggalnya
❌ diubah nama karyawan
❌ diubah alasan SP
```

Jika perlu perubahan, buat versi baru.

---

## 13. QR Verification

QR bukan pengganti TTE. QR digunakan sebagai sarana verifikasi.

Contoh:

```text
QR
 ↓
https://hris.company.id/verify/8F92A7C19D2E
 ↓
Verification Page
```

Halaman verifikasi:

```text
DOKUMEN VALID

Nomor:
SP-001/HR/2026

Jenis:
Surat Peringatan

Penandatangan:

✓ Ahmad
  Pembuat

✓ Budi
  Mengetahui

✓ Candra
  Karyawan

Tanggal:
03 Oktober 2026

Status:
VALID
```

QR sebaiknya unik untuk dokumen/version/signature set tertentu.

Jangan menggunakan satu QR statis untuk semua dokumen milik signer.

---

## 14. Hash dan Dokumen

Contoh:

```text
Final Document
      ↓
SHA-256
      ↓
A8F91C7D...
```

Hash membantu memastikan data yang diverifikasi sesuai dengan data yang ditandatangani.

Jika data berubah:

```text
Dokumen asli    → ABC123
Dokumen berubah → XYZ789
```

Hash berbeda menunjukkan data yang dibandingkan sudah berubah.

---

## 15. Penting: QR dan Hash PDF

Hindari proses sederhana:

```text
1. Generate PDF
2. Hash seluruh PDF
3. Sign hash
4. Tambahkan QR ke PDF
```

Karena:

```text
PDF sebelum QR
     ↓
Hash A

Tambahkan QR
     ↓
PDF berubah
     ↓
Hash B
```

Akibatnya:

```text
Hash A != Hash B
```

Karena itu implementasi harus menentukan payload yang ditandatangani dan bagaimana QR serta metadata verifikasi diikat ke dokumen.

Contoh payload:

```text
Signed Payload
----------------------
document_number
document_type
document_version
document_content_hash
signer_id
signer_name
signer_position
signed_at
```

Kemudian:

```text
Signed Payload
      ↓
Hash
      ↓
Digital Signature
```

---

## 16. Jika Ada Perubahan Setelah Signed

Dokumen final tidak diedit.

Contoh:

```text
SP Version 1
     ↓
SIGNED
     ↓
Ada kesalahan tanggal
```

Jangan:

```text
Edit Version 1
```

Tetapi:

```text
Version 1
Status: SUPERSEDED
       ↓
Create Version 2
       ↓
Review
       ↓
FIX
       ↓
Signing ulang
       ↓
Final Version 2
```

Version 1 tetap disimpan sebagai histori.

---

## 17. Contoh Versioning

### Version 1

```text
SP-001/HR/2026

Version: 1
Status : SUPERSEDED

Pembuat       ✓
Mengetahui    ✓
Karyawan      ✓

Hash:
ABC123...
```

### Version 2

```text
SP-001/HR/2026

Version: 2
Status : VALID

Pembuat       ✓
Mengetahui    ✓
Karyawan      ✓

Hash:
XYZ789...
```

Version 2 menjadi versi aktif.

---

## 18. Struktur Database

```text
documents
    │
    └── document_versions
             │
             ├── document_signatures
             │       ├── Signer 1
             │       ├── Signer 2
             │       └── Signer 3
             │
             └── document_verifications
```

### documents

```text
id
document_type_id
document_number
status
current_version_id
created_by
created_at
updated_at
```

Contoh status:

```text
draft
signing
signed
superseded
cancelled
revoked
```

### document_versions

```text
id
document_id
version
file_path
content_hash
hash_algorithm
status
fixed_at
created_at
updated_at
```

Contoh status:

```text
draft
fixed
signing
signed
superseded
```

### document_signatures

```text
id
document_id
document_version_id
signer_id
signer_name
signer_position
sequence
signature_type
algorithm
signature_data
signed_at
status
created_at
updated_at
```

Contoh:

```text
version_id | signer      | sequence | status
-----------|-------------|----------|--------
202        | Pembuat     | 1        | signed
202        | Mengetahui  | 2        | signed
202        | Karyawan    | 3        | signed
```

### document_verifications

```text
id
document_id
document_version_id
verification_code
status
created_at
updated_at
```

---

## 19. State Machine

```text
DRAFT
  |
  v
FIXED
  |
  v
SIGNING
  |
  v
SIGNED
  |
  +------> SUPERSEDED
  |
  +------> REVOKED
```

Revisi:

```text
SIGNED
  |
  v
SUPERSEDED
  |
  v
New Version
  |
  v
DRAFT
  |
  v
FIXED
  |
  v
SIGNING
  |
  v
SIGNED
```

---

## 20. Audit Trail

Catat event penting:

```text
document_created
document_updated
document_fixed
signature_requested
signature_viewed
document_signed
verification_created
document_finalized
document_revoked
document_superseded
```

Contoh:

```text
User       : Ahmad
Event      : document_signed
Document   : SP-001/HR/2026
Version    : 1
Timestamp  : 2026-10-03 10:30:22
IP Address : ...
User Agent : ...
```

---

## 21. Alur SP / Berita Acara

```text
┌─────────────────────┐
│ 1. CREATE DOCUMENT  │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 2. DRAFT            │
│    Masih dapat edit │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 3. REVIEW           │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 4. FIX / LOCK       │
│    Content dikunci  │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 5. SIGNER #1        │
│    PEMBUAT          │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 6. SIGNER #2        │
│    MENGETAHUI       │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 7. SIGNER #3        │
│    KARYAWAN         │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 8. ALL SIGNED       │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 9. FINALIZE         │
│    PDF + Hash + QR  │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ 10. IMMUTABLE       │
│     Tidak dapat edit│
└─────────────────────┘
```

---

## 22. Jika Ada Revisi

```text
             FINAL V1
                 │
                 ↓
           Ada Perubahan
                 │
                 ↓
          Jangan Edit V1
                 │
                 ↓
          Create Version 2
                 │
                 ↓
              DRAFT
                 │
                 ↓
             REVIEW
                 │
                 ↓
             FIX / LOCK
                 │
                 ↓
       Signer 1 → Signer 2 → Signer 3
                 │
                 ↓
             FINAL V2
```

---

## 23. Dokumen dengan Jumlah Signer Berbeda

Arsitektur dapat mendukung jumlah signer yang berbeda.

### 1 Signer

```text
FIX
 ↓
Signer
 ↓
FINAL
```

### 2 Signer

```text
FIX
 ↓
Signer 1
 ↓
Signer 2
 ↓
FINAL
```

### 3 Signer

```text
FIX
 ↓
Signer 1
 ↓
Signer 2
 ↓
Signer 3
 ↓
FINAL
```

### N Signer

```text
FIX
 ↓
Signer Sequence
 ↓
Semua Required Signer Selesai
 ↓
FINAL
```

Gunakan konfigurasi sequence:

```text
1 = Pembuat
2 = Kepala Unit
3 = Direktur
```

---

## 24. Arsitektur Konseptual

```text
                    HRIS
                     │
                     ▼
             Document Management
                     │
                     ▼
               Document Version
                     │
             ┌───────┴────────┐
             │                │
             ▼                ▼
          Approval         Signature
                              │
                  ┌───────────┼───────────┐
                  ▼           ▼           ▼
                TTD 1       TTD 2       TTD 3
                  │           │           │
                  └───────────┼───────────┘
                              ▼
                         All Signed
                              │
                              ▼
                    Final Document
                              │
                 ┌────────────┴────────────┐
                 ▼                         ▼
               Hash                  Verification
                                           │
                                           ▼
                                           QR
```

---

## 25. Prinsip Keamanan

Minimal sistem harus memperhatikan:

- Authentication signer
- Authorization signer
- Explicit consent
- Re-authentication untuk dokumen penting
- Signing credential/key protection
- Document hash
- Timestamp
- Audit trail
- Versioning
- Revocation
- Verification
- Backup
- Access control

Private key atau credential signing tidak boleh diletakkan di database biasa tanpa perlindungan yang memadai.

---

## 26. Hubungan dengan PP 71/2019

Arsitektur perlu dirancang dengan memperhatikan:

```text
PP 71 Tahun 2019

Pasal 59 ayat (3)
→ persyaratan TTE agar memiliki kekuatan hukum

Pasal 60
→ TTE tersertifikasi dan tidak tersertifikasi

Pasal 61
→ data pembuatan TTE dan penguasaannya

Pasal 62
→ proses pembuatan/penggunaan TTE dan persetujuan signer

Pasal 64
→ verifikasi informasi elektronik yang ditandatangani
```

> QR saja tidak menjadikan dokumen otomatis sebagai TTE. QR adalah sarana verifikasi. Implementasi TTE perlu memperhatikan identifikasi, kontrol credential, persetujuan, integritas, dan verifikasi.

---

## 27. Kesimpulan

Pola standar HRIS:

```text
DRAFT
  ↓
REVIEW
  ↓
FIX / LOCK CONTENT
  ↓
SIGNER 1
  ↓
SIGNER 2
  ↓
SIGNER 3
  ↓
ALL SIGNED
  ↓
GENERATE FINAL DOCUMENT
  ↓
HASH + QR VERIFICATION
  ↓
IMMUTABLE
```

Jika terjadi perubahan:

```text
IMMUTABLE DOCUMENT
        ↓
     REVISI
        ↓
   NEW VERSION
        ↓
      DRAFT
        ↓
       FIX
        ↓
SIGNING ULANG
        ↓
FINAL VERSION BARU
```

**Prinsip utama:**

> Dokumen dikunci sebelum proses tanda tangan dimulai. Dokumen menjadi final/immutable setelah seluruh penandatangan yang diwajibkan selesai. Jika isi dokumen berubah, jangan mengubah dokumen lama; buat versi baru dan lakukan tanda tangan kembali.

Pola ini memungkinkan satu engine dokumen digunakan untuk Berita Acara, SP, Surat Tugas, Surat Keterangan, dan dokumen lain dengan jumlah penandatangan yang berbeda.
