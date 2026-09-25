# HRIS — Penerapan Fitur SP & Demo Merit Point

## Tujuan

Dokumen ini menjadi spesifikasi implementasi untuk AI coding agent dalam menambahkan:

1. **Surat Peringatan (SP)** sebagai fitur HRIS.
2. **Merit Point** sebagai fitur demo/percobaan karena aturan HR belum final.

Prinsip:
- Sederhana dan tidak over-engineering.
- Ikuti convention project existing.
- Coaching dan Attendance dapat menjadi sumber rekomendasi SP.
- SP yang diterbitkan adalah entity terpisah dari Coaching.
- Merit Point menggunakan transaction/ledger.
- Rule dapat otomatis, manual berbasis rule, atau manual bebas.

---

# 1. Surat Peringatan (SP)

## 1.1 Alur

SP tidak sama dengan Coaching.

```text
Coaching
   ↓
Rekomendasi SP
   ↓
Review HR
   ↓
Employee SP
   ↓
Employee memberikan keterangan
```

Attendance dapat menghasilkan rekomendasi SP secara otomatis:

```text
Attendance/Fingerprint
   ↓
Attendance Rule
   ↓
Rekomendasi SP
   ↓
Review HR
   ↓
Employee SP
```

Jangan membuat Coaching palsu untuk kasus otomatis dari Attendance.

---

## 1.2 Master alasan SP

Buat tabel:

```text
sp_reasons
-------------------------
id
code
name
is_active
created_at
updated_at
```

Contoh seed:

```text
LATE_ATTENDANCE
UNAUTHORIZED_ABSENCE
NOT_FOLLOWING_INSTRUCTION
SOP_VIOLATION
INAPPROPRIATE_BEHAVIOR
```

Alasan menggunakan master karena daftar alasan dapat berkembang.

---

## 1.3 Employee SP

Buat tabel:

```text
employee_sp
-------------------------
id
employee_id
sp_level
reason_id
source_type
source_id
start_date
end_date
reason_note
employee_response
response_date
issued_by
issued_at
created_at
updated_at
```

### SP Level

Gunakan enum:

```text
SP1
SP2
SP3
```

Tidak perlu membuat master table untuk level SP.

### Source Type

Gunakan enum:

```text
COACHING
ATTENDANCE
MANUAL
```

`source_id` nullable.

Contoh:

```text
source_type = COACHING
source_id = 123
```

berarti SP berasal dari Coaching ID 123.

Jika dibuat langsung HR:

```text
source_type = MANUAL
source_id = NULL
```

### Masa berlaku

Saat SP diterbitkan:

```text
SP1 -> end_date = start_date + 90 hari
SP2 -> end_date = start_date + 180 hari
SP3 -> end_date = NULL
```

Jangan meminta HR memasukkan `end_date` secara manual untuk SP1/SP2.

---

## 1.4 Active SP

Tidak perlu menyimpan status `ACTIVE` atau `EXPIRED` jika dapat dihitung dari tanggal.

SP aktif jika:

```sql
start_date <= CURRENT_DATE
AND (end_date IS NULL OR end_date >= CURRENT_DATE)
```

Dengan begitu:
- SP1 otomatis selesai setelah 90 hari.
- SP2 otomatis selesai setelah 180 hari.
- SP3 tetap aktif karena `end_date = NULL`.

Untuk MVP tidak perlu job khusus hanya untuk mengubah status menjadi expired.

---

## 1.5 Employee Response

Employee yang terkena SP harus memberikan keterangan.

Untuk MVP simpan langsung di `employee_sp`:

```text
employee_response
response_date
```

Interpretasi:

```text
employee_response IS NULL
```

= belum memberikan keterangan.

```text
employee_response IS NOT NULL
```

= sudah memberikan keterangan.

Tidak perlu tabel response terpisah selama satu SP hanya membutuhkan satu keterangan.

---

## 1.6 Tampilan Employee

Jika ada SP aktif, tampilkan misalnya:

```text
Surat Peringatan Aktif

SP 1
Mulai       : 01 Oktober 2026
Berlaku s/d : 29 Desember 2026

Alasan:
Tidak mengikuti instruksi atasan.

Status Keterangan:
Belum diberikan

[ Berikan Keterangan ]
```

Setelah response:

```text
Surat Peringatan Aktif

SP 1
Mulai       : 01 Oktober 2026
Berlaku s/d : 29 Desember 2026

Alasan:
Tidak mengikuti instruksi atasan.

Keterangan:
Saya memahami peringatan tersebut dan akan memperbaiki hal tersebut.

Dikirim:
02 Oktober 2026
```

---

# 2. SP Recommendation

Rekomendasi SP dan SP yang sudah diterbitkan adalah dua hal berbeda.

```text
Recommendation
     ↓
HR Review
   ↙   ↘
Reject Approve
          ↓
     employee_sp
```

Jika project membutuhkan entity recommendation tersendiri, gunakan:

```text
sp_recommendations
-------------------------
id
employee_id
sp_level
reason_id
source_type
source_id
reason_note
decision
reviewed_by
reviewed_at
created_at
```

Namun jangan membuat tabel ini hanya untuk menambah kompleksitas. Jika kebutuhan MVP dapat dipenuhi dengan field nullable pada Coaching dan trigger dari Attendance, gunakan desain yang lebih sederhana.

---

# 3. Coaching Integration

Coaching bersifat satu arah:

```text
Coach -> Employee
```

Contoh data existing:

```text
id
employee_id
coach_id
coaching_date
topic
description
instruction
created_by
created_at
updated_at
```

Jika Coaching dapat merekomendasikan SP, tambahkan field nullable yang sesuai convention project, misalnya:

```text
recommended_sp_level
recommended_sp_reason_id
```

atau gunakan relation ke `sp_recommendations` bila entity tersebut memang dibuat.

Coaching tidak perlu memiliki status session/chat.

---

# 4. Merit Point Demo

Merit Point adalah ledger transaction.

Jangan hanya menyimpan angka total. Semua perubahan point harus tercatat sebagai transaction.

```text
Rule / Manual
     ↓
Merit Point Transaction
     ↓
SUM(points)
     ↓
Balance 0..100
```

Merit Point pada dokumen ini hanya **demo**. Rule contoh bukan aturan HR final.

---

# 5. Merit Point Rules

Buat tabel:

```text
merit_point_rules
-------------------------
id
type
name
points
trigger_type
is_active
created_at
updated_at
```

## Type

Gunakan enum:

```text
REWARD
PENALTY
CONTRIBUTION
```

Tidak perlu `merit_point_types` table untuk demo.

## Trigger Type

Gunakan enum:

```text
AUTOMATIC
MANUAL
```

`AUTOMATIC` berarti rule dapat dipanggil oleh sistem berdasarkan event.

`MANUAL` berarti rule dipilih oleh user yang berwenang.

Manual custom tidak membutuhkan rule sama sekali.

---

# 6. Demo Merit Point Rules

Seed data berikut hanya untuk demo:

| Type | Rule | Points | Trigger |
|---|---|---:|---|
| PENALTY | Terlambat | -5 | AUTOMATIC |
| PENALTY | Tidak hadir tanpa keterangan | -10 | AUTOMATIC |
| REWARD | Sertifikasi | +20 | MANUAL |
| REWARD | Employee Recognition | +10 | MANUAL |
| CONTRIBUTION | Membantu Production Incident | +15 | MANUAL |

Berikan komentar/penanda bahwa data tersebut adalah demo.

---

# 7. Merit Point Transactions

Buat tabel:

```text
merit_point_transactions
-------------------------
id
employee_id
rule_id
points
reason
source_type
source_id
created_by
created_at
```

`rule_id` nullable.

Jika transaction berasal dari rule:

```text
rule_id = 123
```

Jika manual bebas:

```text
rule_id = NULL
```

---

# 8. Tiga Cara Pemberian Merit Point

## 8.1 Automatic Rule

Contoh:

```text
Attendance
   ↓
Terlambat 20 menit
   ↓
Rule: Terlambat
   ↓
-5 point
```

Transaction:

```text
employee_id = 10
rule_id = 1
points = -5
reason = "Terlambat 20 menit"
source_type = "ATTENDANCE"
source_id = 987
```

---

## 8.2 Manual Menggunakan Rule

Manager memilih:

```text
Employee : Budi
Rule     : Sertifikasi +20
```

Transaction:

```text
employee_id = 10
rule_id = 3
points = +20
reason = "Mendapatkan sertifikasi"
```

Jika menggunakan rule, backend harus mengambil nilai point dari rule. Jangan mempercayai nilai point dari client.

---

## 8.3 Manual Bebas

Manager tiba-tiba ingin memberikan point tertentu.

Contoh:

```text
Employee : Budi
Point    : +13
Reason   : Membantu menyelesaikan production incident
```

Transaction:

```text
employee_id = 10
rule_id = NULL
points = +13
reason = "Membantu menyelesaikan production incident"
source_type = NULL
source_id = NULL
```

Endpoint manual bebas harus menggunakan authorization/permission existing project.

---

# 9. Point Calculation

Balance:

```text
SUM(transaction.points)
```

Dengan batas:

```text
minimum = 0
maximum = 100
```

Contoh:

```text
Initial = 80
+20     = 100
+15     = 100
-5      = 95
-200    = 0
```

Jangan mengubah nilai transaction asli.

Untuk demo, rekomendasi:
- Simpan transaction asli.
- Hitung balance dengan clamp 0..100.
- Jangan edit transaction lama.

Jika ada kesalahan, buat transaction koreksi baru.

Contoh:

```text
+20  "Sertifikasi"
-20  "Koreksi transaksi #123"
```

---

# 10. Merit Point UI

## Employee Detail

```text
Merit Point
----------------
75 / 100

[ + Tambah Point ]
[ - Kurangi Point ]

History
----------------
+15  Membantu Production Incident
+20  Sertifikasi
 -5  Terlambat
+10  Employee Recognition
```

## Manual Transaction

Sediakan dua mode:

```text
Gunakan Rule
Manual Custom
```

### Gunakan Rule

```text
Rule:
[ Sertifikasi +20 ]

Reason:
[........................]
```

### Manual Custom

```text
Point:
[ +13 ]

Reason:
[ Membantu menyelesaikan incident ]
```

---

# 11. API/Backend

Sesuaikan dengan convention project existing.

Contoh resource Merit Point:

```text
GET    /merit-point/rules
POST   /merit-point/rules
PUT    /merit-point/rules/{id}
DELETE /merit-point/rules/{id}

GET    /employees/{id}/merit-point
GET    /employees/{id}/merit-point/transactions
POST   /employees/{id}/merit-point/transactions
```

Contoh resource SP:

```text
GET    /employees/{id}/sp
GET    /employees/{id}/sp/active
POST   /employees/{id}/sp
POST   /employee-sp/{id}/response
```

Jangan mengikuti endpoint ini secara literal jika project memiliki standar routing berbeda.

---

# 12. Validation

## SP

- Employee wajib ada.
- `sp_level` hanya SP1/SP2/SP3.
- `reason_id` wajib valid.
- `start_date` wajib valid.
- SP1 otomatis menghasilkan end_date +90 hari.
- SP2 otomatis menghasilkan end_date +180 hari.
- SP3 menghasilkan end_date NULL.
- Employee hanya dapat memberikan response pada SP miliknya.
- Authorization mengikuti permission existing project.

## Merit Point

- Employee wajib ada.
- Jika `rule_id` ada, rule harus aktif.
- Jika transaction menggunakan rule, point berasal dari rule.
- Manual custom wajib memiliki reason.
- Manual custom wajib memiliki point valid.
- Point dapat positif atau negatif.
- Jangan menghapus/edit history transaction tanpa mekanisme koreksi.
- Authorization mengikuti permission existing project.

---

# 13. Database Migration

Sebelum coding, AI coding agent harus:

1. Inspect repository.
2. Identifikasi framework/backend/frontend.
3. Identifikasi ORM/database.
4. Identifikasi employee model/table.
5. Identifikasi authentication dan authorization.
6. Identifikasi migration convention.
7. Identifikasi enum convention.
8. Identifikasi API/controller/service convention.
9. Identifikasi UI/component convention.

Kemudian:
- Buat migration mengikuti convention project.
- Jangan mengasumsikan nama tabel employee adalah `employees`.
- Jangan membuat enum/table duplicate jika sudah tersedia.
- Gunakan foreign key sesuai pola existing project.

---

# 14. Seed Demo Merit Point

Buat seed/demo data:

```text
Terlambat                     -5   AUTOMATIC
Tidak hadir tanpa keterangan -10   AUTOMATIC
Sertifikasi                  +20   MANUAL
Employee Recognition         +10   MANUAL
Membantu Incident            +15   MANUAL
```

Gunakan employee seed existing jika tersedia.

Tambahkan beberapa transaction agar halaman history dapat langsung didemokan.

Jangan mengubah data production.

---

# 15. Testing

## SP

Minimal test:

1. SP1 menghasilkan end_date +90 hari.
2. SP2 menghasilkan end_date +180 hari.
3. SP3 menghasilkan end_date NULL.
4. SP aktif ditemukan berdasarkan tanggal.
5. SP yang sudah lewat end_date tidak dianggap aktif.
6. Employee dapat memberikan response.
7. Response mencatat response_date.
8. SP memiliki reason.
9. Source Coaching dapat disimpan.
10. Source Attendance dapat disimpan.
11. Source Manual dapat disimpan.

## Merit Point

Minimal test:

1. Rule dapat dibuat.
2. Rule dapat dinonaktifkan.
3. Transaction menggunakan rule mengambil point dari rule.
4. Manual custom membuat transaction dengan rule_id NULL.
5. Point positif bekerja.
6. Point negatif bekerja.
7. Balance tidak lebih dari 100.
8. Balance tidak kurang dari 0.
9. History tidak berubah ketika balance berubah.
10. Koreksi dibuat sebagai transaction baru.

---

# 16. Non-Goals

Jangan implementasikan kecuali project memang sudah membutuhkan:

- Workflow approval kompleks.
- Chat Coaching.
- Multiple employee response.
- Calibration Performance Appraisal.
- Merit Point period.
- Merit Point expiry.
- Gamification.
- Leaderboard.
- SP escalation otomatis berdasarkan jumlah Coaching.
- SP level master table.
- Merit Point type master table.
- Background job hanya untuk mengubah status SP.
- Audit system terpisah.
- Notification system kompleks.

---

# 17. Definition of Done

## SP

- HR dapat melihat daftar SP.
- HR dapat membuat SP manual.
- SP1/SP2/SP3 tersedia.
- End date otomatis sesuai level.
- SP3 tidak memiliki end date.
- HR dapat memilih alasan dari master.
- Source dapat dicatat.
- Employee dapat melihat SP aktif.
- Employee dapat memberikan keterangan.
- Sistem dapat membedakan sudah/belum memberikan keterangan.
- SP expired tidak dianggap active SP.

## Merit Point Demo

- Admin/HR dapat mengelola demo rule.
- Rule memiliki type enum.
- Rule memiliki trigger type enum.
- Employee dapat memiliki transaction.
- Transaction dapat berasal dari rule.
- Transaction dapat dibuat manual bebas.
- History dapat ditampilkan.
- Balance dapat dihitung.
- Balance dibatasi 0..100.
- Demo seed tersedia.
- Test dasar tersedia.

---

# 18. Catatan Penting untuk AI Coding Agent

Sebelum coding, inspect repository dan gunakan convention existing.

Prioritas:

```text
Existing project convention
        >
Dokumen ini
        >
Asumsi baru
```

Jika ada konflik dengan struktur existing, pertahankan convention existing dan pilih implementasi paling sederhana yang tetap memenuhi requirement.

Jangan membuat arsitektur baru hanya karena contoh tabel/API di dokumen berbeda dengan project.

---

# 19. Ringkasan Arsitektur

```text
                         HRIS
                          |
          +---------------+----------------+
          |                                |
       COACHING                         ATTENDANCE
          |                                |
          | recommendation                 | automatic rule
          v                                v
     +----------------+              +----------------+
     |       SP Recommendation / Review               |
     +----------------+
              |
              v
        +-------------+
        | employee_sp |
        +-------------+
          |         |
          |         +--> Employee Response
          |
          +--> Active SP


                    MERIT POINT DEMO

        +----------------------+
        | merit_point_rules    |
        +----------------------+
                  |
          +-------+-------+
          |               |
      AUTOMATIC         MANUAL
          |               |
          +-------+-------+
                  |
                  v
        +--------------------------+
        | merit_point_transactions |
        +--------------------------+
                  |
                  v
          SUM(points), clamp 0..100
                  |
                  v
            Employee Balance
```

# 20. Prinsip Akhir

SP dan Merit Point adalah dua fitur berbeda.

SP:

```text
Recommendation -> HR Review -> SP -> Employee Response -> Expiry
```

Merit Point:

```text
Rule / Manual -> Transaction -> Balance
```

Keduanya dapat memiliki sumber otomatis maupun manual, tetapi business logic keduanya jangan disatukan.

Untuk demo, prioritaskan alur end-to-end yang dapat berjalan daripada membuat semua kemungkinan fitur HR.
