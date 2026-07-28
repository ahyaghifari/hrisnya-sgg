<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperAuditLog
 * @property string $id
 * @property string|null $company_id
 * @property string|null $user_id
 * @property string $module
 * @property string $key
 * @property string|null $label
 * @property string|null $old_value
 * @property string|null $new_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereNewValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereOldValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserId($value)
 * @mixin \Eloquent
 */
class AuditLog extends Model
{
    use HasUlids;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'module',
        'key',
        'label',
        'old_value',
        'new_value',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cuma catat perubahan yang beneran dilakukan user lewat UI (Auth::check()) — seeder/console
     * tidak ikut kecatat, biar audit_logs tidak dipenuhi noise `php artisan db:seed`/migrate.
     */
    public static function record(string $module, ?string $companyId, string $key, ?string $label, ?string $oldValue, ?string $newValue): void
    {
        if (! Auth::check()) {
            return;
        }

        static::create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'module' => $module,
            'key' => $key,
            'label' => $label,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}
