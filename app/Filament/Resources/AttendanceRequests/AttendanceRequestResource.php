<?php

namespace App\Filament\Resources\AttendanceRequests;

use App\Filament\Resources\AttendanceRequests\Pages\CreateAttendanceRequest;
use App\Filament\Resources\AttendanceRequests\Pages\EditAttendanceRequest;
use App\Filament\Resources\AttendanceRequests\Pages\ListAttendanceRequests;
use App\Filament\Resources\AttendanceRequests\Schemas\AttendanceRequestForm;
use App\Filament\Resources\AttendanceRequests\Tables\AttendanceRequestsTable;
use App\Helpers\PermissionHelper;
use App\Models\Attendance\AttendanceRequests;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendanceRequestResource extends Resource
{
    protected static ?string $model = AttendanceRequests::class;

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

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
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with([
            'user.company',
            'approvalSnapshot.activity'
        ]);

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
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
        $user = Auth::user();
        $canApprove = static::canApprove();

        $getCount = function (string $type) use ($user, $canApprove) {
            $query = AttendanceRequests::where('type', $type)
                ->where('status', 'in_review');

            if ($canApprove) {
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
            } else {
                $query->where('user_id', $user->id);
            }

            $count = $query->count();
            return $count > 0 ? (string) $count : 0;
        };

        return [
            NavigationItem::make('Izin')
                ->label('Izin')
                ->url(static::getUrl('index', ['type' => 'permission', 'tab' => '0']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'permission')
                ->group(static::getNavigationGroup())
                ->badge($getCount('permission')),

            NavigationItem::make('Cuti')
                ->label('Cuti')
                ->url(static::getUrl('index', ['type' => 'leave', 'tab' => '1']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'leave')
                ->group(static::getNavigationGroup())
                ->badge($getCount('leave')),

            NavigationItem::make('Lembur')
                ->label('Lembur')
                ->url(static::getUrl('index', ['type' => 'overtime', 'tab' => '1']))
                ->icon('heroicon-o-calendar-date-range')
                ->isActiveWhen(fn (): bool => request()->query('type') === 'overtime')
                ->group(static::getNavigationGroup())
                ->badge($getCount('overtime')),
        ];
    }

    public static function canApprove(): bool
    {
        return true;
    }
}
