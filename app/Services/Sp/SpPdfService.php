<?php

namespace App\Services\Sp;

use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpReport;
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
        $pdf = Pdf::loadView('pdf.sp.sp', ['sp' => $this->loadForPdf($sp)]);

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf->stream();
            },
            $this->filename($sp),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Beda dari download() — dompdf stream() defaultnya Content-Disposition: inline (buka di
     * tab baru/PDF viewer browser), TIDAK dibungkus response()->streamDownload() yang selalu
     * memaksa attachment. Dipakai tombol "Preview" (link biasa target=_blank via route, BUKAN
     * aksi Livewire — Livewire selalu memaksa download apa pun response-nya). Pola persis
     * PayrollService::preview().
     */
    public function preview(EmployeeSp $sp)
    {
        $pdf = Pdf::loadView('pdf.sp.sp', ['sp' => $this->loadForPdf($sp)]);

        return $pdf->stream($this->filename($sp));
    }

    private function loadForPdf(EmployeeSp $sp): EmployeeSp
    {
        $sp->loadMissing([
            'company',
            'items',
            'user.activeJobPosition.jobPosition',
            'createdBy.activeJobPosition.jobPosition',
            'knowsBy.activeJobPosition.jobPosition',
            'coaching.coachBy.activeJobPosition.jobPosition',
        ]);

        return $sp;
    }

    private function filename(EmployeeSp $sp): string
    {
        $level = strtoupper($sp->sp_level->value);
        $identity = $sp->user->nip ?: $sp->user_id;
        $name = str_replace(' ', '_', $sp->user->name);

        return "{$level}-{$identity}_{$name}.pdf";
    }

    public function previewBeritaAcara(EmployeeSp $sp)
    {
        $pdf = Pdf::loadView('pdf.sp.berita-acara', ['sp' => $this->loadForBeritaAcaraPdf($sp)]);

        return $pdf->stream($this->filename($sp));
    }

     private function loadForBeritaAcaraPdf(EmployeeSp $sp): EmployeeSp
    {
        $sp->loadMissing([
            'company',
            'report',
            'user.profile',
            'user.activeJobPosition.jobPosition.orgStructure.parent',
            'createdBy.activeJobPosition.jobPosition',
            'knowsBy.activeJobPosition.jobPosition',
            'coaching.coachBy.activeJobPosition.jobPosition',
        ]);

        return $sp;
    }

    private function filenameBeritaAcara(EmployeeSp $sp): string
    {
        $level = strtoupper($sp->sp_level->value);
        $identity = $sp->user->nip ?: $sp->user_id;
        $name = str_replace(' ', '_', $sp->user->name);

        return "beritaacara-{$level}-{$identity}_{$name}.pdf";

    }
}
