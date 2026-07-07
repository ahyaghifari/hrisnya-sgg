# Filament — Analisis Scoping Data (per 2026-07-03)

Ringkasan singkat: apakah listing data di `/admin` sudah terfilter sesuai user yang login (per company, per hierarki atasan-bawahan)?

## Hierarki atasan-bawahan: TIDAK ADA

Tidak ditemukan satu pun logic yang membatasi listing berdasarkan "supervisor cuma lihat bawahannya". Field "Atasan Langsung" di form Job Position cuma nyimpen `parent_id` struktur jabatan, bukan filter query. Jadi Supervisor/Kepala Departemen yang login akan lihat SEMUA data di company-nya (semua tim), bukan cuma anak buahnya — di AttendanceRequest, Task, Coaching, Counseling, dll.

## Scoping company: manual per-Resource, tidak konsisten

Filament tenancy bawaan tidak dipakai (`AdminPanelProvider` tidak ada `->tenant()`). Company-scoping ditulis manual tiap Resource:
```php
if ($user->company_id && !PermissionHelper::hasGlobalRole($user)) {
    $query->where('company_id', $user->company_id);
}
```
(`hasGlobalRole()` cuma cek `company_id === ID holding`, bukan cek role sungguhan.)

Karena manual & copy-paste, banyak Resource yang **lupa** menerapkannya:
- Hampir semua sub-resource **konfigurasi Payroll** (PayrollPeriods, CompanySalaryComponents, OvertimeRates, ProgramIncentives, dll)
- Sebagian besar modul **LMS**, **Assessment Template/Type**, **Competency**
- **`UserShiftScheduleResource`** — bocor total, tidak ada filter sama sekali, dan permission gate-nya salah nama (pakai izin lowongan kerja)

Resource inti yang sudah benar: ApprovalFlow, AttendanceRecords, AttendanceRequests, Coaching, Counseling, Employee, Payroll (nominal individu), Role, TaskRequest, Companies, JobPositions, master data org (Bank/Department/Division/dst).

## Temuan paling serius: IDOR di 2 Resource

- **`UserResource`**: listing "Data Diri" cuma nampilin diri sendiri (filter di level tabel), tapi halaman **edit** tidak cek kepemilikan record — `canEdit()` cuma cek permission blanket. User dengan izin `edit_data_pribadi` (lazim dimiliki semua karyawan) bisa edit profil karyawan lain lewat URL langsung kalau tahu ID-nya.
- **`UserShiftSwapResource`**: pola sama — bisa lihat/edit pengajuan tukar shift karyawan lain lewat URL langsung.

Ini bug keamanan nyata (bukan cuma gap desain), perlu diprioritaskan sebelum resource-resource ini dipakai lebih luas.

## Implikasi untuk rencana "Filament cuma buat master data + custom view per role"

Temuan ini memperkuat rencana yang sudah disepakati: modul operasional (attendance, task, shift, payroll individu) memang sebaiknya pindah ke view/portal custom per role, karena Filament di sini tidak dirancang untuk membatasi visibilitas per-hierarki — kalau tetap di Filament, tiap resource harus di-audit satu-satu untuk company-scope, dan hierarki atasan-bawahan harus dibangun dari nol (belum ada sama sekali).
