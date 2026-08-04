<?php

namespace App\Filament\Resources\Master\LeaveTypes;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Filament\Resources\BaseCompanyResource;
use App\Filament\Resources\Master\LeaveTypes\Pages\ManageLeaveTypes;
use App\Models\Master\Attendance\LeaveTypes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LeaveTypeResource extends BaseCompanyResource
{
    protected static ?string $model = LeaveTypes::class;

    protected static ?string $navigationLabel = 'Jenis Cuti';

    protected static string|UnitEnum|null $navigationGroup = 'Master Perusahaan';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Jenis Cuti';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::companyFormField(),

                TextInput::make('name')
                    ->label('Nama Tipe Cuti')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Select::make('category')
                    ->label('Kategori')
                    ->options(collect(LeaveTypeCategoryEnum::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->placeholder('Tidak ada kategori khusus')
                    ->helperText('Pilih "Cuti Tahunan" kalau jenis cuti ini mau kena aturan batas akumulasi per bulan (setting `cuti_tahunan_max_per_bulan`, belum ada UI-nya sendiri).')
                    ->columnSpanFull(),

                TextInput::make('quota_days')
                    ->label('Kuota (Hari)')
                    ->required()
                    ->numeric(),

                Select::make('reset_period')
                    ->label('Periode Reset')
                    ->options([
                        'yearly' => 'Tahunan',
                        'monthly' => 'Bulanan',
                        'none' => 'Tidak Ada',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::companyTableColumn(),
                TextColumn::make('name')->label('Nama Tipe Cuti')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => $state ? LeaveTypeCategoryEnum::tryFrom($state)?->label() ?? $state : null),
                TextColumn::make('quota_days')->label('Kuota (Hari)')->searchable()->sortable(),
                TextColumn::make('reset_period')->label('Periode Reset')->searchable()->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'yearly' => 'Tahunan',
                        'monthly' => 'Bulanan',
                        'none' => 'Tidak Ada',
                        default => $state,
                    }),
            ])
            ->filters([
                static::companyTableFilter(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (LeaveTypes $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLeaveTypes::route('/'),
        ];
    }
}
