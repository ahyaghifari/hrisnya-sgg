<?php

namespace App\Filament\Candidate\Resources\JobApplicants\Schemas;

use App\Enum\Recruitment\ProcessStatus;
use App\Enum\Recruitment\ProcessType;
use App\Enum\Recruitment\RecruitmentProcessStatus;
use App\Filament\Candidate\Resources\CandidateProfiles\CandidateProfileResource;
use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use App\Models\Candidate\CandidateRecruitmentProcess;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Arabic;

class JobApplicantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof CreateRecord
                    ? self::createSchema()
                    : self::editSchema()
            )
            ->columns(2);
    }

    protected static function createSchema(): array
    {
        return [
            ...self::formSchema(),
            Wizard::make()
                ->columnSpanFull()
                ->skippable()
                ->steps([
                    // self::detailJobSchema(),
                    self::applicationSchema(),
                    self::educationSchema(),
                    self::experienceSchema(),
                    self::additionalInfoSchema(),
                ])
        ];
    }

    protected static function editSchema(): array
    {
        return [
            Section::make('Informasi Lowongan')
                ->description('Berikut adalah informasi mengenai lowongan pekerjaan yang Anda lamar.')
                ->icon('heroicon-o-briefcase')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('jobVacancy.jobPosition.name')
                                ->label('Posisi yang Dilamar'),

                            TextEntry::make('jobVacancy.jobPosition.orgStructure.name')
                                ->label('Unit'),

                            TextEntry::make('jobVacancy.company.name')
                                ->label('Perusahaan'),

                            TextEntry::make('current_stage')
                                ->label('Tahap Saat Ini')
                                ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state)))
                                ->badge(),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($state) => ProcessStatus::tryFrom($state)?->getLabel() ?? '-')
                                ->color(fn ($state): string => match ($state) {
                                    'pending' => 'warning',
                                    'accepted' => 'success',
                                    'rejected' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('applied_at')
                                ->label('Tanggal Melamar')
                                ->dateTime('d F Y H:i'),

                            TextEntry::make('finished_at')
                                ->label('Tanggal Selesai')
                                ->dateTime('d F Y H:i')
                                ->placeholder('-'),
                        ]),
                ]),


            Section::make('Proses Lamaran')
                ->description('Berikut adalah informasi mengenai proses lamaran pekerjaan Anda.')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->columnSpanFull()
                ->schema([
                    Repeater::make('recruitmentProcesses')
                        ->relationship(name: 'recruitmentProcesses', modifyQueryUsing: fn ($query) => $query->orderBy('id', 'desc'))
                        ->columnSpanFull()
                        ->addable(false)
                        ->deletable(false)
                        ->itemLabel('Proses Rekrutmen')
                        ->hiddenLabel()
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->schema([
                            Fieldset::make('Detail Proses')
                                ->columnSpanFull()
                                ->schema([
                                    TextEntry::make('process_name')
                                        ->label('Proses'),
        
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->formatStateUsing(fn ($state) => RecruitmentProcessStatus::tryFrom($state)?->getLabel() ?? '-')
                                        ->badge()
                                        ->color(fn ($state): string => RecruitmentProcessStatus::tryFrom($state)?->getColor() ?? 'gray')
                                        ->icon(fn ($state): string => RecruitmentProcessStatus::tryFrom($state)?->getIcon() ?? null),
        
                                    TextEntry::make('metadata.scheduled_at')
                                        ->label('Jadwal')
                                        ->dateTime('d F Y')
                                        ->placeholder('-'),
        
                                    TextEntry::make('metadata.maximum_completion_at')
                                        ->label('Batas Waktu Penyelesaian/Konfirmasi')
                                        ->dateTime('d F Y')
                                        ->placeholder('-'),

                                    TextEntry::make('metadata.rescheduled_at')
                                        ->label('Jadwal Ulang MCU')
                                        ->visible(fn ($state) => !empty($state))
                                        ->dateTime('d F Y')
                                        ->placeholder('-'),
        
                                    TextEntry::make('link')
                                        ->label('Link')
                                        ->visible(fn ($state) => !empty($state))
                                        ->url(fn ($state): string => $state)
                                        ->openUrlInNewTab()
                                        ->placeholder('-'),

                                    TextEntry::make('attachments')
                                        ->label('Lampiran')
                                        ->visible(fn ($state) => !empty($state))
                                        ->url(fn ($state): string => asset(Storage::url($state)))
                                        ->formatStateUsing(fn ($state): string => basename($state))
                                        ->openUrlInNewTab()
                                        ->placeholder('-'),
        
                                    TextEntry::make('notes')
                                        ->label('Catatan')
                                        ->placeholder('-'),

                                    TextEntry::make('metadata.reschedule_notes')
                                        ->label('Alasan Penjadwalan Ulang')
                                        ->visible(fn ($state) => !empty($state))
                                        ->placeholder('-'),

                                    Action::make('mark_as_completed')
                                        ->label('Tandai Sebagai Selesai')
                                        ->color('success')
                                        ->icon(Heroicon::OutlinedCheckCircle)
                                        ->visible(fn (callable $get) => 
                                            $get('status') === RecruitmentProcessStatus::ASSIGNED->value
                                            && !in_array($get('process_type'), [
                                                ProcessType::OFFERING->value,
                                                ProcessType::MCU->value,
                                                ProcessType::SIGNING->value,
                                            ])
                                        )
                                        ->requiresConfirmation()
                                        ->modalHeading('Tandai Proses Sebagai Selesai')
                                        ->modalDescription('Apakah Anda yakin ingin menandai proses ini sebagai selesai?')
                                        ->action(function (callable $get) {
                                            $process = CandidateRecruitmentProcess::find($get('id'));
                                            if ($process) {
                                                $metadata = $process->metadata ?? [];
                                                $metadata['submitted_at'] = now();
                                                $process->update([
                                                    'status' => RecruitmentProcessStatus::SUBMITTED->value,
                                                    'metadata' => $metadata,
                                                ]);
                                            }
                                            
                                            redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                        }),

                                    Action::make('accept_offer')
                                        ->label('Terima Penawaran')
                                        ->color('success')
                                        ->icon(Heroicon::OutlinedCheckCircle)
                                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::OFFERING->value && $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                                        ->requiresConfirmation()
                                        ->modalHeading('Terima Penawaran')
                                        ->modalDescription('Apakah Anda yakin ingin menerima penawaran ini? Tambahkan catatan atau lampiran jika diperlukan.')
                                        ->schema([
                                            Textarea::make('notes')
                                                ->label('Catatan (Opsional)'),

                                            FileUpload::make('attachments')
                                                ->label('Lampiran (Opsional)')
                                                ->directory('recruitment-process-attachments/offer-acceptance')
                                                ->disk('public')
                                                ->maxSize(10240)
                                        ])
                                        ->action(function (callable $get, array $data) {
                                            $process = CandidateRecruitmentProcess::find($get('id'));
                                            $application = $process ? $process->application : null;
                                            if ($process) {
                                                $metadata = $process->metadata ?? [];
                                                $metadata['submitted_at'] = now();
                                                $metadata['offer_acceptance_notes'] = $data['notes'] ?? null;
                                                if (!empty($data['attachments'])) {
                                                    $metadata['offer_acceptance_attachments'] = $data['attachments'];
                                                }

                                                $process->update([
                                                    'status' => RecruitmentProcessStatus::ACCEPTED->value,
                                                    'metadata' => $metadata,
                                                ]);
                                            }

                                            if ($application) {
                                                $application->update([
                                                    'current_stage' => 'offered',
                                                    'status' => ProcessStatus::OFFERED->value,
                                                ]);
                                            }
                                            
                                            redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                        }),

                                    Action::make('reject_offer')
                                        ->label('Tolak Penawaran')
                                        ->color('danger')
                                        ->icon(Heroicon::OutlinedXCircle)
                                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::OFFERING->value && $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                                        ->requiresConfirmation()
                                        ->modalHeading('Tolak Penawaran')
                                        ->modalDescription('Apakah Anda yakin ingin menolak penawaran ini?')
                                        ->action(function (callable $get) {
                                            $process = CandidateRecruitmentProcess::find($get('id'));
                                            $application = $process ? $process->application : null;
                                            if ($process) {
                                                $metadata = $process->metadata ?? [];
                                                $metadata['submitted_at'] = now();
                                                $process->update([
                                                    'status' => RecruitmentProcessStatus::REJECTED->value,
                                                    'metadata' => $metadata,
                                                ]);
                                            }

                                            if ($application) {
                                                $application->update([
                                                    'current_stage' => 'offered',
                                                    'status' => ProcessStatus::WITHDRAWN->value,
                                                ]);
                                            }

                                            redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                        }),

                                    Action::make('accepted_schedule_mcu')
                                        ->label('Konfirmasi Jadwal MCU')
                                        ->color('success')
                                        ->icon(Heroicon::OutlinedCheckCircle)
                                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::MCU->value && $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                                        ->requiresConfirmation()
                                        ->modalHeading('Terima Jadwal MCU')
                                        ->modalDescription('Apakah Anda yakin ingin menerima jadwal MCU ini?')
                                        ->schema([
                                            Select::make('confirmation')
                                                ->label('Konfirmasi')
                                                ->options([
                                                    'accepted' => 'Terima Jadwal',
                                                    'reschedule' => 'Minta Penjadwalan Ulang',
                                                ])
                                                ->reactive(),

                                            DatePicker::make('rescheduled_at')
                                                ->label('Jadwal Ulang MCU')
                                                ->visible(fn (callable $get) => $get('confirmation') === 'reschedule')
                                                ->required(fn (callable $get) => $get('confirmation') === 'reschedule')
                                                ->minDate(now()->addDay()),

                                            Textarea::make('notes')
                                                ->label('Alasan Penolakan')
                                                ->visible(fn (callable $get) => $get('confirmation') === 'reschedule')
                                                ->required(fn (callable $get) => $get('confirmation') === 'rejected')
                                        ])
                                        ->action(function (callable $get, array $data) {
                                            $process = CandidateRecruitmentProcess::find($get('id'));
                                            
                                            if ($process) {
                                                $metadata = $process->metadata ?? [];

                                                if ($data['confirmation'] === 'accepted') {
                                                    $process->update([
                                                        'status' => RecruitmentProcessStatus::ACCEPTED->value,
                                                    ]);
                                                } elseif ($data['confirmation'] === 'reschedule') {
                                                    $metadata['rescheduled_at'] = $data['rescheduled_at'] ?? null;
                                                    $metadata['reschedule_notes'] = $data['notes'] ?? null;
                                                    $process->update([
                                                        'status' => RecruitmentProcessStatus::REQUEST_RESCHEDULED->value,
                                                        'metadata' => $metadata,
                                                    ]);
                                                }
                                            }
                                            
                                            redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                        }),

                                    Grid::make()
                                        ->columnSpanFull()
                                        ->schema([
                                            Action::make('accepted_schedule_signing')
                                                ->label('Konfirmasi Penandatanganan Kontrak')
                                                ->color('success')
                                                ->icon(Heroicon::OutlinedCheckCircle)
                                                ->visible(fn (callable $get) => $get('process_type') === ProcessType::SIGNING->value && $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                                                ->requiresConfirmation()
                                                ->modalHeading('Terima Jadwal Penandatanganan Kontrak')
                                                ->modalDescription('Apakah Anda yakin ingin menerima jadwal penandatanganan kontrak ini?')
                                                ->action(function (callable $get) {
                                                    $process = CandidateRecruitmentProcess::find($get('id'));
                                                    if ($process) {
                                                        $metadata = $process->metadata ?? [];
                                                        $metadata['submitted_at'] = now();
                                                        $process->update([
                                                            'status' => RecruitmentProcessStatus::ACCEPTED->value,
                                                            'metadata' => $metadata,
                                                        ]);
                                                    }
                                                    
                                                    redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                                }),

                                            Action::make('reject_signing')
                                                ->label('Tolak Penandatanganan Kontrak')
                                                ->color('danger')
                                                ->icon(Heroicon::OutlinedXCircle)
                                                ->visible(fn (callable $get) => $get('process_type') === ProcessType::SIGNING->value && $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                                                ->requiresConfirmation()
                                                ->modalHeading('Tolak Jadwal Penandatanganan Kontrak')
                                                ->modalDescription('Apakah Anda yakin ingin menolak jadwal penandatanganan kontrak ini?')
                                                ->schema([
                                                    Textarea::make('notes')
                                                        ->label('Alasan Penolakan')
                                                        ->required(),
                                                ])
                                                ->action(function (callable $get, array $data) {
                                                    $process = CandidateRecruitmentProcess::find($get('id'));
                                                    if ($process) {
                                                        $metadata = $process->metadata ?? [];
                                                        $metadata['submitted_at'] = now();
                                                        $metadata['signing_rejection_reason'] = $data['notes'] ?? null;
                                                        $process->update([
                                                            'status' => RecruitmentProcessStatus::REJECTED->value,
                                                            'metadata' => $metadata,
                                                        ]);
                                                    }
                                                    
                                                    redirect(JobApplicantResource::getUrl('edit', ['record' => $process->application_id]));
                                                })
                                        ])
                                ])
                        ])
                ]),
        ];
    }

    protected static function formSchema(): array
    {
        return [
            Hidden::make('candidate_id')
                ->default(Auth::guard('candidate')->user()->id)
                ->dehydrated(),

            Hidden::make('job_vacancy_id')
                ->default(fn () => request()->query('job_vacancy_id'))
                ->dehydrated(),

            Hidden::make('current_stage')
                ->default('submitted')
                ->dehydrated(),

            Hidden::make('status')
                ->default(ProcessStatus::SUBMITTED->value)
                ->dehydrated(),

            Hidden::make('applied_at')
                ->default(now())
                ->dehydrated(),
        ];
    }

    protected static function detailJobSchema(): Step
    {
        return Step::make('Detail Lowongan')
            ->icon('heroicon-o-briefcase')
            ->completedIcon('heroicon-s-briefcase')
            ->schema([
                Fieldset::make('Posisi Lowongan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('jobPosition.name')
                            ->state(function ($livewire) {
                                $record = $livewire->getRecord();
                                return $record ? $record->jobPosition->name : '-';
                            })
                            ->label('Posisi Lowongan'),

                        TextEntry::make('jobPosition.orgStructure.name')
                            ->state(function ($livewire) {
                                $record = $livewire->getRecord();
                                return $record ? $record->jobPosition->orgStructure->name : '-';
                            })
                            ->label('Unit'),

                        TextEntry::make('company.name')
                            ->state(function ($livewire) {
                                $record = $livewire->getRecord();
                                return $record ? $record->company->name : '-';
                            })
                            ->label('Perusahaan')
                            ->columnSpanFull(),
                    ]),

                Fieldset::make('Deskripsi')
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->label('Deskripsi Lowongan')
                            ->state(function ($livewire) {
                                $record = $livewire->getRecord();
                                return $record ? $record->description : '-';
                            })
                            ->html()
                            ->columnSpanFull(),

                        TextEntry::make('requirements')
                            ->label('Persyaratan:')
                            ->state(function ($livewire) {
                                $record = $livewire->getRecord();
                                return $record ? $record->requirements : '-';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
            ]);
    }

    protected static function applicationSchema(): Step
    {
        return Step::make('Informasi Diri')
            ->icon('heroicon-o-user-circle')
            ->completedIcon('heroicon-s-user-circle')
            ->columnSpanFull()
            ->schema([
                Section::make('Informasi Data Diri')
                    ->description('Pastikan informasi data diri Anda sudah benar.'),

                Grid::make([
                    'default' => 1,
                    'md' => 2,
                    'lg' => 4,
                ])
                ->columnSpanFull()
                ->schema([
                    Fieldset::make('Informasi Pribadi')
                        ->columnSpan([
                            'default' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                        ->schema([
                            TextInput::make('candidate.name')
                                ->label('Nama Lengkap')
                                ->columnSpanFull()
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->name : '-';
                                }),

                            TextInput::make('candidate.nickname')
                                ->label('Nama Panggilan')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->nickname : '-';
                                }),

                            Select::make('gender')
                                ->label('Jenis Kelamin')
                                ->options([
                                    'laki_laki' => 'Laki-laki',
                                    'perempuan' => 'Perempuan',
                                ])
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->gender : '-';
                                })
                                ->required(),

                            TextInput::make('candidate.blood_type')
                                ->label('Golongan Darah')
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->blood_type : '-';
                                }),

                            TextInput::make('candidate.birth_place')
                                ->label('Tempat Lahir')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->birth_place : '-';
                                }),

                            DatePicker::make('candidate.birth_date')
                                ->label('Tanggal Lahir')
                                ->required()
                                ->default(function () {  
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->birth_date : '-';
                                }),

                            TextInput::make('candidate.body_height_weight')
                                ->label('Tinggi Badan / Berat Badan')
                                ->helperText('Format: Tinggi(cm) / Berat(kg), contoh: 170/65')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->body_height_weight : '-';
                                }),

                            Select::make('status_marital')
                                ->label('Status Perkawinan')
                                ->options([
                                    'Belum Menikah' => 'Belum Menikah',
                                    'Menikah' => 'Menikah',
                                    'Cerai' => 'Cerai',
                                ])
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->status_marital : '-';
                                })
                                ->live()
                                ->reactive()
                                ->required(),

                            DatePicker::make('candidate.marriage_date')
                                ->label('Tanggal Perkawinan')
                                ->live()
                                ->reactive()
                                ->visible(fn (callable $get) => $get('status_marital') === 'Menikah')
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->marriage_date : '-';
                                }),

                            TextInput::make('candidate.email')
                                ->label('Email')
                                ->required()
                                ->columnSpanFull()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->email : '-';
                                }),

                            TextInput::make('candidate.phone_number')
                                ->label('Nomor Telepon')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->phone_number : '-';
                                }),

                            TextInput::make('candidate.nik')
                                ->label('NO KTP')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->nik : '-';
                                }),

                            TextInput::make('candidate.social_media_accounts')
                                ->label('Akun Media Sosial')
                                ->helperText('Pisahkan dengan koma jika lebih dari satu, contoh: Instagram: @contoh, LinkedIn: linkedin.com/in/contoh')
                                ->columnSpanFull()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->social_media_accounts : '-';
                                }),

                            TextInput::make('candidate.last_education')
                                ->label('Pendidikan Terakhir')
                                ->required()
                                ->columnSpanFull()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->last_education : '-';
                                }),

                            Textarea::make('candidate.domicile_address')
                                ->label('Alamat Lengkap')
                                ->required()
                                ->columnSpanFull()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->domicile_address : '-';
                                }),

                            TextInput::make('candidate.nationality')
                                ->label('Kewarganegaraan')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->nationality : '-';
                                }),

                            TextInput::make('candidate.religion')
                                ->label('Agama')
                                ->required()
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate ? $candidate->religion : '-';
                                }),
                        ]),

                    Fieldset::make('Foto')
                        ->columnSpan(1)
                        ->schema([
                            FileUpload::make('candidate.profile_picture')
                                ->columnSpanFull()
                                ->hiddenLabel()
                                ->image()
                                ->disk('public')
                                ->directory('candidate-profiles/' . Auth::user()->id . '/')
                                ->default(function () {
                                    $candidate = Auth::guard('candidate')->user();
                                    return $candidate && $candidate->profile_picture ? asset(Storage::url($candidate->profile_picture)) : null;
                                })
                                ->openable(),
                        ])
                    ]),

                Fieldset::make('Hubungan Keluarga (Termasuk Diri Anda Sendiri)')
                    ->relationship('candidate')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('family_members')
                            ->hiddenLabel()
                            ->relationship('familyMembers')
                            ->itemLabel('Anggota Keluarga')
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Anggota Keluarga')
                            ->columns(2)
                            ->default(function () {
                                $candidate = Auth::guard('candidate')->user();
                                return $candidate ? $candidate->familyMembers->toArray() : [];
                            })
                            ->schema([
                                TextInput::make('relation')
                                    ->label('Hubungan Keluarga'),

                                TextInput::make('gender')
                                    ->label('Jenis Kelamin'),

                                TextInput::make('name')
                                    ->label('Nama')
                                    ->columnSpanFull(),

                                DatePicker::make('birth_date')
                                    ->label('Tanggal Lahir'),

                                TextInput::make('education')
                                    ->label('Pendidikan'),

                                TextInput::make('job')
                                    ->label('Pekerjaan'),

                                TextInput::make('phone')
                                    ->label('Nomor Telepon'),
                            ])
                    ])
            ]);
    }

    protected static function educationSchema(): Step
    {
        return Step::make('Pendidikan')
            ->icon('heroicon-o-academic-cap')
            ->completedIcon('heroicon-s-academic-cap')
            ->columnSpanFull()
            ->schema([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Pendidikan Formal')
                            ->schema([
                                Fieldset::make('Pendidikan Formal')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('educations')
                                            ->hiddenLabel()
                                            ->relationship('educations')
                                            ->itemLabel('Pendidikan Formal')
                                            ->columnSpanFull()
                                            ->addActionLabel('Tambah Pendidikan Formal')
                                            ->columns(2)
                                            ->default(function () {
                                                $candidate = Auth::guard('candidate')->user();
                                                return $candidate ? $candidate->educations->toArray() : [];
                                            })
                                            ->schema([
                                                Select::make('level')
                                                    ->label('Tingkat Pendidikan')
                                                    ->columnSpanFull()
                                                    ->options([
                                                        'SMK' => 'SMA/SMK',
                                                        'Diploma' => 'Diploma - D1/D2/D3',
                                                        'Sarjana' => 'Sarjana - S1',
                                                        'Magister' => 'Magister - S2',
                                                        'Doktor' => 'Doktor - S3',
                                                    ]),
                
                                                TextInput::make('school_name')
                                                    ->label('Nama Sekolah/Universitas')
                                                    ->columnSpanFull(),
                
                                                TextInput::make('major')
                                                    ->label('Jurusan'),
                
                                                TextInput::make('gpa')
                                                    ->label('IPK/GPA'),
                
                                                TextInput::make('start_year')
                                                    ->label('Tahun Masuk'),
                
                                                TextInput::make('end_year')
                                                    ->label('Tahun Lulus'),
                                            ])
                                    ]),
                            ]),

                        Tab::make('Pendidikan Informal / Pelatihan')
                            ->schema([
                                Fieldset::make('Pendidikan Informal / Pelatihan')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('trainings')
                                            ->hiddenLabel()
                                            ->relationship('trainings')
                                            ->itemLabel('Pendidikan Informal / Pelatihan')
                                            ->columnSpanFull()
                                            ->addActionLabel('Tambah Pendidikan Informal / Pelatihan')
                                            ->columns(2)
                                            ->default(function () {
                                                $candidate = Auth::guard('candidate')->user();
                                                return $candidate ? $candidate->trainings->toArray() : [];
                                            })
                                            ->schema([
                                                TextInput::make('type')
                                                    ->label('Jenis Pelatihan'),
                
                                                TextInput::make('institution')
                                                    ->label('Institusi Penyelenggara'),
                
                                                TextInput::make('material')
                                                    ->label('Materi Pelatihan')
                                                    ->columnSpanFull(),
                
                                                TextInput::make('duration')
                                                    ->label('Durasi Pelatihan')
                                                    ->helperText('Contoh: 3 Hari, 2 Minggu, 1 Bulan'),
                
                                                TextInput::make('location')
                                                    ->label('Lokasi Pelatihan'),
                                            ])
                                    ])
                            ]),

                        Tab::make('Organisasi')
                            ->schema([
                                Fieldset::make('Pengalaman Organisasi')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('organizations')
                                            ->hiddenLabel()
                                            ->relationship('organizations')
                                            ->itemLabel('Organisasi')
                                            ->columnSpanFull()
                                            ->addActionLabel('Tambah Organisasi')
                                            ->columns(2)
                                            ->default(function () {
                                                $candidate = Auth::guard('candidate')->user();
                                                return $candidate ? $candidate->organizations->toArray() : [];
                                            })
                                            ->schema([
                                                TextInput::make('organization_name')
                                                    ->label('Nama Organisasi')
                                                    ->columnSpanFull(),

                                                TextInput::make('field')
                                                    ->label('Bidang Organisasi'),

                                                TextInput::make('position')
                                                    ->label('Jabatan'),

                                                TextInput::make('duration')
                                                    ->label('Durasi Keanggotaan')
                                                    ->helperText('Contoh: 3 Tahun, 2 Tahun 6 Bulan'),

                                                TextInput::make('location') 
                                                    ->label('Lokasi Organisasi'),
                                            ])
                                    ])
                            ])
                    ]),

            ]);
    }

    protected static function experienceSchema(): Step
    {
        return Step::make('Pengalaman Kerja')
            ->icon('heroicon-o-briefcase')
            ->completedIcon('heroicon-s-briefcase')
            ->columnSpanFull()
            ->schema([
                Fieldset::make('Pengalaman Kerja')
                    ->relationship('candidate')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('work_experiences')
                            ->hiddenLabel()
                            ->relationship('workExperiences')
                            ->itemLabel('Pengalaman Kerja')
                            ->columnSpanFull()
                            ->addActionLabel('Tambah Pengalaman Kerja')
                            ->columns(2)
                            ->default(function () {
                                $candidate = Auth::guard('candidate')->user();
                                return $candidate ? $candidate->workExperiences->toArray() : [];
                            })
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Nama Perusahaan')
                                    ->columnSpanFull(),

                                TextInput::make('business_type')
                                    ->label('Jenis Usaha/Industri'),

                                TextInput::make('company_address')
                                    ->label('Alamat Perusahaan'),

                                TextInput::make('position')
                                    ->label('Posisi/Jabatan'),

                                DatePicker::make('start_date')
                                    ->label('Tanggal Mulai Bekerja'),

                                DatePicker::make('end_date')
                                    ->label('Tanggal Berhenti Bekerja / Saat Ini Bekerja')
                                    ->visible(fn (callable $get) => !$get('is_current')),

                                Checkbox::make('is_current')
                                    ->label('Saat Ini Bekerja di Perusahaan Ini'),

                                TextInput::make('salary')
                                    ->label('Gaji Terakhir (IDR)')
                                    ->numeric()
                                    ->prefix('Rp '),

                                TextInput::make('resign_reason')
                                    ->label('Alasan Berhenti Bekerja')
                                    ->columnSpanFull(),
                            ])
                    ])
            ]);
    }

    protected static function  additionalInfoSchema(): Step
    {
        return Step::make('Informasi Tambahan')
            ->icon('heroicon-o-information-circle')
            ->completedIcon('heroicon-s-information-circle')
            ->columnSpanFull()
            ->schema([
                Tabs::make()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Referensi')
                            ->schema([
                                Fieldset::make('Referensi')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('references')
                                            ->hiddenLabel()
                                            ->relationship('references')
                                            ->itemLabel('Referensi')
                                            ->columnSpanFull()
                                            ->addActionLabel('Tambah Referensi')
                                            ->columns(2)
                                            ->default(function () {
                                                $candidate = Auth::guard('candidate')->user();
                                                return $candidate ? $candidate->references->toArray() : [];
                                            })
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nama Referensi')
                                                    ->columnSpanFull(),

                                                TextInput::make('address')
                                                    ->label('Alamat Referensi')
                                                    ->columnSpanFull(),

                                                TextInput::make('job')
                                                    ->label('Pekerjaan Referensi'),

                                                TextInput::make('phone')
                                                    ->label('Nomor Telepon Referensi'),

                                                TextInput::make('relation')
                                                    ->label('Hubungan dengan Referensi'),
                                            ])
                                    ]),
                            ]),

                        Tab::make('Informasi Tambahan')
                            ->schema([
                                Fieldset::make('Informasi Tambahan')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Grid::make()
                                            ->relationship('additionalInfos')
                                            ->columnSpanFull()
                                            ->schema([
                                                Checkbox::make('allow_reference_check')
                                                    ->label('Apakah Anda keberatan seandainya kami menghubungi nama di atas untuk mendapatkan referensi?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->allow_reference_check ?? false;
                                                    }),

                                                Checkbox::make('has_disease')
                                                    ->label('Apakah Anda mempunyai catatan tentang penyakit yang pernah diderita atau apakah Anda sedang dirawat karena suatu penyakit?')
                                                    ->helperText('(termasuk: ayan, hepatitis, tuli, vertigo, covid-19 dan lainnya)')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->live()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->has_disease ?? false;
                                                    }),

                                                TextInput::make('disease_notes')
                                                    ->label('Sebutkan jika ada')
                                                    ->columnSpanFull()
                                                    ->visible(fn (callable $get) => $get('has_disease'))
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->disease_notes;
                                                    }),

                                                Textarea::make('hobbies')
                                                    ->label('Sebutkan aktivitas yang Anda lakukan di waktu luang Anda?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->hobbies;
                                                    }),

                                                Textarea::make('languages')
                                                    ->label('Sebutkan bahasa yang Anda kuasai?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->languages;
                                                    }),

                                                Textarea::make('last_facilities')
                                                    ->label('Sebutkan benefit dan fasilitas yang Anda peroleh di pekerjaan terakhir/sekarang?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->last_facilities;
                                                    }),

                                                Textarea::make('expected_salary_facilities')
                                                    ->label('Sebutkan gaji dan fasilitas yang Anda harapkan di Perusahaan kami?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->expected_salary_facilities;
                                                    }),

                                                DatePicker::make('available_start_date')
                                                    ->label('Kapan Anda dapat mulai bekerja bersama kami?')
                                                    ->columnSpanFull()
                                                    ->required()
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->additionalInfos?->available_start_date;
                                                    }),
                                            ])
                                    ])
                            ]),

                        Tab::make('Penilaian Diri')
                            ->schema([
                                Fieldset::make('Penilaian Terhadap Diri Sendiri')
                                    ->relationship('candidate')
                                    ->columnSpanFull()
                                    ->schema([
                                        Grid::make()
                                            ->relationship('selfAssessments')
                                            ->columnSpanFull()
                                            ->columns(1)
                                            ->schema([
                                                Textarea::make('strengths')
                                                    ->label('1. Apa-apa saja yang menjadi kebaikan/kelebihan Anda? (Terutama yang berkaitan dengan sifat dan kebiasaaan)')
                                                    ->columnSpanFull()
                                                    ->rows(4)
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->selfAssessments?->strengths;
                                                    }),

                                                Textarea::make('weaknesses')
                                                    ->label('2. Apa-apa saja yang menjadi keburukan/kekurangan Anda? (Terutama yang berkaitan dengan sifat dan kebiasaan)')
                                                    ->columnSpanFull()
                                                    ->rows(4)
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->selfAssessments?->weaknesses;
                                                    }),

                                                Select::make('satisfaction_level')
                                                    ->label('3. Secara keseluruhan saya merasa:')
                                                    ->columnSpanFull()
                                                    ->options([
                                                        'very_unsatisfied' => 'a. Sangat tidak puas',
                                                        'unsatisfied'        => 'b. Tidak puas',
                                                        'somewhat_unsatisfied'   => 'c. Agak tidak puas',
                                                        'satisfied'        => 'd. Cukup puas',
                                                        'very_satisfied'       => 'e. Sangat puas',
                                                    ])
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->selfAssessments?->satisfaction_level;
                                                    }),

                                                Textarea::make('reason')
                                                    ->label('Alasannya')
                                                    ->columnSpanFull()
                                                    ->rows(3)
                                                    ->default(function () {
                                                        $candidate = Auth::guard('candidate')->user();
                                                        return $candidate?->selfAssessments?->reason;
                                                    }),
                                            ])
                                    ])
                            ])
                    ])
            ]);
    }
}
