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

    // Ke karyawan, saat payroll-nya ditandai sudah dibayar (App\Jobs tidak terlibat — dikirim
    // langsung saat tombol "Tandai Sudah Dibayar" diklik, TERPISAH dari email slip gaji).
    case PAYROLL_PAID = 'payroll_paid';

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
            self::PAYROLL_PAID => 'Gaji Saya Sudah Dibayar',
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
            self::PAYROLL_PAID => 'Dikirim saat payroll Anda ditandai sudah dibayar oleh finance.',
        };
    }
}
