<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Role;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EmployeeForm
{
    /** Opsi struktur organisasi satu level untuk sebuah perusahaan (select tampilan di step Pekerjaan). */
    private static function orgOptions(?string $companyId, string $level)
    {
        return $companyId
            ? OrgStructure::where('company_id', $companyId)->where('level', $level)->pluck('name', 'id')
            : [];
    }

    /** [departemen, divisi, sub divisi] id dari struktur milik jabatan. */
    private static function orgPartsOfJob(?string $jobPositionId): array
    {
        $org = $jobPositionId
            ? \App\Models\Master\JobPositions::with('orgStructure.parent.parent')->find($jobPositionId)?->orgStructure
            : null;

        return match ($org?->level) {
            OrgStructurLevel::LEVEL_DEPARTMENT->value => [$org->id, null, null],
            OrgStructurLevel::LEVEL_DIVISION->value => [$org->parent_id, $org->id, null],
            OrgStructurLevel::LEVEL_SUBDIVISION->value => [$org->parent?->parent_id, $org->parent_id, $org->id],
            default => [null, null, null],
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Informasi Akun Karyawan')->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('nip')
                            ->label('NIP')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->columnSpanFull()
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi.'),
                        
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->relationship('company', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive() // Membuat field reactive
                            // Admin non-global dikunci ke company sendiri — sebelumnya field ini
                            // bisa diubah bebas ke company manapun walau yang login admin
                            // company lain (celah bikin karyawan nyasar ke company orang).
                            ->disabled(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()))
                            ->dehydrated()
                            ->default(fn () => Auth::user()->company_id)
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Reset department_id & role_ids ketika company berubah — dua-duanya
                                // di-scope ke company itu, opsi lama sudah tidak relevan lagi.
                                $set('profile.department_id', null);
                                $set('role_ids', []);
                            })
                            ->columnSpanFull(),

                        Select::make('role_ids')
                            ->label('Peran')
                            ->multiple()
                            ->options(function ($get) {
                                $companyId = $get('company_id');

                                $roles = $companyId
                                    ? Role::where('company_id', $companyId)->pluck('name', 'id')
                                    : Role::whereNull('company_id')->pluck('name', 'id');

                                return $roles->map(function ($name) {
                                    return ucwords(str_replace('_', ' ', $name));
                                });
                            })
                            ->default(fn (?User $record) => $record?->roles->pluck('id')->toArray() ?? [])
                            ->afterStateHydrated(fn ($component, ?User $record) => $component->state($record?->roles->pluck('id')->toArray() ?? []))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Bisa pilih lebih dari 1 peran.')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Karyawan Aktif')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->helperText('Nonaktifkan jika karyawan sudah tidak bekerja di perusahaan.'),
                    ]),
                    
                    Step::make('Informasi Pribadi Karyawan')->schema([
                        Section::make('Informasi Pribadi')
                            ->relationship('profile')
                            ->columns(2)
                            ->schema([
                                TextInput::make('nickname')
                                    ->label('Nama Panggilan'),

                                TextInput::make('email_pribadi')
                                    ->label('Email Pribadi')
                                    ->email()
                                    ->maxLength(255)
                                    ->helperText('Tujuan pengiriman slip gaji.'),

                                Select::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->options([
                                        'laki_laki' => 'Laki-laki',
                                        'perempuan' => 'Perempuan',
                                    ]),

                                Select::make('religion_id')
                                    ->label('Agama')
                                    // Religions itu master data global (tidak ada kolom
                                    // company_id sama sekali) — jangan difilter per company.
                                    ->relationship('religion', 'name')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('place_of_birth')
                                    ->label('Tempat Lahir'),

                                DatePicker::make('date_of_birth')
                                    ->label('Tanggal Lahir'),

                                TextInput::make('no_nik')
                                    ->label('No. NIK')
                                    ->maxLength(20),

                                TextInput::make('no_kk')
                                    ->label('No. KK')
                                    ->maxLength(20),

                                TextInput::make('no_bpjs_kes')
                                    ->label('No. BPJS Kesehatan')
                                    ->maxLength(20),
                                
                                TextInput::make('no_bpjs_ket')
                                    ->label('No. BPJS Ketenagakerjaan')
                                    ->maxLength(20),

                                TextInput::make('npwp')
                                    ->label('NPWP')
                                    ->maxLength(20),
                        
                                TextInput::make('bank_account_number')
                                    ->label('No. Rekening Bank')
                                    ->maxLength(30),

                                TextInput::make('bank_account_name')
                                    ->label('Atas Nama Rekening Bank')
                                    ->maxLength(100),
                            ]),

                        Section::make('Alamat')
                            ->relationship('profile')
                            ->columns(2)
                            ->schema([
                                TextInput::make('country')
                                    ->label('Negara')
                                    ->maxLength(50),

                                TextInput::make('province')
                                    ->label('Provinsi')
                                    ->maxLength(50),    

                                TextInput::make('city')
                                    ->label('Kota/Kabupaten')
                                    ->maxLength(50),

                                TextInput::make('district')
                                    ->label('Kecamatan')
                                    ->maxLength(50),

                                TextInput::make('village')
                                    ->label('Kelurahan/Desa')
                                    ->maxLength(50),

                                TextInput::make('postal_code')
                                    ->label('Kode Pos')
                                    ->maxLength(10),

                                TextInput::make('rt')
                                    ->label('RT')
                                    ->maxLength(5),

                                TextInput::make('rw')
                                    ->label('RW')
                                    ->maxLength(5),

                                Textarea::make('address')
                                    ->label('Alamat Lengkap')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Kontak')
                            ->columns(2)
                            ->relationship('profile')
                            ->schema([
                                TextInput::make('phone')
                                    ->label('No. Telepon')
                                    ->maxLength(15),

                                TextInput::make('whatsapp')
                                    ->label('No. WhatsApp')
                                    ->maxLength(15),

                            ]),
                    ]),

                    Step::make('Informasi Pekerjaan Karyawan')->schema([
                        Grid::make(2)->relationship('profile')->schema([
                            // Departemen/Divisi/Sub Divisi hanya tampilan: turunan struktur jabatan
                            // (jabatan.org_structure_id), tidak disimpan di profil. Ikut berubah saat
                            // Jabatan diganti (lihat afterStateUpdated Jabatan di bawah).
                            Select::make('department_id')
                                ->label('Departemen')
                                ->options(fn ($get) => static::orgOptions($get('../company_id') ?? Auth::user()->company_id, OrgStructurLevel::LEVEL_DEPARTMENT->value))
                                ->afterStateHydrated(fn ($set, $record) => $set('department_id', static::orgPartsOfJob($record?->job_position_id)[0]))
                                ->helperText('Otomatis mengikuti jabatan.')
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('division_id')
                                ->label('Divisi')
                                ->options(fn ($get) => static::orgOptions($get('../company_id') ?? Auth::user()->company_id, OrgStructurLevel::LEVEL_DIVISION->value))
                                ->afterStateHydrated(fn ($set, $record) => $set('division_id', static::orgPartsOfJob($record?->job_position_id)[1]))
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('sub_division_id')
                                ->label('Sub Divisi')
                                ->options(fn ($get) => static::orgOptions($get('../company_id') ?? Auth::user()->company_id, OrgStructurLevel::LEVEL_SUBDIVISION->value))
                                ->afterStateHydrated(fn ($set, $record) => $set('sub_division_id', static::orgPartsOfJob($record?->job_position_id)[2]))
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('job_position_id')
                                ->label('Jabatan')
                                ->relationship('jobPosition', 'name', function ($query, $get) {
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) {
                                        $query->whereRaw('0 = 1');
                                        return;
                                    }

                                    $query->where('company_id', $companyId);
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($set, $state) {
                                    [$dept, $div, $sub] = static::orgPartsOfJob($state);
                                    $set('department_id', $dept);
                                    $set('division_id', $div);
                                    $set('sub_division_id', $sub);
                                })
                                ->rules(function ($record) {
                                    return [
                                        function () use ($record) {
                                            return function (string $attribute, $value, \Closure $fail) use ($record) {
                                                if (!$value) return;

                                                // Skip validation if job position hasn't changed
                                                if ($record && $record->job_position_id == $value) {
                                                    return;
                                                }

                                                $jobPosition = \App\Models\Master\JobPositions::find($value);
                                                if (!$jobPosition) return;

                                                $used = $jobPosition->userAssignments()->count();
                                                $available = ($jobPosition->manpower ?? 0) - $used;

                                                if ($available <= 0) {
                                                    $fail('Kuota jabatan ini sudah penuh (kuota: ' . ($jobPosition->manpower ?? 0) . ', terisi: ' . $used . '). Tidak dapat menambahkan karyawan pada jabatan ini.');
                                                }
                                            };
                                        },
                                    ];
                                }),

                            Select::make('pangkat_id')
                                ->label('Pangkat')
                                ->relationship('pangkat', 'name', function ($query, $get) {
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) {
                                        $query->whereRaw('0 = 1');
                                        return;
                                    }

                                    $query->where('company_id', $companyId)->orderByDesc('level');
                                })
                                ->searchable()
                                ->preload(),

                            Select::make('kelas_id')
                                ->label('Kelas')
                                ->relationship('kelas', 'name', function ($query, $get) {
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) {
                                        $query->whereRaw('0 = 1');
                                        return;
                                    }

                                    $query->where('company_id', $companyId);
                                })
                                ->searchable()
                                ->preload(),

                            Select::make('employee_type_id')
                                ->label('Tipe Karyawan')
                                ->relationship('employeeType', 'name', function ($query, $get) {
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) {
                                        $query->whereRaw('0 = 1');
                                        return;
                                    }

                                    $query->where('company_id', $companyId);
                                })
                                ->searchable()
                                ->preload(),

                            DatePicker::make('joining_date')
                                ->label('Tanggal Bergabung'),
                        ]),
                    ]),
                ])->columnSpanFull()->skippable(),
            ]);
    }
}
