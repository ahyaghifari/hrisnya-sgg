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
use App\Services\Doctor\DoctorDocumentService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
        // Grid 3 kolom; setiap section selebar penuh.
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
            Section::make('Lisensi (STR / STR-KT)')->columnSpanFull()->collapsed()->visibleOn('create')
                ->description('Opsional. SIP diisi di section Penempatan Rumah Sakit.')->schema([
                    Repeater::make('lisensi')->label('')->defaultItems(0)->addActionLabel('Tambah Lisensi')->columns(3)->schema([
                        Select::make('jenis')->label('Jenis')->required()
                            ->options(collect([DoctorLicenseType::STR, DoctorLicenseType::STR_KT])->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()])->all()),
                        TextInput::make('nomor')->label('Nomor')->maxLength(150),
                        FileUpload::make('file')->label('File Scan (opsional)')->storeFiles(false)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240),
                        DatePicker::make('tanggal_terbit')->label('Tanggal Terbit'),
                        DatePicker::make('tanggal_berlaku')->label('Berlaku Mulai'),
                        DatePicker::make('tanggal_berakhir')->label('Berakhir')->afterOrEqual('tanggal_berlaku'),
                    ]),
                ]),

            // Akun login HRIS & foto di-hold (foto = users.profile_picture, butuh akun). SIP terikat tempat praktik,
            // jadi disimpan sebagai doctor_licenses (jenis SIP) milik penempatan, bukan di tab Lisensi/Dokumen.
            Section::make('Penempatan Rumah Sakit')->columnSpanFull()->schema([
                Repeater::make('hospitals')->relationship()->label('')->defaultItems(0)->addActionLabel('Tambah Penempatan')->columns(4)->schema([
                    Select::make('rumah_sakit_id')->label('Rumah Sakit')->required()->searchable()->columnSpan(2)
                        ->options(fn () => Companies::query()->orderBy('name')->pluck('name', 'id')),
                    DatePicker::make('tanggal_mulai')->label('Mulai'),
                    DatePicker::make('tanggal_selesai')->label('Selesai'),
                    Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),

                    Repeater::make('sips')->relationship('sips')->label('SIP di rumah sakit ini')->defaultItems(0)
                        ->addActionLabel('Tambah SIP')->columns(4)->columnSpanFull()
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, $livewire) => static::sipData($data, $livewire->getRecord()))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, $livewire) => static::sipData($data, $livewire->getRecord()))
                        ->schema([
                            TextInput::make('nomor')->label('Nomor SIP')->required()->maxLength(150),
                            DatePicker::make('tanggal_terbit')->label('Tanggal Terbit'),
                            DatePicker::make('tanggal_berlaku')->label('Berlaku Mulai'),
                            DatePicker::make('tanggal_berakhir')->label('Berakhir')->afterOrEqual('tanggal_berlaku'),
                            Select::make('status')->options(['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'])->default('aktif')->required(),
                            // Disimpan terenkripsi lewat DoctorDocumentService (sipData); file lama tetap kalau tidak diganti.
                            FileUpload::make('file')->label('Scan SIP (opsional)')->storeFiles(false)->columnSpan(3)
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240),
                        ]),
                ]),
            ]),
        ]);
    }

    /** Lengkapi data SIP dari repeater penempatan: jenis/doctor_id, dan file scan -> dokumen terenkripsi. */
    private static function sipData(array $data, Doctor $doctor): array
    {
        $file = $data['file'] ?? null;
        unset($data['file']);

        $data['jenis'] = DoctorLicenseType::SIP->value;
        $data['doctor_id'] = $doctor->id;

        if ($file) {
            $data['doctor_document_id'] = app(DoctorDocumentService::class)->store($doctor, [
                'document_type' => DoctorLicenseType::SIP->value,
                'document_number' => $data['nomor'] ?? null,
                'issued_at' => $data['tanggal_terbit'] ?? null,
                'expired_at' => $data['tanggal_berakhir'] ?? null,
            ], $file)->id;
        }

        return $data;
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
            ->recordActions([EditAction::make()]);
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
