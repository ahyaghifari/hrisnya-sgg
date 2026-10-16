<?php

namespace App\Services\LMS;

use App\Enum\CourseUserStatus;
use App\Enum\LsmCourseStatus;
use App\Models\LMS\LmsCourseMeetingUsers;
use App\Models\LMS\LmsCourseUsers;
use App\Models\LMS\LmsMeetings;
use App\Models\User;
use Illuminate\Support\HtmlString;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Absensi QR per pertemuan. Token = "{meetingId}.{masuk|pulang}.{hmac}" — ditandatangani dengan
 * APP_KEY, jadi tidak ada kolom token yang perlu disimpan; tiap pertemuan & jenis punya kode sendiri.
 */
class MeetingQrAttendanceService
{
    public const TYPES = ['masuk', 'pulang'];

    public function token(LmsMeetings $meeting, string $type): string
    {
        return "{$meeting->id}.{$type}." . $this->sign($meeting->id, $type);
    }

    public function svg(LmsMeetings $meeting, string $type): HtmlString
    {
        return new HtmlString((string) QrCode::format('svg')->size(640)->margin(1)->errorCorrection('M')->generate($this->token($meeting, $type)));
    }

    /** Catat absensi (dipanggil setelah peserta konfirmasi). @return array{ok: bool, message: string} */
    public function scan(User $user, string $token): array
    {
        $check = $this->inspect($user, $token);

        if (! $check['ok']) {
            return $check;
        }

        ['meeting' => $meeting, 'type' => $type] = $check;
        $column = $type === 'masuk' ? 'absen_masuk' : 'absen_pulang';
        $row = LmsCourseMeetingUsers::firstOrNew(['course_id' => $meeting->course_id, 'meeting_id' => $meeting->id, 'user_id' => $user->id]);
        $label = "Absen {$type} — {$meeting->title}";

        if ($row->{$column}) {
            return ['ok' => true, 'message' => "{$label} sudah tercatat sebelumnya."];
        }

        $row->{$column} = true;
        $row->save();

        return ['ok' => true, 'message' => "{$label} berhasil dicatat."];
    }

    /** Validasi token + hak peserta tanpa menulis apa pun. @return array{ok: bool, message?: string, meeting?: LmsMeetings, type?: string} */
    public function inspect(User $user, string $token): array
    {
        [$meetingId, $type, $sig] = array_pad(explode('.', trim($token)), 3, '');

        if (! in_array($type, self::TYPES, true) || ! hash_equals($this->sign($meetingId, $type), $sig)) {
            return $this->fail('QR tidak valid.');
        }

        $meeting = LmsMeetings::with('course')->find($meetingId);

        if (! $meeting || $meeting->attendance_method !== 'qr' || $meeting->course?->status !== LsmCourseStatus::Published->value) {
            return $this->fail('Absensi QR untuk pertemuan ini tidak aktif.');
        }

        $participant = LmsCourseUsers::where('course_id', $meeting->course_id)->where('user_id', $user->id)->first();

        if (! $participant || in_array($participant->status, [CourseUserStatus::REQUESTED->value, CourseUserStatus::CANCELED->value], true)) {
            return $this->fail('Anda bukan peserta terdaftar pelatihan ini.');
        }

        $today = now()->toDateString();
        $start = $meeting->start_date ?? $meeting->meeting_date;
        $end = $meeting->end_date ?? $start;

        if ($start && ($today < substr((string) $start, 0, 10) || $today > substr((string) $end, 0, 10))) {
            return $this->fail('Absensi hanya bisa dilakukan pada hari pertemuan.');
        }

        return ['ok' => true, 'meeting' => $meeting, 'type' => $type];
    }

    private function sign(string $meetingId, string $type): string
    {
        return substr(hash_hmac('sha256', "{$meetingId}|{$type}", config('app.key')), 0, 16);
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
