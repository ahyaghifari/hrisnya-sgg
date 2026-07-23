# Gap Migrasi Admin Panel (Filament) ke Portal Karyawan

## Context

Selama beberapa sesi, fitur-fitur di admin panel (Filament, `app/Filament/Resources/`) satu-per-satu dibangun ulang jadi halaman portal (Livewire polos, `app/Livewire/**` + `resources/views/livewire/**`) — misalnya Karyawan, Task/Kanban, Payroll, Presensi/Absensi, Koperasi, Insentif, LMS, Counseling, Coaching, Perubahan Status, Dokumen. Dokumen ini merekap **apa saja yang MASIH cuma ada di admin panel**, supaya jadi acuan urutan migrasi berikutnya.

Data master (Companies, OrgStructure, JobPositions, Pangkats, Golongans, Kelas, EmployeeTypes, Banks, Religions, PermissionTypes, OvertimeTypes, LeaveTypes, Holidays-config, Competencies, JobEvaluationCompanies, dst) sengaja **tidak dimasukkan** — itu memang domain admin/konfigurasi, bukan alur kerja karyawan.

## Belum ada di portal sama sekali

### Rekrutmen / Manpower
- **Lowongan Kerja** (`Filament\Resources\ManPower\JobOpenings`) — posting lowongan: posisi, unit, kuota, jumlah pelamar, syarat.
- **Permintaan Tenaga Kerja** (`ManPower\ManpowerRequests`) — pengajuan headcount/perubahan tenaga kerja (posisi, jenis perubahan, jumlah, lampiran).

### Assessment
- **Assessments** (`Assessment\Assessments`) — instance penilaian (menghubungkan template ke target).
- **Assessment Templates** (`Assessment\AssessmentTemplates`) — builder template/pertanyaan penilaian.
- **User Assessments** (`Assessment\UserAssessments`) — hasil/skor penilaian per karyawan.

### LMS — sisi admin/instruktur (portal baru ada sisi peserta)
- **Question Banks** (`LMS\QuestionBanks`) — bank soal quiz.
- **Quizzes** (`LMS\Quizzes`) — definisi quiz (portal cuma ada `QuizAttempt` buat AMBIL quiz, belum ada buat BIKIN quiz).
- **Trainer Eksternal** (`LMS\TrainerExternals`) — data trainer luar (nama, kontak, rate).
- **Trainer Score Ranges/Variables** (`LMS\TrainerScoreRanges`, `LMS\TrainerScoreVariables`) — rubrik penilaian trainer, dasar hitung honorarium.
- **Training Report** (`LMS\TrainingReport`) — laporan penyelesaian pelatihan (pakai model `LmsCourseBaselines`).

### Payroll
- **Extra Hour Allowances** (`Payroll\ExtraHourAllowances`) — aturan tunjangan jam tambahan, dipakai kalkulasi payroll.
- **Fee Submissions** (`Payroll\FeeSubmissions`) — pengajuan fee/reimbursement (no. referensi, subjek, jenis layanan, penerima).
- **Trainer Salaries** (`Payroll\TrainerSalaries`) — gaji/honor trainer, dihitung dari `TrainerAssessmentResults`.
- **Overtime Rates** (`Payroll\OvertimeRates`, model `ShiftOvertimeCosts`) — prioritas rendah, sifatnya mendekati rate config/master.

## Ambigu / setengah jalan (portal ada, tapi scope beda dari admin)

- **Coaching** — `CoachingPage` (company-wide) sudah dibangun tapi link nav-nya sengaja disembunyikan (`@if (false && $canManageCoaching)` di `nav.blade.php`). Saat ini efektif cuma self-service lewat `MyCoachingPage`.
- **Attendance Requests** — admin bisa CRUD semua pengajuan lintas karyawan; portal cuma self-submit (`MyAttendanceRequests`, `SubmitLeaveRequest`/`SubmitOvertimeRequest`/`SubmitPermissionRequest`) + approval berjenjang (`RiwayatApprovalPage`). Belum ada halaman "kelola semua pengajuan" ala admin.
- **Attendance Records** — admin bisa edit langsung baris presensi mentah; portal cuma nampilin rekap/statistik (`EmployeeAttendanceStatistics`, halaman rekap), tidak ada CRUD record langsung.
- **Task Requests** (model `Task`) — kemungkinan besar sudah tercover modul Task/Kanban portal (`TaskListPage`), tapi belum dicek detail apakah semua alur admin (approve/reject formal request) sudah setara.
- **Approval Flows** (`ApprovalFlows`) — engine definisi alur approval (siapa approve apa, berjenjang). Bukan data master murni, tapi juga bukan fitur harian karyawan — perlu keputusan eksplisit apakah dianggap gap atau tetap admin-only.

## Next step
Belum ada prioritas ditentukan — tunggu arahan modul mana yang mau dikerjakan duluan.
