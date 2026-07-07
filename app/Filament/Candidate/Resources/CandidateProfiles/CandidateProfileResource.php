<?php

namespace App\Filament\Candidate\Resources\CandidateProfiles;

use App\Filament\Candidate\Resources\CandidateProfiles\Pages\CreateCandidateProfile;
use App\Filament\Candidate\Resources\CandidateProfiles\Pages\EditCandidateProfile;
use App\Filament\Candidate\Resources\CandidateProfiles\Pages\ListCandidateProfiles;
use App\Filament\Candidate\Resources\CandidateProfiles\Pages\ViewCandidateProfile;
use App\Filament\Candidate\Resources\CandidateProfiles\RelationManagers\DocumentsRelationManager;
use App\Filament\Candidate\Resources\CandidateProfiles\Schemas\CandidateProfileForm;
use App\Filament\Candidate\Resources\CandidateProfiles\Tables\CandidateProfilesTable;
use App\Models\Candidate\Candidates;
use App\Models\CandidateProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CandidateProfileResource extends Resource
{
    protected static ?string $model = Candidates::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
    
    protected static ?string $navigationLabel = 'Data Diri';

    public static function form(Schema $schema): Schema
    {
        return CandidateProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidateProfilesTable::configure($table);
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
            'index' => ListCandidateProfiles::route('/'),
            'create' => CreateCandidateProfile::route('/create'),
            'edit' => EditCandidateProfile::route('/{record}/edit'),
            'view' => ViewCandidateProfile::route('/{record}'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        return static::getUrl('view', ['record' => Auth::user()->id]);   
    }
}
