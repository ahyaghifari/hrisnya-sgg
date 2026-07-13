# Test Plan — Pengajuan & Persetujuan Cuti/Lembur (Playwright MCP)

## Data konkret (dicek langsung dari DB lokal `hris_db`)

| Peran | Email | Password | Jabatan |
|---|---|---|---|
| Pemohon | ahyaghifari288@gmail.com | password | IT Programmer Staff |
| Atasan 1 | itcoordinator@syifaglobalgroup.com | password | IT Supervisor |
| Atasan 2 | hcga.manager@syifaglobalgroup.com | password | PJ HCGA Manager |

Alur approval yang berlaku untuk departemen IT ("Alur Perizinan IT"): step 1 = IT Supervisor (same_department, mode any), step 2 = PJ HCGA Manager (same_department, mode any, final). Jadi 2 tingkat approval sesuai permintaan.

URL: `http://localhost/login` (guard `web`, redirect ke `/dashboard` untuk non-admin role).

Route pengajuan (Livewire, middleware `auth`+`portal.access`):
- List: `/pengajuan-absensi`
- Form cuti: `/pengajuan-absensi/cuti`
- Form lembur: `/pengajuan-absensi/lembur`
- Detail + approve/reject: `/pengajuan-absensi/{record}`

Leave type tersedia: "Cuti Tahunan". Overtime type tersedia: "Lembur Biasa".

## Skenario

### 1. Pengajuan Cuti
1. Login sebagai ahyaghifari288@gmail.com.
2. Buka `/pengajuan-absensi/cuti`.
3. Isi: leave_type = Cuti Tahunan, tanggal (misal besok), reason.
4. Submit → assert redirect ke detail, status = `submitted`/`in_review`.

### 2. Approval Cuti — Atasan 1 (IT Supervisor)
1. Login sebagai itcoordinator@syifaglobalgroup.com.
2. Cek `PendingApprovalsCard` di dashboard atau langsung buka detail request.
3. Klik approve, isi note wajib, confirm.
4. Assert step 1 selesai, `current_step_order` maju ke 2, status masih `in_review` (belum final).

### 3. Approval Cuti — Atasan 2 (PJ HCGA Manager)
1. Login sebagai hcga.manager@syifaglobalgroup.com.
2. Buka detail request yang sama, approve dengan note.
3. Assert status akhir = `approved` (step final).
4. Login kembali sebagai pemohon, assert status di `/pengajuan-absensi` = Approved.

### 4. Pengajuan Lembur — ulangi langkah 1–3 dengan `/pengajuan-absensi/lembur` (overtime_type = Lembur Biasa, tanggal, task description + start/end time).

### 5. Skenario reject (opsional, satu kali cukup)
Salah satu atasan reject dengan note → assert status `rejected`, alur berhenti (tidak lanjut ke step berikutnya).

## Catatan implementasi Playwright MCP
- Gunakan 3 context/browser tab terpisah (atau login-logout bergantian) karena 3 akun berbeda perlu aktif dalam satu alur end-to-end.
- Ambil snapshot (`browser_snapshot`) sebelum interaksi form untuk dapat ref elemen yang stabil (Livewire re-render sering ubah DOM).
- Tunggu network idle / Livewire update dengan `browser_wait_for` setelah submit sebelum assert status.
- Karena data ini nampak seperti data organisasi asli (nama email perusahaan sungguhan), pastikan test hanya jalan di `APP_ENV=local` (`http://localhost`) — jangan pernah arahkan ke domain produksi.
