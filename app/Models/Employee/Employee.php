<?php

namespace App\Models\Employee;

use App\Models\AuditLog;
use App\Models\Master\JobPositions;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use App\Models\Master\Religions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Salinan data karyawan dari users + user_profiles (lihat EmployeeSyncService): kondisi kepegawaian
 * saat ini DAN data pribadi (tidak ada lagi entitas Person terpisah). Jangan diedit langsung dari
 * aplikasi - tabel lama tetap sumber. Tautan ke akun: employees.user_id.
 */
class Employee extends Model
{
    use HasUlids;

    protected $table = 'employees';

    protected $fillable = [
        'user_id',
        'nip',
        'nama_lengkap',
        'nickname',
        'is_dokter',
        'gender',
        'marital_status',
        'place_of_birth',
        'date_of_birth',
        'religion_id',
        'phone',
        'whatsapp',
        'email_pribadi',
        'company_id',
        'employee_type_id',
        'job_position_id',
        'pangkat_id',
        'kelas_id',
        'joining_date',
        'tmt_tetap',
        'status',
    ];

    protected $casts = [
        'is_dokter' => 'boolean',
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'tmt_tetap' => 'date',
    ];

    /**
     * Audit nama, NIP, dan perusahaan (dulu dicatat model User dari kolom users.*, yang akan dicabut).
     * Dicatat dengan kunci `{user_id}:{name|nip|company_id}` dan modul `data_karyawan` yang SAMA dengan
     * catatan lama, jadi riwayat di halaman Data Diri tetap satu aliran (EmployeeStatistics::
     * getAuditHistoryProperty membaca `{user_id}:%`). Aktornya Auth::id() (AuditLog::record), baik karyawan
     * sendiri maupun admin/HC, seperti LogsFieldAuditChanges. created/deleted sengaja tidak dicatat.
     *
     * nama_lengkap kosong -> terisi TIDAK dicatat: itu isi awal salinan saat identity:backfill, bukan
     * perubahan data.
     *
     * @var array<string, array{0: string, 1: string}> kolom employees => [kunci audit, label]
     */
    private const AUDITED_USER_FIELDS = [
        'nama_lengkap' => ['name', 'Nama'],
        'nip' => ['nip', 'NIP'],
        'company_id' => ['company_id', 'Perusahaan'],
    ];

    protected static function booted(): void
    {
        static::updated(function (Employee $employee) {
            foreach (self::AUDITED_USER_FIELDS as $column => [$key, $label]) {
                if (! $employee->wasChanged($column)) {
                    continue;
                }

                $old = $employee->getOriginal($column);
                $new = $employee->{$column};

                if ((string) $old === (string) $new || ($column === 'nama_lengkap' && ($old === null || $old === ''))) {
                    continue;
                }

                AuditLog::record(
                    'data_karyawan',
                    $employee->company_id,
                    $employee->user_id . ':' . $key,
                    $label,
                    $old === null ? null : (string) $old,
                    $new === null ? null : (string) $new,
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes()->withTrashed();
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religions::class, 'religion_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPositions::class, 'job_position_id');
    }

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function identity(): HasOne
    {
        return $this->hasOne(EmployeeIdentity::class, 'employee_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(EmployeeAddress::class, 'employee_id');
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(EmployeeAddress::class, 'employee_id')->where('is_primary', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function careers(): HasMany
    {
        return $this->hasMany(EmployeeCareer::class, 'employee_id')->orderBy('start_date');
    }
}
