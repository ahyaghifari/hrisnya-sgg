<?php

namespace App\Models\Sarpras;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SarprasRequestItem extends Model
{
    use HasUlids;

    protected $table = 'sarpras_request_items';

    protected $fillable = [
        'sarpras_request_id',
        'nama',
        'spesifikasi',
        'jumlah',
        'satuan',
        'harga_satuan',
        'status',
        'jumlah_disetujui',
        'catatan_direktur',
        'diputuskan_at',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'jumlah_disetujui' => 'integer',
            'harga_satuan' => 'decimal:2',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SarprasRequest::class, 'sarpras_request_id');
    }
}
