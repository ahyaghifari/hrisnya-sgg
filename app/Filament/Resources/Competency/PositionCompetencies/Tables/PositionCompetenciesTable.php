<?php

namespace App\Filament\Resources\Competency\PositionCompetencies\Tables;

use App\Helpers\PermissionHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Master\JobPositions;
use App\Models\Master\Competencies;
use Illuminate\Support\Facades\Auth;

class PositionCompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jabatan')
                    ->formatStateUsing(function ($record) {
                        return $record->name;
                    })
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('competency_names')
                    ->label('Kompetensi')
                    ->formatStateUsing(function ($state) {
                        return empty($state) ? 'Belum ada kompetensi' : $state;
                    })
                    ->searchable(false)
                    ->sortable(false)
                    ->wrap(),

                TextColumn::make('competency_count')
                    ->label('Jumlah')
                    ->formatStateUsing(function ($state) {
                        return $state === 0 ? '0 kompetensi' : $state . ' kompetensi';
                    })
                    ->sortable(false)
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Perusahaan')
                    ->relationship('company', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn () => PermissionHelper::hasGlobalRole(Auth::user()) ? Auth::user()->company_id : null)
                    ->visible(fn () => PermissionHelper::hasGlobalRole(Auth::user())),

                SelectFilter::make('competencies')
                    ->label('Filter Kompetensi')
                    ->relationship('competencies', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                    
                SelectFilter::make('level')
                    ->label('Filter Level Jabatan')
                    ->options([
                        'junior' => 'Junior',
                        'senior' => 'Senior',
                        'manager' => 'Manager',
                        'director' => 'Director',
                    ])
                    ->searchable(),
            ])
            ->recordActions([
                \Filament\Actions\ActionGroup::make([
                    EditAction::make()
                        ->label('Edit Kompetensi')
                        ->slideOver()
                        ->modalWidth('md')
                        ->modalHeading('Edit Kompetensi Jabatan')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('name')
                                ->label('Nama Jabatan')
                                ->disabled()
                                ->dehydrated(false),
                                
                            \Filament\Forms\Components\Select::make('competency_ids')
                                ->label('Kompetensi')
                                ->options(function () {
                                    return Competencies::pluck('name', 'id')->toArray();
                                })
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kompetensi untuk jabatan ini')
                                ->default(function ($record) {
                                    try {
                                        return $record->competencies ? $record->competencies->pluck('id')->toArray() : [];
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                }),
                        ])
                        ->fillForm(function ($record) {
                            try {
                                return [
                                    'name' => $record->name,
                                    'competency_ids' => $record->competencies ? $record->competencies->pluck('id')->toArray() : [],
                                ];
                            } catch (\Exception $e) {
                                return [
                                    'name' => $record->name,
                                    'competency_ids' => [],
                                ];
                            }
                        })
                        ->action(function ($record, $data) {
                            try {
                                // Sync competencies dengan pivot table
                                // sync() akan otomatis handle unique constraint
                                $record->competencies()->sync($data['competency_ids']);
                                
                                \Filament\Notifications\Notification::make()
                                    ->title('Berhasil memperbarui kompetensi')
                                    ->body('Kompetensi untuk jabatan "' . $record->name . '" telah diperbarui')
                                    ->success()
                                    ->send();
                            } catch (\Illuminate\Database\QueryException $e) {
                                if ($e->getCode() === '23000') {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error Duplikasi')
                                        ->body('Terdapat duplikasi data kompetensi yang tidak diperbolehkan')
                                        ->danger()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error Database')
                                        ->body('Gagal memperbarui kompetensi: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error')
                                    ->body('Gagal memperbarui kompetensi: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                                
                                throw $e;
                            }
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_assign_competencies')
                        ->label('Assign Kompetensi ke Jabatan')
                        ->icon('heroicon-o-plus-circle')
                        ->schema([
                            \Filament\Forms\Components\Select::make('competency_ids')
                                ->label('Kompetensi')
                                ->options(function () {
                                    return Competencies::pluck('name', 'id')->toArray();
                                })
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kompetensi yang akan ditambahkan ke jabatan yang dipilih'),
                        ])
                        ->action(function ($records, $data) {
                            $competencyIds = $data['competency_ids'];
                            $updatedPositions = [];
                            
                            foreach ($records as $position) {
                                $existingIds = $position->competencies->pluck('id')->toArray();
                                $newIds = array_unique(array_merge($existingIds, $competencyIds));
                                $position->competencies()->sync($newIds);
                                $updatedPositions[] = $position->name;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Berhasil assign kompetensi')
                                ->body('Kompetensi telah ditambahkan ke: ' . implode(', ', $updatedPositions))
                                ->success()
                                ->send();
                        }),
                        
                    DeleteBulkAction::make()
                        ->label('Hapus Jabatan')
                        ->modalHeading('Hapus Jabatan Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus jabatan yang dipilih? Semua relasi kompetensi akan ikut terhapus.')
                        ->modalSubmitActionLabel('Ya, Hapus'),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->emptyStateHeading('Belum ada jabatan')
            ->emptyStateDescription('Belum ada data jabatan yang tersedia.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }
}
