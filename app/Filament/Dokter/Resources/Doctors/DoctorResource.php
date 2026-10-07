<?php

namespace App\Filament\Dokter\Resources\Doctors;

use App\Enum\Doctor\DoctorDocumentType;
use App\Filament\Dokter\Resources\Doctors\Pages\CreateDoctor;
use App\Filament\Dokter\Resources\Doctors\Pages\EditDoctor;
use App\Filament\Dokter\Resources\Doctors\Pages\ListDoctors;
use App\Enum\Doctor\DoctorLicenseType;
use App\Filament\Dokter\Resources\Doctors\RelationManagers\CredentialingRelationManager;
use App\Filament\Dokter\Resources\Doctors\RelationManagers\DocumentsRelationManager;
use App\Filament\Dokter\Resources\Doctors\RelationManagers\LicensesRelationManager;
use App\Models\Doctor\Doctor;
use App\Models\Master\Companies;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Fase 1 master dokter (DB `doctor`). Login dokter = akun HRIS, ditautkan lewat hris_user_id.
 * NIK/NPWP/rekening sensitif: sengaja tidak ditampilkan di daftar.
 */
class DoctorResource extends Resource
{
    protected static ?string $model = Doctor::class;

    protected static ?string $navigationLabel = 'Daftar Dokter';

    protected static ?string $modelLabel = 'Dokter';

    // Beranda (kartu dokter) sudah jadi pintu masuk; resource ini cuma dipakai halaman tambah/edit.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        // Grid 3 kolom: Data Diri selebar penuh, lalu Foto | Akun | Penempatan sejajar. Kolom field di dalamnya diatur per section.
        return $schema->columns(3)->components([
            Section::make('Data Diri')->columnSpanFull()->columns(3)->schema([
                TextInput::make('gelar_depan')->label('Gelar Depan')->maxLength(100)->placeholder('dr.'),
                TextInput::make('nama')->label('Nama (tanpa gelar)')->required()->maxLength(150),
                TextInput::make('gelar_belakang')->label('Gelar Belakang')->maxLength(100)->placeholder('Sp.PD'),
                TextInput::make('nik')->label('NIK')->maxLength(32),
                TextInput::make('tempat_lahir')->label('Tempat Lahir')->maxLength(100),
                DatePicker::make('tanggal_lahir')->label('Tanggal Lahir'),
                Select::make('jenis_kelamin')->label('Jenis Kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                TextInput::make('agama')->label('Agama')->maxLength(50),
                Select::make('status')->label('Status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
                TextInput::make('nip')->label('NIP')->maxLength(50),
                // ponytail: spesialisasi utama (is_primary) belum ada UI, tambah kalau dibutuhkan.
                Select::make('specialties')->label('Spesialisasi')->columnSpan(2)
                    ->relationship('specialties', 'nama')->multiple()->preload(),
            ]),

            // Dokumen langsung saat tambah dokter (halaman edit punya tab Dokumen sendiri). Disimpan
            // CreateDoctor lewat DoctorDocumentService (terenkripsi, audit) setelah dokter tersimpan.
            Section::make('Dokumen')->columnSpanFull()->collapsed()->visibleOn('create')
                ->description('Opsional. Bisa juga diunggah nanti lewat tab Dokumen di halaman edit.')->schema([
                    Repeater::make('dokumen')->label('')->defaultItems(0)->addActionLabel('Tambah Dokumen')->columns(3)->schema([
                        Select::make('document_type')->label('Jenis Dokumen')->options(DoctorDocumentType::class)->required(),
                        TextInput::make('document_number')->label('Nomor Dokumen')->maxLength(150),
                        FileUpload::make('file')->label('File')->storeFiles(false)->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                            ->helperText('PDF/JPG/PNG, maks. 10 MB.'),
                        DatePicker::make('issued_at')->label('Tanggal Terbit'),
                        DatePicker::make('expired_at')->label('Berlaku Sampai')->afterOrEqual('issued_at'),
                    ]),
                ]),

            // Lisensi langsung saat tambah dokter; file scan-nya ikut jadi dokumen (DoctorLicenseService).
            Section::make('Lisensi (SIP / STR / STR-KT)')->columnSpanFull()->collapsed()->visibleOn('create')
                ->description('Opsional. Bisa juga ditambah nanti lewat tab Lisensi di halaman edit.')->schema([
                    Repeater::make('lisensi')->label('')->defaultItems(0)->addActionLabel('Tambah Lisensi')->columns(3)->schema([
                        Select::make('jenis')->label('Jenis')->options(DoctorLicenseType::class)->required(),
                        TextInput::make('nomor')->label('Nomor')->maxLength(150),
                        FileUpload::make('file')->label('File Scan (opsional)')->storeFiles(false)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240),
                        DatePicker::make('tanggal_terbit')->label('Tanggal Terbit'),
                        DatePicker::make('tanggal_berlaku')->label('Berlaku Mulai'),
                        DatePicker::make('tanggal_berakhir')->label('Berakhir')->afterOrEqual('tanggal_berlaku'),
                    ]),
                ]),

            Section::make('Foto')->columnSpan(1)->description('Opsional. Kalau kosong, kartu menampilkan siluet sesuai jenis kelamin.')->schema([
                // Disimpan ke users.profile_picture akun dokter (bukan kolom Doctor DB) lewat DoctorAccountService.
                FileUpload::make('foto')->label('')->image()->imageEditor()->disk('public')->directory('profile-pictures')->maxSize(2048)
                    ->visible(fn (string $operation, Get $get) => $operation === 'create' || filled($get('hris_user_id'))),
            ]),

            // Tambah dokter = sekaligus buat akun HRIS-nya (DoctorAccountService, dipanggil CreateDoctor).
            Section::make('Akun Login HRIS')->description('Akun dibuat otomatis bersama data dokter. Dokter login lewat HRIS/SSO memakai email ini.')
                ->columnSpan(1)->columns(1)->visibleOn('create')->schema([
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                        ->unique('users', 'email', modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')),
                    TextInput::make('password')->label('Password')->password()->revealable()->required()->minLength(8),
                    Select::make('company_id')->label('Perusahaan')->required()->searchable()->columnSpanFull()
                        ->options(fn () => Companies::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => auth()->user()?->company_id),
                ]),

            // Edit: tautkan akun lama yang sudah ada (dokter yang dibuat sebelum alur "sekalian akun").
            Section::make('Akun Login HRIS')->columnSpan(1)->visibleOn('edit')->schema([
                // Lintas DB: tidak bisa relationship(), jadi opsi dicari manual. Dokter disembunyikan
                // global scope 'dokter' di User, makanya onlyDokter() (akun yang ditandai is_dokter).
                Select::make('hris_user_id')->label('Akun HRIS')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::onlyDokter()->searchNameOrNip($search)->limit(20)->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->nip})"])->all())
                    ->getOptionLabelUsing(fn ($value) => ($u = User::withoutGlobalScopes()->find($value)) ? "{$u->name} ({$u->nip})" : $value)
                    ->helperText('Akun yang dipakai dokter untuk login (SSO/HRIS).'),
            ]),

            Section::make('Penempatan Rumah Sakit')->columnSpan(1)->schema([
                Repeater::make('hospitals')->relationship()->label('')->defaultItems(0)->addActionLabel('Tambah Penempatan')->columns(1)->schema([
                    Select::make('rumah_sakit_id')->label('Rumah Sakit')->required()->searchable()->columnSpanFull()
                        ->options(fn () => Companies::query()->orderBy('name')->pluck('name', 'id')),
                    DatePicker::make('tanggal_mulai')->label('Mulai'),
                    DatePicker::make('tanggal_selesai')->label('Selesai'),
                    Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama_lengkap')->label('Nama')->searchable(['nama', 'nip']),
                TextColumn::make('nip')->label('NIP')->placeholder('-'),
                TextColumn::make('specialties.nama')->label('Spesialisasi')->badge()->placeholder('-'),
                TextColumn::make('hospitals_count')->label('RS')->counts('hospitals'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'aktif' ? 'Aktif' : 'Nonaktif')
                    ->color(fn (string $state) => $state === 'aktif' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']),
                SelectFilter::make('specialty')->label('Spesialisasi')
                    ->relationship('specialties', 'nama'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LicensesRelationManager::class, CredentialingRelationManager::class, DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctors::route('/'),
            'create' => CreateDoctor::route('/create'),
            'edit' => EditDoctor::route('/{record}/edit'),
        ];
    }
}
