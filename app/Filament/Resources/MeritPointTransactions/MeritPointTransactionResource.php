<?php

namespace App\Filament\Resources\MeritPointTransactions;

use App\Enum\Pembinaan\PembinaanSettingEnum;
use App\Filament\Resources\MeritPointTransactions\Pages\CreateMeritPointTransaction;
use App\Filament\Resources\MeritPointTransactions\Pages\ListMeritPointTransactions;
use App\Filament\Resources\MeritPointTransactions\Schemas\MeritPointTransactionForm;
use App\Filament\Resources\MeritPointTransactions\Tables\MeritPointTransactionsTable;
use App\Helpers\PermissionHelper;
use App\Models\MeritPoint\MeritPointTransaction;
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
 * "Beri Merit Point" (planning/2026-09-23-sp-dan-merit-point.md §3.4) - List + Create saja,
 * ledger tidak pernah diedit (§3.2). Masih berstatus DEMO, disembunyikan total kalau
 * MERIT_POINT_ENABLED=false untuk company user (§3.1), sama pola MeritPointRuleResource.
 */
class MeritPointTransactionResource extends Resource
{
    protected static ?string $model = MeritPointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string | UnitEnum | null $navigationGroup = 'Pembinaan';

    protected static ?string $navigationLabel = 'Beri Merit Point';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return MeritPointTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeritPointTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeritPointTransactions::route('/'),
            'create' => CreateMeritPointTransaction::route('/create'),
        ];
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        if (! parent::canAccess()) {
            return false;
        }

        $user = Auth::user();

        if ($user && $user->company_id && ! PermissionHelper::hasGlobalRole($user)) {
            return (bool) Setting::resolve(
                PembinaanSettingEnum::MERIT_POINT_ENABLED->value,
                $user->company_id,
                filter_var(PembinaanSettingEnum::MERIT_POINT_ENABLED->default(), FILTER_VALIDATE_BOOLEAN)
            );
        }

        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery()->latest();

        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->whereHas('user', fn (Builder $q) => $q->where('company_id', $user->company_id));
        }

        return $query;
    }
}
