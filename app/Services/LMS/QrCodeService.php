<?php

namespace App\Services\LMS;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    protected string $directory = 'qr/attendance';

    public function generateQrPath(string $meetingId): string
    {
        $filename = "meeting_{$meetingId}.png";
        $path = "{$this->directory}/{$filename}";

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        if (! Storage::disk('public')->exists($this->directory)) {
            Storage::disk('public')->makeDirectory($this->directory);
        }

        $url = route('lms.attendance.clock-in', ['meetingId' => $meetingId]);

        $qrImage = QrCode::format('png')
            ->size(400)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($url);

        Storage::disk('public')->put($path, $qrImage);

        return $path;
    }

    public function getQrUrl(string $meetingId): string
    {
        $path = $this->generateQrPath($meetingId);
        return asset('storage/' . $path);
    }
}
