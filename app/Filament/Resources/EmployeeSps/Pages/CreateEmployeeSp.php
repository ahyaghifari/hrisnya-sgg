<?php

namespace App\Filament\Resources\EmployeeSps\Pages;

use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpSourceType;
use App\Filament\Resources\EmployeeSps\EmployeeSpResource;
use App\Models\Coaching\Coaching;
use App\Models\Sp\EmployeeSp;
use App\Services\Sp\SpService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

/**
 * SP dibuat lewat resource ini bisa MANUAL murni atau berdasarkan Coaching tertentu lewat field
 * "coaching_id" (opsional, planning/2026-09-23-sp-dan-merit-point.md §2.3, instruksi user
 * 2026-09-23: Coaching & SP dipisah total). Restrukturisasi 2026-09-25: `items` (poin
 * pelanggaran) otomatis disimpan Filament lewat Repeater->relationship() di EmployeeSpForm,
 * TIDAK perlu ditangani manual di sini. Berita Acara (sp_reports) field-nya flat (bukan
 * relationship form component), jadi ditampung dulu di $reportData lalu disimpan manual di
 * afterCreate(). end_date & refresh cache user_hr_balances didelegasikan ke SpService.
 */
class CreateEmployeeSp extends CreateRecord
{
    protected static string $resource = EmployeeSpResource::class;

    protected array $reportData = [];

    // Konfirmasi sebelum terbit (instruksi user 2026-09-25) - SP langsung diterima subjek begitu
    // disimpan, bukan draft yang bisa dibatalkan diam-diam.
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading('Terbitkan Surat Peringatan?')
            ->modalDescription('Surat akan langsung diterima oleh karyawan bersangkutan.')
            ->modalSubmitActionLabel('Ya, Terbitkan');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $auth = auth()->user();

        // Coaching cuma boleh dipilih kalau memang punya karyawan yang sama - cegah ditembus
        // lewat request yang di-craft manual.
        $coachingId = $data['coaching_id'] ?? null;
        if ($coachingId) {
            abort_unless(Coaching::where('id', $coachingId)->where('user_id', $data['user_id'])->exists(), 403);
        }
        unset($data['coaching_id']);

        $this->reportData = Arr::only($data, ['kejadian', 'dasar_items', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan']);
        $data = Arr::except($data, ['kejadian', 'dasar_items', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan']);

        $data['company_id'] = $auth->company_id;
        $data['source_type'] = $coachingId ? SpSourceType::COACHING->value : SpSourceType::MANUAL->value;
        $data['source_id'] = $coachingId;
        $data['created_by'] = $auth->id;
        $data['end_date'] = app(SpService::class)->resolveEndDate(
            SpLevel::from($data['sp_level']),
            $auth->company_id,
            $data['start_date']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var EmployeeSp $record */
        $record = $this->record;

        if (array_filter($this->reportData, fn ($v) => filled($v))) {
            $record->report()->create(Arr::except($this->reportData, 'dasar_items'))
                ->syncDasarItems($this->reportData['dasar_items'] ?? []);
        }

        app(SpService::class)->afterIssued($record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
