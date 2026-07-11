<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Pages;

use App\Enum\Recruitment\ProcessStatus;
use App\Enum\Recruitment\ProcessType;
use App\Enum\Recruitment\RecruitmentProcessStatus;
use App\Filament\Resources\ManPower\JobOpenings\JobOpeningResource;
use App\Models\Candidate\CandidateJobApplications;
use App\Models\Candidate\CandidateRecruitmentProcess;
use App\Models\Role;
use App\Services\Candidate\CandidateService;
use App\Services\Candidate\RecruitmentEmailService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ViewCandidateApplicant extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static string $resource = JobOpeningResource::class;

    protected static ?string $title = 'Detail Kandidat';

    protected string $view = 'filament.resources.man-power.job-openings.pages.view-candidate-applicant';

    public ?Model $applicant = null;
    
    public array $data = [];
    
    public array $recruitmentProcesses = [];

    public function mount(string $record): void
    {
        $this->applicant = CandidateJobApplications::with([
            'candidate.familyMembers',
            'candidate.educations',
            'candidate.trainings',
            'candidate.organizations',
            'candidate.workExperiences',
            'candidate.references',
            'candidate.additionalInfos',
            'candidate.selfAssessments',
            'candidate.documents',
            'jobVacancy.jobPosition',
            'jobVacancy.company',
            'recruitmentProcesses',
        ])->findOrFail($record);

        // Load existing recruitment processes into Livewire property
        $this->recruitmentProcesses = $this->applicant->recruitmentProcesses
            ->map(function ($process) {
                return [
                    'id' => $process->id,
                    'process_name' => $process->process_name,
                    'process_type' => $process->process_type,
                    'link' => $process->link,
                    'notes' => $process->notes,
                    'attachments' => $process->attachments,
                    'metadata' => $process->metadata,
                    'status' => $process->status,
                    'score' => $process->score,
                ];
            })
            ->toArray();

        $this->form->fill([
            'recruitmentProcesses' => $this->recruitmentProcesses,
        ]);
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->record($this->applicant)
            ->components([
                Tabs::make()
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        Tab::make('Detail Kandidat')
                            ->columnSpanFull()
                            ->icon('heroicon-o-user-circle')
                            ->schema($this->detailSchema()),

                        Tab::make('Proses Rekrutmen')
                            ->columnSpanFull()
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema($this->processRecruitemntSchema()),
                    ])
            ]);
    }

    protected function detailSchema(): array
    {
        return [
            Section::make('Informasi Lamaran')
                ->icon('heroicon-o-document-text')
                ->description('Detail lamaran pekerjaan')
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
                                ->formatStateUsing(fn ($state) => ProcessStatus::tryFrom($state)?->getLabel() ?? '-'),

                            TextEntry::make('applied_at')
                                ->label('Tanggal Melamar')
                                ->dateTime('d F Y H:i'),

                            TextEntry::make('finished_at')
                                ->label('Tanggal Selesai')
                                ->dateTime('d F Y H:i')
                                ->placeholder('-'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Informasi Pribadi')
                ->icon('heroicon-o-user')
                ->description('Data pribadi kandidat')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('candidate.name')
                                ->label('Nama Lengkap')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('candidate.email')
                                ->label('Email')
                                ->icon('heroicon-o-envelope')
                                ->copyable(),

                            TextEntry::make('candidate.phone_number')
                                ->label('No. Telepon')
                                ->icon('heroicon-o-phone')
                                ->copyable(),

                            TextEntry::make('candidate.nik')
                                ->label('NIK')
                                ->icon('heroicon-o-identification')
                                ->copyable(),

                            TextEntry::make('candidate.no_kk')
                                ->label('No. KK')
                                ->copyable(),

                            TextEntry::make('candidate.gender')
                                ->label('Jenis Kelamin')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'male' => 'Laki-laki',
                                    'female' => 'Perempuan',
                                    default => $state,
                                })
                                ->color(fn ($state): string => match ($state) {
                                    'male' => 'info',
                                    'female' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('candidate.birth_place')
                                ->label('Tempat Lahir'),

                            TextEntry::make('candidate.birth_date')
                                ->label('Tanggal Lahir')
                                ->date('d F Y'),

                            TextEntry::make('candidate.status_marital')
                                ->label('Status Pernikahan')
                                ->badge()
                                ->formatStateUsing(fn ($state) => ucfirst($state)),
                        ]),
                ])
                ->collapsible(),

            Section::make('Alamat')
                ->icon('heroicon-o-map-pin')
                ->description('Alamat lengkap kandidat')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('candidate.ktp_address')
                                ->label('Alamat KTP')
                                ->columnSpanFull(),

                            TextEntry::make('candidate.domicile_address')
                                ->label('Alamat Domisili')
                                ->columnSpanFull(),

                            TextEntry::make('candidate.village')
                                ->label('Kelurahan'),

                            TextEntry::make('candidate.district')
                                ->label('Kecamatan'),

                            TextEntry::make('candidate.city')
                                ->label('Kota/Kabupaten'),

                            TextEntry::make('candidate.province')
                                ->label('Provinsi'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Pendidikan')
                ->icon('heroicon-o-academic-cap')
                ->description('Riwayat pendidikan kandidat')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('candidate.education')
                                ->label('Pendidikan Terakhir')
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('candidate.major')
                                ->label('Jurusan'),

                            TextEntry::make('candidate.university')
                                ->label('Universitas/Institusi'),

                            TextEntry::make('candidate.graduation_year')
                                ->label('Tahun Lulus'),

                            TextEntry::make('candidate.mother_name')
                                ->label('Nama Ibu Kandung')
                                ->columnSpan(2),
                        ]),
                ])
                ->collapsible(),

            Section::make('Informasi BPJS & Bank')
                ->icon('heroicon-o-credit-card')
                ->description('Data BPJS dan rekening bank')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('candidate.no_bpjskes')
                                ->label('No. BPJS Kesehatan')
                                ->copyable()
                                ->placeholder('-'),

                            TextEntry::make('candidate.faskes')
                                ->label('Faskes')
                                ->placeholder('-'),

                            TextEntry::make('candidate.no_bpjstk')
                                ->label('No. BPJS Ketenagakerjaan')
                                ->copyable()
                                ->placeholder('-'),

                            TextEntry::make('candidate.company_bpjstk')
                                ->label('Perusahaan BPJSTK')
                                ->placeholder('-'),

                            TextEntry::make('candidate.bsi_bank_number')
                                ->label('No. Rekening BSI')
                                ->copyable()
                                ->placeholder('-'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Dokumen Kandidat')
                ->icon('heroicon-o-folder-open')
                ->description('Dokumen pendukung kandidat')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('candidate.documents')
                        ->hiddenLabel()
                        ->grid([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->schema([
                            TextEntry::make('document_label')
                                ->icon('heroicon-o-document-text')
                                ->iconColor('black')
                                ->label('Dokumen'),

                            TextEntry::make('file_path')
                                ->icon('heroicon-o-link')
                                ->iconColor('black')
                                ->hiddenLabel()
                                ->formatStateUsing(fn (string $state): string => basename($state))
                                ->url(fn (string $state): string => asset(Storage::url($state)))
                                ->openUrlInNewTab(),
                        ])
                ])
                ->collapsible(),

            Section::make('Hubungan Keluarga')
                ->icon('heroicon-o-user-group')
                ->description('Data anggota keluarga kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.familyMembers')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Anggota Keluarga')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('name')
                                        ->label('Nama'),

                                    TextEntry::make('relation')
                                        ->label('Hubungan'),

                                    TextEntry::make('gender')
                                        ->label('Jenis Kelamin'),

                                    TextEntry::make('birth_date')
                                        ->label('Tanggal Lahir')
                                        ->date('d F Y')
                                        ->placeholder('-'),

                                    TextEntry::make('education')
                                        ->label('Pendidikan')
                                        ->placeholder('-'),

                                    TextEntry::make('job')
                                        ->label('Pekerjaan')
                                        ->placeholder('-'),

                                    TextEntry::make('phone')
                                        ->label('Nomor Telepon')
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Riwayat Pendidikan Formal')
                ->icon('heroicon-o-academic-cap')
                ->description('Riwayat pendidikan formal kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.educations')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Pendidikan Formal')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('level')
                                        ->label('Tingkat Pendidikan')
                                        ->badge()
                                        ->color('primary'),

                                    TextEntry::make('school_name')
                                        ->label('Nama Sekolah/Universitas'),

                                    TextEntry::make('major')
                                        ->label('Jurusan')
                                        ->placeholder('-'),

                                    TextEntry::make('gpa')
                                        ->label('IPK/GPA')
                                        ->placeholder('-'),

                                    TextEntry::make('start_year')
                                        ->label('Tahun Masuk')
                                        ->placeholder('-'),

                                    TextEntry::make('end_year')
                                        ->label('Tahun Lulus')
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Pendidikan Informal / Pelatihan')
                ->icon('heroicon-o-light-bulb')
                ->description('Riwayat pelatihan dan pendidikan informal kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.trainings')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Pelatihan')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('type')
                                        ->label('Jenis Pelatihan'),

                                    TextEntry::make('institution')
                                        ->label('Institusi Penyelenggara'),

                                    TextEntry::make('material')
                                        ->label('Materi Pelatihan')
                                        ->placeholder('-'),

                                    TextEntry::make('duration')
                                        ->label('Durasi')
                                        ->placeholder('-'),

                                    TextEntry::make('location')
                                        ->label('Lokasi')
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Pengalaman Organisasi')
                ->icon('heroicon-o-building-office')
                ->description('Riwayat organisasi kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.organizations')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Organisasi')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('organization_name')
                                        ->label('Nama Organisasi'),

                                    TextEntry::make('field')
                                        ->label('Bidang')
                                        ->placeholder('-'),

                                    TextEntry::make('position')
                                        ->label('Jabatan')
                                        ->placeholder('-'),

                                    TextEntry::make('duration')
                                        ->label('Durasi')
                                        ->placeholder('-'),

                                    TextEntry::make('location')
                                        ->label('Lokasi')
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Pengalaman Kerja')
                ->icon('heroicon-o-briefcase')
                ->description('Riwayat pengalaman kerja kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.workExperiences')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Pengalaman Kerja')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('company_name')
                                        ->label('Nama Perusahaan'),

                                    TextEntry::make('business_type')
                                        ->label('Jenis Usaha/Industri')
                                        ->placeholder('-'),

                                    TextEntry::make('company_address')
                                        ->label('Alamat Perusahaan')
                                        ->placeholder('-'),

                                    TextEntry::make('position')
                                        ->label('Posisi/Jabatan')
                                        ->placeholder('-'),

                                    TextEntry::make('start_date')
                                        ->label('Tanggal Mulai')
                                        ->date('d F Y')
                                        ->placeholder('-'),

                                    TextEntry::make('end_date')
                                        ->label('Tanggal Berhenti')
                                        ->date('d F Y')
                                        ->placeholder('Masih Bekerja'),

                                    TextEntry::make('salary')
                                        ->label('Gaji Terakhir')
                                        ->money('IDR')
                                        ->placeholder('-'),

                                    TextEntry::make('resign_reason')
                                        ->label('Alasan Berhenti')
                                        ->columnSpan(2)
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Referensi')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->description('Daftar referensi kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('candidate.references')
                        ->hiddenLabel()
                        ->schema([
                            Fieldset::make('Referensi')
                                ->columns(3)
                                ->schema([
                                    TextEntry::make('name')
                                        ->label('Nama Referensi'),

                                    TextEntry::make('job')
                                        ->label('Pekerjaan')
                                        ->placeholder('-'),

                                    TextEntry::make('relation')
                                        ->label('Hubungan')
                                        ->placeholder('-'),

                                    TextEntry::make('address')
                                        ->label('Alamat')
                                        ->columnSpan(2)
                                        ->placeholder('-'),

                                    TextEntry::make('phone')
                                        ->label('Nomor Telepon')
                                        ->placeholder('-'),
                                ]),
                        ]),
                ]),

            Section::make('Informasi Tambahan')
                ->icon('heroicon-o-information-circle')
                ->description('Informasi tambahan dari kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Fieldset::make('Informasi Tambahan')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('candidate.additionalInfos.allow_reference_check')
                                ->label('Keberatan Dihubungi untuk Referensi?')
                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                ->badge()
                                ->color(fn ($state) => $state ? 'warning' : 'success'),

                            TextEntry::make('candidate.additionalInfos.has_disease')
                                ->label('Memiliki Riwayat Penyakit?')
                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                ->badge()
                                ->color(fn ($state) => $state ? 'danger' : 'success'),

                            TextEntry::make('candidate.additionalInfos.disease_notes')
                                ->label('Keterangan Penyakit')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.additionalInfos.hobbies')
                                ->label('Aktivitas / Hobi')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.additionalInfos.languages')
                                ->label('Bahasa yang Dikuasai')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.additionalInfos.last_facilities')
                                ->label('Fasilitas Pekerjaan Terakhir')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.additionalInfos.expected_salary_facilities')
                                ->label('Ekspektasi Gaji & Fasilitas')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.additionalInfos.available_start_date')
                                ->label('Dapat Mulai Bekerja')
                                ->date('d F Y')
                                ->placeholder('-'),
                        ]),
                ]),

            Section::make('Penilaian Diri')
                ->icon('heroicon-o-star')
                ->description('Penilaian terhadap diri sendiri oleh kandidat')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Fieldset::make('Penilaian Terhadap Diri Sendiri')
                        ->columns(1)
                        ->schema([
                            TextEntry::make('candidate.selfAssessments.strengths')
                                ->label('Kebaikan/Kelebihan')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.selfAssessments.weaknesses')
                                ->label('Keburukan/Kekurangan')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('candidate.selfAssessments.satisfaction_level')
                                ->label('Tingkat Kepuasan Diri')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'very_unsatisfied' => 'Sangat Tidak Puas',
                                    'not_satisfied'    => 'Tidak Puas',
                                    'somewhat_unsatisfied' => 'Agak Tidak Puas',
                                    'satisfied'        => 'Cukup Puas',
                                    'very_satisfied'   => 'Sangat Puas',
                                    default             => $state ?? '-',
                                })
                                ->badge()
                                ->color(fn ($state) => match ($state) {
                                    'very_satisfied', 'satisfied' => 'success',
                                    'somewhat_unsatisfied'           => 'warning',
                                    'not_satisfied', 'very_unsatisfied' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('candidate.selfAssessments.reason')
                                ->label('Alasan')
                                ->columnSpanFull()
                                ->placeholder('-'),
                        ]),
                ]),
        ];
    }

    protected function processRecruitemntSchema(): array
    {
        return [
            Section::make('Approval Direktur')
                ->icon(Heroicon::OutlinedUserCircle)
                ->description('Status approval kandidat oleh direktur')
                ->visible(fn ($record) => $record->approval_status !== null)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('approval_status')
                                ->label('Status Approval')
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'pending' => 'Menunggu Proses',
                                    'requested' => 'Diajukan ke Direktur',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                    default => '-',
                                })
                                ->color(fn ($state): string => match ($state) {
                                    'pending' => 'gray',
                                    'requested' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'gray',
                                }),

                            TextEntry::make('approver.name')
                                ->label('Disetujui Oleh')
                                ->placeholder('-'),

                            TextEntry::make('approved_at')
                                ->label('Tanggal Disetujui')
                                ->dateTime('d F Y H:i')
                                ->placeholder('-'),

                            TextEntry::make('metadata.approval_notes')
                                ->label('Catatan Approval')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('metadata.director_approval_notes')
                                ->label('Catatan Persetujuan Direktur')
                                ->columnSpanFull()
                                ->visible(fn ($record) => $record->approval_status === 'approved')
                                ->placeholder('-'),

                            TextEntry::make('metadata.director_rejection_notes')
                                ->label('Alasan Penolakan Direktur')
                                ->columnSpanFull()
                                ->visible(fn ($record) => $record->approval_status === 'rejected')
                                ->placeholder('-'),
                        ]),
                ])
                ->footerActions([
                    Action::make('approve_candidate')
                        ->label('Setujui Kandidat')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Setujui Kandidat')
                        ->modalDescription('Apakah Anda yakin ingin menyetujui kandidat ini?')
                        ->visible(fn () => $this->applicant->approval_status === 'requested')
                        ->schema([
                            Textarea::make('director_approval_notes')
                                ->label('Catatan Approval')
                                ->rows(3)
                                ->columnSpanFull()
                                ->placeholder('Masukkan catatan tambahan untuk kandidat (opsional)'),
                        ])
                        ->action(function (array $data) {
                            $this->applicant->approval_status = 'approved';
                            $this->applicant->approved_by = Auth::id();
                            $this->applicant->approved_at = now();
                            $this->applicant->metadata = array_merge($this->applicant->metadata ?? [], [
                                'director_approval_notes' => $data['director_approval_notes'] ?? null,
                            ]);
                            $this->applicant->save();
                            
                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Kandidat telah disetujui.')
                                ->send();
                        }),

                    Action::make('reject_candidate')
                        ->label('Tolak Kandidat')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Tolak Kandidat')
                        ->modalDescription('Apakah Anda yakin ingin menolak kandidat ini?')
                        ->visible(fn () => $this->applicant->approval_status === 'requested')
                        ->schema([
                            Textarea::make('director_rejection_notes')
                                ->label('Alasan Penolakan')
                                ->rows(3)
                                ->columnSpanFull()
                                ->placeholder('Masukkan alasan penolakan untuk kandidat (opsional)'),
                        ])
                        ->action(function (array $data) {
                            $this->applicant->approval_status = 'rejected';
                            $this->applicant->approved_by = Auth::id();
                            $this->applicant->approved_at = now();
                            $this->applicant->metadata = array_merge($this->applicant->metadata ?? [], [
                                'director_rejection_notes' => $data['director_rejection_notes'] ?? null,
                            ]);
                            $this->applicant->save();

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Kandidat telah ditolak.')
                                ->send();
                        }),
                ])
                ->collapsible(),

            Section::make(fn ($record) => 'Status Rekrutmen Saat Ini - ' . ucfirst(str_replace('_', ' ', $record->status)))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->description(function ($record) {
                    $stage = $record->current_stage;
                    return $record?->status === 'pending'
                        ? 'Kandidate telah mengajukan lamaran, proses rekrutmen dengan klik tombol "Proses Rekrutmen" di samping.'
                        : 'Proses rekrutmen sedang berlangsung pada tahap "' . ucfirst(str_replace('_', ' ', $stage)) . '".';
                })
                ->headerActions([
                    Action::make('process_recruitment')
                        ->label('Proses Rekrutmen')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('primary')
                        ->visible(fn ($record) => $record->status === ProcessStatus::SUBMITTED->value)
                        ->action(function ($record) {
                            $record->status = ProcessStatus::UNDER_REVIEW->value;
                            $record->save();

                            // Kirim notifikasi email ke kandidat bahwa lamaran sedang diproses
                            app(RecruitmentEmailService::class)->notifyApplicationUnderReview($record);
                        }),

                    Action::make('add_recruitment_process')
                        ->label('Lanjutkan Tahapan Rekrutmen')
                        ->icon(Heroicon::OutlinedPlusCircle)
                        ->color('success')
                        ->visible(fn () =>
                            ($this->applicant->status !== ProcessStatus::HIRED->value &&
                                $this->applicant->status !== ProcessStatus::REJECTED->value &&
                                $this->applicant->status !== ProcessStatus::WITHDRAWN->value &&
                                $this->applicant->status !== ProcessStatus::SUBMITTED->value))
                        ->tooltip('Hanya dapat menambahkan tahapan baru jika tahapan sebelumnya telah selesai.')
                        ->schema($this->inputProcessSchema())
                        ->action(function (array $data, $record) {
                            $attachments = $this->normalizeAttachments($data['attachments'] ?? []);
                            $process = $record->recruitmentProcesses()->create([
                                'process_name' => $data['process_name'] ?? null,
                                'process_type' => $data['process_type'] ?? str_replace(' ', '_', strtolower($data['process_name'] ?? '')),
                                'link' => $data['link'] ?? null,
                                'notes' => $data['notes'] ?? null,
                                'attachments' => $attachments,
                                'metadata' => isset($data['metadata']) ? $data['metadata'] : null,
                                'status' => RecruitmentProcessStatus::ASSIGNED->value,
                            ]);

                            // Update current stage
                            $record->status = ProcessStatus::IN_SELECTION->value;
                            $record->current_stage = $data['process_name'] ?? null;
                            $record->save();

                            // Kirim notifikasi email ke kandidat tentang tahapan baru
                            app(RecruitmentEmailService::class)->notifyNewRecruitmentStage($record, $process);

                            $this->redirect(request()->header('Referer') ?? url()->current());

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Tahapan rekrutmen baru telah ditambahkan.')
                                ->send();
                        })                        
                ])
                ->schema([
                    Repeater::make('recruitmentProcesses')
                        ->relationship(
                            name: 'recruitmentProcesses',
                            modifyQueryUsing: fn ($query) => $query->orderBy('id', 'desc')
                        )
                        ->hiddenLabel()
                        ->itemLabel('Proses Rekrutmen')
                        ->addable(false)
                        ->reorderable(false)
                        ->deletable(fn (callable $get) => $get('status') === RecruitmentProcessStatus::ASSIGNED->value)
                        ->columns(3)
                        ->schema(
                            $this->processSchema()
                        ),
                ])
                ->footerActionsAlignment(Alignment::Center)
                ->footerActions([
                    Action::make('request_approval')
                        ->label('Request Approval ke Direktur')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Request Approval Kandidat ke Direktur')
                        ->modalDescription('Apakah Anda yakin ingin mengirim request approval kandidat ini ke direktur?')
                        ->visible(fn () => $this->applicant->approval_status !== 'requested' && $this->applicant->approval_status !== 'approved')
                        ->schema([
                            Textarea::make('approval_notes')
                                ->label('Catatan Approval')
                                ->rows(3)
                                ->columnSpanFull()
                                ->placeholder('Masukkan catatan tambahan untuk direktur (opsional)'),
                        ])
                        ->action(function (array $data) {
                            $this->applicant->approval_status = 'requested';
                            $metadata = $this->applicant->metadata ?? [];
                            $metadata['approval_notes'] = $data['approval_notes'] ?? null;
                            $this->applicant->metadata = $metadata;
                            $this->applicant->save();

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Request approval kandidat telah dikirim ke direktur.')
                                ->send();
                        }),

                    Action::make('reject_application')
                        ->label('Tolak Kandidat')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Tolak Lamaran Kandidat')
                        ->modalDescription('Apakah Anda yakin ingin menolak lamaran kandidat ini? Tindakan ini tidak dapat dibatalkan.')
                        ->visible(fn () => $this->applicant->status !== ProcessStatus::REJECTED->value && $this->applicant->status !== ProcessStatus::HIRED->value)
                        ->action(function () {
                            $this->applicant->status = 'rejected';
                            $this->applicant->finished_at = now();
                            $this->applicant->save();

                            // Kirim notifikasi email penolakan ke kandidat
                            app(RecruitmentEmailService::class)->notifyApplicationRejected($this->applicant);

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Kandidat telah ditolak.')
                                ->send();
                        }),

                    Action::make('accept_application')
                        ->label('Terima Kandidat')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Terima Kandidat')
                        ->modalDescription('Apakah Anda yakin ingin menerima kandidat ini? Tindakan ini tidak dapat dibatalkan. Data kandidat akan otomatis diproses ke dalam sistem karyawan setelah diterima.')
                        ->visible(fn () => $this->applicant->status !== ProcessStatus::HIRED->value && $this->applicant->status !== ProcessStatus::REJECTED->value)
                        ->schema([
                            TextInput::make('nip')
                                ->label('NIP Karyawan')
                                ->required(),

                            Select::make('type_nakes')
                                ->label('Tipe Nakes')
                                ->preload()
                                ->options([
                                    'nakes' => 'Nakes',
                                    'non_nakes' => 'Non Nakes',
                                ])->required(),

                            Select::make('employee_type_id')
                                ->label('Tipe Karyawan')
                                ->preload()
                                ->options(function () {
                                    return \App\Models\Master\EmployeeTypes::pluck('name', 'id')->toArray();
                                })->required(),

                            DatePicker::make('joined_at')
                                ->label('Tanggal Bergabung')
                                ->default(now())
                                ->required(),

                            Select::make('role_id')
                                ->label('Role (Aplikasi Sistem)')
                                ->preload()
                                ->options(function () {
                                    $companyId = Auth::user()->company_id;
                                    return Role::where('company_id', $companyId)->pluck('name', 'id')->toArray();
                                })->required(),
                        ])
                        ->action(function (array $data) {
                            app(CandidateService::class)->syncDataCandidateToUser(
                                $this->applicant->id,
                                [
                                    'nip' => $data['nip'],
                                    'type_nakes' => $data['type_nakes'],
                                    'company_id' => $this->applicant->jobVacancy->company_id,
                                    'employee_type_id' => $data['employee_type_id'],
                                    'role_id' => $data['role_id'],
                                    'joined_at' => $data['joined_at'],
                                ]
                            );

                            $this->applicant->status = ProcessStatus::HIRED->value;
                            $this->applicant->current_stage = 'Kandidat Diterima';
                            $this->applicant->finished_at = now();
                            $this->applicant->save();

                            // Kirim notifikasi email penerimaan ke kandidat
                            app(RecruitmentEmailService::class)->notifyApplicationAccepted($this->applicant);

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Kandidat telah diterima dan data telah disinkronkan ke sistem karyawan.')
                                ->send();
                        })
                ])
        ];
    }

    protected function inputProcessSchema(): array
    {
        return [
            Fieldset::make('Informasi Tahapan Rekrutmen')
                ->columnSpanFull()
                ->schema([
                    Select::make('process_type')
                        ->label('Tipe')
                        ->belowLabel('Pilih tipe proses rekrutmen')
                        ->options(ProcessType::class)
                        ->required(),

                    TextInput::make('process_name')
                        ->label('Nama Proses')
                        ->belowLabel('Contoh: Wawancara HRD, Tes Teknis')
                        ->required(),

                    DatePicker::make('metadata.scheduled_at')
                        ->label('Jadwal Pelaksanaan')
                        ->belowLabel('Tanggal pelaksanaan proses ini'),

                    DatePicker::make('metadata.maximum_completion_at')
                        ->label('Batas Waktu Penyelesaian/Konfirmasi')
                        ->belowLabel('Tanggal batas waktu penyelesaian proses ini'),

                    Textarea::make('link')
                        ->label('Link Terkait')
                        ->belowLabel('Link untuk tes online atau dokumen terkait proses ini')
                        ->columnSpanFull(),

                    FileUpload::make('attachments')
                        ->label('Lampiran')
                        ->belowLabel('Unggah lampiran terkait proses rekrutmen ini')
                        ->multiple()
                        ->disk('public')
                        ->directory('recruitment-process-attachments')
                        ->visibility('public')
                        ->downloadable()
                        ->openable()
                        ->maxSize(10240)
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Catatan')
                        ->belowLabel('Catatan tambahan mengenai proses ini')
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function processSchema(): array
    {
        return [
            Fieldset::make('Informasi Tahapan Rekrutmen')
                ->columnSpan(2)
                ->schema([
                    TextEntry::make('process_name')
                        ->label('Nama Proses'),
                    
                    TextEntry::make('status')
                        ->label('Status Proses')
                        ->formatStateUsing(fn ($state) => RecruitmentProcessStatus::tryFrom($state)?->getLabel() ?? '-')
                        ->color(fn ($state): string => RecruitmentProcessStatus::tryFrom($state)?->getColor() ?? 'gray')
                        ->icon(fn ($state): string => RecruitmentProcessStatus::tryFrom($state)?->getIcon() ?? null)
                        ->badge(),
                        
                    TextEntry::make('metadata.scheduled_at')
                        ->label('Jadwal Pelaksanaan')
                        ->date('d F Y'),

                    TextEntry::make('metadata.maximum_completion_at')
                        ->label('Batas Waktu Penyelesaian')
                        ->visible(fn (callable $get) => !empty($get('metadata.maximum_completion_at')))
                        ->date('d F Y'),

                    TextEntry::make('link')
                        ->label('Link Terkait')
                        ->visible(fn (callable $get) => !empty($get('link')))
                        ->placeholder('-'),

                    TextEntry::make('notes')
                        ->label('Catatan')
                        ->placeholder('-'),

                    TextEntry::make('metadata.rescheduled_at')
                        ->label('Jadwal Ulang')
                        ->date('d F Y')
                        ->visible(fn (callable $get) => !empty($get('metadata.rescheduled_at')))
                        ->placeholder('-'),

                    TextEntry::make('metadata.reschedule_notes')
                        ->label('Alasan Penjadwalan Ulang')
                        ->visible(fn (callable $get) => !empty($get('metadata.reschedule_notes')))
                        ->placeholder('-'),

                    TextEntry::make('attachments')
                        ->label('Lampiran')
                        ->visible(fn ($state) => !empty($state))
                        ->url(fn ($state): string => asset(Storage::url($state)))
                        ->formatStateUsing(fn ($state): string => basename($state))
                        ->openUrlInNewTab()
                        ->placeholder('-'),

                    Action::make('add_score')
                        ->label('Tambah Hasil Penilaian')
                        ->icon(Heroicon::OutlinedStar)
                        ->modalWidth('md')
                        ->modalHeading('Tambah Penilaian untuk Proses Ini')
                        ->modalDescription('Penilaian dapat berupa skor dan catatan tambahan mengenai kinerja kandidat pada proses rekrutmen ini.')
                        ->visible(fn (callable $get) => 
                                $get('status') !== RecruitmentProcessStatus::COMPLETED->value && 
                                $get('status') !== RecruitmentProcessStatus::ACCEPTED->value && 
                                $get('status') !== RecruitmentProcessStatus::FAILED->value)
                        ->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    RecruitmentProcessStatus::COMPLETED->value => 'Lulus',
                                    RecruitmentProcessStatus::FAILED->value => 'Tidak Lulus',
                                ])
                                ->required(),
                            
                            TextInput::make('score')
                                ->label('Skor')
                                ->numeric(),

                            Textarea::make('feedback')
                                ->label('Catatan Penilaian')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->button()
                        ->action(function (array $data, callable $get, callable $set) {
                            $processId = $get('id');
                            if (!$processId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal')
                                    ->body('ID proses tidak ditemukan.')
                                    ->send();
                                return;
                            }

                            // Update recruitmentProcesses array in Livewire property
                            foreach ($this->recruitmentProcesses as &$process) {
                                if (isset($process['id']) && $process['id'] == $processId) {
                                    $process['score'] = $data['score'] ?? null;
                                    $process['feedback'] = $data['feedback'] ?? null;
                                    $process['status'] = $data['status'] ?? RecruitmentProcessStatus::COMPLETED->value;
                                    break;
                                }
                            }
                            unset($process);

                            // Update to database immediately
                            $this->applicant->recruitmentProcesses()
                                ->where('id', $processId)
                                ->update([
                                    'score' => $data['score'] ?? null,
                                    'feedback' => $data['feedback'] ?? null,
                                    'status' => $data['status'] ?? RecruitmentProcessStatus::COMPLETED->value,
                                ]);

                            redirect(request()->header('Referer') ?? url()->current());

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Penilaian berhasil ditambahkan.')
                                ->send();
                        }),

                    Action::make('confirmation_reschedule')
                        ->label('Konfirmasi Penjadwalan Ulang')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Penjadwalan Ulang')
                        ->modalDescription('Apakah Anda yakin ingin mengonfirmasi penjadwalan ulang untuk proses rekrutmen ini?')
                        ->visible(fn (callable $get) => $get('status') === RecruitmentProcessStatus::REQUEST_RESCHEDULED->value)
                        ->action(function (callable $get) {
                            $processId = $get('id');
                            if (!$processId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal')
                                    ->body('ID proses tidak ditemukan.')
                                    ->send();
                                return;
                            }

                            $process = CandidateRecruitmentProcess::find($get('id'));
                            $metadata = $process->metadata ?? [];
                            $metadata['scheduled_at'] = $metadata['rescheduled_at'] ?? null;
                            $metadata['reschedule_notes'] = 'Penjadwalan ulang dikonfirmasi.';

                            // Update status to ASSIGNED
                            $this->applicant->recruitmentProcesses()
                                ->where('id', $processId)
                                ->update([
                                    'status' => RecruitmentProcessStatus::RESCHEDULED->value,
                                    'metadata' => $metadata,
                                ]);

                            redirect(request()->header('Referer') ?? url()->current());

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Penjadwalan ulang telah dikonfirmasi.')
                                ->send();
                        }),

                    Action::make('mark_as_completed_mcu')
                        ->label('Tandai sebagai Selesai')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Proses MCU sebagai Selesai')
                        ->modalDescription('Apakah Anda yakin ingin menandai proses Medical Check Up (MCU) ini sebagai selesai?')
                        ->visible(fn (callable $get) => 
                            $get('process_type') === ProcessType::MCU->value 
                            && (
                                $get('status') === RecruitmentProcessStatus::ACCEPTED->value 
                                || $get('status') === RecruitmentProcessStatus::RESCHEDULED->value
                            )
                        )
                        ->schema([
                            Textarea::make('results_summary')
                                ->label('Ringkasan Hasil MCU')
                                ->rows(3)
                                ->columnSpanFull()
                                ->required(),

                            FileUpload::make('attachments')
                                ->label('Lampiran Hasil MCU')
                                ->disk('public')
                                ->directory('recruitment-process-attachments/result-mcu')
                                ->visibility('public')
                                ->downloadable()
                                ->openable()
                                ->maxSize(10240)
                                ->columnSpanFull()
                        ])
                        ->action(function (callable $get, array $data) {
                            $processId = $get('id');
                            if (!$processId) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal')
                                    ->body('ID proses tidak ditemukan.')
                                    ->send();
                                return;
                            }

                            $process = CandidateRecruitmentProcess::find($get('id'));
                            $metadata = $process->metadata ?? [];
                            $metadata['results_summary'] = $data['results_summary'] ?? null;
                            $metadata['mcu_attachments'] = $data['attachments'] ?? null;
                            $metadata['submitted_at'] = now();

                            // Update status to COMPLETED
                            $this->applicant->recruitmentProcesses()
                                ->where('id', $processId)
                                ->update([
                                    'status' => RecruitmentProcessStatus::COMPLETED->value,
                                    'metadata' => $metadata,
                                ]);

                            redirect(request()->header('Referer') ?? url()->current());

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Proses MCU telah ditandai sebagai selesai.')
                                ->send();
                        }),
                ]),

            Fieldset::make('Hasil Penilaian')
                ->columnSpan(1)
                ->schema([
                    TextEntry::make('metadata.submitted_at')
                        ->label('Tanggal Selesai')
                        ->date('d F Y')
                        ->columnSpanFull()
                        ->visible(fn (callable $get) => !empty($get('metadata.submitted_at')))
                        ->placeholder('-'),

                    TextEntry::make('score')
                        ->label('Skor Penilaian')
                        ->visible(fn (callable $get) => !empty($get('score')))
                        ->columnSpanFull()
                        ->placeholder('-'),

                    TextEntry::make('feedback')
                        ->label('Catatan Penilaian')
                        ->visible(fn (callable $get) => !empty($get('feedback')))
                        ->columnSpanFull()
                        ->placeholder('-'),

                    TextEntry::make('metadata.results_summary')
                        ->label('Ringkasan Hasil MCU')
                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::MCU->value && !empty($get('metadata.results_summary')))
                        ->columnSpanFull()
                        ->placeholder('-'),

                    TextEntry::make('metadata.attachments')
                        ->label('Lampiran Hasil MCU')
                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::MCU->value && !empty($get('metadata.attachments')))
                        ->url(fn (string $state): string => asset(Storage::url($state)))
                        ->formatStateUsing(fn (string $state): string => basename($state))
                        ->openUrlInNewTab()
                        ->placeholder('-'),

                    TextEntry::make('metadata.offer_acceptance_notes')
                        ->label('Catatan')
                        ->visible(fn (callable $get) => !empty($get('metadata.offer_acceptance_notes')))
                        ->columnSpanFull()
                        ->placeholder('-'),

                    TextEntry::make('metadata.offer_acceptance_attachments')
                        ->label('Lampiran')
                        ->visible(fn (callable $get) => !empty($get('metadata.offer_acceptance_attachments')))
                        ->url(fn (string $state): string => asset(Storage::url($state)))
                        ->formatStateUsing(fn (string $state): string => basename($state))
                        ->openUrlInNewTab()
                        ->placeholder('-'),

                    TextEntry::make('metadata.mcu_attachments')
                        ->label('Hasil MCU')
                        ->visible(fn (callable $get) => $get('process_type') === ProcessType::MCU->value && !empty($get('metadata.mcu_attachments')))
                        ->url(fn (string $state): string => asset(Storage::url($state)))
                        ->formatStateUsing(fn (string $state): string => basename($state))
                        ->openUrlInNewTab()
                        ->placeholder('-'),
                ]),
        ];
    }

    protected function normalizeAttachments(?array $attachments): array
    {
        return collect($attachments ?? [])
            ->filter()
            ->values()
            ->toArray();
    }
}
