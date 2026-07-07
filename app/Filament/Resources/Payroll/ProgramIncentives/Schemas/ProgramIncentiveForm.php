<?php

namespace App\Filament\Resources\Payroll\ProgramIncentives\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProgramIncentiveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('user_id', null)),

                Select::make('user_id')
                    ->label('Program Holder')
                    ->options(function (callable $get) {
                        $companyId = $get('company_id');

                        if (! $companyId) {
                            return [];
                        }

                        return User::query()
                            ->where('company_id', $companyId)
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->disabled(fn (callable $get) => ! $get('company_id')),

                TextInput::make('program_name')
                    ->label('Program Name')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('incentive_date')
                    ->label('Date')
                    ->required(),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->nullable(),
            ])->columns(1);
    }
}
