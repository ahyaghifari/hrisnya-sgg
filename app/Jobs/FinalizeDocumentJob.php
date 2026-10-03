<?php

namespace App\Jobs;

use App\Models\Document\Document;
use App\Services\Document\DocumentFinalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Render + simpan PDF final di belakang layar setelah TTD terakhir (UI menampilkan "sedang diproses" selama ini). */
class FinalizeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public string $documentId)
    {
        $this->afterCommit();
    }

    public function handle(DocumentFinalizer $finalizer): void
    {
        if ($document = Document::find($this->documentId)) {
            $finalizer->finalize($document);
        }
    }
}
