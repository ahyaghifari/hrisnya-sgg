<?php

namespace App\Services\LMS;

use App\Models\LMS\LmsRespondent;
use App\Models\User;

class RespondentService
{
    /**
     * Karyawan login: 1 baris respondent per (course, user, category), dipakai ulang lintas
     * kuis/umpan balik dalam kategori yang SAMA di course yang sama. Snapshot nama/nip/company
     * dari data user saat ini.
     */
    public function resolveForEmployee(string $userId, string $courseId, string $category = LmsRespondent::CATEGORY_KUIS): LmsRespondent
    {
        $user = User::findOrFail($userId);

        return LmsRespondent::firstOrCreate(
            ['course_id' => $courseId, 'user_id' => $userId, 'category' => $category],
            [
                'name' => $user->name,
                'nip' => $user->nip,
                'company_id' => $user->company_id,
            ]
        );
    }

    /**
     * Publik (tanpa login): NIP yang diisi dicocokkan ke users.nip cuma untuk link informasional
     * (user_id) — tidak pernah menimpa isian manual dengan data karyawan yang match (§3.1).
     * Kalau NIP-nya cocok ke user yang sama & category yang sama, baris DIPAKAI ULANG (bukan
     * baris baru) — bukan supaya "dedup" secara sengaja, tapi karena unique(course_id, user_id,
     * category) bakal nabrak kalau tetap dipaksa insert baru; efeknya sama seperti "1 attempt per
     * kategori" yang memang sudah berlaku di tabel hasil kuis (unique meeting_quiz_id+respondent_id).
     * NIP yang tidak cocok siapa pun (user_id null) selalu jadi baris baru — NULL tidak pernah
     * tabrakan di unique index, jadi banyak tamu anonim boleh submit bebas.
     */
    public function resolveForPublic(string $courseId, array $data, string $category = LmsRespondent::CATEGORY_KUIS): LmsRespondent
    {
        $matchedUserId = ! empty($data['nip'])
            ? User::whereNip((string) $data['nip'])->value('id')
            : null;

        $snapshot = [
            'name' => $data['name'],
            'nip' => $data['nip'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'unit' => $data['unit'] ?? null,
        ];

        if ($matchedUserId) {
            return LmsRespondent::firstOrCreate(
                ['course_id' => $courseId, 'user_id' => $matchedUserId, 'category' => $category],
                $snapshot
            );
        }

        return LmsRespondent::create([
            'course_id' => $courseId,
            'user_id' => null,
            'category' => $category,
            ...$snapshot,
        ]);
    }
}
