<?php

namespace App\Filament\Resources\Master\Companies\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobEvaluationsRelationManager extends RelationManager
{
    protected static string $relationship = 'jobEvaluations';

    protected static ?string $title = 'Nilai Job Evaluation Perusahaan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->label('Tahun')
                    ->numeric()
                    ->default(date('Y'))
                    ->disabled()
                    ->dehydrated(),

                Select::make('month')
                    ->label('Bulan')
                    ->options(function ($get, $record, $livewire) {
                        $existingMonths = $this->getRelationship()->getResults()
                            ->when($record, fn($query) => $query->where('id', '!=', $record->id))
                            ->pluck('month')
                            ->toArray();

                        $allMonths = [
                            'January' => 'Januari',
                            'February' => 'Februari',
                            'March' => 'Maret',
                            'April' => 'April',
                            'May' => 'Mei',
                            'June' => 'Juni',
                            'July' => 'Juli',
                            'August' => 'Agustus',
                            'September' => 'September',
                            'October' => 'Oktober',
                            'November' => 'November',
                            'December' => 'Desember',
                        ];

                        // Filter bulan yang belum ada di data lain, atau tetap tampilkan bulan yang sedang diedit
                        return collect($allMonths)
                            ->reject(function ($label, $month) use ($existingMonths, $get) {
                                return in_array($month, $existingMonths) && $get('month') !== $month;
                            })
                            ->toArray();
                    })
                    ->required(),

                TextInput::make('score')
                    ->label('Nilai')
                    ->numeric()
                    ->required()
                    ->columnSpanFull()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nilai Job Evaluation Tahun ' . date('Y'))
            ->modifyQueryUsing(fn ($query) => $query->where('year', date('Y')))
            ->columns([
                TextColumn::make('month')
                    ->label('Bulan')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                    'January' => 'Januari',
                    'February' => 'Februari',
                    'March' => 'Maret',
                    'April' => 'April',
                    'May' => 'Mei',
                    'June' => 'Juni',
                    'July' => 'Juli',
                    'August' => 'Agustus',
                    'September' => 'September',
                    'October' => 'Oktober',
                    'November' => 'November',
                    'December' => 'Desember',
                    default => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('score')
                    ->label('Nilai')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Nilai Job Evaluation')
                    ->slideOver()
                    ->modalWidth('md')
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                
            ]);
    }
}
