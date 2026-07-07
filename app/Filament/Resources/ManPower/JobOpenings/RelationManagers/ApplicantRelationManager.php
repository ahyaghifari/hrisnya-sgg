<?php

namespace App\Filament\Resources\ManPower\JobOpenings\RelationManagers;

use App\Enum\Recruitment\ProcessStatus;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicantRelationManager extends RelationManager
{
    protected static string $relationship = 'applicants';
    protected static ?string $title = 'Daftar Kandidat';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('applicants')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('applicants')
            ->columns([
                TextColumn::make('candidate.name')->label('Nama Kandidat')
                    ->description(fn ($record) => $record->candidate->email),
                TextColumn::make('current_stage')->label('Tahap Saat Ini')
                    ->formatStateUsing(fn ($state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color('primary')
                    ->badge(),
                TextColumn::make('status')->label('Status')
                    ->formatStateUsing(fn ($state) => ProcessStatus::from($state)->getLabel())
                    ->badge()
                    ->color(fn ($state) => ProcessStatus::from($state)->getColor())
                    ->icon(fn ($state) => ProcessStatus::from($state)->getIcon()),

                TextColumn::make('applied_at')->label('Tanggal Melamar')
                    ->dateTime('j F Y H:i'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-s-eye')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.man-power.job-openings.view-candidate-applicant', ['record' => $record->id]))
                    ->hiddenLabel(),
            ])
            ->toolbarActions([
            ]);
    }
}
