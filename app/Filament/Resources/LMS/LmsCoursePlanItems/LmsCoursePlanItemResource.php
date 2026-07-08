<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems;

use App\Filament\Resources\LMS\LmsCoursePlanItems\Pages\EditLmsCoursePlanItem;
use App\Filament\Resources\LMS\LmsCoursePlanItems\Pages\ListLmsCoursePlanItems;
use App\Filament\Resources\LMS\LmsCoursePlanItems\Pages\ViewLmsCoursePlanitem;
use App\Filament\Resources\LMS\LmsCoursePlanItems\Schemas\LmsCoursePlanItemForm;
use App\Filament\Resources\LMS\LmsCoursePlanItems\Tables\LmsCoursePlanItemsTable;
use App\Helpers\PermissionHelper;
use App\Models\LMS\LmsCoursePlanItem;
use App\Models\LMS\LmsCoursePlanItems;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LmsCoursePlanItemResource extends Resource
{
    protected static ?string $model = LmsCoursePlanItems::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?string $navigationLabel = 'Item Rencana Pelatihan';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return LmsCoursePlanItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LmsCoursePlanItemsTable::configure($table);
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
            'index' => ListLmsCoursePlanItems::route('/'),
            'edit' => EditLmsCoursePlanItem::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        $user = Auth::user();
        $items = [];

        $getCount = function (string $type) use ($user): int {
            $query = LmsCoursePlanItems::whereNull('approved_at')
                ->where('request_status', '!=', 'draft')
                ->whereHas('user', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });

            return $query->count();
        };

        // Navigation untuk Manajemen Organisasi (approval) - hanya muncul jika punya permission approval_pengajuan_pelatihan
        if (Gate::allows('approve_pengajuan_pelatihan')) {
            $items[] = NavigationItem::make()
                ->label('Pelatihan')
                ->url(static::getUrl('index'))
                ->icon(static::$navigationIcon)
                ->group('Manajemen Organisasi')
                ->sort(4)
                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.l-m-s.lms-course-plan-items.index'))
                ->badge(function () use ($getCount) {
                    $count = $getCount('approval');
                    return $count > 0 ? (string)$count : '0';
                });
        }

        return $items;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->with([
            'user.company',
        ]);

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        return $query;
    }
}
