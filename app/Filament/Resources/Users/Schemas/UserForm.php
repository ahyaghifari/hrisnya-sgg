<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enum\OrgStructurLevel;
use App\Models\Master\OrgStructure;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make()
                    ->skippable()
                    ->columnSpanFull()
                    ->schema([
                        Step::make('Informasi Akun')
                            ->icon('heroicon-o-user-circle')
                            ->completedIcon('heroicon-o-user-circle')
                            ->schema(self::accountInformationSchema()),

                        Step::make('Data Pribadi')
                            ->icon('heroicon-o-identification')
                            ->completedIcon('heroicon-o-identification')
                            ->schema(self::personalDataSchema()),

                        Step::make('Status Kepegawaian')
                            ->icon('heroicon-o-briefcase')
                            ->completedIcon('heroicon-o-briefcase')
                            ->schema(self::employmentStatusSchema()),

                        Step::make('Lampiran')
                            ->icon('heroicon-o-paper-clip')
                            ->completedIcon('heroicon-o-paper-clip')
                            ->schema(self::attachmentsSchema())
                    ])
            ]);
    }

    private static function accountInformationSchema()
    {
        return [
            Section::make('Informasi Akun')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->columns(1),

                    TextInput::make('email')
                        ->label('Email')
                        ->columns(1),

                    TextInput::make('nip')
                        ->label('NIP')
                        ->columns(1),

                    Select::make('company_id')
                        ->label('Perusahaan')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->disabled(),

                    Select::make('role_ids')
                        ->label('Role')
                        ->relationship('roles', 'name')
                        ->options(function ($get) {
                            $companyId = $get('company_id');
                            
                            $roles = $companyId
                                ? Role::where('company_id', $companyId)->pluck('name', 'id')
                                : Role::whereNull('company_id')->pluck('name', 'id');

                            return $roles->map(function ($name) {
                                return ucwords(str_replace('_', ' ', $name));
                            });
                        })
                        ->required()
                        ->searchable()
                        ->disabled()
                        ->preload()
                        ->columnSpanFull(),
                ])
                ->headerActions([
                    Action::make('change_password')
                        ->label('Reset Kata Sandi')
                        ->modalHeading('Reset Kata Sandi')
                        ->action(function (array $data, $record) {
                            $record->password = $data['password'];
                            $record->save();
                        })
                        ->schema([
                            TextInput::make('password')
                                ->label('Kata Sandi')
                                ->password()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->minLength(8)
                                ->columnSpanFull()
                                ->prefixIcon('heroicon-o-lock-closed')
                                ->revealable()
                                ->dehydrated(fn ($state) => filled($state))
                                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null),

                            TextInput::make('password_confirmation')
                                ->label('Konfirmasi Kata Sandi')
                                ->password()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->minLength(8)
                                ->columnSpanFull()
                                ->prefixIcon('heroicon-o-lock-closed')
                                ->revealable()
                                ->same('password')
                                ->validationMessages(['same' => 'Kata sandi dan konfirmasi kata sandi harus sama.'])
                        ])
                        ->requiresConfirmation(true)
                        ->modalHeading('Reset Kata Sandi')
                        ->modalDescription('Masukkan kata sandi baru anda'),
                ])
        ];
    }

    private static function personalDataSchema()
    {
        return [
            Group::make()
                ->relationship('profile')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Grid::make(1)
                                ->columnSpan(2)
                                ->schema([
                                    Fieldset::make('Informasi Pribadi')
                                        ->columnSpan(2)
                                        ->schema([
                                            TextInput::make('nickname')
                                                ->label('Nama Panggilan'),
        
                                            Select::make('gender')
                                                ->label('Jenis Kelamin')
                                                ->options([
                                                    'laki_laki' => 'Laki-laki',
                                                    'perempuan' => 'Perempuan',
                                                ]),
        
                                            TextInput::make('place_of_birth')
                                                ->label('Tempat Lahir'),
        
                                            DatePicker::make('date_of_birth')
                                                ->label('Tanggal Lahir'),
        
                                            Select::make('religion_id')
                                                ->label('Agama')
                                                ->relationship('religion', 'name')
                                                ->searchable()
                                                ->preload(),
        
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
        
                                    Fieldset::make('Alamat')
                                        ->columnSpan(2)
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
        
                                    Fieldset::make('Kontak')
                                        ->columnSpan(2)
                                        ->schema([
                                            TextInput::make('phone')
                                                ->label('No. Telepon')
                                                ->maxLength(15),
        
                                            TextInput::make('whatsapp')
                                                ->label('No. WhatsApp')
                                                ->maxLength(15),
        
                                        ]),
                                ]),
        
                            Fieldset::make('Foto Profil')
                                ->schema([
                                    FileUpload::make('profile_picture')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->panelAspectRatio('4:4')
                                        ->image()
                                        ->multiple()
                                        ->maxFiles(1)
                                        ->disk('public')
                                        ->directory('profile-pictures')
                                        ->visibility('public')
                                        ->acceptedFileTypes(['image/jpeg', 'image/jpg', 'image/png'])
                                        ->maxSize(2048)
                                ])
                        ])
                ])
        ];
    }

    private static function employmentStatusSchema()
    {
        return [
            Grid::make(2)->relationship('profile')->schema([
                Fieldset::make('Status Kepegawaian')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('department_id')
                            ->label('Departemen')
                            ->options(function ($get) {
                                $step = $get('../../'); 
                                $companyId = $step['company_id'];
                                if (!$companyId) return [];

                                return OrgStructure::query()
                                    ->where('company_id', $companyId)
                                    ->where('level', OrgStructurLevel::LEVEL_DEPARTMENT->value)
                                    ->pluck('name', 'id');
                            })
                            ->reactive()
                            ->searchable()
                            ->disabled()
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
                            ->disabled()
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
                            ->disabled()
                            ->afterStateUpdated(function (callable $set, $state) {
                                // Update org_structure_id when sub_division changes
                                $set('org_structure_id', $state);
                            })
                            ->dehydrated(false),

                        Hidden::make('org_structure_id')
                            ->dehydrated(true)
                            ->afterStateHydrated(function ($state, $set, $record) {
                                // Set default value from existing record if available
                                if ($record?->org_structure_id) {
                                    $set('org_structure_id', $record->org_structure_id);
                                }
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, $get, $set) {
                                // Update org_structure_id based on the last filled field
                                $orgStructureId = $get('sub_division_id') 
                                    ?? $get('division_id') 
                                    ?? $get('department_id');
                                
                                if ($orgStructureId) {
                                    $set('org_structure_id', $orgStructureId);
                                }
                            })
                            ->live(true),

                        Select::make('job_position_id')
                            ->label('Jabatan')
                            ->relationship('jobPosition', 'name')
                            ->searchable()
                            ->disabled()
                            ->preload(),

                        Select::make('pangkat_id')
                            ->label('Pangkat')
                            ->relationship('pangkat', 'name')
                            ->searchable()
                            ->disabled()
                            ->preload(),

                        Select::make('golongan_id')
                            ->label('Golongan')
                            ->relationship('golongan', 'name')
                            ->searchable()
                            ->disabled()
                            ->preload(),

                        Select::make('kelas_id')
                            ->label('Kelas')
                            ->relationship('kelas', 'name')
                            ->searchable()
                            ->disabled()
                            ->preload(),

                        Select::make('employee_type_id')
                            ->label('Tipe Karyawan')
                            ->relationship('employeeType', 'name')
                            ->searchable()
                            ->disabled()
                            ->preload(),
                    ])
                ]),
        ];
    }

    private static function attachmentsSchema()
    {
        return [
            Repeater::make('user_documents')
                ->label('Lampiran Dokumen')
                ->relationship('userPersonalDocuments')
                ->addActionLabel('Tambah Lampiran Dokumen')
                ->schema([
                    TextInput::make('document_type')
                        ->label('Tipe Dokumen')
                        ->required()
                        ->columnSpanFull(),
                    
                    FileUpload::make('file_path')
                        ->label('Lampiran Dokumen')
                        ->columnSpanFull()
                        ->disk('public')
                        ->directory(fn () => 'user-documents/' . (Auth::id() ?? 'unknown'))
                        ->visibility('public')
                        ->openable()
                        ->maxSize(10240)
                        ->acceptedFileTypes(['application/pdf', 'image/*']),

                    Hidden::make('uploaded_by_user')
                        ->default(true),

                    Hidden::make('user_name')
                        ->default(fn () => Auth::user()?->name ?? 'Unknown'),
                ])
        ];
    }
}
