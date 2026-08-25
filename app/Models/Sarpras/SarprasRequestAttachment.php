<?php

namespace App\Models\Sarpras;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SarprasRequestAttachment extends Model
{
    use HasUlids;

    protected $table = 'sarpras_request_attachments';

    protected $fillable = [
        'sarpras_request_id',
        'user_id',
        'nama_asli',
        'nama_simpan',
        'mime',
        'ukuran',
    ];

    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SarprasRequest::class, 'sarpras_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
