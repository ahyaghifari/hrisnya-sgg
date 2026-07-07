<?php

namespace App\Filament\Resources\LMS\QuestionBanks;

use App\Filament\Resources\LMS\QuestionBanks\Pages\CreateQuestionBanks;
use App\Filament\Resources\LMS\QuestionBanks\Pages\EditQuestionBanks;
use App\Filament\Resources\LMS\QuestionBanks\Pages\ListQuestionBanks;
use App\Filament\Resources\LMS\QuestionBanks\Schemas\QuestionBanksForm;
use App\Filament\Resources\LMS\QuestionBanks\Tables\QuestionBanksTable;
use App\Models\LMS\QuestionBanks;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class QuestionBanksResource extends Resource
{
    protected static ?string $model = QuestionBanks::class;
    
    protected static ?string $navigationLabel = "Bank Soal";

    protected static string | UnitEnum | null $navigationGroup = 'LMS';

    protected static ?string $navigationParentItem = 'Pelatihan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return QuestionBanksForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionBanksTable::configure($table);
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
            'index' => ListQuestionBanks::route('/'),
            'create' => CreateQuestionBanks::route('/create'),
            'edit' => EditQuestionBanks::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_bank_soal') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->can('view_bank_soal') ?? false;
    }
    
    public static function canCreate(): bool
    {
        return Auth::user()?->can('create_bank_soal') ?? false;
    }
    
    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('edit_bank_soal') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('delete_bank_soal') ?? false;
    }
}
