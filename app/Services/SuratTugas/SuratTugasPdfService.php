<?php

namespace App\Services\SuratTugas;

use App\Models\SuratTugas\SuratTugas;
use App\Services\Document\DocumentFinalizer;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SuratTugasPdfService
{
    /** Kode company rumah sakit (RSU Syifa Medika Banjarbaru & Barabai) - format surat sendiri. */
    private const RUMAH_SAKIT_CODES = ['0001', '0002'];

    /** PDF final untuk disimpan DocumentFinalizer begitu TTD masuk. */
    public function renderFinal(SuratTugas $surat): string
    {
        return $this->pdf($surat)->output();
    }

    /**
     * Preview inline di browser (link biasa target=_blank, bukan aksi Livewire - Livewire selalu
     * memaksa download). Dokumen yang sudah final menyajikan file tersimpannya; draft/menunggu
     * TTD dirender live tanpa QR.
     */
    public function preview(SuratTugas $surat): Response
    {
        $surat->loadMissing('document');
        $final = $surat->document ? app(DocumentFinalizer::class)->contents($surat->document) : null;

        $contents = $final ?? $this->pdf($surat)->output();

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Surat-Perintah-Tugas.pdf"',
        ]);
    }

    private function pdf(SuratTugas $surat): \Barryvdh\DomPDF\PDF
    {
        $surat->loadMissing(['peserta.user.activeJobPosition.jobPosition', 'periode', 'company', 'ttdUser', 'document.signatures.verification']);

        $signature = $surat->document?->signatures->firstWhere('signer_role', SuratTugas::ROLE_PEMBERI_TUGAS);

        // Satu dokumen 2 halaman: Surat Perintah Tugas + Surat Perintah Perjalanan Dinas.
        // Template default = holding; hanya rumah sakit yang punya format sendiri.
        $view = in_array($surat->company?->code, self::RUMAH_SAKIT_CODES, true)
            ? 'pdf.surat-tugas.rumah-sakit'
            : 'pdf.surat-tugas.holding';

        return Pdf::loadView($view, [
            'surat' => $surat,
            'verification' => $this->verification($signature?->verification?->verification_code, $this->logoFile($surat)),
            // Tanggal surat = tanggal tanda tangan; sebelum ditandatangani (preview) = hari ini.
            'tanggalSurat' => $signature?->signed_at ?? now(),
            'bgPath' => $this->kopDataUri($surat),
        ]);
    }

    /**
     * Kop surat: gambar background privat per company, pola sama dengan SP
     * (storage/app/private/surat-bg/{kode company}.jpg, fallback default.jpg), di-embed base64.
     */
    private function kopDataUri(SuratTugas $surat): string
    {
        $dir = storage_path('app/private/surat-bg/');
        $file = $dir . ($surat->company?->code ?? '') . '.jpg';

        if (! is_file($file)) {
            $file = $dir . 'default.jpg';
        }

        return is_file($file) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($file)) : '';
    }

    /** Berkas logo perusahaan (disk public, kolom companies.logo), null kalau belum ada. */
    private function logoFile(SuratTugas $surat): ?string
    {
        $path = $surat->company?->logo;
        $file = $path ? Storage::disk('public')->path($path) : null;

        return $file && is_file($file) ? $file : null;
    }

    /** @return ?array{code: string, qr: string} QR sebagai PNG data URI (logo sudah menyatu di tengah). */
    private function verification(?string $code, ?string $logoFile): ?array
    {
        if (! $code) {
            return null;
        }

        return [
            'code' => $code,
            'qr' => 'data:image/png;base64,' . base64_encode($this->qrPng(route('public.verify', ['code' => $code]), $logoFile)),
        ];
    }

    /**
     * QR level koreksi H dengan logo perusahaan di tengah, digambar dengan GD jadi satu gambar
     * PNG: di template cukup satu <img> dalam alur biasa (tanpa posisi absolut). Logo ditaruh di
     * alas putih yang menutup sekitar 5% area QR, jauh di bawah kapasitas koreksi H (~30%).
     */
    private function qrPng(string $url, ?string $logoFile): string
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::H(), 'UTF-8')->getMatrix();
        $modules = $matrix->getWidth();
        $quiet = 2;
        $scale = 8;
        $side = ($modules + 2 * $quiet) * $scale;

        $img = imagecreatetruecolor($side, $side);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $quiet) * $scale;
                    $py = ($y + $quiet) * $scale;
                    imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        $logo = $logoFile ? @imagecreatefromstring((string) file_get_contents($logoFile)) : false;

        if ($logo) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            // Alas putih PERSEGI (1:1) di tengah, sisinya 30% sisi area QR (menutup ~9% area, masih
            // jauh di bawah kapasitas koreksi H ~30%). Logo dimuat utuh di dalamnya dengan sedikit
            // ruang tepi, rasio aslinya dijaga.
            $codeSide = $modules * $scale;
            $padSide = (int) round($codeSide * 0.30);
            $inner = (int) round($padSide * 0.88);
            $ratio = min($inner / $lw, $inner / $lh);
            $w = max(1, (int) round($lw * $ratio));
            $h = max(1, (int) round($lh * $ratio));

            $x0 = intdiv($side - $padSide, 2);
            imagefilledrectangle($img, $x0, $x0, $x0 + $padSide - 1, $x0 + $padSide - 1, $white);
            imagealphablending($img, true);
            imagecopyresampled($img, $logo, intdiv($side - $w, 2), intdiv($side - $h, 2), 0, 0, $w, $h, $lw, $lh);
        }

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
