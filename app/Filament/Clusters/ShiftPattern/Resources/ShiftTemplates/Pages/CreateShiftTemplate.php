<?php

namespace App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\Pages;

use App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\ShiftTemplateResource;
use App\Models\Shift\ShiftTemplateDays;
use App\Models\Shift\ShiftTemplates;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateShiftTemplate extends CreateRecord
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $days = $data['days'] ?? [];
        unset($data['days']);

        $template = DB::transaction(function () use ($data, $days): ShiftTemplates {
            /** @var ShiftTemplates $template */
            $template = ShiftTemplates::create($data);

            if (!empty($days)) {
                $dayRows = collect($days)->map(fn (array $day, int $index) => [
                    'id'                => (string) Str::ulid(),
                    'shift_template_id' => $template->id,
                    'day_number'        => $day['day_number'] ?? ($index + 1),
                    'shift_id'          => empty($day['is_off_day']) ? ($day['shift_id'] ?? null) : null,
                    'is_off_day'        => (bool) ($day['is_off_day'] ?? false),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ])->values()->toArray();

                ShiftTemplateDays::insert($dayRows);
            }

            return $template;
        });

        Notification::make()
            ->success()
            ->title('Template shift berhasil dibuat')
            ->body("Template \"{$template->name}\" dengan siklus {$template->cycle_length} hari telah disimpan.")
            ->send();

        return $template;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
