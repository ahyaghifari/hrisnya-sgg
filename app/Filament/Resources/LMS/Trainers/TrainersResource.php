<?php

namespace App\Filament\Resources\LMS\Trainers;

use App\Filament\Resources\LMS\Trainers\Pages\CreateTrainers;
use App\Filament\Resources\LMS\Trainers\Pages\EditTrainers;
use App\Filament\Resources\LMS\Trainers\Pages\ListTrainers;
use App\Filament\Resources\LMS\Trainers\Schemas\TrainersForm;
use App\Filament\Resources\LMS\Trainers\Tables\TrainersTable;
use App\Models\LMS\Trainer\Trainers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Sebelumnya model ini TIDAK punya Resource/Policy sama sekali (dropdown "Pilih Trainer" di
 * form Meeting cuma listing, tidak ada create/edit di mana pun) — dibuat sekarang murni biar
 * Shield auto-generate permission ViewAny/View/Create/Update/Delete:Trainers + Policy, yang
 * lalu dipakai halaman portal "Pemateri" (App\Livewire\LMS\TrainersPage). Bukan buat dipakai
 * sebagai UI utama (itu di portal), tapi tetap fungsional kalau perlu diakses dari /admin.
 */
class TrainersResource extends Resource
{
    protected static ?string $model = Trainers::class;

    protected static ?string $navigationLabel = 'Pemateri';

    protected static ?string $modelLabel = 'Pemateri';

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return TrainersForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainersTable::configure($table);
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
            'index' => ListTrainers::route('/'),
            // 'create' => CreateTrainers::route('/create'),
            // 'edit' => EditTrainers::route('/{record}/edit'),
        ];
    }
}
