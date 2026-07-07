<?php

namespace App\Filament\Resources\ApprovalFlows;

use App\Filament\Resources\ApprovalFlows\Pages\CreateApprovalFlow;
use App\Filament\Resources\ApprovalFlows\Pages\EditApprovalFlow;
use App\Filament\Resources\ApprovalFlows\Pages\ListApprovalFlows;
use App\Filament\Resources\ApprovalFlows\Schemas\ApprovalFlowForm;
use App\Filament\Resources\ApprovalFlows\Tables\ApprovalFlowsTable;
use App\Helpers\PermissionHelper;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ApprovalFlowResource extends Resource
{
    protected static ?string $model = MasterApprovalFlows::class;

    protected static ?string $navigationLabel = "Manajemen Persetujuan";

    protected static string | UnitEnum | null $navigationGroup = 'Manajemen Organisasi';

    protected static ?int $navigationSort = 9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBarsArrowDown;

    public static function form(Schema $schema): Schema
    {
        return ApprovalFlowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApprovalFlowsTable::configure($table);
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
            'index' => ListApprovalFlows::route('/'),
            'create' => CreateApprovalFlow::route('/create'),
            'edit' => EditApprovalFlow::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['steps']);

        $user = Auth::user();
        if ($user && $user->company_id && !PermissionHelper::hasGlobalRole($user)) {
            $query->where('company_id', $user->company_id);
        }

        return $query;
    }


    public static function canAccess(): bool
    {
        return Gate::allows('view_manajemen_persetujuan');
    }

    public static function canView($record): bool
    {
        return Gate::allows('view_manajemen_persetujuan');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create_manajemen_persetujuan');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('edit_manajemen_persetujuan');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('delete_manajemen_persetujuan');
    }
}
