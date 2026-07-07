# Planning: Web Portal Pengajuan Absensi

> Dokumen ini dibuat berdasarkan sesi desain & eksplorasi kode pada 2 Juli 2026.
> Tujuan: menjadi referensi lengkap saat implementasi di session baru.

---

## 1. Gambaran Umum

Web portal terpisah dari `/admin` (Filament). Portal ini ditujukan untuk **karyawan** agar bisa:
- Login dan mengelola akun
- Mengajukan cuti, izin, dan lembur
- Melihat riwayat dan status pengajuan

Backend **sudah ada** — semua controller, service, request validation, dan API route sudah tersedia. Yang perlu dibangun hanya sisi frontend (blade views / SPA / atau framework pilihan).

---

## 2. Demo & Referensi Desain

File demo HTML tersedia di `planning-web-attendance/demo/`. Semua sudah responsive (mobile-first, Tailwind CDN).

| File | Halaman |
|---|---|
| `login.html` | Halaman login |
| `forgot-password.html` | Request reset password |
| `reset-password.html` | Reset password via token |
| `dashboard.html` | Dashboard karyawan (bento grid) |
| `form-cuti.html` | Form pengajuan cuti |
| `form-izin.html` | Form pengajuan izin |
| `form-lembur.html` | Form pengajuan lembur |
| `list-pengajuan.html` | Daftar & riwayat semua pengajuan |
| `detail-pengajuan.html` | Detail satu pengajuan + timeline approval |
| `change-password.html` | Ganti password (di dalam portal) |

### Design Tokens
```
Font      : Plus Jakarta Sans (Google Fonts)
Warna utama: #042c6c (navy), #1a56c4 (biru), #047C54 (hijau success)
Background: #f1f5f9
Card      : #fff, border-radius 18px, shadow 0 1px 4px rgba(0,0,0,0.06)
```

### Pola Responsive
- Tailwind CDN untuk layout utility
- Custom CSS untuk neumorphism, pseudo-element, dropdown hover
- Breakpoint utama: `640px` (sm), `768px` (md), `1024px` (lg)
- Nav: hamburger mobile (`<768px`) → full nav desktop (`≥768px`)
- Bento grid dashboard: 1 col → 2 col (sm) → 4 col bento (lg)
- Tabel: dibungkus `overflow-x:auto` + `min-width` agar tidak collapse

---

## 3. Auth Flow

### Urutan halaman
```
login → (lupa password?) → forgot-password → [email link] → reset-password
                                                                    ↓
                                                               dashboard
                                                                    ↓
                                                         (di dalam portal) change-password
```

### API Endpoints Auth

Semua di prefix `/api/auth`, **tanpa** middleware (kecuali logout & change-password).

| Method | Endpoint | Middleware | Keterangan |
|---|---|---|---|
| POST | `/api/auth/login` | — | Login, return Sanctum token |
| POST | `/api/auth/request-reset-password` | — | Kirim email link reset |
| POST | `/api/auth/reset-password` | — | Reset password via token |
| POST | `/api/auth/logout` | `auth:sanctum` | Invalidate token |
| POST | `/api/auth/change-password` | `auth:sanctum` | Ganti password (harus login) |

### Penyimpanan Token
Token Sanctum disimpan di `localStorage` atau cookie httpOnly (rekomendasikan cookie untuk keamanan lebih baik).

### Request Body Login
```json
{ "email": "string|required", "password": "string|required|min:8" }
```

---

## 4. API Endpoints Pengajuan

Semua di prefix `/api/attendance`, middleware `auth:sanctum`.

### List & Detail
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/attendance/requests` | List semua pengajuan milik user yang login |
| GET | `/api/attendance/requests/{id}` | Detail satu pengajuan |

**Query params GET `/requests`:** bisa difilter (lihat service `getListAttendanceRequestsByUserId`, parameter `$filters` dari `$request->query()`).

### Buat Pengajuan

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/attendance/leave` | Buat pengajuan **cuti** |
| POST | `/api/attendance/permission` | Buat pengajuan **izin** |
| POST | `/api/attendance/overtime` | Buat pengajuan **lembur** |

### Saldo Cuti
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/attendance/leave/balance/{typeId}` | Saldo cuti user untuk tipe tertentu |

---

## 5. Validasi Request per Tipe

### Cuti — `POST /api/attendance/leave`
```
type_id      : required, exists:m_leave_types,id
is_one_day   : required, boolean
start_date   : required, date
end_date     : required_if:is_one_day,false | nullable | date | after_or_equal:start_date
```

### Izin — `POST /api/attendance/permission`
```
type_id              : required, exists:m_permission_types,id
date                 : required, date
start_time           : required, format H:i
end_time             : required, format H:i, after:start_time
purpose              : nullable, string
location             : nullable, string
substitute_user_id   : nullable, exists:users,id
substitute_user_name : nullable, string
substitute_task      : nullable, string
```

### Lembur — `POST /api/attendance/overtime`
```
date                      : required, date
type_id                   : required, exists:m_overtime_types,id
tasks                     : nullable, array, min:1
tasks.*.task_description  : nullable, string, max:500
tasks.*.start_time        : nullable, format H:i
tasks.*.end_time          : nullable, format H:i, after:tasks.*.start_time
attachments               : nullable, array
attachments.*             : file, mimes:pdf,jpg,jpeg,png, max:10MB
```

---

## 6. Master API (Data Dropdown)

Middleware `auth:sanctum`. Digunakan untuk mengisi dropdown di form pengajuan.

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/master/attendance/request-types?type=leave` | Tipe cuti (m_leave_types) |
| GET | `/api/master/attendance/request-types?type=permission` | Tipe izin (m_permission_types) |
| GET | `/api/master/attendance/request-types?type=overtime` | Tipe lembur (m_overtime_types) |
| GET | `/api/master/departments/other` | Departemen lain (untuk form lembur, field pengganti) |

> **Catatan:** Di web portal, boleh juga query model Laravel langsung tanpa melewati Master API jika implementasi pakai Blade (bukan SPA).

---

## 7. Tabel Database yang Relevan

Database: `hris_db` (143 tabel total).

| Tabel | Baris (per Juli 2026) | Status | Kegunaan |
|---|---|---|---|
| `attendance_requests` | 149 | **Aktif** | Menyimpan semua pengajuan cuti/izin/lembur |
| `m_leave_types` | — | Aktif | Master tipe cuti (dropdown) |
| `m_permission_types` | — | Aktif | Master tipe izin (dropdown) |
| `m_overtime_types` | — | Aktif | Master tipe lembur (dropdown) |
| `users` | — | Aktif | Data user / karyawan |
| `user_profiles` | — | Aktif | Profil karyawan (departemen, tipe karyawan) |
| `counselings` | 12 | Jarang dipakai | Konseling karyawan |
| `coachings` | 4 | Jarang dipakai | Coaching karyawan |
| `task_records` | 2 | Jarang dipakai | Catatan tugas (bukan `tasks`) |
| `roles`, `permissions`, `model_has_roles` | — | Aktif | Spatie Permission |
| `approval_flows`, `approval_activities` | — | Aktif | Alur approval pengajuan |

---

## 8. Halaman & Fitur yang Perlu Diimplementasi

### 8.1 Auth Pages (tidak perlu nav portal)

#### Login (`/login`)
- Form: email + password, toggle show/hide password, "Ingat saya" checkbox
- Submit → `POST /api/auth/login` → simpan token → redirect ke `/dashboard`
- Link "Lupa password?" → `/forgot-password`

#### Forgot Password (`/forgot-password`)
- Form: email saja
- Submit → `POST /api/auth/request-reset-password`
- Setelah submit: tampilkan state sukses (email dikirim, berlaku 60 menit)
- Dua state di satu halaman: form-state vs success-state

#### Reset Password (`/reset-password?token=...`)
- Form: password baru + konfirmasi
- Password strength meter (4 level: lemah → kuat)
- Submit → `POST /api/auth/reset-password` dengan token dari URL query param
- Sukses → link ke `/login`

#### Change Password (`/change-password`) — di dalam portal, ada nav
- Form: password saat ini + password baru + konfirmasi
- Password strength meter
- Sidebar: tips keamanan + info sesi aktif
- Submit → `POST /api/auth/change-password`
- Feedback: toast notification

### 8.2 Dashboard (`/dashboard`)

Layout bento grid:
```
┌─────────────────────┬──────────┬──────────┐
│                     │ Saldo    │ Menunggu │
│   Welcome Card      │ Tahunan  │Persetujuan│
│   (quick action)    ├──────────┼──────────┤
│                     │ Saldo    │ Disetujui│
│                     │ Sakit    │Bulan ini │
├─────────────────────┴──────────┴──────────┤
│         Riwayat Pengajuan Terbaru          │
└────────────────────────────────────────────┘
```

Data yang dibutuhkan:
- `GET /api/user` — nama, NIP, departemen, perusahaan
- `GET /api/attendance/leave/balance/{typeId}` — saldo cuti tahunan & sakit
- `GET /api/attendance/requests` — riwayat terbaru (5 data terakhir)

Quick action di welcome card: tombol Cuti, Izin, Lembur → masing-masing ke form.

Tombol "Admin Panel" → `/admin` (target blank), ditampilkan di nav kanan.

### 8.3 Form Cuti (`/pengajuan/cuti`)

Field:
- Jenis cuti (dropdown dari `GET /api/master/attendance/request-types?type=leave`)
- Tanggal mulai
- Tanggal selesai (disabled jika "cuti 1 hari" dicentang)
- Checkbox: cuti 1 hari
- Keterangan (optional)

Saldo cuti ditampilkan di samping/bawah dropdown jenis cuti:
`GET /api/attendance/leave/balance/{typeId}` dipanggil setelah jenis dipilih.

Submit → `POST /api/attendance/leave`

### 8.4 Form Izin (`/pengajuan/izin`)

Field:
- Jenis izin (dropdown dari `GET /api/master/attendance/request-types?type=permission`)
- Tanggal
- Waktu mulai & waktu selesai
- Keperluan izin
- Lokasi
- Nama pengganti (opsional)
- Tugas pengganti (opsional)

Submit → `POST /api/attendance/permission`

### 8.5 Form Lembur (`/pengajuan/lembur`)

Field:
- Jenis lembur (dropdown dari `GET /api/master/attendance/request-types?type=overtime`)
- Tanggal lembur
- Rincian tugas (repeater): deskripsi tugas, waktu mulai, waktu selesai
- Lampiran file (pdf/jpg/jpeg/png, max 10MB per file)

Submit → `POST /api/attendance/overtime` (multipart/form-data jika ada lampiran)

### 8.6 List Pengajuan (`/pengajuan`)

- Fetch: `GET /api/attendance/requests`
- Filter tab: Semua / Cuti / Izin / Lembur
- Filter status: Semua / Menunggu / Disetujui / Ditolak
- Search: nama / tanggal (client-side atau query param)
- Tabel dengan kolom: Tipe, Tanggal Pengajuan, Periode, Status, Aksi
- Pagination
- Klik baris → `/pengajuan/{id}`

### 8.7 Detail Pengajuan (`/pengajuan/{id}`)

- Fetch: `GET /api/attendance/requests/{id}`
- Layout 2 kolom: detail di kiri, timeline approval di kanan
- Tampilkan semua field sesuai tipe (cuti/izin/lembur)
- Badge status dengan warna
- Timeline approval history (tanggal, aktor, role, catatan, aksi)
- Tombol Batalkan (hanya jika status `in_review` / `submitted`)

---

## 9. Aksesibilitas `/admin`

### Kondisi saat ini
Di [`app/Models/User.php:125`](../app/Models/User.php):
```php
public function canAccessPanel(\Filament\Panel $panel): bool
{
    return true; // semua user bisa masuk
}
```

**Semua user yang login bisa akses `/admin`.** Kontrol akses hanya ada per-resource menggunakan Spatie Permission (`Gate::allows('view_pengajuan_absensi')` dst).

### Jika ingin restrict
Ubah `canAccessPanel()` menjadi:
```php
return $this->hasAnyRole(['super_admin', 'hr_admin', 'manager']);
// atau exclude karyawan biasa:
return ! $this->hasRole('karyawan');
```

### Gap di /admin: Form Cuti & Lembur belum ada
Di `AttendanceRequestForm::createSchema()`, hanya ada `permissionFormSchema()`.
Kalau buka `/admin/attendance-requests/create?type=leave` → form kosong.
Perlu ditambahkan `leaveFormSchema()` dan `overtimeFormSchema()` di file yang sama.

---

## 10. Arsitektur Implementasi yang Disarankan

### Opsi A — Blade + Alpine.js + Livewire (recommended untuk proyek ini)
- Konsisten dengan stack Laravel yang sudah ada
- Livewire untuk interaktivitas (filter, form dinamis, saldo realtime)
- Alpine.js untuk toggle, dropdown, strength meter
- Layout terpisah dari Filament admin (`resources/views/portal/`)

### Opsi B — SPA (Vue/React)
- Cocok jika kelak portal mau dijadikan PWA atau mobile app
- Konsumsi semua endpoint API yang sudah ada
- Auth: token Sanctum disimpan di httpOnly cookie

### Route Web yang Perlu Ditambahkan (Opsi A)
```php
// routes/web.php
Route::prefix('portal')->group(function () {
    // Guest
    Route::get('/login', [PortalAuthController::class, 'showLogin']);
    Route::post('/login', [PortalAuthController::class, 'login']);
    Route::get('/forgot-password', ...);
    Route::post('/forgot-password', ...);
    Route::get('/reset-password', ...);
    Route::post('/reset-password', ...);

    // Auth
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/pengajuan', [PengajuanController::class, 'index']);
        Route::get('/pengajuan/cuti', [PengajuanController::class, 'formCuti']);
        Route::post('/pengajuan/cuti', [PengajuanController::class, 'submitCuti']);
        Route::get('/pengajuan/izin', [PengajuanController::class, 'formIzin']);
        Route::post('/pengajuan/izin', [PengajuanController::class, 'submitIzin']);
        Route::get('/pengajuan/lembur', [PengajuanController::class, 'formLembur']);
        Route::post('/pengajuan/lembur', [PengajuanController::class, 'submitLembur']);
        Route::get('/pengajuan/{id}', [PengajuanController::class, 'detail']);
        Route::get('/change-password', [PortalAuthController::class, 'showChangePassword']);
        Route::post('/change-password', [PortalAuthController::class, 'changePassword']);
        Route::post('/logout', [PortalAuthController::class, 'logout']);
    });
});
```

> Jika menggunakan session auth (bukan token), pastikan session guard terpisah dari guard Filament (`web` vs `sanctum`).

---

## 11. Checklist Implementasi

### Auth
- [ ] Login form + session/token handling
- [ ] Forgot password (kirim email)
- [ ] Reset password (via token URL)
- [ ] Change password (di dalam portal)
- [ ] Logout

### Portal
- [ ] Dashboard + fetch data user, saldo, riwayat terbaru
- [ ] Form cuti + dropdown tipe + tampilkan saldo setelah pilih tipe
- [ ] Form izin + dropdown tipe
- [ ] Form lembur + repeater task + upload lampiran
- [ ] List pengajuan + filter tab + filter status
- [ ] Detail pengajuan + timeline approval
- [ ] Batalkan pengajuan (jika masih bisa)

### Tambahan /admin (opsional)
- [ ] Tambahkan `leaveFormSchema()` di `AttendanceRequestForm`
- [ ] Tambahkan `overtimeFormSchema()` di `AttendanceRequestForm`
- [ ] Pertimbangkan restrict `canAccessPanel()` agar karyawan biasa diarahkan ke portal
