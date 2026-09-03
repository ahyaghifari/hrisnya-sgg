<?php

namespace App\Models\Payroll;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kredensial SMTP satu perusahaan, KHUSUS kirim slip gaji email (bukan mail modul lain).
 * `password` dienkripsi (cast `encrypted`, key APP_KEY). Konsumen: App\Services\Payroll\PayrollMailer.
 */
class PayrollMailSetting extends Model
{
    use HasUlids;

    protected $table = 'payroll_mail_settings';

    protected $fillable = [
        'company_id', 'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name', 'is_active',
        'last_tested_at', 'last_test_error',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = ['password'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class);
    }

    /** Config array untuk `config(['mail.mailers.<name>' => ...])`. */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption ?: null,
            'timeout' => null,
            'local_domain' => null,
        ];
    }
}
