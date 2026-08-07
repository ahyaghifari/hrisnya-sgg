<?php

namespace App\Filament\Resources\Presensi\Schedules;

use App\Enum\ScheduleType;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Presensi\Schedules\Pages\ManageSchedules;
use App\Models\Shift\Schedule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Versi admin dari modul Jadwal (`Schedule`, header/master jadwal) - sengaja MINIM, cuma
 * ganti nama/company/warna + hapus. Isi konkret jadwal (hari fixed/flexible, tipe shift,
 * rotasi, penempatan karyawan) TETAP cuma dikelola lewat portal (SchedulesPage/
 * CreateSchedulePage/ScheduleAssignmentsPage/ScheduleShiftRotationTab dkk) — wizard multi-step
 * itu tidak direplikasi di sini. Resource ini dibuat murni supaya permission Create/Update/
 * Delete:Schedule (portal) dan ViewAny/View:Schedule ke-generate rapi lewat `shield:generate`,
 * gantikan permission manual yang dulu ditulis tangan di SchedulePolicy/CustomPermissionsSeeder
 * (instruksi user 2026-08-07, lihat riwayat migration cleanup_orphaned_shield_permissions).
 * Makanya TIDAK ada CreateAction di admin (lihat ManageSchedules::getHeaderActions()) - bikin
 * jadwal baru cuma lewat wizard portal, tapi permission Create:Schedule tetap ke-generate
 * Shield & tetap dipakai gerbang CreateSchedulePage di portal.
 */
class ScheduleResource extends BaseCompanyResource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationLabel = 'Jadwal';

    protected static ?string $modelLabel = 'Jadwal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Presensi & Absensi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Jadwal')
                    ->required()
                    ->maxLength(150),

                // Ganti tipe jadwal existing bisa bikin data anak (fixedDays/flexibleDays/
                // shiftTypes, beda tabel per tipe) yatim/salah baca - dikunci begitu record
                // sudah ada, cuma boleh dipilih saat bikin baru dari portal.
                Select::make('schedule_type')
                    ->label('Tipe Jadwal')
                    ->options(ScheduleType::class)
                    ->required()
                    ->disabled(fn (?Schedule $record) => $record !== null)
                    ->dehydrated(),

                ColorPicker::make('color')
                    ->label('Warna'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),

                TextColumn::make('name')
                    ->label('Nama Jadwal')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule_type')
                    ->label('Tipe Jadwal')
                    ->badge(),

                ColorColumn::make('color')
                    ->label('Warna'),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                // Jadwal yang masih punya penempatan karyawan aktif tidak boleh dihapus dari
                // sini - hapus lewat portal (ada konfirmasi + penanganan riwayat presensi).
                DeleteAction::make()
                    ->visible(fn (Schedule $record) => static::canDelete($record))
                    ->before(function (DeleteAction $action, Schedule $record) {
                        if ($record->assignments()->exists()) {
                            Notification::make()
                                ->title('Jadwal ini masih punya karyawan yang ditempatkan, tidak bisa dihapus.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->assignments()->exists();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchedules::route('/'),
        ];
    }
}
