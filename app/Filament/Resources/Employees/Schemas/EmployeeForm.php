<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enum\OrgStructurLevel;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\LeaveUserBalances;
use App\Models\Role;
use App\Models\Master\OrgStructure;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EmployeeForm
{
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
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Bisa pilih lebih dari 1 peran.')
                            ->columnSpanFull(),
                            
                        Select::make('type_nakes')
                            ->label('Tipe Nakes')
                            ->helperText('Pilih tipe nakes untuk karyawan ini.')
                            ->options([
                                'nakes' => 'Nakes',
                                'non-nakes' => 'Non-Nakes',
                                'all' => 'Nakes & Non-Nakes',
                            ])
                            ->nullable(),

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
                            Select::make('department_id')
                                ->label('Departemen')
                                ->options(function ($get) {
                                    // Field ini ada di dalam Grid->relationship('profile'), jadi
                                    // path state aslinya "profile.department_id" — company_id
                                    // (di Step 1, top-level) dicapai dengan naik SATU level pakai
                                    // "../company_id", BUKAN absolute path "/company_id" (sudah
                                    // dicoba & dites, absolute path balik null di sini).
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) return [];

                                    return OrgStructure::query()
                                        ->where('company_id', $companyId)
                                        ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                        ->pluck('name', 'id');
                                })
                                ->reactive()
                                ->searchable()
                                ->disabled(fn ($get) => !($get('../company_id') ?? Auth::user()->company_id))
                                ->helperText(fn ($get) => !($get('../company_id') ?? Auth::user()->company_id) ? 'Pilih perusahaan terlebih dahulu di step sebelumnya' : null)
                                ->afterStateHydrated(function ($set, $record) {
                                    if (!$record?->org_structure_id) return;

                                    $org = OrgStructure::with('parent.parent')->find($record->org_structure_id);
                                    if ($org?->level === 1) $set('department_id', $org->id);
                                    if ($org?->level === 2) $set('department_id', $org->parent?->id);
                                    if ($org?->level === 3) $set('department_id', $org->parent?->parent?->id);
                                })
                                ->afterStateUpdated(function (callable $set, $state) {
                                    $set('division_id', null);
                                    $set('sub_division_id', null);
                                    // Update org_structure_id when department changes
                                    $set('org_structure_id', $state);
                                })
                                ->dehydrated(false),

                            Select::make('division_id')
                                ->label('Divisi')
                                ->options(function ($get) {
                                    $departmentId = $get('department_id');
                                    if (!$departmentId) return [];

                                    return OrgStructure::query()
                                        ->where('parent_id', $departmentId)
                                        ->where('level', OrgStructurLevel::LEVEL_DIVISION->value)
                                        ->pluck('name', 'id');
                                })
                                ->reactive()
                                ->searchable()
                                ->disabled(fn ($get) => !($get('department_id')))
                                ->helperText(fn ($get) => !($get('department_id')) ? 'Pilih departemen terlebih dahulu' : null)
                                ->afterStateUpdated(function (callable $set, $state) {
                                    $set('sub_division_id', null);
                                    // Update org_structure_id when division changes
                                    $set('org_structure_id', $state);
                                })
                                ->afterStateHydrated(function ($set, $record) {
                                    if (!$record?->org_structure_id) return;

                                    $org = OrgStructure::with('parent.parent')->find($record->org_structure_id);
                                    if ($org?->level === 2) $set('division_id', $org->id);
                                    if ($org?->level === 3) $set('division_id', $org->parent?->id);
                                })
                                ->dehydrated(false),

                            Select::make('sub_division_id')
                                ->label('Sub Divisi')
                                ->options(function ($get) {
                                    $divisionId = $get('division_id');
                                    if (!$divisionId) return [];

                                    return OrgStructure::query()
                                        ->where('parent_id', $divisionId)
                                        ->where('level', OrgStructurLevel::LEVEL_SUBDIVISION->value)
                                        ->pluck('name', 'id');
                                })
                                ->afterStateHydrated(function ($set, $record) {
                                    if (!$record?->org_structure_id) return;

                                    $org = OrgStructure::find($record->org_structure_id);
                                    if ($org?->level === 3) $set('sub_division_id', $org->id);
                                })
                                ->reactive()
                                ->searchable()
                                ->disabled(fn ($get) => !($get('division_id')))
                                ->helperText(fn ($get) => !($get('division_id')) ? 'Pilih divisi terlebih dahulu' : null)
                                ->afterStateUpdated(function (callable $set, $state) {
                                    // Update org_structure_id when sub_division changes
                                    $set('org_structure_id', $state);
                                })
                                ->dehydrated(false),

                            // Nilainya di-set langsung oleh afterStateUpdated() department_id/
                            // division_id/sub_division_id di atas — jangan tambah
                            // afterStateUpdated() lain di sini yang nebak ulang dari 3 field itu,
                            // 2 penulis buat 1 field gampang balapan/rebutan urutan eksekusi.
                            Hidden::make('org_structure_id')
                                ->dehydrated(true)
                                ->afterStateHydrated(function ($set, $record) {
                                    if ($record?->org_structure_id) {
                                        $set('org_structure_id', $record->org_structure_id);
                                    }
                                }),

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
                                                $available = ($jobPosition->actual_manpower ?? 0) - $used;

                                                if ($available <= 0) {
                                                    $fail('Kuota jabatan ini sudah penuh (actual manpower: ' . ($jobPosition->actual_manpower ?? 0) . ', terisi: ' . $used . '). Tidak dapat menambahkan karyawan pada jabatan ini.');
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

                                    $query->where('company_id', $companyId);
                                })

                                ->searchable()
                                ->preload(),

                            Select::make('golongan_id')
                                ->label('Golongan')
                                ->relationship('golongan', 'name', function ($query, $get) {
                                    $companyId = $get('../company_id') ?? Auth::user()->company_id;
                                    if (!$companyId) {
                                        $query->whereRaw('0 = 1');
                                        return;
                                    }

                                    $query->where('company_id', $companyId);
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

                        Section::make('Informasi Lainnya')
                            ->schema([
                                Fieldset::make('Kuota Cuti')
                                    ->columnSpanFull()
                                    ->schema([
                                        RepeatableEntry::make('leave_quota')
                                            ->hiddenLabel()
                                            ->state(function ($record) {
                                                if (!$record) return [];

                                                return LeaveUserBalances::where('user_id', $record->id)
                                                    ->with('leaveType')
                                                    ->get();
                                            })
                                            ->schema([
                                                TextEntry::make('leaveType.name')
                                                    ->label('Jenis Cuti')
                                                    ->belowContent(fn ($record) => Carbon::parse($record->period_start)->format('Y'))
                                                    ->weight(FontWeight::SemiBold),

                                                TextEntry::make('quota')
                                                    ->label('Kuota')
                                                    ->badge()
                                                    ->color('info'),

                                                TextEntry::make('used')
                                                    ->label('Terpakai')
                                                    ->badge()
                                                    ->color('danger'),

                                                TextEntry::make('sisa')
                                                    ->label('Sisa')
                                                    ->badge()
                                                    ->color('success')
                                                    ->state(fn ($record) => ($record->quota ?? 0) - ($record->used ?? 0)),
                                            ])
                                            ->columns(4)
                                            ->columnSpanFull(),
                                    ])
                            ])
                    ]),
                ])->columnSpanFull()->skippable(),
            ]);
    }
}
