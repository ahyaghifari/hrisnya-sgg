<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Config "Akses Filter Perusahaan" (Manajemen Pengguna) - per company+role/user, sistem mana
 * saja yang boleh dipakai buat filter lintas company. Dicek lewat
 * User::canFilterByCompanyForSystem(), lapisan tambahan di atas permission
 * lihat_perusahaan_lain (bukan pengganti).
 */
class CompanyFilterAccess extends Model
{
    use HasUlids;

    protected $table = 'company_filter_access';

    protected $fillable = ['company_id', 'grantee_type', 'grantee_id', 'systems'];

    protected function casts(): array
    {
        return [
            'systems' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }
}
