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

    public function label(): string
    {
        return match ($this) {
            self::APPROVAL_NEEDS_ACTION => 'Pengajuan Menunggu Persetujuan Saya',
            self::APPROVAL_APPROVED => 'Pengajuan Saya Disetujui',
            self::APPROVAL_REJECTED => 'Pengajuan Saya Ditolak',
            self::APPROVAL_CANCELLED => 'Pengajuan Saya Dibatalkan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::APPROVAL_NEEDS_ACTION => 'Dikirim saat ada pengajuan cuti/izin/lembur (atau modul lain) yang perlu Anda setujui/tolak, baik sebagai approver pertama maupun lanjutan.',
            self::APPROVAL_APPROVED => 'Dikirim ke Anda saat pengajuan yang Anda buat disetujui sampai selesai.',
            self::APPROVAL_REJECTED => 'Dikirim ke Anda saat pengajuan yang Anda buat ditolak salah satu approver.',
            self::APPROVAL_CANCELLED => 'Dikirim ke Anda saat pengajuan yang Anda buat dibatalkan.',
        };
    }
}
