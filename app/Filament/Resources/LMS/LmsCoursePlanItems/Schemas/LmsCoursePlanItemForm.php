<?php

namespace App\Filament\Resources\LMS\LmsCoursePlanItems\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class LmsCoursePlanItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof CreateRecord
                    ? self::createSchema()
                    : (
                        ($schema->getRecord()?->request_status === 'draft')
                            ? self::createSchema()
                            : self::editSchema()
                    )
            )
            ->columns(2);
    }

    public static function createSchema(): array
    {
        return [
            Section::make('Pengajuan Pelatihan')
                ->icon(Heroicon::OutlinedBookOpen)
                ->iconColor('black')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('source_type')
                        ->default('employee'),

                    Hidden::make('requested_by')
                        ->default(fn () => Auth::id()),

                    Hidden::make('request_status'),

                    TextInput::make('proposed_title')
                        ->label('Judul Pelatihan')
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->required(),

                    RichEditor::make('proposed_description')
                        ->label('Deskripsi Pelatihan')
                        ->belowLabel('Bisa ditambahkannya informasi mengenai tujuan pelatihan, materi yang ingin dipelajari, link referensi, dsb.')
                        ->columnSpanFull(),

                    TextInput::make('estimated_participants')
                        ->label('Estimasi Peserta')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('estimated_cost')
                        ->label('Estimasi Biaya (IDR)')
                        ->numeric()
                        ->nullable()
                        ->prefix('IDR ')
                ]),
        ];
    }

    public static function editSchema(): array
    {
        return [
             Section::make('Informasi Pemohon Pelatihan')
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->iconColor('black')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('requester.name')
                        ->label('Nama Pemohon'),

                    TextEntry::make('requester.nip')
                        ->label('NIP'),

                    TextEntry::make('requester.profile.jobPosition.name')
                        ->label('Jabatan'),

                    TextEntry::make('requester.profile.department.name')
                        ->label('Departemen'),

                    TextEntry::make('requested_at')
                        ->label('Tanggal Pengajuan')
                        ->dateTime('j F Y'),

                    TextEntry::make('request_status')
                        ->label('Status Pengajuan')
                        ->formatStateUsing(fn ($state) => ucfirst($state))
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'submitted' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        })
                ]),

            Section::make('Informasi Pengajuan Pelatihan')
                ->icon(Heroicon::OutlinedClipboard)
                ->iconColor('black')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextEntry::make('proposed_title')
                        ->columnSpanFull()
                        ->label('Nama Pelatihan'),

                    TextEntry::make('proposed_description')
                        ->html()
                        ->columnSpanFull()
                        ->label('Deskripsi Pelatihan'),

                    TextEntry::make('estimated_cost')
                        ->label('Estimasi Biaya')
                        ->money('idr', true),

                    TextEntry::make('estimated_participants')
                        ->label('Estimasi Peserta'),
                ]),
        ];
    }
}
