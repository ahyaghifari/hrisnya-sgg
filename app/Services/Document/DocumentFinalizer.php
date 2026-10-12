<?php

namespace App\Services\Document;

use App\Models\Document\Document;
use App\Services\Sp\SpPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * "Dokumen jadi": begitu TTD terakhir masuk, PDF dirender SEKALI, disimpan di storage privat, dan
 * hash file-nya dicatat. Setelah itu PDF yang disajikan SELALU file tersimpan itu - tidak pernah
 * dirender ulang dari data live - dan dicek hash-nya tiap dibuka, jadi perubahan file di disk
 * ketahuan. Dokumen lama yang lengkap TTD tapi belum punya file difinalkan lazy saat dibuka.
 */
class DocumentFinalizer
{
    private const DISK = 'local';

    public function finalize(Document $document): void
    {
        if ($document->finalized_at) {
            return;
        }

        DB::transaction(function () use ($document) {
            // Kunci baris: dua TTD hampir bersamaan tidak boleh merender/menyimpan dua versi file.
            $locked = Document::with('documentable')->lockForUpdate()->find($document->id);

            if ($locked->finalized_at) {
                return;
            }

            $pdf = $locked->documentable instanceof \App\Models\SuratTugas\SuratTugas
                ? app(\App\Services\SuratTugas\SuratTugasPdfService::class)->renderFinal($locked->documentable)
                : app(SpPdfService::class)->renderFinal($locked->documentable);
            $path = "documents/final/{$locked->id}.pdf";

            Storage::disk(self::DISK)->put($path, $pdf);
            $locked->finalize($path, hash('sha256', $pdf));
        });

        $document->refresh();
    }

    /** Isi PDF final, atau null kalau dokumen belum lengkap TTD. Abort 500 kalau file berubah/hilang. */
    public function contents(Document $document): ?string
    {
        if (! $document->finalized_at) {
            $document->unsetRelation('signatures');

            if (! $document->isFullySigned()) {
                return null;
            }

            $this->finalize($document);
        }

        $disk = Storage::disk(self::DISK);
        $contents = $disk->exists($document->file_path) ? $disk->get($document->file_path) : null;

        abort_unless($contents !== null && hash_equals($document->file_hash, hash('sha256', $contents)), 500, 'File dokumen final rusak atau telah diubah.');

        return $contents;
    }
}
