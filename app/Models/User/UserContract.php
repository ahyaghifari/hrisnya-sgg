<?php

namespace App\Models\User;

use App\Models\Master\Companies;
use App\Models\Master\EmployeeTypes;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Ledger riwayat kontrak per tahun/periode - append-only, 1 baris = 1 periode kontrak yang
 * SUDAH disetujui (Pengangkatan Orientasi ke Kontrak, atau tiap Perpanjangan Kontrak). Diisi
 * otomatis oleh UserTransitionService::approve(), tidak pernah diedit manual. Beda dari
 * UserProfiles::contract_start_date/contract_end_date (cuma kontrak AKTIF saat ini, dibaca cepat
 * tanpa JOIN) - tabel ini buat query "riwayat kontrak tahun ke berapa" tanpa filter
 * transition_type+status ke user_transitions setiap saat.
 */
class UserContract extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'company_id',
        'employee_type_id',
        'contract_start_date',
        'contract_end_date',
        'file_path',
        'source_transition_id',
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeTypes::class, 'employee_type_id');
    }

    public function sourceTransition(): BelongsTo
    {
        return $this->belongsTo(UserTransition::class, 'source_transition_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
