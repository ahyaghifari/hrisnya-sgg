<?php

namespace App\Livewire\Sp\Concerns;

use App\Models\Sp\EmployeeSp;
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
        return SpReason::availableFor($this->reasonCompanyId(), SpReason::KIND_PELANGGARAN, $this->reasonLevel())->orderBy('name')->pluck('name', 'id');
    }

    /**
     * SP karyawan ini yang jadi sumber "pelanggaran berulang": hanya untuk SP2/SP3, yang masih dalam masa
     * berlaku, sudah terbit, dan levelnya lebih rendah dari level yang diisi.
     *
     * @return \Illuminate\Support\Collection<int, EmployeeSp>
     */
    protected function repeatableSps(string $userId, ?string $level, ?string $exceptSpId = null)
    {
        $rank = ['sp1' => 1, 'sp2' => 2, 'sp3' => 3];
        $target = $rank[$level] ?? 1;

        if ($target < 2) {
            return collect();
        }

        return EmployeeSp::active()->issued()->where('user_id', $userId)
            ->when($exceptSpId, fn ($q) => $q->whereKeyNot($exceptSpId))
            ->with('items.reason')->orderBy('start_date')->get()
            ->filter(fn ($sp) => $rank[$sp->sp_level->value] < $target)
            ->values();
    }

    /**
     * Pilihan "pelanggaran sama dengan SP sebelumnya" (hanya SP2/SP3, lihat repeatableSps).
     * value "repeat:<sp>:<reason>" -> label.
     *
     * @return array<string, string>
     */
    protected function repeatOptionsFor(string $userId, ?string $level, ?string $exceptSpId = null): array
    {
        $options = [];

        foreach ($this->repeatableSps($userId, $level, $exceptSpId) as $sp) {
            foreach ($sp->items->whereNotNull('reason_id') as $item) {
                $options["repeat:{$sp->id}:{$item->reason_id}"] = "{$item->reason?->name} - berulang dari {$sp->sp_level->getLabel()} ({$sp->nomor})";
            }
        }

        return $options;
    }

    /**
     * Pelanggaran master dari level SP yang lebih rendah (SP2 -> pelanggaran SP1; SP3 -> SP1 dan SP2) yang
     * BELUM pernah dilakukan karyawan ini pada SP sebelumnya (yang sudah pernah muncul di pilihan "berulang").
     * Dipakai kalau karyawan melanggar hal lain saat masih dalam masa SP: level naik walau pelanggarannya
     * tercatat di level di bawahnya.
     *
     * @return array<string, array<string, string>> label grup => [id => nama]
     */
    protected function lowerLevelReasonGroups(?string $userId, ?string $level, ?string $exceptSpId = null): array
    {
        $rank = ['sp1' => 1, 'sp2' => 2, 'sp3' => 3];
        $target = $rank[$level] ?? 1;

        if ($target < 2 || ! $userId) {
            return [];
        }

        $done = $this->repeatableSps($userId, $level, $exceptSpId)
            ->flatMap(fn ($sp) => $sp->items->pluck('reason_id'))->filter()->unique()->all();

        $groups = [];

        foreach (\App\Enum\Sp\SpLevel::cases() as $lower) {
            if ($rank[$lower->value] >= $target) {
                continue;
            }

            $names = SpReason::availableFor($this->reasonCompanyId())
                ->where('sp_level', $lower->value)->whereNotIn('id', $done)->orderBy('name')->pluck('name', 'id');

            if ($names->isNotEmpty()) {
                $groups["Pelanggaran {$lower->getLabel()}"] = $names->all();
            }
        }

        return $groups;
    }

    /** Karyawan yang sedang diisi SP-nya (untuk pilihan berulang & pelanggaran level bawah). */
    protected function reasonUserId(): ?string
    {
        return null;
    }

    protected function reasonExceptSpId(): ?string
    {
        return null;
    }

    public function getLowerReasonGroupsProperty(): array
    {
        return $this->lowerLevelReasonGroups($this->reasonUserId(), $this->reasonLevel(), $this->reasonExceptSpId());
    }

    /** Pelanggaran master yang boleh dipilih: yang berlaku untuk level ini, atau dari level lebih rendah yang belum pernah dilakukan. */
    protected function isReasonAllowed(?string $id): bool
    {
        return filled($id) && ($this->reasonOptions->has($id) || collect($this->lowerReasonGroups)->contains(fn ($group) => isset($group[$id])));
    }
    /** Level SP yang sedang diisi (menyaring pelanggaran); null = tidak disaring. */
    protected function reasonLevel(): ?string
    {
        return null;
    }

    /** Pilihan Dasar Klarifikasi BA (master terpisah dari pelanggaran SP). */
    public function getDasarReasonOptionsProperty()
    {
        return SpReason::availableFor($this->reasonCompanyId(), SpReason::KIND_DASAR)->orderBy('name')->pluck('name', 'id');
    }

    /** @return array{reason: string, note: string} */
    protected function blankReasonRow(): array
    {
        return ['reason' => '', 'note' => ''];
    }

    /** Baris form dari poin tersimpan; poin lama tanpa master dimuat sebagai catatan saja. */
    protected function reasonRowsFrom($items, bool $allowOther = false): array
    {
        // allowOther (poin SP): poin tanpa master = "Pelanggaran Lainnya" dengan teksnya; poin bermaster tanpa catatan.
        $rows = collect($items)->map(fn ($item) => [
            'reason' => $item->reason_id ? ($item->repeat_sp_id ? "repeat:{$item->repeat_sp_id}:{$item->reason_id}" : (string) $item->reason_id) : ($allowOther ? SpReason::CHOICE_OTHER : ''),
            'note' => $item->reason_id ? ($allowOther ? '' : (string) $item->note) : trim(strip_tags((string) $item->text)),
        ])->values()->all();

        return $rows ?: [$this->blankReasonRow()];
    }

    /** Baris form -> input service (SpReason::composeItem). */
    protected function reasonPayload(array $rows, bool $noteOnlyForOther = false): array
    {
        // noteOnlyForOther (poin SP): teks hanya dipakai untuk "Pelanggaran Lainnya"; selain itu tidak ada keterangan.
        return array_map(fn ($row) => SpReason::parseChoice($row['reason'] ?? null) + [
            'note' => $noteOnlyForOther && ($row['reason'] ?? null) !== SpReason::CHOICE_OTHER ? null : ($row['note'] ?? null),
        ], $rows);
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
