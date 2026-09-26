<?php

namespace App\Filament\Resources\MeritPointRules;

use App\Enum\Kepegawaian\KepegawaianSettingEnum;
use App\Filament\Resources\MeritPointRules\Pages\CreateMeritPointRule;
use App\Filament\Resources\MeritPointRules\Pages\EditMeritPointRule;
use App\Filament\Resources\MeritPointRules\Pages\ListMeritPointRules;
use App\Filament\Resources\MeritPointRules\Schemas\MeritPointRuleForm;
use App\Filament\Resources\MeritPointRules\Tables\MeritPointRulesTable;
use App\Helpers\PermissionHelper;
use App\Models\MeritPoint\MeritPointRule;
use App\Models\Setting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Merit Point masih berstatus DEMO (planning/2026-09-23-sp-dan-merit-point.md §3). Menu ini
 * disembunyikan total (§3.1) untuk user company-scoped yang company-nya belum mengaktifkan
 * MERIT_POINT_ENABLED - Super Admin/global tetap lihat (perlu bisa bikin rule dulu sebelum
 * toggle diaktifkan client-nya).
 */
class MeritPointRuleResource extends Resource
{
    protected static ?string $model = MeritPointRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string | UnitEnum | null $navigationGroup = 'Kepegawaian';

    protected static ?string $navigationLabel = 'Aturan Merit Poin';

    protected static bool $shouldRegisterNavigation = false; // diganti halaman portal kepegawaian.merit-poin

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return MeritPointRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeritPointRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeritPointRules::route('/'),
            'create' => CreateMeritPointRule::route('/create'),
            'edit' => EditMeritPointRule::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        if (! parent::canAccess()) {
            return false;
        }

        $user = Auth::user();

        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            return (bool) Setting::resolve(
                KepegawaianSettingEnum::MERIT_POINT_ENABLED->value,
                $user->company_id,
                filter_var(KepegawaianSettingEnum::MERIT_POINT_ENABLED->default(), FILTER_VALIDATE_BOOLEAN)
            );
        }

        return true;
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
