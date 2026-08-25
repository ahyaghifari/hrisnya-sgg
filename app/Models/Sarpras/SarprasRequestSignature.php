<?php

namespace App\Models\Sarpras;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SarprasRequestSignature extends Model
{
    use HasUlids;

    protected $table = 'sarpras_request_signatures';

    protected $fillable = [
        'sarpras_request_id',
        'user_id',
        'peran',
        'nama',
        'jabatan',
        'kode',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SarprasRequest::class, 'sarpras_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
