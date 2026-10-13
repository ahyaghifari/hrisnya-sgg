<?php

namespace App\Filament\Rekrutmen\Resources\Companies;

use App\Enum\Recruitment\OfferingLetterTemplate;
use App\Filament\Rekrutmen\Resources\Companies\Pages\EditCompany;
use App\Filament\Rekrutmen\Resources\Companies\Pages\ListCompanies;
use App\Models\Rekrutmen\Company;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Perusahaan (rekrutmen) — port dari CompanyResource project `rekrutmen` (FL3 -> FL4), DB
 * `recruitment`. SET TERPISAH dari m_companies HRIS (jembatan `hris_code`). Read-mostly:
 * nama & kode HRIS tidak diedit di sini, yang penting `requires_mcu` (menentukan alur MCU)
 * + kontak + warna brand. Upload logo/hero ditunda (butuh disk publik bersama).
 *
 * Edit lewat halaman penuh (List+Edit), bukan modal (2026-09-17) — form-nya sudah berat
 * (2 repeater Benefit/Ketentuan Umum + section Offering Letter), sama seperti CompanyResource
 * project rekrutmen yang juga pakai List+Create+Edit, bukan modal.
 *
 * Section "Offering Letter" (collapsible, default collapsed — bukan info yang perlu selalu
 * kelihatan): background diupload ke disk `rekrutmen` (privat, satu root fisik dengan disk
 * `local` project rekrutmen). Tanda tangan diupload ke disk `rekrutmen_public` (2026-09-17 —
 * root beda dari `rekrutmen`, tapi satu server juga, lihat config/filesystems.php) — jadi
 * SEKARANG bisa diupload langsung dari sini, tidak perlu lagi ke panel rekrutmen (beda dari
 * logo/hero perusahaan di atas yang masih ditunda). Benefit & Ketentuan Umum disatukan ke sini
 * sebagai Repeater (bukan resource Master terpisah) — Komponen Kompensasi PINDAH ke
 * JobOpeningResource (nominal beda tiap posisi/lowongan, bukan flat per perusahaan).
 */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Perusahaan';

    protected static ?string $modelLabel = 'Perusahaan';

    protected static ?string $pluralModelLabel = 'Perusahaan';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')->schema([
                TextInput::make('name')->label('Nama Perusahaan')->disabled(),
                TextInput::make('hris_code')->label('Kode HRIS')->disabled()->helperText('Jembatan ke m_companies HRIS.'),
            ])->columns(2),

            Section::make('Alur & Tampilan')->schema([
                Toggle::make('requires_mcu')->label('Wajib MCU')
                    ->helperText('Kalau nonaktif, kandidat lolos wawancara langsung ke Onboarding (lompati MCU).'),
                Toggle::make('requires_agreement')->label('Wajib Persetujuan Onboarding')
                    ->helperText('Kalau nonaktif, kandidat langsung mengisi Data Diri tanpa persetujuan (naskah persetujuan tidak ditampilkan).'),
                Toggle::make('is_active')->label('Perusahaan Aktif')
                    ->helperText('Nonaktif = lowongan disembunyikan dari halaman publik.'),
                ColorPicker::make('brand_color')->label('Warna Brand'),
            ])->columns(2),

            Section::make('Kontak')->schema([
                TextInput::make('email')->label('Email Kontak')->email(),
                TextInput::make('phone')->label('Telepon / WA')->tel(),
            ])->columns(2),

            Section::make('Offering Letter')
                ->description('Penandatangan & tampilan berlaku untuk semua offering letter perusahaan ini. Komponen kompensasi (nominal beda tiap posisi) diatur per lowongan di menu Lowongan, bukan di sini.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Select::make('offering_letter_template')
                        ->label('Gaya Surat')
                        ->options(fn () => collect(OfferingLetterTemplate::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->default(OfferingLetterTemplate::Sgg->value)
                        ->required()
                        ->columnSpanFull(),

                    FileUpload::make('offering_letter_background_path')
                        ->label('Background Surat (Letterhead)')
                        ->image()
                        ->disk('rekrutmen')
                        ->directory('offering-letter-bg')
                        ->imagePreviewHeight('120')
                        ->helperText('Gambar full A4 (mis. 1241x1755px) berisi kop surat, kontak, watermark. Kosongkan untuk pakai background default.')
                        ->nullable()
                        ->columnSpanFull(),

                    TextInput::make('offering_signatory_name')->label('Nama Penandatangan')->maxLength(255),
                    TextInput::make('offering_signatory_title')->label('Jabatan Penandatangan')->maxLength(255),

                    FileUpload::make('offering_signatory_signature_path')
                        ->label('Gambar Tanda Tangan')
                        ->image()
                        ->disk('rekrutmen_public')
                        ->directory('companies/signatures')
                        ->imagePreviewHeight('60')
                        ->nullable(),

                    TextInput::make('offering_benefit_note')
                        ->label('Catatan Benefit Berbintang')
                        ->helperText('Muncul di bawah daftar benefit kalau ada item yang ditandai bintang.')
                        ->placeholder('*) akan diberikan jika telah menjadi karyawan tetap.')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Repeater::make('offeringBenefits')
                        ->relationship('offeringBenefits')
                        ->label('Daftar Benefit')
                        ->schema([
                            TextInput::make('label')->label('Benefit')->required()->maxLength(255),
                            Toggle::make('is_starred')->label('Bintang (*)'),
                        ])
                        ->columns(2)
                        ->orderColumn('sort_order')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Benefit baru')
                        ->addActionLabel('Tambah benefit')
                        ->defaultItems(0)
                        ->columnSpanFull(),

                    Repeater::make('offeringTerms')
                        ->relationship('offeringTerms')
                        ->label('Ketentuan Umum')
                        ->schema([
                            TextInput::make('label')->label('Ketentuan')->required()->maxLength(255),
                        ])
                        ->orderColumn('sort_order')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'Ketentuan baru')
                        ->addActionLabel('Tambah ketentuan')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('hris_code')->label('Kode HRIS')->badge(),
                IconColumn::make('requires_mcu')->label('Wajib MCU')->boolean(),
                IconColumn::make('requires_agreement')->label('Persetujuan')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Telepon')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
