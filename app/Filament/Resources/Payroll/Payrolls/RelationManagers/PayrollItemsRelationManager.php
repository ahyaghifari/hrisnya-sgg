<?php

namespace App\Filament\Resources\Payroll\Payrolls\RelationManagers;

use App\Enum\Payroll\PayrollComponentEnum;
use App\Filament\Resources\Payroll\Payrolls\PayrollResource;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollItemsRelationManager extends RelationManager
{
    protected $listeners = ['refreshRelation' => '$refresh'];
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Component')
                    ->searchable(),

                TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => match (true) {
                        $state === null => '-',
                        $state === 'manual' => 'Manual',
                        default => PayrollComponentEnum::tryFrom($state)?->label() ?? $state,
                    }),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'earning',
                        'danger'  => 'deduction',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(0)
                    ->alignCenter()
                    ->placeholder('-'),

                TextColumn::make('unit')
                    ->label('Unit')
                    ->placeholder('-'),

                TextColumn::make('unit_amount')
                    ->label('Unit Amount')
                    ->money('IDR', true)
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR', true)
                    ->weight('bold')
                    ->color(fn ($record) =>
                    $record->type === 'earning'
                        ? 'success'
                        : 'danger'
                    ),
            ])
            ->headerActions(actions: [
                CreateAction::make()
                    ->label('Add Adjustment')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->modalHeading('Add Payroll Adjustment')
                    ->modalDescription('This item will be treated as a manual payroll adjustment.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Component Name')
                            ->required(),

                        Select::make('type')
                            ->options([
                                'earning' => 'Earning',
                                'deduction' => 'Deduction',
                            ])
                            ->required(),

                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('IDR'),

                        Textarea::make('note')
                            ->label('Note')
                            ->required()
                            ->rows(3),
                    ])
                    ->mutateFormDataUsing(function (array $data) {
                        return array_merge($data, [
                            'quantity' => 1,
                            'unit' => null,
                            'unit_amount' => null,
                            'source_type' => 'manual',
                        ]);
                    })
                    ->after(function ($record, array $data, $livewire) {
                        $payroll = $this->getOwnerRecord();

                        if ($data['type'] === 'earning') {
                            $payroll->increment('gross_salary', $data['amount']);
                            $payroll->increment('net_salary', $data['amount']);
                        } else {
                            $payroll->increment('total_deductions', $data['amount']);
                            $payroll->decrement('net_salary', $data['amount']);
                        }

                        $livewire->dispatch('refresh');
                    }),
            ])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->is_paid;
    }
}
