<div>
    <x-page-header title="Gaji Pemateri" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <x-company-filter :companies="$this->companies" wire:model.live="companyId" />
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pemateri</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pelatihan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pertemuan</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Honor</th>
                        @if ($this->canEdit)
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->results as $result)
                        <tr wire:key="'gaji-pemateri-'.{{ $result->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $result->trainer?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $result->course?->title ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $result->meeting?->title ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;">Rp {{ number_format($result->honor_amount, 0, ',', '.') }}</td>
                            @if ($this->canEdit)
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <button type="button" wire:click="removeResult('{{ $result->id }}')" wire:confirm="Hapus data gaji pemateri ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                        <i class="fa-solid fa-trash"></i> Hapus
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->canEdit ? 5 : 4 }}" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada data gaji pemateri untuk perusahaan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
