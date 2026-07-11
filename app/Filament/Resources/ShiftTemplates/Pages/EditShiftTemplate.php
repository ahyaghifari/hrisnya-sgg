<?php

namespace App\Filament\Resources\ShiftTemplates\Pages;

use App\Filament\Resources\ShiftTemplates\ShiftTemplateResource;
use App\Models\Shift\ShiftTemplateDays;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditShiftTemplate extends EditRecord
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => ShiftTemplateResource::canDelete($record))
        ];
    }

    /**
     * Populate the 'days' Repeater field from the stored relationship.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['days'] = $record->days()
            ->orderBy('day_number')
            ->get()
            ->map(fn ($day) => [
                'day_number' => $day->day_number,
                'shift_id'   => $day->shift_id,
                'is_off_day' => (bool) $day->is_off_day,
            ])
            ->toArray();

        return $data;
    }

    /**
     * Sync template days when the record is saved.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $days = $data['days'] ?? [];
        unset($data['days']);

        DB::transaction(function () use ($record, $data, $days): void {
            $record->update($data);

            // Delete all existing days and re-insert from form state
            $record->days()->delete();

            if (!empty($days)) {
                $dayRows = collect($days)->map(fn (array $day, int $index) => [
                    'id'                => (string) Str::ulid(),
                    'shift_template_id' => $record->id,
                    'day_number'        => $day['day_number'] ?? ($index + 1),
                    'shift_id'          => empty($day['is_off_day']) ? ($day['shift_id'] ?? null) : null,
                    'is_off_day'        => (bool) ($day['is_off_day'] ?? false),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])->values()->toArray();

                ShiftTemplateDays::insert($dayRows);
            }
        });

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
