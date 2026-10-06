<?php

namespace App\Livewire\Sp\Concerns;

use App\Models\Sp\SpReason;

/**
 * Baris poin pelanggaran dari master (Dasar Klarifikasi BA / poin SP) untuk form Livewire:
 * tiap baris ['reason' => id | "repeat:<sp>:<id>", 'note' => teks]. Property array-nya (mis.
 * $dasar_items, $items) tetap dideklarasikan di komponen masing-masing.
 */
trait HasReasonItems
{
    abstract protected function reasonCompanyId(): ?string;

    /** @return \Illuminate\Support\Collection<string, string> id => nama */
    public function getReasonOptionsProperty()
    {
        return SpReason::availableFor($this->reasonCompanyId())->orderBy('name')->pluck('name', 'id');
    }

    /** @return array{reason: string, note: string} */
    protected function blankReasonRow(): array
    {
        return ['reason' => '', 'note' => ''];
    }

    /** Baris form dari poin tersimpan; poin lama tanpa master dimuat sebagai catatan saja. */
    protected function reasonRowsFrom($items): array
    {
        $rows = collect($items)->map(fn ($item) => [
            'reason' => $item->reason_id ? ($item->repeat_sp_id ? "repeat:{$item->repeat_sp_id}:{$item->reason_id}" : (string) $item->reason_id) : '',
            'note' => $item->reason_id ? (string) $item->note : trim(strip_tags((string) $item->text)),
        ])->values()->all();

        return $rows ?: [$this->blankReasonRow()];
    }

    /** Baris form -> input service (SpReason::composeItem). */
    protected function reasonPayload(array $rows): array
    {
        return array_map(fn ($row) => SpReason::parseChoice($row['reason'] ?? null) + ['note' => $row['note'] ?? null], $rows);
    }

    protected function addReasonRow(array $rows): array
    {
        return [...$rows, $this->blankReasonRow()];
    }

    protected function removeReasonRow(array $rows, int $index): array
    {
        unset($rows[$index]);

        return array_values($rows) ?: [$this->blankReasonRow()];
    }
}
