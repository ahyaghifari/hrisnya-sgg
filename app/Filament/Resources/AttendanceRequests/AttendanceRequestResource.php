<?php

namespace App\Filament\Resources\AttendanceRequests;

use App\Filament\Resources\AttendanceRequests\Pages\CreateAttendanceRequest;
use App\Filament\Resources\AttendanceRequests\Pages\EditAttendanceRequest;
use App\Filament\Resources\AttendanceRequests\Pages\ListAttendanceRequests;
use App\Filament\Resources\AttendanceRequests\Schemas\AttendanceRequestForm;
use App\Filament\Resources\AttendanceRequests\Tables\AttendanceRequestsTable;
use App\Filament\Resources\BaseResource;
use App\Models\Attendance\AttendanceRequests;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Company-nya di sini bukan kolom langsung (attendance_requests tidak punya company_id, cuma
 * lewat relasi user) - jadi extend BaseResource (helper isSuperAdmin()/companyId()/user()) BUKAN
 * BaseCompanyResource (itu asumsi company_id kolom sendiri di tabel, lihat komentarnya).
 */
class AttendanceRequestResource extends BaseResource
{
    protected static ?string $model = AttendanceRequests::class;

    protected static string | UnitEnum | null $navigationGroup = 'Presensi & Absensi';

    public static function form(Schema $schema): Schema
    {
        return AttendanceRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'user.company',
            'approvalSnapshot.activity'
        ]);

        if (! static::isSuperAdmin() && static::companyId()) {
            $query->whereHas('user', function ($q) {
                $q->where('company_id', static::companyId());
            });
        }

        return $query;
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
            'index' => ListAttendanceRequests::route('/'),
            'create' => CreateAttendanceRequest::route('/create'),
            'edit' => EditAttendanceRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Izin')
                ->label('Izin')
                ->url(static::getUrl('index', ['type' => 'permission']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'permission')
                ->group(static::getNavigationGroup()),

            NavigationItem::make('Cuti')
                ->label('Cuti')
                ->url(static::getUrl('index', ['type' => 'leave']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'leave')
                ->group(static::getNavigationGroup()),

            NavigationItem::make('Lembur')
                ->label('Lembur')
                ->url(static::getUrl('index', ['type' => 'overtime']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'overtime')
                ->group(static::getNavigationGroup()),
        ];
    }

    public static function canApprove(): bool
    {
        return true;
    }
}
