<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings;

use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\CreateJobOpening;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\EditJobOpening;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\ListJobOpenings;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\ViewJobOpening;
use App\Models\Master\Companies as HrisCompanies;
use App\Models\Master\JobPositions;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobOpening;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Kelola Lowongan — port dari JobOpeningResource project `rekrutmen` (FL3 -> FL4), model
 * di koneksi `recruitment`. Lowongan yang `hris_job_opening_id` terisi (di-push dari HRIS
 * lama) tetap read-only di sini. Company-scoping: user tanpa LIHAT_PERUSAHAAN_LAIN dikunci ke
 * perusahaan rekrutmen milik company-nya (map m_companies.code == companies.hris_code).
 */
class JobOpeningResource extends Resource
{
    protected static ?string $model = JobOpening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Lowongan';

    protected static ?string $modelLabel = 'Lowongan';

    protected static ?string $pluralModelLabel = 'Lowongan';

    // Tanpa grup — item navigasi mandiri di paling atas.
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    /** Boleh ubah status lowongan (publish / jadikan draft) — samakan dgn aksi mutasi pipeline. */
    protected static function canModify(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('Update:JobOpenings');
    }

    /** Company yang dikunci utk user tanpa LIHAT_PERUSAHAAN_LAIN (null = boleh semua perusahaan). */
    protected static function lockedCompanyId(): ?string
    {
        $u = filament()->auth()->user();

        return $u?->canFilterByCompany() ? null : Company::idForHrisCompany($u?->company_id);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->when(static::lockedCompanyId(), fn ($q, $id) => $q->where('company_id', $id));
    }

    /** Perusahaan rekrutmen ini dikelola penuh dari HRIS (jabatan wajib dari master HRIS). */
    protected static function isHrisMode(?string $companyId): bool
    {
        return $companyId ? Company::find($companyId)?->management_mode === 'hris' : false;
    }

    /** Master jabatan HRIS milik perusahaan (dipetakan lewat companies.hris_code). */
    protected static function hrisJobPositions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        $hrisCode = Company::find($companyId)?->hris_code;
        $hrisCompanyId = $hrisCode ? HrisCompanies::where('code', $hrisCode)->value('id') : null;

        return JobPositions::query()
            ->when($hrisCompanyId, fn ($q) => $q->where('company_id', $hrisCompanyId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Lowongan')->schema([
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Company::query()
                        ->when(static::lockedCompanyId(), fn ($q, $id) => $q->whereKey($id))
                        ->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => static::lockedCompanyId())
                    ->disabled(fn () => static::lockedCompanyId() !== null)
                    ->dehydrated()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('company_name', Company::find($state)?->name)),

                TextInput::make('company_name')
                    ->label('Nama Perusahaan (manual)')
                    ->helperText('Otomatis dari perusahaan di atas, atau isi manual.'),

                Select::make('job_position_id')
                    ->label('Jabatan (dari HRIS)')
                    ->options(fn (Get $get) => static::hrisJobPositions($get('company_id')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('title', JobPositions::find($state)?->name))
                    ->visible(fn (Get $get) => static::isHrisMode($get('company_id')))
                    ->required(fn (Get $get) => static::isHrisMode($get('company_id')))
                    ->helperText('Perusahaan mode HRIS: jabatan wajib diambil dari master jabatan HRIS.'),

                TextInput::make('title')
                    ->label('Nama Lowongan')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn (Get $get) => static::isHrisMode($get('company_id'))
                        ? 'Terisi otomatis dari jabatan HRIS, boleh diubah (mis. "Perawat Rawat Inap Lantai 3"). Tautan jabatan tetap dari pilihan di atas.'
                        : 'Perusahaan mode Bebas: isi manual.'),

                TextInput::make('quota')
                    ->label('Kuota Posisi')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),

                TextInput::make('max_applicants')
                    ->label('Maks. Pelamar')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Kosongkan = tidak terbatas.'),

                DatePicker::make('applications_deadline')
                    ->label('Batas Lamaran')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('hris_job_opening_id')
                    ->label('ID Lowongan HRIS')
                    ->unique(ignoreRecord: true)
                    ->helperText('Terisi otomatis dari HRIS. Jangan ubah manual.')
                    ->disabled(fn ($record) => $record?->hris_job_opening_id !== null),

                Placeholder::make('status_hint')
                    ->label('Status')
                    ->content(function ($record) {
                        if (! $record) {
                            return 'Nonaktif — lowongan baru mulai sebagai draft. Publish lewat tombol di daftar lowongan.';
                        }

                        $parts = [$record->is_active
                            ? 'Tayang — pelamar bisa melihat & melamar.'
                            : 'Nonaktif — tidak tampil di halaman pelamar.'];

                        if ($record->created_by_name) {
                            $parts[] = 'Dibuat oleh ' . $record->created_by_name . '.';
                        }
                        if ($record->published_at) {
                            $parts[] = 'Publish pertama ' . $record->published_at->translatedFormat('d M Y')
                                . ($record->published_by ? ' oleh ' . $record->published_by : '') . '.';
                        }
                        if ($record->closed_at) {
                            $parts[] = 'Ditutup ' . $record->closed_at->translatedFormat('d M Y')
                                . ($record->closed_by ? ' oleh ' . $record->closed_by : '') . '.';
                        }

                        return implode(' ', $parts);
                    })
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Tes')->schema([
                Select::make('quizzes')
                    ->label('Kuis')
                    ->relationship('quizzes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Kuis yang dipakai untuk lowongan ini. Kelola soal di menu Kuis.')
                    ->columnSpanFull(),

                Toggle::make('requires_psychotest')
                    ->label('Wajib Psikotes Offline')
                    ->helperText('Aktifkan untuk memilih Tes Offline yang dipakai lowongan ini.')
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => ! $state ? $set('offlineTests', []) : null),

                Select::make('offlineTests')
                    ->label('Tes Offline')
                    ->relationship('offlineTests', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Tes offline (tulis di lokasi) untuk lowongan ini. Kelola master tes di menu Master > Tes Offline. Jadwal & undangan tetap diatur di menu Kandidat > Tes.')
                    ->hidden(fn (Get $get) => ! $get('requires_psychotest'))
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Detail')->schema([
                RichEditor::make('description')->label('Deskripsi Jabatan')->columnSpanFull(),
                RichEditor::make('requirements')->label('Kualifikasi & Persyaratan')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_display')
                    ->label('Perusahaan')
                    ->state(fn ($record) => $record->company?->name ?? $record->company_name)
                    ->searchable(query: fn ($query, $search) => $query
                        ->whereHas('company', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('company_name', 'like', "%{$search}%")),

                TextColumn::make('title')->label('Posisi')->searchable()->sortable(),

                TextColumn::make('stage')
                    ->label('Tahap')
                    ->state(fn ($record) => $record->currentStage()['label'])
                    ->badge()
                    ->color(fn ($record) => $record->currentStage()['color']),

                TextColumn::make('quota')->label('Kuota')->sortable(),

                TextColumn::make('max_applicants')
                    ->label('Maks. Pelamar')
                    ->default('∞')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('applications_deadline')
                    ->label('Batas Lamaran')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('hris_job_opening_id')
                    ->label('HRIS')
                    ->boolean()
                    ->state(fn ($record) => $record->hris_job_opening_id !== null)
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-minus')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('is_active')->label('Status')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => $state ? 'Tayang' : 'Nonaktif')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_by_name')->label('Dibuat oleh')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')->label('Publish')->dateTime('d M Y, H:i')->placeholder('—')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_by')->label('Publish oleh')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('closed_at')->label('Ditutup')->dateTime('d M Y, H:i')->placeholder('—')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_by')->label('Ditutup oleh')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')
                    ->trueLabel('Tayang')->falseLabel('Nonaktif'),
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->visible(fn () => static::lockedCompanyId() === null)
                    ->options(fn () => Company::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('stageLogs')
                    ->label('Riwayat')
                    ->icon('heroicon-m-chart-bar')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Riwayat Proses — ' . $record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn ($record): HtmlString => static::stageLogsTable($record)),

                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-m-rocket-launch')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_active && static::canModify())
                    ->requiresConfirmation()
                    ->modalHeading('Publish lowongan ini?')
                    ->modalDescription('Lowongan langsung tampil di halaman pelamar.')
                    ->action(function ($record): void {
                        $record->update([
                            'is_active' => true,
                            'published_at' => $record->published_at ?? now(),
                            'published_by' => filament()->auth()->user()?->name,
                            'closed_at' => null,
                            'closed_by' => null,
                        ]);
                    }),

                Action::make('unpublish')
                    ->label('Tutup Lowongan')
                    ->icon('heroicon-m-lock-closed')
                    ->color('gray')
                    ->visible(fn ($record) => $record->is_active && static::canModify())
                    ->requiresConfirmation()
                    ->modalHeading('Tutup lowongan ini?')
                    ->modalDescription('Lowongan disembunyikan dari halaman pelamar. Proses kandidat yang sudah masuk tetap berjalan.')
                    ->action(fn ($record) => $record->update([
                        'is_active' => false,
                        'closed_at' => now(),
                        'closed_by' => filament()->auth()->user()?->name,
                    ])),

                // Panel ini di dalam HRIS — edit lowongan (termasuk hasil sinkron dari HRIS) aman
                // di sini. Read-only untuk company mode 'hris' ditegakkan di project rekrutmen,
                // bukan di sini. ponytail: kalau lowongan ini disimpan ulang di HRIS induk,
                // RecruitmentSync menimpa lagi field yang diubah di sini.
                EditAction::make(),
                DeleteAction::make()->visible(fn ($record) => $record->hris_job_opening_id === null),
            ])
            ->toolbarActions([]);
    }

    private static function stageLogsTable(JobOpening $record): HtmlString
    {
        $labelMap = [
            'applied' => 'Lamaran Masuk', 'screening' => 'Peninjauan Berkas',
            'shortlisted' => 'Lolos Seleksi Berkas', 'interview' => 'Wawancara',
            'offering' => 'Offering Letter', 'mcu' => 'MCU', 'onboarding' => 'TTD & Onboarding',
            'passed' => 'Diterima', 'rejected' => 'Tidak Dilanjutkan',
        ];
        $colorMap = [
            'shortlisted' => '#047C54', 'interview' => '#0284c7', 'mcu' => '#7c3aed',
            'onboarding' => '#d97706', 'passed' => '#16a34a', 'rejected' => '#dc2626',
            'screening' => '#64748b', 'applied' => '#94a3b8',
        ];

        $logs = $record->stageLogs()->with('actor')->get();

        if ($logs->isEmpty()) {
            return new HtmlString('<p style="color:#94a3b8;font-size:13px;padding:16px 0;">Belum ada riwayat proses untuk lowongan ini.</p>');
        }

        $rows = $logs->map(function ($log) use ($labelMap, $colorMap) {
            $from = $log->from_status ? ($labelMap[$log->from_status] ?? $log->from_status) : '—';
            $to = $labelMap[$log->to_status] ?? $log->to_status;
            $color = $colorMap[$log->to_status] ?? '#64748b';
            $actor = $log->actor?->name ?? 'Sistem';
            $date = $log->done_at?->translatedFormat('d M Y, H:i') ?? '—';

            return "<tr style=\"border-bottom:1px solid #f1f5f9;\">
                <td style=\"padding:10px 12px;font-size:12px;color:#64748b;white-space:nowrap;\">{$date}</td>
                <td style=\"padding:10px 12px;font-size:12px;color:#334155;\">{$from}</td>
                <td style=\"padding:10px 12px;\"><span style=\"background:{$color}20;color:{$color};font-weight:700;font-size:11px;padding:3px 10px;border-radius:999px;\">{$to}</span></td>
                <td style=\"padding:10px 12px;font-size:13px;font-weight:700;color:#1e293b;text-align:center;\">{$log->count}</td>
                <td style=\"padding:10px 12px;font-size:12px;color:#64748b;\">{$actor}</td>
            </tr>";
        })->join('');

        return new HtmlString("
            <div style=\"overflow-x:auto;\"><table style=\"width:100%;border-collapse:collapse;\">
                <thead><tr style=\"background:#f8fafc;border-bottom:2px solid #e2e8f0;\">
                    <th style=\"padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;text-align:left;\">Waktu</th>
                    <th style=\"padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;text-align:left;\">Dari</th>
                    <th style=\"padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;text-align:left;\">Ke Tahap</th>
                    <th style=\"padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;text-align:center;\">Jumlah</th>
                    <th style=\"padding:8px 12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;text-align:left;\">Oleh</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table></div>
        ");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobOpenings::route('/'),
            'create' => CreateJobOpening::route('/create'),
            'edit' => EditJobOpening::route('/{record}/edit'),
            'view' => ViewJobOpening::route('/{record}'),
        ];
    }
}
