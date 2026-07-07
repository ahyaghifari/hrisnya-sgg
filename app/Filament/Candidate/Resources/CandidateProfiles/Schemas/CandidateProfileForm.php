<?php

namespace App\Filament\Candidate\Resources\CandidateProfiles\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CandidateProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof EditRecord
                    ? self::editSchema()
                    : self::viewSchema()
            )
            ->columns(2);
    }

    protected static function editSchema(): array
    {
        return [
            Wizard::make()
                ->columnSpanFull()
                ->steps([
                    Step::make('Informasi Akun')
                        ->icon('heroicon-o-user-circle')
                        ->completedIcon('heroicon-s-user-circle')
                        ->schema([
                            Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                    'lg' => 4,
                                ])
                                ->schema([
                                    Fieldset::make('Akun')
                                        ->columnSpan(3)
                                        ->columns(2)
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Nama Lengkap')
                                                ->required(),
        
                                            TextInput::make('email')
                                                ->label('Email')
                                                ->email()
                                                ->required(),
        
                                            TextInput::make('phone_number')
                                                ->label('No. Handphone')
                                                ->required(),
                                        ]),

                                    Fieldset::make('Foto')
                                        ->columnSpan(1)
                                        ->schema([
                                            FileUpload::make('profile_picture')
                                                ->columnSpanFull()
                                                ->hiddenLabel()
                                                ->image()
                                                ->disk('public')
                                                ->directory('candidate-profiles/' . Auth::user()->id . '/')
                                                ->openable(),
                                        ])
                                ]),
                        ]),

                    Step::make('Informasi Pribadi')
                        ->icon('heroicon-o-identification')
                        ->completedIcon('heroicon-s-identification')
                        ->schema([
                            Grid::make([
                                'default' => 1,
                                'md' => 2,
                            ])->schema([
                                Fieldset::make('Data Pribadi')  
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('nik')
                                            ->label('NIK')
                                            ->minLength(16)
                                            ->required(),
            
                                        TextInput::make('no_kk')
                                            ->label('No. KK')
                                            ->minLength(16),
            
                                        TextInput::make('birth_place')
                                            ->label('Tempat Lahir')
                                            ->required(),
            
                                        DatePicker::make('birth_date')
                                            ->label('Tanggal Lahir')
                                            ->required(),
            
                                        Select::make('gender')
                                            ->label('Jenis Kelamin')
                                            ->options([
                                                'laki_laki' => 'Laki-laki',
                                                'perempuan' => 'Perempuan',
                                            ])
                                            ->required(),
            
                                        Select::make('status_marital')
                                            ->label('Status Perkawinan')
                                            ->options([
                                                'Belum Menikah' => 'Belum Menikah',
                                                'Menikah' => 'Menikah',
                                                'Cerai' => 'Cerai',
                                            ])
                                            ->required(),
            
                                        TextInput::make('mother_name')
                                            ->label('Nama Ibu Kandung')
                                            ->columnSpanFull()
                                            ->required(),
                                    ]),

                                Fieldset::make('BPJS')
                                    ->columns(1)
                                    ->schema([
                                        TextInput::make('no_bpjskes')
                                            ->label('No. BPJS Kesehatan')
                                            ->columnSpanFull(),

                                        TextInput::make('faskes')
                                            ->label('Faskes')
                                            ->columnSpanFull(),

                                        TextInput::make('no_bpjstk')
                                            ->label('No. BPJS Ketenagakerjaan')
                                            ->columnSpanFull(),

                                        TextInput::make('company_bpjstk')
                                            ->label('Perusahaan BPJS Ketenagakerjaan')
                                            ->columnSpanFull(),

                                        TextInput::make('bsi_bank_number')
                                            ->label('No. Rekening BSI')
                                            ->columnSpanFull(),
                                    ])
                            ])
                        ]),

                    Step::make('Pendidikan & Alamat')
                        ->icon('heroicon-o-home')
                        ->completedIcon('heroicon-s-home')
                        ->schema([
                            Grid::make([
                                'default' => 1,
                                'md' => 2,
                                'lg' => 4,
                            ])->schema([
                                Fieldset::make('Data Pendidikan')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('education')
                                            ->label('Pendidikan Terakhir')
                                            ->required(),

                                        TextInput::make('major')
                                            ->label('Jurusan')
                                            ->required(),

                                        TextInput::make('university')
                                            ->label('Universitas')
                                            ->required(),

                                        TextInput::make('graduation_year')
                                            ->label('Tahun Lulus')
                                            ->required(),
                                    ]),

                                Fieldset::make('Alamat')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        Textarea::make('ktp_address')
                                            ->label('Alamat KTP')
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->required(),

                                        Textarea::make('domicile_address')
                                            ->label('Alamat Domisili')
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->required(),

                                        TextInput::make('village')
                                            ->label('Kelurahan/Desa')
                                            ->required(),

                                        TextInput::make('district')
                                            ->label('Kecamatan')
                                            ->required(),

                                        TextInput::make('city')
                                            ->label('Kota/Kabupaten')
                                            ->required(),

                                        TextInput::make('province')
                                            ->label('Provinsi')
                                            ->required(),
                                    ])
                            ])
                        ]),

                    Step::make('Dokumen')
                        ->icon('heroicon-o-document-text')
                        ->completedIcon('heroicon-s-document-text')
                        ->schema([
                            Fieldset::make('Dokumen')
                                ->columns(2)
                                ->schema([
                                    FileUpload::make('ktp')
                                        ->label('KTP')
                                        ->directory('candidate-documents/ktp/' . Auth::id())
                                        ->disk('public')
                                        ->openable(),

                                    FileUpload::make('kk')
                                        ->label('Kartu Keluarga (KK)')
                                        ->directory('candidate-documents/kk/' . Auth::id())
                                        ->disk('public')
                                        ->openable(),

                                    FileUpload::make('bpjstk')
                                        ->label('BPJS Ketenagakerjaan')
                                        ->directory('candidate-documents/bpjstk/' . Auth::id())
                                        ->disk('public')
                                        ->openable(),

                                    FileUpload::make('ijazah')
                                        ->label('Ijazah')
                                        ->directory('candidate-documents/ijazah/' . Auth::id())
                                        ->disk('public')
                                        ->openable(),

                                    Repeater::make('additional_documents')
                                        ->hiddenLabel()
                                        ->columnSpanFull()
                                        ->reorderable(false)
                                        ->addActionLabel('Tambah Dokumen Lainnya')
                                        ->itemLabel('Dokumen Lainnya (CV, Sertifikat, dll.)')
                                        ->schema([
                                            TextInput::make('document_label_name')
                                                ->label('Label Dokumen')
                                                ->placeholder('Contoh: CV, Sertifikat Pelatihan, dll.'),

                                            FileUpload::make('document_file')
                                                ->label('Dokumen')
                                                ->directory('candidate-documents/additional/' . Auth::id())
                                                ->disk('public')
                                                ->openable(),
                                        ])
                                ]),
                        ])
                ])
        ];
    }
    
    protected static function viewSchema(): array
    {
        return [
            Section::make('Data Diri Kandidat')
                ->icon('heroicon-o-user-circle')
                ->iconColor('black')
                ->description('Informasi lengkap mengenai data diri kandidat. Untuk edit data / dokumen, silakan gunakan tombol "Edit" di pojok kanan atas.')
                ->columnSpanFull(),

            Grid::make([
                    'default' => 1,
                    'md' => 2,
                    'lg' => 4,
                ])
                ->columnSpanFull()
                ->schema([
                    Section::make('Informasi Akun')
                        ->icon('heroicon-o-user-circle')
                        ->iconColor('black')
                        ->columnSpan(3)
                        ->columns(2)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Nama Lengkap'),
        
                            TextEntry::make('email')
                                ->label('Email'),
        
                            TextEntry::make('phone_number')
                                ->label('No. Handphone'),
                        ]),

                    Section::make('Foto')
                        ->icon('heroicon-o-photo')
                        ->iconColor('black')
                        ->columnSpan(1)
                        ->schema([
                            ImageEntry::make('profile_picture')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->visibility('public')
                                ->disk('public')
                                ->imageWidth(175)
                                ->imageHeight(200)
                                ->alignCenter()
                        ]),
                ]),

            Section::make('Informasi Pribadi')
                ->icon('heroicon-o-identification')
                ->iconColor('black')
                ->columns(2)
                ->schema([
                    TextEntry::make('nik')
                        ->label('NIK'),

                    TextEntry::make('no_kk')
                        ->label('No. KK'),

                    TextEntry::make('birth_place')
                        ->label('Tempat Lahir'),

                    TextEntry::make('birth_date')
                        ->label('Tanggal Lahir')
                        ->date('j F Y'),

                    TextEntry::make('gender')
                        ->label('Jenis Kelamin'),

                    TextEntry::make('status_marital')
                        ->label('Status Perkawinan'),

                    TextEntry::make('mother_name')
                        ->label('Nama Ibu Kandung'),
                ]),

            Section::make('BPJS & Rekening Bank')
                ->icon('heroicon-o-document-text')
                ->iconColor('black')
                ->columns(2)
                ->schema([
                    TextEntry::make('no_bpjskes')
                        ->label('No. BPJS Kesehatan'),

                    TextEntry::make('bsi_bank_number')
                        ->label('No. Rekening BSI'),

                    TextEntry::make('faskes')
                        ->label('Faskes')
                        ->columnSpanFull(),

                    TextEntry::make('no_bpjstk')
                        ->label('No. BPJS Ketenagakerjaan')
                        ->columnSpanFull(),

                    TextEntry::make('company_bpjstk')
                        ->label('Perusahaan BPJS Ketenagakerjaan')
                        ->columnSpanFull(),
                ]),

            Section::make('Data Pendidikan')
                ->icon('heroicon-o-academic-cap')
                ->iconColor('black')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('education')
                        ->label('Pendidikan Terakhir'),

                    TextEntry::make('major')
                        ->label('Jurusan'),

                    TextEntry::make('university')
                        ->label('Universitas'),

                    TextEntry::make('graduation_year')
                        ->label('Tahun Lulus'),
                ]),

            Section::make('Alamat')
                ->icon('heroicon-o-home')
                ->iconColor('black')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('ktp_address')
                        ->label('Alamat KTP')
                        ->columnSpanFull(),

                    TextEntry::make('domicile_address')
                        ->label('Alamat Domisili')
                        ->columnSpanFull(),

                    TextEntry::make('village')
                        ->label('Kelurahan/Desa'),

                    TextEntry::make('district')
                        ->label('Kecamatan'),

                    TextEntry::make('city')
                        ->label('Kota/Kabupaten'),

                    TextEntry::make('province')
                        ->label('Provinsi'),
                ]),

            Section::make('Dokumen')
                ->icon('heroicon-o-document-text')
                ->iconColor('black')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('documents')
                        ->hiddenLabel()
                        ->grid([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->schema([
                            TextEntry::make('document_label')
                                ->icon('heroicon-o-document-text')
                                ->iconColor('black')
                                ->label('Nama Dokumen'),

                            TextEntry::make('file_path')
                                ->icon('heroicon-o-link')
                                ->iconColor('black')
                                ->hiddenLabel()
                                ->formatStateUsing(fn (string $state): string => basename($state))
                                ->url(fn (string $state): string => asset(Storage::url($state)))
                                ->openUrlInNewTab(),
                        ])
                ])
        ];
    }
}
