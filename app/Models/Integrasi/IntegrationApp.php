<?php

namespace App\Models\Integrasi;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registrasi sistem eksternal yang boleh mengakses API integrasi HRIS (mis.
 * GET /api/integrasi/karyawan/{nip}). Autentikasi via Bearer token — token
 * asli hanya diketahui sekali saat dibuat/di-generate ulang (ditampilkan di
 * panel Keycloak), setelahnya cuma hash yang tersimpan di kolom token_hash.
 */
class IntegrationApp extends Model
{
    use HasUlids;

    protected $table = 'integration_apps';

    protected $fillable = [
        'name',
        'token_hash',
        'is_active',
        'created_by',
        'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Token baru dalam bentuk plaintext, dipakai sekali untuk ditampilkan ke admin. */
    public static function newPlainToken(): string
    {
        return Str::random(48);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Cari aplikasi aktif berdasarkan token plaintext dari header Authorization. */
    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', static::hashToken($token))
            ->where('is_active', true)
            ->first();
    }

    public function recordUsage(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
