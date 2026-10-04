<?php

namespace App\Filament\Resources\ExtraShiftAssignments;

use App\Filament\Resources\UserColumn;
use App\Filament\Resources\ExtraShiftAssignments\Pages\ManageExtraShiftAssignments;
use App\Helpers\PermissionHelper;
use App\Models\Master\Companies;
use App\Models\Shift\ExtraShiftAssignment;
use App\Models\Shift\ScheduleAssignment;
use App\Models\Shift\ScheduleShiftAssignment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

    protected static ?int $navigationSort = 2; // persis di bawah Attendance Requests (pengajuan izin/cuti/lembur)

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
                    ->afterStateUpdated(function (callable $set) {
                        $set('covering_user_id', null);
                        $set('shift_type_id', null);
                    }),

                // Urutan: karyawan (yang extra shift) -> karyawan yang diambil shiftnya -> baru
                // shift_type kebaca dari jadwal MILIK karyawan yang diambil shiftnya itu - sama
                // logika ExtraShiftPage::getShiftTypesProperty().
                Select::make('covering_user_id')
                    ->label('Karyawan yang Diambil Shiftnya')
                    ->options(fn (callable $get) => $get('company_id')
                        ? ScheduleAssignment::whereNotNull('user_id')
                            ->where('user_id', '!=', $get('user_id'))
                            ->whereHas('schedule', fn ($q) => $q->where('schedule_type', 'shift')->where('company_id', $get('company_id')))
                            ->with('user')
                            ->get()
                            ->pluck('user.name', 'user.id')
                        : [])
                    ->searchable()
                    ->required()
                    ->live()
                    ->disabled(fn (callable $get) => ! $get('user_id'))
                    ->afterStateUpdated(fn (callable $set) => $set('shift_type_id', null)),

                // Roster REAL (tanggal + shift yang benar-benar dijadwalkan) milik karyawan yang
                // di-cover, hari ini s/d roster yang sudah di-generate - sama logika
                // ExtraShiftPage::getRosterOptionsProperty(). Pilihan ini nge-set shift_type_id
                // & date sekaligus lewat 2 field Hidden di bawah, bukan kolom asli.
                Select::make('roster_id')
                    ->label('Tanggal & Shift yang Diambil')
                    ->options(function (callable $get) {
                        if (! $get('covering_user_id')) {
                            return [];
                        }

                        return ScheduleShiftAssignment::where('user_id', $get('covering_user_id'))
                            ->where('off_day', false)
                            ->whereNotNull('shift_type_id')
                            ->whereDate('date', '>=', now()->toDateString())
                            ->with('shiftType')
                            ->orderBy('date')
                            ->get()
                            ->mapWithKeys(fn (ScheduleShiftAssignment $roster) => [
                                $roster->id => $roster->date->translatedFormat('d M Y') . ' - ' . $roster->shiftType?->name
                                    . ' (' . substr($roster->shiftType?->start_time, 0, 5) . '-' . substr($roster->shiftType?->end_time, 0, 5) . ')',
                            ]);
                    })
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->disabled(fn (callable $get) => ! $get('covering_user_id'))
                    ->afterStateUpdated(function (callable $set, $state) {
                        $roster = $state ? ScheduleShiftAssignment::find($state) : null;
                        $set('shift_type_id', $roster?->shift_type_id);
                        $set('date', $roster?->date?->toDateString());
                    }),

                Hidden::make('shift_type_id'),
                Hidden::make('date'),

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
                    ->searchable(query: UserColumn::searchCompany())
                    ->sortable(query: UserColumn::sortCompany()),
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable(query: UserColumn::searchName())
                    ->sortable(query: UserColumn::sortName()),
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
