<?php

namespace App\Models\Integrasi;

use App\Models\Master\JobPositions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 baris = 1 jabatan otomatis dapat 1 role di 1 client Keycloak. Lihat
 * App\Filament\Keycloak\Pages\KeycloakRoleMappings & migration terkait.
 */
class KeycloakRoleMapping extends Model
{
    use HasUlids;

    protected $table = 'keycloak_role_mappings';

    protected $fillable = [
        'job_position_id',
        'keycloak_client_uuid',
        'keycloak_client_label',
        'keycloak_role_name',
        'created_by',
    ];

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
