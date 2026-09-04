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
    case SIKARTAP_DIRECTOR_DECISION = 'sikartap_director_decision';
    case SARPRAS_REQUEST = 'sarpras_request';
    case ORIENTATION_REQUEST = 'orientation_request';

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
            self::TRAINING_REQUEST => 'Pengajuan Pelatihan',
            self::SIKARTAP_DIRECTOR_DECISION => 'Keputusan Direktur - Karyawan Tetap (Sikartap)',
            self::SARPRAS_REQUEST => 'Permohonan Sarana & Prasarana',
            self::ORIENTATION_REQUEST => 'Penilaian Orientasi Karyawan',
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
            self::SIKARTAP_DIRECTOR_DECISION => 'Keputusan direktur pengajuan karyawan tetap',
            self::SARPRAS_REQUEST => 'Pengajuan sarana & prasarana',
            self::ORIENTATION_REQUEST => 'Pengajuan penilaian orientasi',
        };
    }

    /** @return array<string, string> value => label, buat dropdown Filament. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
