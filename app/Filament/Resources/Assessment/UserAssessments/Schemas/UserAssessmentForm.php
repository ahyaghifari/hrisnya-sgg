<?php

namespace App\Filament\Resources\Assessment\UserAssessments\Schemas;

use App\Enum\AssessmentTemplateItemType;
use App\Models\Assessment\Assessments;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class UserAssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Penilaian')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->iconColor('black')
                    ->schema([
                        TextEntry::make('assessments.name')
                            ->label('Nama Penilaian'),

                        TextEntry::make('periode')
                            ->label('Periode Penilaian')
                            ->default(function ($record) {
                                $assessment = Assessments::find($record->assessment_id);
                                return $assessment ? 
                                    Carbon::parse($assessment->start_date)->format('j F Y') . ' - ' .
                                    Carbon::parse($assessment->end_date)->format('j F Y')
                                    : '';
                            })
                    ]),
                    
                Section::make('Karyawan yang Dinilai')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->iconColor('black')
                    ->schema([
                        TextEntry::make('target.name')
                            ->label('Nama Karyawan'),

                        TextEntry::make('target.nip')
                            ->label('NIP Karyawan'),
                    ]),

                Section::make('Formulir Penilaian')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->iconColor('black')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('scores')
                            ->relationship('scores', modifyQueryUsing: function ($query, $record) {
                                if (!$record) return $query;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return $query;
                                
                                $templateItemIds = $assessment->template->templateItems()
                                    ->whereNull('type')
                                    ->pluck('id')
                                    ->toArray();
                                
                                return $query->whereIn('template_item_id', $templateItemIds);
                            })
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->addable(false)
                            ->deletable(false)
                            ->visible(function ($record) {
                                if (!$record) return false;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return false;
                                
                                return $assessment->template->templateItems()
                                    ->whereNull('type')
                                    ->exists();
                            })
                            ->defaultItems(function ($record) {
                                if (!$record) return 0;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return 0;
                                
                                return $assessment->template->templateItems()
                                    ->whereNull('type')
                                    ->count();
                            })
                            ->table([
                                TableColumn::make('Indikator Penilaian')
                                    ->alignStart(),

                                TableColumn::make('Bobot (%)')
                                    ->alignCenter(),

                                TableColumn::make('Nilai')
                                    ->alignCenter(),

                                TableColumn::make('Target')
                                    ->alignCenter(),
                            ])
                            ->schema([
                                Hidden::make('template_item_id')
                                    ->default(function ($record) {
                                        return $record?->template_item_id;
                                    }),

                                TextEntry::make('templateItem.name')
                                    ->hiddenLabel(),

                                TextEntry::make('templateItem.weight_percent')
                                    ->alignCenter()
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . '%')
                                    ->hiddenLabel(),

                                TextInput::make('score')
                                    ->type('number')
                                    ->minValue(0)
                                    ->maxValue(function ($record) {
                                        return $record?->templateItem?->max_score ?? 100;
                                    })
                                    ->step(0.01)
                                    ->disabled(function ($livewire) {
                                        return $livewire->record?->final_score !== null;
                                    })
                                    ->hiddenLabel(),

                                TextEntry::make('templateItem.max_score')
                                    ->alignCenter()
                                    ->hiddenLabel(),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $record, $livewire): array {
                                $assessmentUser = $livewire->record;
                                $assessment = $assessmentUser->assessments;
                                
                                if (!$assessment || !$assessment->template) {
                                    return $data;
                                }

                                $templateItems = $assessment->template->templateItems()
                                    ->whereNull('type')
                                    ->orderBy('sort_order')
                                    ->get();
                                
                                $index = $livewire->getFormStatePath();
                                preg_match('/scores\.(\d+)/', $index, $matches);
                                $itemIndex = $matches[1] ?? 0;
                                
                                if (isset($templateItems[$itemIndex])) {
                                    $data['template_item_id'] = $templateItems[$itemIndex]->id;
                                }
                                
                                return $data;
                            }),

                        // Repeater untuk Orientasi Pegawai Baru
                        Repeater::make('orientation_scores')
                            ->relationship('scores', modifyQueryUsing: function ($query, $record) {
                                if (!$record) return $query;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return $query;
                                
                                $templateItemIds = $assessment->template->templateItems()
                                    ->where('type', AssessmentTemplateItemType::ORIENTATION->value)
                                    ->pluck('id')
                                    ->toArray();
                                
                                return $query->whereIn('template_item_id', $templateItemIds);
                            })
                            ->label('Orientasi Pegawai Baru')
                            ->columnSpanFull()
                            ->addable(false)
                            ->deletable(false)
                            ->visible(function ($record) {
                                if (!$record) return false;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return false;
                                
                                return $assessment->template->templateItems()
                                    ->where('type', AssessmentTemplateItemType::ORIENTATION->value)
                                    ->exists();
                            })
                            ->defaultItems(function ($record) {
                                if (!$record) return 0;
                                
                                $assessment = $record->assessments;
                                if (!$assessment || !$assessment->template) return 0;
                                
                                return $assessment->template->templateItems()
                                    ->where('type', AssessmentTemplateItemType::ORIENTATION->value)
                                    ->count();
                            })
                            ->table([
                                TableColumn::make('Kompetensi')
                                    ->alignStart(),

                                TableColumn::make('Tanggal Pelaksana')
                                    ->alignCenter(),

                                TableColumn::make('Aktivitas')
                                    ->alignStart(),

                                TableColumn::make('Deskripsi')
                                    ->alignStart(),

                                TableColumn::make('PIC / Trainer')
                                    ->alignCenter(),

                                TableColumn::make('Catatan')
                                    ->alignCenter(),

                                TableColumn::make('Nilai')
                                    ->alignCenter(),
                            ])
                            ->schema([
                                Hidden::make('template_item_id')
                                    ->default(function ($record) {
                                        return $record?->template_item_id;
                                    }),

                                TextEntry::make('templateItem.name')
                                    ->label('Kompetensi')
                                    ->hiddenLabel(),

                                DatePicker::make('execution_date')
                                    ->label('Tanggal Pelaksana')
                                    ->displayFormat('d/m/Y')
                                    ->native(false)
                                    ->disabled(function ($livewire) {
                                        return $livewire->record?->final_score !== null;
                                    })
                                    ->hiddenLabel(),

                                TextEntry::make('templateItem.metadata.activity')
                                    ->label('Aktivitas')
                                    ->html()
                                    ->hiddenLabel()
                                    ->formatStateUsing(function ($record) {
                                        return $record?->templateItem?->metadata['activity'] ?? '-';
                                    }),

                                TextEntry::make('templateItem.metadata.description')
                                    ->label('Deskripsi')
                                    ->html()
                                    ->hiddenLabel()
                                    ->formatStateUsing(function ($record) {
                                        return $record?->templateItem?->metadata['description'] ?? '-';
                                    }),

                                TextEntry::make('templateItem.metadata.trainer_id')
                                    ->label('PIC / Trainer')
                                    ->hiddenLabel()
                                    ->formatStateUsing(function ($record) {
                                        $trainerId = $record?->templateItem?->metadata['trainer_id'] ?? null;
                                        if (!$trainerId) return '-';
                                        
                                        $trainer = \App\Models\User::find($trainerId);
                                        return $trainer?->name ?? '-';
                                    }),

                                TextInput::make('notes')
                                    ->label('Catatan')
                                    ->placeholder('Tambahkan catatan...')
                                    ->disabled(function ($livewire) {
                                        return $livewire->record?->final_score !== null;
                                    })
                                    ->hiddenLabel(),

                                TextInput::make('score')
                                    ->label('Nilai')
                                    ->type('number')
                                    ->minValue(0)
                                    ->maxValue(function ($record) {
                                        return $record?->templateItem?->max_score ?? 100;
                                    })
                                    ->step(0.01)
                                    ->disabled(function ($livewire) {
                                        return $livewire->record?->final_score !== null;
                                    })
                                    ->hiddenLabel(),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $record, $livewire): array {
                                $assessmentUser = $livewire->record;
                                $assessment = $assessmentUser->assessments;
                                
                                if (!$assessment || !$assessment->template) {
                                    return $data;
                                }

                                $templateItems = $assessment->template->templateItems()
                                    ->where('type', AssessmentTemplateItemType::ORIENTATION->value)
                                    ->orderBy('sort_order')
                                    ->get();
                                
                                $index = $livewire->getFormStatePath();
                                preg_match('/orientation_scores\.(\d+)/', $index, $matches);
                                $itemIndex = $matches[1] ?? 0;
                                
                                if (isset($templateItems[$itemIndex])) {
                                    $data['template_item_id'] = $templateItems[$itemIndex]->id;
                                }
                                
                                return $data;
                            }),

                        // Placeholder untuk schema dengan type tertentu (akan ditambahkan nanti)
                        // Contoh struktur:
                        // Section::make('Custom Assessment Type')
                        //     ->visible(fn ($record) => $record?->assessments?->template?->templateItems()->where('type', 'specific_type')->exists())
                        //     ->schema([
                        //         // Schema khusus untuk type tertentu
                        //     ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_weighted_score')
                                    ->label('Total Nilai')
                                    ->default(function ($record) {
                                        if (!$record || !$record->scores) return '0.00';
                                        
                                        $total = $record->scores->sum('weighted_score');
                                        return number_format($total, 2);
                                    })
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('final_score')
                                    ->label('Nilai Akhir')
                                    ->default(function ($record) {
                                        return $record?->final_score ? number_format($record->final_score, 2) : '-';
                                    })
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('final_category')
                                    ->label('Kategori')
                                    ->default(function ($record) {
                                        return $record?->final_category ?? '-';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if (!$record?->final_category) return 'gray';
                                        
                                        return match($record->final_category) {
                                            'Sangat Baik' => 'success',
                                            'Baik' => 'primary',
                                            'Cukup' => 'warning',
                                            'Kurang' => 'danger',
                                            'Sangat Kurang' => 'danger',
                                            default => 'gray',
                                        };
                                    }),
                            ]),
                    ]),
            ]);
    }
}
