<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enum\UserDocumentType;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'userCompaniesDocuments';

    protected static ?string $title = 'Dokumen Karyawan';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dokumen Karyawan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('user_name')
                            ->label('Nama Karyawan'),
                        
                        TextEntry::make('document_type')
                            ->label('Nama Dokumen')
                            ->formatStateUsing(
                                fn ($state) => UserDocumentType::tryFrom($state)?->label() ?? $state
                            ),

                        TextEntry::make('document_number')
                            ->label('Nomor Dokumen'),

                        TextEntry::make('issued_date')
                            ->label('Tanggal Dikeluarkan')
                            ->formatStateUsing(fn ($state) => date('j F Y', strtotime($state))),

                        TextEntry::make('details.retirement_date')
                            ->label('Tanggal Pensiun')
                            ->formatStateUsing(fn ($state) => date('j F Y', strtotime($state)))
                            ->visible(fn ($record) => $record->document_type === UserDocumentType::RETIREMENT->value),

                        TextEntry::make('details.retirement_type')
                            ->label('Tipe Pensiun')
                            ->visible(fn ($record) => $record->document_type === UserDocumentType::RETIREMENT->value),

                        TextEntry::make('details.reason')
                            ->label('Alasan Pengunduran Diri')
                            ->columnSpanFull()
                            ->visible(function (callable $get) {
                                $type = $get('document_type');

                                return in_array($type, [
                                    UserDocumentType::RESIGNATION->label(),
                                    UserDocumentType::RETIREMENT->label(),
                                ]);
                            }),

                        TextEntry::make('details.set_in')
                            ->label('Ditetapkan di.')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->document_type === UserDocumentType::RETIREMENT->value),

                        TextEntry::make('url_document')
                            ->label('File Dokumen')
                            ->url(fn ($record) => asset('storage/' . $record->file_path))
                            ->default('Buka Dokumen')
                            ->openUrlInNewTab(true),
                    ]),
            ]);
    }
    
    public function getTabs(): array
    {
        return [
            'resignation' => Tab::make('Pengunduran Diri')->query(fn ($query) => $query->where('document_type', UserDocumentType::RESIGNATION->value)),
            'retirement' => Tab::make('Surat Pensiun')->query(fn ($query) => $query->where('document_type', UserDocumentType::RETIREMENT->value)),
            'assignment' => Tab::make('Surat Tugas')->query(fn ($query) => $query->where('document_type', UserDocumentType::ASSIGNMENT->value)),
            'work_certificate' => Tab::make('Surat Keterangan Kerja')->query(fn ($query) => $query->where('document_type', UserDocumentType::WORK_CERTIFICATE->value)),
            'employment_contract' => Tab::make('Surat Kontrak Kerja')->query(fn ($query) => $query->where('document_type', UserDocumentType::EMPLOYMENT_CONTRACT->value))
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user_name')->label('Nama Karyawan')->searchable(),
                TextColumn::make('document_number')->label('Nomor Dokumen')->searchable(),
                TextColumn::make('issued_date')->label('Tanggal Dikeluarkan')->date('j F Y')->sortable(),
                
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel(),
            ]);
    }
}
