<?php

namespace App\Filament\Resources\ExtraShiftAssignments;

use App\Enum\HideableModuleEnum;
use App\Filament\Resources\ExtraShiftAssignments\Pages\ManageExtraShiftAssignments;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Setting;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftType;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Sisi admin dari halaman portal /presensi/extra-shift (App\Livewire\Presensi\ExtraShiftPage) -
 * versi pertama cuma jalur "assigned" (langsung approved, tidak lewat approval engine), lihat
 * planning/2026-07-31-fitur-extra-shift.md. Pola Resource niru BpjsDependentResource: 1 file,
 * form/table inline, create/edit lewat modal (ManageRecords).
 */
class ExtraShiftAssignmentResource extends Resource
{
    protected static ?string $model = ExtraShiftAssignment::class;

    protected static ?string $navigationLabel = 'Extra Shift';

    protected static ?string $modelLabel = 'Extra Shift';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Absensi';

    protected static ?int $navigationSort = 5;

    /**
     * Extra Shift bisa disembunyikan per company lewat App\Filament\Pages\ModuleVisibilitySettings
     * (mis. Holding belum pakai). Default aktif kalau belum pernah diatur.
     */
    public static function canAccess(): bool
    {
        return parent::canAccess()
            && Setting::resolve(HideableModuleEnum::EXTRA_SHIFT->value, Auth::user()?->company_id, true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // company_id cuma dipakai buat nyaring pilihan Karyawan - bukan kolom asli di
                // tabel extra_shift_assignments (company diturunkan dari user->company_id).
                Select::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->default(fn () => ! PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (callable $set) => $set('user_id', null)),

                // Cuma karyawan yang jadwalnya tipe Shift - schedule non-shift tidak punya
                // shift_type buat dipilih.
                Select::make('user_id')
                    ->label('Karyawan')
                    ->options(fn (callable $get) => $get('company_id')
                        ? ScheduleAssignment::whereNotNull('user_id')
                            ->whereHas('schedule', fn ($q) => $q->where('schedule_type', 'shift')->where('company_id', $get('company_id')))
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'user.id')
                        : [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('shift_type_id', null)),

                // Jenis shift kebaca dari jadwal MILIK karyawan yang dipilih, bukan 1 jadwal
                // yang dipilih manual duluan - sama logika ExtraShiftPage::getShiftTypesProperty().
                Select::make('shift_type_id')
                    ->label('Shift yang Diambil')
                    ->options(function (callable $get) {
                        if (! $get('user_id')) {
                            return [];
                        }

                        $assignment = ScheduleAssignment::where('user_id', $get('user_id'))
                            ->whereHas('schedule', fn ($q) => $q->where('schedule_type', 'shift'))
                            ->with('schedule.shiftTypes')
                            ->first();

                        return ($assignment?->schedule?->shiftTypes ?? collect())
                            ->mapWithKeys(fn (ScheduleShiftType $type) => [
                                $type->id => "{$type->name} (" . substr($type->start_time, 0, 5) . '-' . substr($type->end_time, 0, 5) . ')',
                            ]);
                    })
                    ->required()
                    ->disabled(fn (callable $get) => ! $get('user_id')),

                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),

                Select::make('covering_user_id')
                    ->label('Menggantikan (opsional)')
                    ->options(fn (callable $get) => $get('company_id')
                        ? User::where('company_id', $get('company_id'))->pluck('name', 'id')
                        : [])
                    ->searchable(),

                Textarea::make('reason')
                    ->label('Alasan')
                    ->rows(2)
                    ->columnSpanFull(),

                // Admin yang buat lewat sini = otomatis jalur "assigned" (langsung approved),
                // sama seperti input dari halaman portal - lihat ExtraShiftPage::assign().
                Hidden::make('status')->default('approved'),
                Hidden::make('source')->default('assigned'),
                Hidden::make('created_by')->default(fn () => Auth::id()),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.company.name')
                    ->label('Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shiftType.name')
                    ->label('Shift'),
                TextColumn::make('coveringUser.name')
                    ->label('Menggantikan')
                    ->placeholder('-'),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('createdBy.name')
                    ->label('Ditugaskan Oleh'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->options(fn () => Companies::query()->pluck('name', 'id'))
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? null : Auth::user()->company_id)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (ExtraShiftAssignment $record) => [
                        ...$record->attributesToArray(),
                        'company_id' => $record->user?->company_id,
                    ]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExtraShiftAssignments::route('/'),
        ];
    }
}
