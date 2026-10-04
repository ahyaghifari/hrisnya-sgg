<?php

namespace App\Filament\Resources\AttendanceRecords;

use App\Filament\Resources\AttendanceRecords\Pages\AttendanceReport;
use App\Filament\Resources\AttendanceRecords\Pages\CreateAttendanceRecord;
use App\Filament\Resources\AttendanceRecords\Pages\EditAttendanceRecord;
use App\Filament\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Filament\Resources\AttendanceRecords\Pages\ViewAttendanceRecord;
use App\Filament\Resources\AttendanceRecords\Schemas\AttendanceRecordForm;
use App\Filament\Resources\AttendanceRecords\Tables\AttendanceRecordsTable;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendanceRecords;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecords::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | UnitEnum | null $navigationGroup = 'Presensi & Absensi';

    protected static ?string $navigationLabel = 'Data Absensi';

    protected static ?int $navigationSort = 4; // lihat AttendanceRequestResource

    protected static ?string $modelLabel = 'Absensi';

    protected static ?string $pluralModelLabel = 'Data Absensi';

    public static function form(Schema $schema): Schema
    {
        return AttendanceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceRecordsTable::configure($table);
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
            'index' => AttendanceReport::route('/'),
            'records' => ListAttendanceRecords::route('/records'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with([
            'user.company',
        ]);

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->whereCompany($user->company_id);
            });
        }

        return $query;
    }

    public static function getNavigationItems(): array
    {
        $items = [];

        $items[] = NavigationItem::make()
            ->label('Data Absensi')
            ->icon(static::$navigationIcon)
            ->group('Presensi & Absensi')
            ->sort(3)
            ->url(static::getUrl('index', ['type' => 'report']))
            ->isActiveWhen(fn (): bool => (
                request()->get('type') === 'report'
                || (
                    request()->routeIs('filament.admin.resources.attendance-records.records')
                    && request()->get('from') === 'kepegawaian'
                )
            ));

        return $items;
    }
}
