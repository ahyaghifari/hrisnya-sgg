<?php

namespace App\Enum\Approval;

enum ApprovalModuleEnum: string
{
    case ATTENDANCE_REQUEST = 'attendance_request';
    case TASK_REQUEST = 'task_request';
    case JOBPOSITION_REQUEST = 'jobposition_request';
    case APPROVAL_CANDIDATE_APPLICANT = 'approval_candidate_applicant';
    case USER_TRANSITION = 'user_transition';
    case SWAP_SHIFT_REQUEST = 'swap_shift_request';
    case ADDITIONAL_EMPLOYEE_REQUEST = 'additional_employee_request';
    case EMPLOYEE_REPLACEMENT_REQUEST = 'employee_replacement_request';
    case ADDITIONAL_QUOTA_REQUEST = 'additional_quota_request';
    case TRAINING_REQUEST = 'training_request';
    case ATTENDANCE_REQUEST_CANCELLATION = 'attendance_request_cancellation';
    case SHIFT_SWAP_REQUEST_CANCELLATION = 'shift_swap_request_cancellation';
    case SCHEDULE_ROSTER_CHANGE = 'schedule_roster_change';
    case TRAINING_CLAIM = 'training_claim';
    case TRAINING_ABSENCE = 'training_absence';

    /** Label deskriptif — dropdown & kolom "Modul" di /admin/approval-flows. */
    public function label(): string
    {
        return match ($this) {
            self::ATTENDANCE_REQUEST => 'Permintaan Izin, Cuti, Lembur',
            self::TASK_REQUEST => 'Permintaan Penugasan Individu',
            self::JOBPOSITION_REQUEST => 'Penambahan/Perubahan Jabatan',
            self::APPROVAL_CANDIDATE_APPLICANT => 'Persetujuan Pelamar Kerja',
            self::USER_TRANSITION => 'Perubahan Status Karyawan',
            self::SWAP_SHIFT_REQUEST => 'Permintaan Tukar Shift',
            self::ADDITIONAL_EMPLOYEE_REQUEST => 'Permintaan Penambahan Karyawan',
            self::EMPLOYEE_REPLACEMENT_REQUEST => 'Permintaan Penggantian Karyawan',
            self::ADDITIONAL_QUOTA_REQUEST => 'Permintaan Penambahan Kuota',
            self::TRAINING_REQUEST => 'Pengajuan Pelatihan (Belum Terlaksana)',
            self::ATTENDANCE_REQUEST_CANCELLATION => 'Pembatalan Cuti/Izin',
            self::SHIFT_SWAP_REQUEST_CANCELLATION => 'Pembatalan Tukar Shift',
            self::SCHEDULE_ROSTER_CHANGE => 'Perubahan Jadwal Shift (Kepala Ruangan)',
            self::TRAINING_CLAIM => 'Pengajuan Pelatihan (Sudah Terlaksana)',
            self::TRAINING_ABSENCE => 'Izin Tidak Hadir Pelatihan',
        };
    }

    /** Label ringkas — badan notifikasi ("{label} dari X memerlukan persetujuan Anda"). */
    public function notificationLabel(): string
    {
        return match ($this) {
            self::ATTENDANCE_REQUEST => 'Pengajuan absensi',
            self::TASK_REQUEST => 'Pengajuan tugas',
            self::JOBPOSITION_REQUEST => 'Pengajuan jabatan',
            self::APPROVAL_CANDIDATE_APPLICANT => 'Persetujuan pelamar kerja',
            self::USER_TRANSITION => 'Pengajuan perubahan status karyawan',
            self::SWAP_SHIFT_REQUEST => 'Pengajuan tukar shift',
            self::ADDITIONAL_EMPLOYEE_REQUEST => 'Pengajuan penambahan karyawan',
            self::EMPLOYEE_REPLACEMENT_REQUEST => 'Pengajuan penggantian karyawan',
            self::ADDITIONAL_QUOTA_REQUEST => 'Pengajuan penambahan kuota',
            self::TRAINING_REQUEST => 'Pengajuan pelatihan',
            self::ATTENDANCE_REQUEST_CANCELLATION => 'Pengajuan pembatalan cuti/izin',
            self::SHIFT_SWAP_REQUEST_CANCELLATION => 'Pengajuan pembatalan tukar shift',
            self::SCHEDULE_ROSTER_CHANGE => 'Pengajuan perubahan jadwal shift',
            self::TRAINING_CLAIM => 'Pengajuan pelatihan terlaksana',
            self::TRAINING_ABSENCE => 'Pengajuan izin tidak hadir pelatihan',
        };
    }

    /** @return array<string, string> value => label, buat dropdown Filament. */
    public static function options(): array
    {
        // Pembatalan tukar shift tidak punya alur master: approver-nya Kepala Ruangan jadwal
        // (lihat ShiftSwapCancellationService), jadi tidak ditawarkan di pengaturan alur.
        return collect(self::cases())
            ->reject(fn (self $case) => in_array($case, [self::SHIFT_SWAP_REQUEST_CANCELLATION, self::TRAINING_ABSENCE], true))
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
