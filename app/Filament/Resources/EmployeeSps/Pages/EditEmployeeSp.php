<?php

namespace App\Filament\Resources\EmployeeSps\Pages;

use App\Filament\Resources\EmployeeSps\EmployeeSpResource;
use App\Models\Sp\EmployeeSp;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

/**
 * SP resmi pada dasarnya read-only (planning/2026-09-23-sp-dan-merit-point.md §2.1) - field lain
 * di EmployeeSpForm::editSchema() masih infolist. Perkecualian: uraian/kronologi (items) dan
 * Berita Acara (report) dibuka buat diedit (instruksi user 2026-09-24) - makanya tombol save
 * dikembalikan, tapi header actions (delete dll) tetap kosong.
 *
 * Berita Acara field-nya flat ('kejadian' dst, sama seperti CreateEmployeeSp) karena sp_reports
 * relasi 1:1 opsional, bukan HasMany yang Filament urus otomatis lewat Repeater->relationship() -
 * jadi dibaca manual saat fill, disimpan manual saat save.
 */
class EditEmployeeSp extends EditRecord
{
    protected static string $resource = EmployeeSpResource::class;

    protected array $reportData = [];

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var EmployeeSp $record */
        $record = $this->record;

        return [...$data, ...($record->report?->only([
            'kejadian', 'dasar_klarifikasi', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan',
        ]) ?? [])];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->reportData = Arr::only($data, ['kejadian', 'dasar_klarifikasi', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan']);

        return Arr::except($data, ['kejadian', 'dasar_klarifikasi', 'subject_clarification', 'analysis', 'kesimpulan', 'followup_plan']);
    }

    protected function afterSave(): void
    {
        /** @var EmployeeSp $record */
        $record = $this->record;

        $record->report()->updateOrCreate([], $this->reportData);
    }
}
