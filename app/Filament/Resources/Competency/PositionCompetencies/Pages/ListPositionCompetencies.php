<?php

namespace App\Filament\Resources\Competency\PositionCompetencies\Pages;

use App\Filament\Resources\Competency\PositionCompetencies\PositionCompetencyResource;
use App\Models\Master\JobPositionCompetencies;
use App\Models\Master\Competencies;
use App\Models\Master\JobPositions;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListPositionCompetencies extends ListRecords
{
    protected static string $resource = PositionCompetencyResource::class;
    protected static ?string $title = 'Daftar Jabatan & Kompetensi';

    protected function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('competencies');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $jobPositionId = $data['job_position_id'];
        $competencyIds = $data['competency_id'];

        $jobPosition = JobPositions::find($jobPositionId);
        
        if (!$jobPosition) {
            throw new \Exception('Jabatan tidak ditemukan.');
        }

        $existingCompetencyIds = $jobPosition->competencies->pluck('id')->toArray();
        $newCompetencyIds = array_diff($competencyIds, $existingCompetencyIds);
        $duplicateCompetencyIds = array_intersect($competencyIds, $existingCompetencyIds);

        $added = [];
        $duplicates = [];
        
        if (!empty($newCompetencyIds)) {
            foreach ($newCompetencyIds as $competencyId) {
                $competencyName = Competencies::find($competencyId)->name ?? 'Unknown';
                $added[] = $competencyName;
            }
            
            $jobPosition->competencies()->attach($newCompetencyIds);
        }
        
        if (!empty($duplicateCompetencyIds)) {
            foreach ($duplicateCompetencyIds as $competencyId) {
                $competencyName = Competencies::find($competencyId)->name ?? 'Unknown';
                $duplicates[] = $competencyName;
            }
        }

        if (!empty($added)) {
            Notification::make()
                ->title('Berhasil menambahkan kompetensi')
                ->body('Kompetensi yang ditambahkan: ' . implode(', ', $added))
                ->success()
                ->send();
        }
        
        if (!empty($duplicates)) {
            Notification::make()
                ->title('Beberapa kompetensi sudah ada')
                ->body('Kompetensi yang sudah ada: ' . implode(', ', $duplicates))
                ->warning()
                ->send();
        }

        if (empty($added) && !empty($duplicates)) {
            Notification::make()
                ->title('Tidak ada kompetensi baru')
                ->body('Semua kompetensi yang dipilih sudah terkait dengan jabatan ini.')
                ->warning()
                ->send();
        }

        return $jobPosition;
    }
}
