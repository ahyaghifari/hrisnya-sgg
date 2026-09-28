<?php

namespace App\Services\Document;

use App\Enum\DocumentType;

/**
 * Pola kode verifikasi BARU (instruksi user 2026-09-28, beda dari pola sarpras) -
 * `{PREFIX}-{YYMM}-{6 karakter acak}`, mis. `SP-2610-K7XQ2P`. Prefix dari
 * DocumentType::codePrefix() supaya kode langsung kelihatan jenis dokumennya tanpa buka halaman
 * verifikasi dulu; YYMM dari bulan TERBIT (bukan bulan generate ulang) buat rentang waktu kasar
 * yang gampang dibaca manusia. 6 karakter acak pakai alfabet Crockford Base32 (tanpa I/L/O/U -
 * huruf yang gampang salah baca/ketik manual kalau QR-nya rusak/tidak bisa discan) - 32^6 ≈ 1.07
 * miliar kombinasi per prefix+bulan, cukup besar untuk tidak ditebak brute-force (juga dipagari
 * throttle di route /verify/{code}, lihat routes/web.php).
 */
class VerificationCodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford Base32, tanpa I L O U

    public function generate(DocumentType $type, ?\DateTimeInterface $issuedAt = null): string
    {
        $issuedAt ??= now();
        $yearMonth = $issuedAt->format('ym');
        $random = $this->randomSuffix(6);

        return "{$type->codePrefix()}-{$yearMonth}-{$random}";
    }

    private function randomSuffix(int $length): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $suffix = '';

        for ($i = 0; $i < $length; $i++) {
            $suffix .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $suffix;
    }
}
