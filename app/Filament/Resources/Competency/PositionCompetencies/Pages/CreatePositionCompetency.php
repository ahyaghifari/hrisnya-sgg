<?php

namespace App\Filament\Resources\Competency\PositionCompetencies\Pages;

use App\Filament\Resources\Competency\PositionCompetencies\PositionCompetencyResource;
use App\Models\Master\JobPositionCompetencies;
use App\Models\Master\Competencies;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePositionCompetency extends CreateRecord
{
    protected static string $resource = PositionCompetencyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $jobPositionId = $data['job_position_id'];
        $competencyIds = $data['competency_id'];

        $records = [];
        $duplicates = [];
        $created = [];
        
        foreach ($competencyIds as $competencyId) {
            $exists = JobPositionCompetencies::where('job_position_id', $jobPositionId)
                ->where('competency_id', $competencyId)
                ->exists();
                
            if ($exists) {
                $competencyName = Competencies::find($competencyId)->name ?? 'Unknown';
                $duplicates[] = $competencyName;
            } else {
                $record = JobPositionCompetencies::create([
                    'job_position_id' => $jobPositionId,
                    'competency_id' => $competencyId,
                ]);
                $records[] = $record;
                
                $competencyName = Competencies::find($competencyId)->name ?? 'Unknown';
                $created[] = $competencyName;
            }
        }

        if (!empty($created)) {
            Notification::make()
                ->title('Berhasil menambahkan kompetensi')
                ->body('Kompetensi yang ditambahkan: ' . implode(', ', $created))
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

        if (empty($records)) {
            throw new \Exception('Semua kompetensi yang dipilih sudah terkait dengan jabatan ini.');
        }

        return $records[0];
    }
}
