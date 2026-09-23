<?php

namespace App\Services\Sp;

use App\Models\Sp\EmployeeSp;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cetak SP jadi PDF (instruksi user 2026-09-25) - pola persis PayrollService::download() (kop
 * surat = gambar background privat per company, storage/app/private/sp-bg/{code}.jpg, fallback
 * default.jpg, di-embed base64 di resources/views/pdf/sp/sp.blade.php).
 */
class SpPdfService
{
    public function download(EmployeeSp $sp): StreamedResponse
    {
        $sp->loadMissing([
            'company',
            'items',
            'user.activeJobPosition.jobPosition',
            'createdBy.activeJobPosition.jobPosition',
            'knowsBy.activeJobPosition.jobPosition',
            'coaching.coachBy.activeJobPosition.jobPosition',
        ]);

        $pdf = Pdf::loadView('pdf.sp.sp', ['sp' => $sp]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            $this->filename($sp),
            ['Content-Type' => 'application/pdf']
        );
    }

    private function filename(EmployeeSp $sp): string
    {
        $level = strtoupper($sp->sp_level->value);
        $identity = $sp->user->nip ?: $sp->user_id;
        $name = str_replace(' ', '_', $sp->user->name);

        return "{$level}-{$identity}_{$name}.pdf";
    }
}
