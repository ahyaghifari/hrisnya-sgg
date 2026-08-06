<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests;

use App\Enum\JobPositionReqStatus;
use App\Enum\ManpowerRequestType;
use App\Filament\Resources\ManPower\ManpowerRequests\Pages\CreateManpowerRequest;
use App\Filament\Resources\ManPower\ManpowerRequests\Pages\EditManpowerRequest;
use App\Filament\Resources\ManPower\ManpowerRequests\Pages\ListManpowerRequests;
use App\Filament\Resources\ManPower\ManpowerRequests\Schemas\ManpowerRequestForm;
use App\Filament\Resources\ManPower\ManpowerRequests\Tables\ManpowerRequestsTable;
use App\Helpers\PermissionHelper;
use App\Models\Job\ManpowerRequests;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManpowerRequestResource extends Resource
{
    protected static ?string $model = ManpowerRequests::class;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function form(Schema $schema): Schema
    {
        return ManpowerRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManpowerRequestsTable::configure($table);
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
            'index' => ListManpowerRequests::route('/'),
            'create' => CreateManpowerRequest::route('/create'),
            'edit' => EditManpowerRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        $user = Auth::user();

        $getCount = function (string $type) use ($user): int {
            $query = ManpowerRequests::where('type', $type)
                ->where('status', JobPositionReqStatus::REQUESTED->value)
                ->where('company_id', $user->company_id);

            return $query->count();
        };

        return [
            NavigationItem::make('Kuota')
                ->label('Kuota')
                ->icon(self::$navigationIcon)
                ->sort(2)
                ->url(static::getUrl('index', ['type' => 'quota']))
                ->isActiveWhen(fn (): bool => request()->get('type') === 'quota')
                ->badge(function () use ($getCount) {
                    $count = $getCount(ManpowerRequestType::ADDITIONAL_QUOTA->value);
                    return $count > 0 ? (string)$count : '0';
                }),

            NavigationItem::make('Perubahan Karyawan')
                ->label('Perubahan Karyawan')
                ->icon(self::$navigationIcon)
                ->sort(3)
                ->url(static::getUrl('index', ['type' => 'employee_change']))
                ->isActiveWhen(fn (): bool => request()->get('type') === 'employee_change')
                ->badge(function () use ($getCount) {
                    $count = $getCount(ManpowerRequestType::ADDITIONAL_EMPLOYEE->value) +
                             $getCount(ManpowerRequestType::EMPLOYEE_REPLACEMENT->value);
                    return $count > 0 ? (string)$count : '0';
                }),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }
}
