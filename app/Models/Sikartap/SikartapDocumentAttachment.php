<?php

namespace App\Models\Sikartap;

use App\Enum\Sikartap\SikartapDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikartapDocumentAttachment extends Model
{
    use HasUlids;

    protected $table = 'sikartap_document_attachments';

    protected $fillable = [
        'sikartap_id',
        'doc_type',
        'file_path',
        'status',
        'catatan_penolakan',
    ];

    protected function casts(): array
    {
        return [
            'doc_type' => SikartapDocumentType::class,
        ];
    }

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }
}
