<?php

use App\Enum\DocumentType;
use App\Services\Document\VerificationCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 1 kode verifikasi PER TANDA TANGAN, bukan per dokumen lagi (instruksi user 2026-09-29 - beda
 * dari keputusan awal §0/§100 docs/2026-09-28-tte-qr-internal-fase-1.md: "1 dokumen = 1 kode
 * verifikasi tetap"). Alasannya: TTD itu personal/atas nama masing-masing penanda tangan, jadi
 * QR-nya juga harus personal - bukan 1 QR yang sama ditempel di created_by DAN knows_by.
 * `document_verifications` sekarang 1:1 ke `document_signatures` (bukan ke `documents` lagi) -
 * lihat HasDocumentSignatures::sign() yang sekarang membangkitkan kode BARENGAN dengan baris
 * signature-nya, bukan lewat ensureVerificationCode() terpisah yang idempotent per-dokumen.
 *
 * Backfill: kode yang SUDAH ada (dibuat sebelum migration ini, 1 per dokumen) dilekatkan ke TTD
 * PALING AWAL dokumennya (signed_at tertua - itu pasti `created_by`, karena kode lama cuma
 * dibuat sekali saat sign pertama). TTD lain (mis. `knows_by` yang sudah konfirmasi) belum punya
 * kode sendiri - dibangkitkan kode BARU untuk masing-masing di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_verifications', function (Blueprint $table) {
            $table->foreignUlid('document_signature_id')->nullable()->after('document_id')
                ->constrained('document_signatures')->cascadeOnDelete();
        });

        // FK document_id HARUS dicabut sebelum unique index-nya - MySQL menolak drop index yang
        // masih dipakai constraint FK ("Cannot drop index ... needed in a foreign key constraint").
        Schema::table('document_verifications', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropUnique(['document_id']);
        });

        $this->backfill();

        Schema::table('document_verifications', function (Blueprint $table) {
            $table->dropColumn('document_id');
        });

        Schema::table('document_verifications', function (Blueprint $table) {
            $table->ulid('document_signature_id')->nullable(false)->change();
        });

        Schema::table('document_verifications', function (Blueprint $table) {
            $table->unique('document_signature_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_verifications', function (Blueprint $table) {
            $table->foreignUlid('document_id')->nullable()->after('id')
                ->constrained('documents')->cascadeOnDelete();
        });

        DB::table('document_verifications as dv')
            ->join('document_signatures as ds', 'ds.id', '=', 'dv.document_signature_id')
            ->update(['dv.document_id' => DB::raw('ds.document_id')]);

        // Cuma 1 kode boleh tersisa per dokumen (unique lama) - buang sisanya, simpan yang
        // paling awal dibuat. Lossy secara desain, downgrade balik ke "1 dokumen = 1 kode" tetap
        // kehilangan kode milik TTD lain yang sudah punya kodenya sendiri.
        $keepIds = DB::table('document_verifications')
            ->select('document_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('document_id')
            ->pluck('keep_id');

        DB::table('document_verifications')->whereNotIn('id', $keepIds)->delete();

        Schema::table('document_verifications', function (Blueprint $table) {
            $table->dropForeign(['document_signature_id']);
            $table->dropColumn('document_signature_id');
        });

        Schema::table('document_verifications', function (Blueprint $table) {
            $table->ulid('document_id')->nullable(false)->change();
            $table->unique('document_id');
        });
    }

    private function backfill(): void
    {
        $generator = new VerificationCodeGenerator();

        DB::table('document_verifications')->get()->each(function ($verification) {
            $firstSignature = DB::table('document_signatures')
                ->where('document_id', $verification->document_id)
                ->orderBy('signed_at')
                ->first();

            if ($firstSignature) {
                DB::table('document_verifications')->where('id', $verification->id)
                    ->update(['document_signature_id' => $firstSignature->id]);
            }
        });

        $alreadyLinked = DB::table('document_verifications')->whereNotNull('document_signature_id')->pluck('document_signature_id')->all();

        $missingSignatures = DB::table('document_signatures')
            ->when($alreadyLinked !== [], fn ($q) => $q->whereNotIn('id', $alreadyLinked))
            ->get();

        foreach ($missingSignatures as $signature) {
            $document = DB::table('documents')->where('id', $signature->document_id)->first();

            if (! $document) {
                continue;
            }

            do {
                $code = $generator->generate(DocumentType::from($document->document_type), Carbon::parse($document->issued_at));
            } while (DB::table('document_verifications')->where('verification_code', $code)->exists());

            DB::table('document_verifications')->insert([
                'id' => (string) Str::ulid(),
                'document_id' => $signature->document_id,
                'document_signature_id' => $signature->id,
                'verification_code' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
