<?php

namespace App\Filament\Resources\EmployeeSps\Schemas;

use App\Enum\Sp\SpLevel;
use App\Helpers\PermissionHelper;
use App\Models\Coaching\Coaching;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * SP dibuat lewat resource ini bisa MANUAL murni atau berdasarkan Coaching tertentu (opsional,
 * field "coaching_id" - instruksi user 2026-09-23: Coaching & SP dipisah total). Restrukturisasi
 * 2026-09-25 (lihat diagram ER): reason_id/reason_note DIGANTI TOTAL oleh `items` (poin
 * pelanggaran bebas per baris, Repeater ->relationship() - Filament otomatis kelola sp_items).
 * Berita Acara (sp_reports, 1:1, opsional) ditangani manual di
 * CreateEmployeeSp::afterCreate() karena bukan relasi HasMany. end_date dihitung otomatis dari
 * PembinaanSettingEnum di CreateEmployeeSp::mutateFormDataBeforeCreate().
 */
class EmployeeSpForm
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
        $auth = Auth::user();
        $userOptions = User::query()
            ->when(
                $auth && $auth->company_id && !PermissionHelper::hasGlobalRole($auth),
                fn ($q) => $q->where('company_id', $auth->company_id)
            )
            ->pluck('name', 'id');

        $levelOptions = collect(SpLevel::cases())->mapWithKeys(fn ($level) => [$level->value => $level->getLabel()]);

        return [
            Section::make('Informasi Karyawan')
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Karyawan')
                        ->searchable()
                        ->options($userOptions)
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('coaching_id', null))
                        ->required(),

                    Select::make('coaching_id')
                        ->label('Sumber dari Coaching (opsional)')
                        ->helperText('Isi kalau SP ini terbit sebagai tindak lanjut dari sesi Coaching tertentu.')
                        ->options(function (callable $get) {
                            if (! $get('user_id')) {
                                return [];
                            }

                            return Coaching::where('user_id', $get('user_id'))
                                ->latest()
                                ->get()
                                ->mapWithKeys(fn (Coaching $c) => [$c->id => "{$c->title} ({$c->created_at->format('d M Y')})"]);
                        })
                        ->disabled(fn (callable $get) => ! $get('user_id')),
                ])
                ->columnSpanFull(),

            Section::make('Surat Peringatan')
                ->columns(2)
                ->schema([
                    TextInput::make('nomor')
                        ->label('Nomor Surat')
                        ->placeholder('mis. 001/SP/HC-GA/IX/2026')
                        ->required(),

                    Select::make('sp_level')
                        ->label('Level SP')
                        ->options($levelOptions)
                        ->required(),

                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->default(today())
                        ->required(),

                    Select::make('knows_by')
                        ->label('Mengetahui')
                        ->helperText('Pimpinan/HC Supervisor/Manager yang menyetujui penerbitan SP ini.')
                        ->searchable()
                        ->options($userOptions)
                        ->required(),

                    Repeater::make('items')
                        ->relationship('items')
                        ->label('Poin Pelanggaran')
                        ->helperText('Nomor poin ditulis manual di masing-masing baris (mis. "1. ...").')
                        ->simple(
                            Textarea::make('text')->required()->rows(2)
                        )
                        ->orderColumn('sort_order')
                        ->addActionLabel('Tambah Poin')
                        ->defaultItems(0)
                        ->minItems(1)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Berita Acara (Opsional)')
                ->description('Catatan investigasi HR-internal, tidak ditampilkan ke karyawan.')
                ->columns(1)
                ->collapsible()
                ->schema([
                    RichEditor::make('kejadian')
                        ->label('Kejadian'),

                    RichEditor::make('subject_clarification')
                        ->label('Klarifikasi Karyawan'),

                    RichEditor::make('analysis')
                        ->label('Analisis'),

                    RichEditor::make('followup_plan')
                        ->label('Rencana Tindak Lanjut'),
                ])
                ->columnSpanFull(),
        ];
    }

    protected static function editSchema(): array
    {
        return [
            Section::make('SP')
                ->icon('heroicon-o-exclamation-triangle')
                ->columns(2)
                ->schema([
                    TextEntry::make('nomor')
                        ->label('Nomor Surat')
                        ->placeholder('-'),

                    TextEntry::make('user.name')
                        ->label('Karyawan'),

                    TextEntry::make('sp_level')
                        ->label('Level SP')
                        ->badge(),

                    TextEntry::make('source_type')
                        ->label('Sumber'),

                    TextEntry::make('coaching.title')
                        ->label('Coaching Terkait')
                        ->placeholder('-'),

                    TextEntry::make('start_date')
                        ->label('Tanggal Mulai')
                        ->date(),

                    TextEntry::make('end_date')
                        ->label('Tanggal Berakhir')
                        ->date()
                        ->placeholder('Tidak pernah berakhir (SP 3)'),

                    TextEntry::make('createdBy.name')
                        ->label('Dibuat Oleh'),

                    TextEntry::make('knowsBy.name')
                        ->label('Mengetahui')
                        ->placeholder('-'),

                    TextEntry::make('created_at')
                        ->label('Diterbitkan Pada')
                        ->dateTime(),
                ])->columnSpanFull(),

            Section::make('Poin Pelanggaran')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('text')->hiddenLabel(),
                        ]),
                ])->columnSpanFull(),

            Section::make('Berita Acara')
                ->icon('heroicon-o-document-text')
                ->columns(1)
                ->visible(fn ($record) => $record->report !== null)
                ->schema([
                    TextEntry::make('report.kejadian')
                        ->label('Kejadian')
                        ->placeholder('-')
                        ->html(),

                    TextEntry::make('report.subject_clarification')
                        ->label('Klarifikasi Karyawan')
                        ->placeholder('-')
                        ->html(),

                    TextEntry::make('report.analysis')
                        ->label('Analisis')
                        ->placeholder('-')
                        ->html(),

                    TextEntry::make('report.followup_plan')
                        ->label('Rencana Tindak Lanjut')
                        ->placeholder('-')
                        ->html(),
                ])->columnSpanFull(),
        ];
    }
}
