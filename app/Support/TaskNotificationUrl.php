<?php

namespace App\Support;

use App\Models\Task\TaskProject;

/** Tujuan klik notifikasi tugas di dalam aplikasi (lonceng & halaman Notifikasi) - sejalan dengan public/sw.js untuk push. */
class TaskNotificationUrl
{
    public static function for(array $data): ?string
    {
        // SP terbit -> langsung ke halaman detail SP milik karyawan sendiri (instruksi user
        // 2026-09-25). Dicek duluan, bukan cuma notifikasi task yang class ini urus namanya.
        if (! empty($data['employee_sp_id'])) {
            return route('sp.saya.show', $data['employee_sp_id']);
        }

        // Pengumuman admin -> halaman detail pengumuman.
        if (! empty($data['announcement_id'])) {
            return route('pengumuman.show', $data['announcement_id']);
        }

        // Notifikasi pelatihan (diikutkan/disetujui/baru tersedia) -> halaman detail pelatihan.
        if (! empty($data['course_id'])) {
            return route('pelatihan.show', $data['course_id']);
        }

        // Notifikasi "Menunggu Tanda Tangan Anda" (TTE Fase 1) -> halaman tanda tangan generik
        // (routes/documents.php), BUKAN sp.saya.show (itu cuma buat pemilik dokumen sendiri).
        if (! empty($data['document_sign_id'])) {
            return route('documents.sign', $data['document_sign_id']);
        }

        // Undangan (klarifikasi, coaching, dst.) -> halaman baca-saja undangan.
        if (! empty($data['invitation_url'])) {
            return $data['invitation_url'];
        }

        // Perkembangan Berita Acara (non-tanda-tangan) -> URL halaman BA yang relevan untuk penerimanya.
        if (! empty($data['sp_report_url'])) {
            return $data['sp_report_url'];
        }

        if (($data['request_type'] ?? null) !== 'task') {
            return null;
        }

        // Tugas/penilaian/undangan di Board -> langsung ke halaman Board-nya (kalau Board masih ada).
        // Diskusi Board -> langsung ke tab Diskusi; notifikasi kartu -> kartu langsung terbuka (?task=).
        $query = ! empty($data['discussion'])
            ? ['boardFilter' => 'diskusi']
            : (! empty($data['task_id']) ? ['task' => $data['task_id']] : []);

        if (! empty($data['project_id'])) {
            return TaskProject::whereKey($data['project_id'])->exists()
                ? route('tugas.papan.board', ['project' => $data['project_id']] + $query)
                : route('tugas');
        }

        return route('tugas.papan', ['scope' => 'saya'] + $query);
    }
}
