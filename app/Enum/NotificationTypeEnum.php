<?php

namespace App\Enum;

/**
 * Vocabulary tertutup untuk jenis notifikasi yang bisa di-on/off per user (lihat
 * UserNotificationPreference). Tambah case baru kalau ada jenis notifikasi baru - tidak perlu
 * migration baru, cuma nambah 1 baris di sini + pengecekan `NotificationPreferenceService`.
 */
enum NotificationTypeEnum: string
{
    // Ada pengajuan (cuti/izin/lembur, dst) yang menunggu diproses user ini - dipakai baik saat
    // approver PERTAMA kali dikabari (submit) maupun approver BERIKUTNYA di chain (step maju).
    case APPROVAL_NEEDS_ACTION = 'approval_needs_action';

    // Ke requester, saat pengajuannya berubah status.
    case APPROVAL_APPROVED = 'approval_approved';
    case APPROVAL_REJECTED = 'approval_rejected';
    case APPROVAL_CANCELLED = 'approval_cancelled';

    // Task portal — lihat App\Services\Task\TaskService.
    case TASK_ASSIGNED = 'task_assigned';
    case TASK_REVIEW_DECIDED = 'task_review_decided';
    case TASK_REVIEWER_ASSIGNED = 'task_reviewer_assigned';
    case TASK_COMMENTED = 'task_commented';
    case TASK_PROJECT_MEMBER_ADDED = 'task_project_member_added';
    case TASK_REVIEW_REQUESTED = 'task_review_requested';
    case TASK_COMPLETED_BY_ASSIGNEE = 'task_completed_by_assignee';

    // Ke karyawan, saat payroll-nya ditandai sudah dibayar (App\Jobs tidak terlibat — dikirim
    // langsung saat tombol "Tandai Sudah Dibayar" diklik, TERPISAH dari email slip gaji).
    case PAYROLL_PAID = 'payroll_paid';

    // Coaching & Counseling — lihat App\Services\Coaching\CoachingService, App\Services\Counseling\CounselingService.
    case COUNSELING_RECEIVED = 'counseling_received';
    case COACHING_ASSIGNED = 'coaching_assigned';
    case COACHING_PROOF_SUBMITTED = 'coaching_proof_submitted';
    case COACHING_REVIEW_DECIDED = 'coaching_review_decided';

    // Absensi otomatis dari access_logs (mesin absensi) — lihat
    // App\Console\Commands\SyncAccessLogsAttendance.
    case ATTENDANCE_CHECKED_IN = 'attendance_checked_in';
    case ATTENDANCE_CHECKED_OUT = 'attendance_checked_out';
    case ATTENDANCE_SCAN_REJECTED = 'attendance_scan_rejected';

    public function label(): string
    {
        return match ($this) {
            self::APPROVAL_NEEDS_ACTION => 'Pengajuan Menunggu Persetujuan Saya',
            self::APPROVAL_APPROVED => 'Pengajuan Saya Disetujui',
            self::APPROVAL_REJECTED => 'Pengajuan Saya Ditolak',
            self::APPROVAL_CANCELLED => 'Pengajuan Saya Dibatalkan',
            self::TASK_ASSIGNED => 'Tugas Baru Ditugaskan ke Saya',
            self::TASK_REVIEW_DECIDED => 'Tugas Saya Direview',
            self::TASK_REVIEWER_ASSIGNED => 'Saya Diminta Jadi Reviewer',
            self::TASK_COMMENTED => 'Komentar Baru di Tugas Saya',
            self::TASK_PROJECT_MEMBER_ADDED => 'Ditambahkan ke Project',
            self::TASK_REVIEW_REQUESTED => 'Pelaksana Mengajukan Review Tugas',
            self::TASK_COMPLETED_BY_ASSIGNEE => 'Pelaksana Menyelesaikan Tugas',
            self::PAYROLL_PAID => 'Gaji Saya Sudah Dibayar',
            self::COUNSELING_RECEIVED => 'Ada Karyawan Konsul ke Saya',
            self::COACHING_ASSIGNED => 'Saya Mendapatkan Coaching',
            self::COACHING_PROOF_SUBMITTED => 'Bukti Coaching Menunggu Persetujuan Saya',
            self::COACHING_REVIEW_DECIDED => 'Coaching Saya Disetujui/Ditolak',
            self::ATTENDANCE_CHECKED_IN => 'Absensi Masuk Tercatat',
            self::ATTENDANCE_CHECKED_OUT => 'Absensi Pulang Tercatat',
            self::ATTENDANCE_SCAN_REJECTED => 'Absensi Saya Tidak Diproses',
        };
    }

    /** Dipakai buat mengelompokkan halaman Pengaturan Notifikasi — bukan kategori "resmi" di
     * model manapun, cuma pengelompokan tampilan. */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'approval_'), str_starts_with($this->value, 'attendance_') => 'Absensi',
            str_starts_with($this->value, 'task_') => 'Tugas',
            str_starts_with($this->value, 'payroll_') => 'Penggajian',
            str_starts_with($this->value, 'counseling_'), str_starts_with($this->value, 'coaching_') => 'Coaching & Counseling',
            default => 'Lainnya',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::APPROVAL_NEEDS_ACTION => 'Dikirim saat ada pengajuan cuti/izin/lembur (atau modul lain) yang perlu Anda setujui/tolak, baik sebagai approver pertama maupun lanjutan.',
            self::APPROVAL_APPROVED => 'Dikirim ke Anda saat pengajuan yang Anda buat disetujui sampai selesai.',
            self::APPROVAL_REJECTED => 'Dikirim ke Anda saat pengajuan yang Anda buat ditolak salah satu approver.',
            self::APPROVAL_CANCELLED => 'Dikirim ke Anda saat pengajuan yang Anda buat dibatalkan.',
            self::TASK_ASSIGNED => 'Dikirim saat orang lain menugaskan sebuah tugas ke Anda.',
            self::TASK_REVIEW_DECIDED => 'Dikirim saat tugas yang Anda kerjakan disetujui atau ditolak reviewer.',
            self::TASK_REVIEWER_ASSIGNED => 'Dikirim saat Anda ditunjuk sebagai reviewer sebuah tugas.',
            self::TASK_COMMENTED => 'Dikirim saat ada komentar baru di tugas yang Anda kerjakan/buat.',
            self::TASK_PROJECT_MEMBER_ADDED => 'Dikirim saat Anda dimasukkan sebagai anggota sebuah project tugas.',
            self::TASK_REVIEW_REQUESTED => 'Dikirim ke pembuat tugas saat pelaksana mengajukan tugas untuk direview (kalau dicentang saat bikin tugas).',
            self::TASK_COMPLETED_BY_ASSIGNEE => 'Dikirim ke pembuat tugas saat pelaksana menandai tugas selesai tanpa review (kalau dicentang saat bikin tugas).',
            self::PAYROLL_PAID => 'Dikirim saat payroll Anda ditandai sudah dibayar oleh finance.',
            self::COUNSELING_RECEIVED => 'Dikirim saat ada karyawan yang menunjuk Anda sebagai konselor untuk konsul.',
            self::COACHING_ASSIGNED => 'Dikirim saat atasan/HC membuat sesi coaching baru untuk Anda.',
            self::COACHING_PROOF_SUBMITTED => 'Dikirim ke coach saat karyawan yang dibina mengajukan bukti pelaksanaan.',
            self::COACHING_REVIEW_DECIDED => 'Dikirim saat coach menyetujui atau menolak bukti coaching yang Anda ajukan.',
            self::ATTENDANCE_CHECKED_IN => 'Dikirim saat mesin absensi mencatat Anda check-in.',
            self::ATTENDANCE_CHECKED_OUT => 'Dikirim saat mesin absensi mencatat Anda check-out.',
            self::ATTENDANCE_SCAN_REJECTED => 'Dikirim saat scan absensi Anda tidak bisa diproses sistem (mis. jadwal tidak ditemukan), beserta alasannya.',
        };
    }
}
