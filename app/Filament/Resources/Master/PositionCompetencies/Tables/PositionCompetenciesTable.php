<?php

namespace App\Filament\Resources\Master\PositionCompetencies\Tables;

use App\Helpers\PermissionHelper;
use App\Models\Master\Competencies;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class PositionCompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jabatan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('competency_names')
                    ->label('Kompetensi')
                    ->formatStateUsing(fn ($state) => empty($state) ? 'Belum ada kompetensi' : $state)
                    ->searchable(false)
                    ->sortable(false)
                    ->wrap(),

                TextColumn::make('competency_count')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state) => $state === 0 ? '0 kompetensi' : $state . ' kompetensi')
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
                ActionGroup::make([
                    EditAction::make()
                        ->label('Edit Kompetensi')
                        ->slideOver()
                        ->modalWidth('md')
                        ->modalHeading('Edit Kompetensi Jabatan')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Jabatan')
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('competency_ids')
                                ->label('Kompetensi')
                                ->options(fn () => Competencies::pluck('name', 'id')->toArray())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kompetensi untuk jabatan ini'),
                        ])
                        ->fillForm(fn ($record) => [
                            'name' => $record->name,
                            'competency_ids' => $record->competencies->pluck('id')->toArray(),
                        ])
                        ->action(function ($record, array $data): void {
                            try {
                                $record->competencies()->sync($data['competency_ids']);

                                Notification::make()
                                    ->title('Berhasil memperbarui kompetensi')
                                    ->body('Kompetensi untuk jabatan "' . $record->name . '" telah diperbarui')
                                    ->success()
                                    ->send();
                            } catch (QueryException $e) {
                                if ($e->getCode() === '23000') {
                                    Notification::make()
                                        ->title('Error Duplikasi')
                                        ->body('Terdapat duplikasi data kompetensi yang tidak diperbolehkan')
                                        ->danger()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('Error Database')
                                        ->body('Gagal memperbarui kompetensi: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_assign_competencies')
                        ->label('Assign Kompetensi ke Jabatan')
                        ->icon('heroicon-o-plus-circle')
                        ->schema([
                            Select::make('competency_ids')
                                ->label('Kompetensi')
                                ->options(fn () => Competencies::pluck('name', 'id')->toArray())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kompetensi yang akan ditambahkan ke jabatan yang dipilih'),
                        ])
                        ->action(function ($records, array $data): void {
                            $competencyIds = $data['competency_ids'];
                            $updatedPositions = [];

                            foreach ($records as $position) {
                                $existingIds = $position->competencies->pluck('id')->toArray();
                                $newIds = array_unique(array_merge($existingIds, $competencyIds));
                                $position->competencies()->sync($newIds);
                                $updatedPositions[] = $position->name;
                            }

                            Notification::make()
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
