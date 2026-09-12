<?php

namespace App\Models\Integrasi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 panggilan ke GET /api/integrasi/karyawan/{nip}, apa pun hasilnya (termasuk
 * yang gagal auth) — supaya kelihatan jelas apakah sistem client benar-benar menjangkau
 * HRIS. Ditulis dari App\Http\Controllers\Api\Integrasi\EmployeeDirectoryController.
 */
class IntegrationAppLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'integration_app_id',
        'nip',
        'status',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(IntegrationApp::class, 'integration_app_id');
    }
}
