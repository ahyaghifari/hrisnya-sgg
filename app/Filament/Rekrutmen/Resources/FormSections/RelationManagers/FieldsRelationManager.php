<?php

namespace App\Filament\Rekrutmen\Resources\FormSections\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Field per bagian form. Port dari FieldsRelationManager project `rekrutmen` (FL3 -> FL4).
 * `options` disimpan sebagai JSON array (cast di model); di form diedit sebagai teks JSON.
 */
class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Field';

    protected static ?string $modelLabel = 'Field';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Key (nama variabel)')
                ->required()
                ->helperText('Huruf kecil + underscore. Contoh: nik, tinggi_badan')
                ->rules(['regex:/^[a-z_]+$/'])
                ->maxLength(100),

            TextInput::make('label')->label('Label Tampilan')->required()->maxLength(255),

            Select::make('type')->label('Tipe')->required()->default('text')
                ->options([
                    'text' => 'Text',
                    'textarea' => 'Textarea',
                    'number' => 'Number',
                    'date' => 'Date',
                    'select' => 'Select (dropdown)',
                    'radio' => 'Radio (pilihan)',
                    'file' => 'File Upload',
                ])
                ->live(),

            Toggle::make('is_required')->label('Wajib Diisi')->default(true),

            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),

            Textarea::make('options')->label('Opsi (JSON)')
                ->rows(3)
                ->columnSpanFull()
                ->helperText('select/radio: {"choices":["Opsi A","Opsi B"]} | file: {"max_kb":5120,"mimes":["pdf"]}')
                ->visible(fn (Get $get) => in_array($get('type'), ['select', 'radio', 'file']))
                ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)
                ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->label('Key')->fontFamily('mono')->badge()->color('gray'),
                TextColumn::make('label')->label('Label'),
                TextColumn::make('type')->label('Tipe')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'textarea' => 'warning',
                        'select', 'radio' => 'success',
                        'file' => 'danger',
                        'number', 'date' => 'gray',
                        default => 'primary',
                    }),
                IconColumn::make('is_required')->label('Wajib')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
