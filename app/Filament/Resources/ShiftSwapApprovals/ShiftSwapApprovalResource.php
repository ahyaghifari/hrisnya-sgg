<?php

namespace App\Filament\Resources\ShiftSwapApprovals;

use App\Filament\Clusters\ShiftPattern\ShiftPatternCluster;
use App\Filament\Resources\ShiftSwapApprovals\Pages\EditShiftSwapApproval;
use App\Filament\Resources\ShiftSwapApprovals\Pages\ListShiftSwapApprovals;
use App\Filament\Resources\ShiftSwapApprovals\Schemas\ShiftSwapApprovalForm;
use App\Filament\Resources\ShiftSwapApprovals\Tables\ShiftSwapApprovalsTable;
use App\Models\Shift\ShiftSwapRequests;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShiftSwapApprovalResource extends Resource
{
    protected static ?string $model = ShiftSwapRequests::class;

    protected static ?string $cluster = ShiftPatternCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Persetujuan Tukar Shift';

    protected static ?int $navigationSort = 4;


    public static function form(Schema $schema): Schema
    {
        return ShiftSwapApprovalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftSwapApprovalsTable::configure($table);
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
            'index' => ListShiftSwapApprovals::route('/'),
            'edit'  => EditShiftSwapApproval::route('/{record}/edit'),
        ];
    }
}
