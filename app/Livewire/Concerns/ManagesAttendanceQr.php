<?php

namespace App\Livewire\Concerns;

use App\Services\LMS\MeetingQrAttendanceService;
use Illuminate\Support\HtmlString;

/**
 * Pilih metode absensi (manual/QR) per pertemuan + modal QR besar masuk/pulang.
 * Dipakai halaman admin LMS dan halaman pelatihan (pemateri). Butuh $this->course.
 */
trait ManagesAttendanceQr
{
    public ?string $qrMeetingId = null;
    public ?string $qrType = null;

    abstract protected function canManageAttendanceQr(): bool;

    public function setAttendanceMethod(string $meetingId, string $method): void
    {
        abort_unless($this->canManageAttendanceQr() && in_array($method, ['manual', 'qr'], true), 403);

        $this->course->meetings->firstWhere('id', $meetingId)?->update(['attendance_method' => $method]);
    }

    public function showAttendanceQr(string $meetingId, string $type): void
    {
        abort_unless($this->canManageAttendanceQr() && in_array($type, MeetingQrAttendanceService::TYPES, true), 403);

        // QR hanya berfungsi untuk pelatihan yang sudah dipublish (scan ditolak kalau belum).
        if ($this->course->status !== \App\Enum\LsmCourseStatus::Published->value) {
            \Filament\Notifications\Notification::make()->title('Publish pelatihan dulu sebelum menampilkan QR absensi.')->warning()->send();

            return;
        }

        $meeting = $this->course->meetings->firstWhere('id', $meetingId);
        abort_unless($meeting && $meeting->attendance_method === 'qr', 404);

        $this->qrMeetingId = $meetingId;
        $this->qrType = $type;
    }

    public function closeAttendanceQr(): void
    {
        $this->qrMeetingId = $this->qrType = null;
    }

    public function getAttendanceQrSvgProperty(): ?HtmlString
    {
        $meeting = $this->qrMeetingId ? $this->course->meetings->firstWhere('id', $this->qrMeetingId) : null;

        return $meeting ? app(MeetingQrAttendanceService::class)->svg($meeting, $this->qrType) : null;
    }
}
