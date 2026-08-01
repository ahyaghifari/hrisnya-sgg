<?php

namespace App\Models\User;

use App\Models\Concerns\LogsFieldAuditChanges;
use App\Models\Master\OrgStructure;
use App\Models\Master\Religions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperUserProfiles
 * @property string $id
 * @property string $user_id
 * @property string|null $nickname
 * @property string|null $gender
 * @property string|null $place_of_birth
 * @property string|null $date_of_birth
 * @property string|null $no_nik
 * @property string|null $no_kk
 * @property string|null $no_bpjs_kes
 * @property string|null $no_bpjs_ket
 * @property string|null $bank_account_number
 * @property string|null $bank_account_name
 * @property string|null $bank_name
 * @property string|null $npwp
 * @property string|null $country
 * @property string|null $province
 * @property string|null $city
 * @property string|null $district
 * @property string|null $village
 * @property string|null $address
 * @property string|null $postal_code
 * @property string|null $rt
 * @property string|null $rw
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $religion_id
 * @property string|null $org_structure_id
 * @property string|null $job_position_id
 * @property string|null $pangkat_id
 * @property string|null $kelas_id
 * @property string|null $employee_type_id
 * @property string|null $business_unit_id
 * @property array<array-key, mixed>|null $profile_picture
 * @property string|null $joining_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Master\EmployeeTypes|null $employeeType
 * @property-read mixed $department
 * @property-read mixed $division
 * @property-read mixed $sub_division
 * @property-read \App\Models\Master\JobPositions|null $jobPosition
 * @property-read \App\Models\Master\Kelas|null $kelas
 * @property-read OrgStructure|null $orgStructure
 * @property-read \App\Models\Master\Pangkats|null $pangkat
 * @property-read Religions|null $religion
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereBankAccountName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereBankAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereBusinessUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereDistrict($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereEmployeeTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereJobPositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereJoiningDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereKelasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNoBpjsKes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNoBpjsKet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNoKk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNoNik($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereNpwp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereOrgStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles wherePangkatId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles wherePlaceOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereProfilePicture($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereProvince($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereReligionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereRt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereRw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereVillage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfiles whereWhatsapp($value)
 * @mixin \Eloquent
 */
class UserProfiles extends Model
{
    use HasUlids;
    use LogsFieldAuditChanges;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'nickname',
        'gender',
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
        'religion_id',
        'org_structure_id',
        'job_position_id',
        'pangkat_id',
        'kelas_id',
        'employee_type_id',
        'profile_picture',
        'joining_date',
        'contract_end_date',
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
