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

    // Pembinaan Karyawan — lihat App\Services\Coaching\CoachingService, App\Services\Counseling\CounselingService.
    case COUNSELING_RECEIVED = 'counseling_received';
    case COACHING_ASSIGNED = 'coaching_assigned';

    // SP resmi terbit (approve sp_recommendations) — lihat App\Services\Sp\SpService.
    case SP_ISSUED = 'sp_issued';

    // Absensi otomatis dari access_logs (mesin absensi) — lihat
    // App\Console\Commands\SyncAccessLogsAttendance.
    case ATTENDANCE_CHECKED_IN = 'attendance_checked_in';
    case ATTENDANCE_CHECKED_OUT = 'attendance_checked_out';
    case ATTENDANCE_SCAN_REJECTED = 'attendance_scan_rejected';

    // Ke rekan (target_id) yang jadwalnya mau ditukar, kalau setting minta tanggapan diaktifkan -
    // lihat App\Services\Shift\ShiftSwapRequestService. Opsional, TIDAK menggerbang approval.
    case SHIFT_SWAP_TARGET_RESPONSE_REQUESTED = 'shift_swap_target_response_requested';

    // Ke semua karyawan aktif, saat pelatihan LMS baru di-publish — lihat
    // App\Filament\Resources\LMS\LmsCourses\Pages\CreateLmsCourses / EditLmsCourses.
    case COURSE_PUBLISHED = 'course_published';

    // Ke peserta, saat pendaftarannya ke sebuah pelatihan disetujui approver — lihat
    // App\Services\LMS\CourseUserService::approveEnrollment().
    case COURSE_ENROLLMENT_APPROVED = 'course_enrollment_approved';

    // Ke peserta yang DITAMBAHKAN admin (bukan self-enroll) ke pelatihan portal, saat
    // pelatihannya dipublish — lihat App\Livewire\LMS\LmsCourseDetailPage::publishCourse().
    // Beda dari COURSE_PUBLISHED yang broadcast ke SEMUA karyawan aktif tanpa peduli status
    // keanggotaan (jalur admin Filament, belum ada peserta terdaftar saat publish di sana).
    case COURSE_PARTICIPANT_ENROLLED = 'course_participant_enrolled';

    // Ke karyawan/pelamar, saat 1 dokumen administratif pengajuan karyawan tetap ditolak HRD —
    // lihat App\Notifications\Sikartap\SikartapDocumentRejected. Sengaja HANYA reject (bukan
    // approve juga) - approve per dokumen tidak actionable buat karyawan (masih nunggu dokumen
    // lain), reject butuh reupload segera (instruksi user 2026-08-26).
    case SIKARTAP_DOCUMENT_REJECTED = 'sikartap_document_rejected';
    case SIKARTAP_BERKAS_STATUS_CHANGED = 'sikartap_berkas_status_changed';
    case SIKARTAP_JADWAL_TES = 'sikartap_jadwal_tes';
    case SIKARTAP_HASIL_TES = 'sikartap_hasil_tes';
    case SIKARTAP_JADWAL_WAWANCARA = 'sikartap_jadwal_wawancara';
    case SIKARTAP_HASIL_WAWANCARA = 'sikartap_hasil_wawancara';
    case SIKARTAP_HASIL_MCU = 'sikartap_hasil_mcu';
    case SIKARTAP_IDE_INOVASI_DIREVIEW = 'sikartap_ide_inovasi_direview';
    case SIKARTAP_KEPUTUSAN_DIREKTUR = 'sikartap_keputusan_direktur';

    // Orientasi
    case ORIENTATION_REVIEWED = 'orientation_reviewed';
    case ORIENTATION_REVIEW_REQUESTED = 'orientation_review_requested';
    case ORIENTATION_REVISION_REQUESTED = 'orientation_revision_requested';
    case ORIENTATION_PRESENTATION_SCHEDULED = 'orientation_presentation_scheduled';
    case ORIENTATION_PRESENTATION_APPROVAL_REQUESTED = 'orientation_presentation_approval_requested';
    case CONTRACT_REVIEW_REQUESTED = 'contract_review_requested';
    case CONTRACT_REVIEWED = 'contract_reviewed';
    case CONTRACT_REVISION_REQUESTED = 'contract_revision_requested';

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
            self::SP_ISSUED => 'SP Saya Terbit',
            self::ATTENDANCE_CHECKED_IN => 'Absensi Masuk Tercatat',
            self::ATTENDANCE_CHECKED_OUT => 'Absensi Pulang Tercatat',
            self::ATTENDANCE_SCAN_REJECTED => 'Absensi Saya Tidak Diproses',
            self::SHIFT_SWAP_TARGET_RESPONSE_REQUESTED => 'Diminta Tanggapan Tukar Shift',
            self::COURSE_PUBLISHED => 'Pelatihan Baru Tersedia',
            self::COURSE_ENROLLMENT_APPROVED => 'Pendaftaran Pelatihan Saya Disetujui',
            self::COURSE_PARTICIPANT_ENROLLED => 'Diikutkan ke Pelatihan',
            self::SIKARTAP_DOCUMENT_REJECTED => 'Dokumen Pengajuan Karyawan Tetap Saya Ditolak',
            self::SIKARTAP_BERKAS_STATUS_CHANGED => 'Berkas Pengajuan Karyawan Tetap Saya Diputuskan',
            self::SIKARTAP_JADWAL_TES => 'Jadwal Tes Karyawan Tetap Saya Ditetapkan',
            self::SIKARTAP_HASIL_TES => 'Hasil Tes Karyawan Tetap Saya Keluar',
            self::SIKARTAP_JADWAL_WAWANCARA => 'Jadwal Wawancara Karyawan Tetap Saya Ditetapkan',
            self::SIKARTAP_HASIL_WAWANCARA => 'Hasil Wawancara Karyawan Tetap Saya Keluar',
            self::SIKARTAP_HASIL_MCU => 'Hasil MCU Karyawan Tetap Saya Keluar',
            self::SIKARTAP_IDE_INOVASI_DIREVIEW => 'Ide Inovasi Saya Dinilai',
            self::SIKARTAP_KEPUTUSAN_DIREKTUR => 'Keputusan Direktur atas Pengajuan Karyawan Tetap Saya',
            self::ORIENTATION_REVIEWED => 'Penilaian Orientasi Saya Selesai',
            self::ORIENTATION_REVIEW_REQUESTED => 'Pengajuan Orientasi Perlu Dinilai',
            self::ORIENTATION_REVISION_REQUESTED => 'Bukti Orientasi Saya Diminta Revisi',
            self::ORIENTATION_PRESENTATION_SCHEDULED => 'Jadwal Presentasi Orientasi Saya Ditetapkan',
            self::ORIENTATION_PRESENTATION_APPROVAL_REQUESTED => 'Jadwal Presentasi Orientasi Perlu Persetujuan Anda',
            self::CONTRACT_REVIEW_REQUESTED => 'Pengajuan Perpanjangan Kontrak Perlu Dinilai',
            self::CONTRACT_REVIEWED => 'Perpanjangan Kontrak Saya Diputuskan',
            self::CONTRACT_REVISION_REQUESTED => 'Dokumen Perpanjangan Kontrak Saya Diminta Revisi',
        };
    }

    /** Dipakai buat mengelompokkan halaman Pengaturan Notifikasi — bukan kategori "resmi" di
     * model manapun, cuma pengelompokan tampilan. */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'approval_'), str_starts_with($this->value, 'attendance_'), str_starts_with($this->value, 'shift_swap_') => 'Absensi',
            str_starts_with($this->value, 'task_') => 'Tugas',
            str_starts_with($this->value, 'payroll_') => 'Penggajian',
            str_starts_with($this->value, 'counseling_'), str_starts_with($this->value, 'coaching_'), str_starts_with($this->value, 'sp_') => 'Pembinaan Karyawan',
            str_starts_with($this->value, 'course_') => 'Pelatihan',
            str_starts_with($this->value, 'sikartap_') => 'Sikartap',
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
            self::TASK_COMMENTED => 'Dikirim saat ada komentar baru di tugas yang Anda kerjakan.',
            self::TASK_PROJECT_MEMBER_ADDED => 'Dikirim saat Anda dimasukkan sebagai anggota sebuah project tugas.',
            self::TASK_REVIEW_REQUESTED => 'Dikirim ke pembuat tugas saat pelaksana mengajukan tugas untuk direview (kalau dicentang saat bikin tugas).',
            self::TASK_COMPLETED_BY_ASSIGNEE => 'Dikirim ke pembuat tugas saat pelaksana menandai tugas selesai tanpa review (kalau dicentang saat bikin tugas).',
            self::PAYROLL_PAID => 'Dikirim saat payroll Anda ditandai sudah dibayar oleh finance.',
            self::COUNSELING_RECEIVED => 'Dikirim saat ada karyawan yang menunjuk Anda sebagai konselor untuk konsul.',
            self::COACHING_ASSIGNED => 'Dikirim saat atasan/HC membuat sesi coaching baru untuk Anda.',
            self::SP_ISSUED => 'Dikirim saat HR menyetujui rekomendasi dan SP Anda resmi terbit.',
            self::ATTENDANCE_CHECKED_IN => 'Dikirim saat mesin absensi mencatat Anda check-in.',
            self::ATTENDANCE_CHECKED_OUT => 'Dikirim saat mesin absensi mencatat Anda check-out.',
            self::ATTENDANCE_SCAN_REJECTED => 'Dikirim saat scan absensi Anda tidak bisa diproses sistem (mis. jadwal tidak ditemukan), beserta alasannya.',
            self::SHIFT_SWAP_TARGET_RESPONSE_REQUESTED => 'Dikirim saat rekan kerja mengajukan tukar shift dengan jadwal Anda - tanggapan Anda opsional, tidak menghambat proses persetujuan atasan.',
            self::COURSE_PUBLISHED => 'Dikirim ke Anda saat ada pelatihan LMS baru yang di-publish dan bisa Anda ikuti.',
            self::COURSE_ENROLLMENT_APPROVED => 'Dikirim ke Anda saat pendaftaran Anda ke sebuah pelatihan disetujui approver.',
            self::COURSE_PARTICIPANT_ENROLLED => 'Dikirim ke Anda saat admin menambahkan Anda sebagai peserta pelatihan yang baru dipublish.',
            self::SIKARTAP_DOCUMENT_REJECTED => 'Dikirim saat HRD menolak salah satu dokumen administratif pengajuan karyawan tetap Anda, beserta catatan alasannya.',
            self::SIKARTAP_BERKAS_STATUS_CHANGED => 'Dikirim saat HRD menyetujui atau menolak keseluruhan berkas administratif pengajuan karyawan tetap Anda.',
            self::SIKARTAP_JADWAL_TES => 'Dikirim saat HRD menetapkan jadwal tes bidang/psikotes Anda.',
            self::SIKARTAP_HASIL_TES => 'Dikirim saat hasil tes bidang/psikotes Anda diinput HRD.',
            self::SIKARTAP_JADWAL_WAWANCARA => 'Dikirim saat HRD menetapkan jadwal wawancara Anda.',
            self::SIKARTAP_HASIL_WAWANCARA => 'Dikirim saat hasil wawancara Anda diinput HRD.',
            self::SIKARTAP_HASIL_MCU => 'Dikirim saat keputusan hasil MCU (fit/unfit) Anda diinput HRD.',
            self::SIKARTAP_IDE_INOVASI_DIREVIEW => 'Dikirim saat ide inovasi Anda dinilai atasan, direktur, atau komisaris.',
            self::SIKARTAP_KEPUTUSAN_DIREKTUR => 'Dikirim saat Direktur memutuskan pengajuan karyawan tetap Anda (lulus/tidak lulus).',
            self::ORIENTATION_REVIEWED => 'Dikirim saat penilai memutuskan pengajuan orientasi Anda (direkomendasikan untuk kontrak kerja / tidak dilanjutkan).',
            self::ORIENTATION_REVIEW_REQUESTED => 'Dikirim ke penilai saat ada karyawan orientasi mengajukan penilaian.',
            self::ORIENTATION_REVISION_REQUESTED => 'Dikirim saat penilai mengembalikan pengajuan orientasi Anda untuk diperbaiki, beserta catatannya.',
            self::ORIENTATION_PRESENTATION_SCHEDULED => 'Dikirim saat jadwal presentasi kegiatan orientasi Anda sudah disetujui HR & jabatan pendukung.',
            self::ORIENTATION_PRESENTATION_APPROVAL_REQUESTED => 'Dikirim ke pemegang jabatan pendukung saat ada jadwal presentasi orientasi yang perlu disetujui.',
            self::CONTRACT_REVIEW_REQUESTED => 'Dikirim ke penilai saat ada karyawan mengajukan perpanjangan kontrak.',
            self::CONTRACT_REVIEWED => 'Dikirim saat penilai memutuskan pengajuan perpanjangan kontrak Anda (perpanjang / tidak diperpanjang).',
            self::CONTRACT_REVISION_REQUESTED => 'Dikirim saat penilai mengembalikan pengajuan perpanjangan kontrak Anda untuk melengkapi dokumen, beserta catatannya.',
        };
    }
}
