<?php

namespace App\Services\LMS;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    protected string $directory = 'qr/attendance';

    public function generateQrPath(string $meetingId): string
    {
        $url = route('lms.attendance.clock-in', ['meetingId' => $meetingId]);

        return $this->cachedQrPath($this->directory, "meeting_{$meetingId}.svg", $url);
    }

    public function getQrUrl(string $meetingId): string
    {
        $path = $this->generateQrPath($meetingId);
        return asset('storage/' . $path);
    }

    public function getQuizQrUrl(string $meetingQuizId, string $token): string
    {
        $url = route('lms.public.quiz', ['token' => $token]);
        $path = $this->cachedQrPath('qr/quiz', "meeting_quiz_{$meetingQuizId}.svg", $url);

        return asset('storage/' . $path);
    }

    public function getFeedbackQrUrl(string $courseId, string $token): string
    {
        $url = route('lms.public.feedback', ['token' => $token]);
        $path = $this->cachedQrPath('qr/feedback', "course_{$courseId}.svg", $url);

        return asset('storage/' . $path);
    }

    private function cachedQrPath(string $directory, string $filename, string $url): string
    {
        $path = "{$directory}/{$filename}";

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // ponytail: SVG bukan PNG — BaconQrCode cuma render PNG lewat Imagick (tidak terinstall
        // di server ini), sedangkan SVG murni XML tanpa perlu ext-imagick/GD sama sekali.
        $qrImage = QrCode::format('svg')
            ->size(400)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($url);

        Storage::disk('public')->put($path, $qrImage);

        return $path;
    }
}
