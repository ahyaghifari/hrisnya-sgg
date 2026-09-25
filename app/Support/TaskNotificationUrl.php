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

        if (($data['request_type'] ?? null) !== 'task') {
            return null;
        }

        // Tugas/penilaian/undangan di Board -> langsung ke halaman Board-nya (kalau Board masih ada).
        if (! empty($data['project_id'])) {
            return TaskProject::whereKey($data['project_id'])->exists()
                ? route('tugas.papan.board', $data['project_id'])
                : route('tugas');
        }

        return route('tugas.papan', 'saya');
    }
}
