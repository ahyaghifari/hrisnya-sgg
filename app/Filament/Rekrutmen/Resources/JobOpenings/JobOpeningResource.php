<?php

namespace App\Filament\Rekrutmen\Resources\JobOpenings;

use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\CreateJobOpening;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\EditJobOpening;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\ListJobOpenings;
use App\Filament\Rekrutmen\Resources\JobOpenings\Pages\ViewJobOpening;
use App\Models\Rekrutmen\Company;
use App\Models\Rekrutmen\JobOpening;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Kelola Lowongan — port dari JobOpeningResource project `rekrutmen` (FL3 -> FL4), model
 * di koneksi `recruitment`. Lowongan yang `hris_job_opening_id` terisi (di-push dari HRIS
 * lama) tetap read-only di sini. Company-scoping DIHILANGKAN dulu (perusahaan rekrutmen ≠
 * m_companies HRIS) — semua pemegang SISTEM_REKRUTMEN lihat semua.
 */
class JobOpeningResource extends Resource
{
    protected static ?string $model = JobOpening::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Lowongan';

    protected static ?string $modelLabel = 'Lowongan';

    protected static ?string $pluralModelLabel = 'Lowongan';

    protected static string|UnitEnum|null $navigationGroup = 'Lowongan';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $u = filament()->auth()->user();

        return $u->hasRole(['Admin', 'Super Admin']) || $u->can('ViewAny:JobOpenings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Lowongan')->schema([
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Company::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('company_name', Company::find($state)?->name)),

                TextInput::make('company_name')
                    ->label('Nama Perusahaan (manual)')
                    ->helperText('Otomatis dari perusahaan di atas, atau isi manual.'),

                TextInput::make('title')
                    ->label('Posisi / Jabatan')
                    ->required()
                    ->maxLength(255),

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

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
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
                    ->helperText('Psikotes offline tambahan di luar Tes sistem.'),
            ])->columns(2),

            Section::make('Detail')->schema([
                RichEditor::make('description')->label('Deskripsi Jabatan')->columnSpanFull(),
                RichEditor::make('requirements')->label('Kualifikasi & Persyaratan')->columnSpanFull(),
            ]),

            Section::make('Persetujuan Khusus Jabatan')
                ->description('Persetujuan khusus jabatan ini. Judul SAMA dengan persetujuan perusahaan = menimpa; judul baru = tambahan.')
                ->schema([
                    Repeater::make('agreements')
                        ->relationship()
                        ->label('')
                        ->schema([
                            TextInput::make('title')->label('Judul')->required()->maxLength(255),
                            RichEditor::make('content')->label('Naskah')->required()->columnSpanFull(),
                        ])
                        ->orderColumn('sort_order')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Persetujuan baru')
                        ->addActionLabel('Tambah persetujuan')
                        ->defaultItems(0),
                ])
                ->collapsible()
                ->collapsed(),
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

                IconColumn::make('is_active')->label('Aktif')->boolean()->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status'),
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
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

                EditAction::make()->visible(fn ($record) => $record->hris_job_opening_id === null),
                DeleteAction::make()->visible(fn ($record) => $record->hris_job_opening_id === null),
                ViewAction::make()->visible(fn ($record) => $record->hris_job_opening_id !== null),
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
