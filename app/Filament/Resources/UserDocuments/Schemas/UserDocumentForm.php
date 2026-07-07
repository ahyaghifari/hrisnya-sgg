<?php

namespace App\Filament\Resources\UserDocuments\Schemas;

use App\Enum\UserDocumentType;
use App\Models\Master\Companies;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dokumen Karyawan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('company_id')
                            ->label('Perusahaan')
                            ->columnSpanFull()
                            ->searchable()
                            ->preload()
                            ->options(fn () => Companies::orderBy('name')->pluck('name', 'id')->toArray())
                            ->placeholder('Pilih Perusahaan')
                            ->reactive()
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('user_id', null);
                                $set('user_name', null);
                            }),

                        Select::make('user_id')
                            ->label('Nama Karyawan')
                            ->searchable()
                            ->required()
                            ->placeholder('Pilih Karyawan')
                            ->reactive()
                            ->options(function (callable $get) {
                                $companyId = $get('company_id');
                                $query = \App\Models\User::query();
                                if ($companyId) {
                                    $query->where('company_id', $companyId);
                                }
                                return $query->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->afterStateHydrated(function ($state, callable $set) {
                                $user = \App\Models\User::find($state);
                                $set('user_name', $user?->name);
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $user = \App\Models\User::find($state);
                                $set('user_name', $user?->name);
                            }),

                        TextInput::make('type')
                            ->label('Tipe Dokumen')
                            ->default(fn ($record) => request()->query('type') ?? $record?->document_type)
                            ->formatStateUsing(fn ($state, $record) => UserDocumentType::tryFrom($state)?->value ?? UserDocumentType::tryFrom($record?->document_type)?->value ?? $state)
                            ->readOnly()
                            ->dehydrated(false),

                        TextInput::make('document_number')
                            ->label('Nomor Dokumen')
                            ->required(),

                        DatePicker::make('issued_date')
                            ->label('Tanggal Diterbitkan')
                            ->required(),

                        DatePicker::make('details.retirement_date')
                            ->label('Tanggal Pensiun')
                            ->visible(function (callable $get) {
                                $type = $get('document_type');

                                return $type === UserDocumentType::RETIREMENT->value;
                            }),

                        Select::make('details.retirement_type')
                            ->label('Tipe Pensiun')
                            ->options([
                                'normal' => 'Pensiun Normal',
                                'early' => 'Pensiun Dini',
                                'disability' => 'Pensiun Cacat',
                            ])
                            ->visible(function (callable $get) {
                                $type = $get('document_type');

                                return $type === UserDocumentType::RETIREMENT->value;
                            }),

                        Textarea::make('details.reason')
                            ->label('Alasan')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(function (callable $get) {
                                $type = $get('document_type');

                                return in_array($type, [
                                    UserDocumentType::RESIGNATION->value,
                                    UserDocumentType::RETIREMENT->value,
                                ]);
                            }),

                        TextInput::make('details.set_in')
                            ->label('Ditetapkan di.')
                            ->columnSpanFull()
                            ->visible(function (callable $get) {
                                $type = $get('document_type');

                                return $type === UserDocumentType::RETIREMENT->value;
                            }),

                        Hidden::make('user_name'),
                        Hidden::make('uploaded_by_user')
                            ->default(false),
                        Hidden::make('document_type')
                            ->default(request()->query('type')),

                        FileUpload::make('file_path')
                            ->label('Unggah Dokumen')
                            ->required()
                            ->columnSpanFull()
                            ->openable(true)
                            ->disk('public')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->directory(fn ($get) => 'user-documents/' . ($get('user_id') ?? 'unknown'))
                            ->visibility('public'),
                    ])
            ]);
    }
}
