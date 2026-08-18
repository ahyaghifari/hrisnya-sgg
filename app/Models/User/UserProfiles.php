<?php

namespace App\Models\User;

use App\Models\Concerns\LogsFieldAuditChanges;
use App\Models\Master\OrgStructure;
use App\Models\Master\Religions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UserProfiles extends Model
{
    use HasUlids;
    use LogsFieldAuditChanges;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'nickname',
        'gender',
        'marital_status',
        'place_of_birth',
        'date_of_birth',
        'no_nik',
        'no_kk',
        'no_bpjs_kes',
        'no_bpjs_ket',
        'bank_account_number',
        'bank_account_name',
        'bank_name',
        'npwp',
        'country',
        'province',
        'city',
        'district',
        'village',
        'address',
        'postal_code',
        'rt',
        'rw',
        'phone',
        'whatsapp',
        'email_pribadi',
        'religion_id',
        'org_structure_id',
        'job_position_id',
        'pangkat_id',
        'kelas_id',
        'employee_type_id',
        'profile_picture',
        'joining_date',
    ];

    protected $casts = [
        'profile_picture' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function religion()
    {
        return $this->belongsTo(Religions::class, 'religion_id', 'id');
    }

    public function orgStructure()
    {
        return $this->belongsTo(OrgStructure::class, 'org_structure_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(\App\Models\Master\JobPositions::class, 'job_position_id');
    }

    public function getDepartmentAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 1
            ? $this->orgStructure
            : ($this->orgStructure->parent?->parent ?? $this->orgStructure->parent);
    }

    public function getDivisionAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 2
            ? $this->orgStructure
            : $this->orgStructure->parent;
    }

    public function getSubDivisionAttribute()
    {
        if (!$this->orgStructure) {
            return null;
        }

        return $this->orgStructure->level === 3
            ? $this->orgStructure
            : null;
    }

    public function pangkat()
    {
        return $this->belongsTo(\App\Models\Master\Pangkats::class, 'pangkat_id', 'id');
    }

    public function kelas()
    {
        return $this->belongsTo(\App\Models\Master\Kelas::class, 'kelas_id', 'id');
    }

    public function employeeType()
    {
        return $this->belongsTo(\App\Models\Master\EmployeeTypes::class, 'employee_type_id', 'id');
    }

    protected function auditModule(): string
    {
        return 'data_karyawan';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->user?->company_id;
    }

    protected function auditRecordKey(): string
    {
        return $this->user_id;
    }

    /** profile_picture sengaja tidak diikutkan — isinya path file, bukan data identitas yang
     * perlu dibandingkan lama/baru seperti field lain. */
    protected function auditFieldLabels(): array
    {
        return [
            'nickname' => 'Nama Panggilan',
            'gender' => 'Jenis Kelamin',
            'marital_status' => 'Status Perkawinan',
            'religion_id' => 'Agama',
            'place_of_birth' => 'Tempat Lahir',
            'date_of_birth' => 'Tanggal Lahir',
            'no_nik' => 'No. NIK',
            'no_kk' => 'No. KK',
            'no_bpjs_kes' => 'No. BPJS Kesehatan',
            'no_bpjs_ket' => 'No. BPJS Ketenagakerjaan',
            'bank_name' => 'Bank',
            'bank_account_number' => 'No. Rekening Bank',
            'bank_account_name' => 'Nama Pemilik Rekening',
            'npwp' => 'NPWP',
            'country' => 'Negara',
            'province' => 'Provinsi',
            'city' => 'Kota/Kabupaten',
            'district' => 'Kecamatan',
            'village' => 'Kelurahan/Desa',
            'address' => 'Alamat',
            'postal_code' => 'Kode Pos',
            'rt' => 'RT',
            'rw' => 'RW',
            'phone' => 'No. Telepon',
            'whatsapp' => 'No. WhatsApp',
            'org_structure_id' => 'Struktur Organisasi',
            'job_position_id' => 'Jabatan',
            'pangkat_id' => 'Pangkat',
            'kelas_id' => 'Kelas',
            'employee_type_id' => 'Tipe Karyawan',
            'joining_date' => 'Tanggal Bergabung',
        ];
    }
}
