<?php

namespace App\Filament\Resources\Coachings\Schemas;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CoachingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(
                $schema->getLivewire() instanceof CreateRecord
                    ? self::createSchema()
                    : self::editSchema()
            )
            ->columns(2);
    }

    protected static function createSchema(): array
    {
        $auth = Auth::user();
        $departmentId = $auth->profile?->department?->id;

        return [
            Section::make('Informasi Karyawan')
                ->columns(2)
                ->description('Pilih karyawan dari departemen yang sama untuk melakukan coaching.')
                ->schema([
                    Select::make('user_id')
                        ->label('Karyawan')
                        ->searchable()
                        ->options(
                            User::whereHas('profile', function ($q) use ($departmentId) {
                                $q->where('org_structure_id', $departmentId);
                            })->pluck('name', 'id')
                        )
                        ->required(),
                    Select::make('coach_by')
                        ->label('Coach')
                        ->searchable()
                        ->options(
                            User::whereHas('profile', function ($q) use ($departmentId) {
                                $q->where('org_structure_id', $departmentId);
                            })->pluck('name', 'id')
                        )
                        ->default($auth->id)
                        ->required()
                        ->hidden(false),
                    Textarea::make('title')
                        ->label('Judul')
                        ->required()
                        ->columnSpanFull(),
                    DatePicker::make('improvement_due_date')
                        ->label('Deadline Perbaikan')
                        ->required()
                        ->minDate(now())
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Tujuan & Kondisi')
                ->schema([
                    Textarea::make('goals')
                        ->label('Tujuan Coaching')
                        ->rows(3),

                    Textarea::make('employee_condition')
                        ->label('Kondisi Karyawan')
                        ->rows(3),
                ])
                ->columnSpanFull(),

            Section::make('Solusi & Tindakan Perbaikan')
                ->schema([
                    Textarea::make('solution')
                        ->label('Solusi')
                        ->rows(3),

                    Textarea::make('improvement_action')
                        ->label('Tindakan Perbaikan')
                        ->rows(3),
                ])
                ->columnSpanFull(),
        ];
    }

    protected static function editSchema(): array
    {
        return [
            Section::make('Informasi Coaching')
                ->icon('heroicon-o-clipboard-document-check')
                ->columns(2)
                ->schema([
                    TextEntry::make('title')
                        ->label('Judul'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->icon(fn ($state) => \App\Enum\CoachingStatus::from($state)->getIcon())
                        ->color(fn ($state) => \App\Enum\CoachingStatus::from($state)->getColor())
                        ->formatStateUsing(fn ($state) => \App\Enum\CoachingStatus::from($state)->getLabel()),

                    TextEntry::make('user.name')
                        ->label('Karyawan'),

                    TextEntry::make('coachBy.name')
                        ->label('Coach'),

                    TextEntry::make('improvement_due_date')
                        ->label('Deadline Perbaikan')
                        ->date(),
                ])->columnSpanFull(),

            Section::make('Tujuan & Kondisi')
                ->icon('heroicon-o-document-text')
                ->columns(1)
                ->schema([
                    TextEntry::make('goals')
                        ->label('Tujuan Coaching')
                        ->placeholder('-')
                        ->alignJustify(),

                    TextEntry::make('employee_condition')
                        ->label('Kondisi Karyawan')
                        ->placeholder('-')
                        ->alignJustify(),
                ])->columnSpanFull(),

            Section::make('Solusi & Tindakan Perbaikan')
                ->icon('heroicon-o-light-bulb')
                ->columns(1)
                ->schema([
                    TextEntry::make('solution')
                        ->label('Solusi')
                        ->placeholder('-')
                        ->alignJustify(),

                    TextEntry::make('improvement_action')
                        ->label('Tindakan Perbaikan')
                        ->placeholder('-')
                        ->alignJustify(),
                ])->columnSpanFull(),

            Section::make('Bukti Pelaksanaan')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    RepeatableEntry::make('grouped_proofs')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('date')
                                ->hiddenLabel()
                                ->formatStateUsing(fn ($state) =>
                                    Carbon::parse($state)->translatedFormat('d M Y')
                                )
                                ->size('lg')
                                ->weight('bold'),

                            RepeatableEntry::make('proofs')
                                ->hiddenLabel()
                                ->schema([
                                    ImageEntry::make('file_url')
                                        ->hiddenLabel()
                                        ->openUrlInNewTab()
                                        ->imageHeight(180)
                                        ->extraAttributes([
                                            'class' =>
                                                'w-full h-full object-cover rounded-lg border border-gray-200 shadow-sm overflow-hidden',
                                        ]),
                                    TextEntry::make('status')
                                        ->hiddenLabel()
                                        ->badge()
                                        ->formatStateUsing(fn ($state) => \App\Enum\CoachingStatus::tryFrom($state)?->getLabel() ?? $state)
                                        ->color(fn ($state) => \App\Enum\CoachingStatus::tryFrom($state)?->getColor() ?? 'gray')
                                        ->alignCenter(),
                                ])
                                ->grid(4)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                ])
                ->columnSpanFull(),
        ];
    }
}
