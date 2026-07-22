<div>
    <x-page-header title="Riwayat Approval" subtitle="Riwayat pengajuan yang pernah Anda setujui/tolak sebagai approver" />

    @php
        $typeLabel = fn (string $type) => ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$type] ?? $type;
        $actionBadge = fn (string $action) => $action === 'approved' ? 'badge-approved' : 'badge-rejected';
        $actionLabel = fn (string $action) => $action === 'approved' ? 'Disetujui' : 'Ditolak';
    @endphp

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div style="min-width:220px;">
            <label class="form-label">Cari Nama Pemohon</label>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama...">
        </div>

        <div style="min-width:170px;">
            <label class="form-label">Jenis Pengajuan</label>
            <select wire:model.live="typeFilter" class="form-input">
                <option value="">Semua Jenis</option>
                <option value="leave">Cuti</option>
                <option value="permission">Izin</option>
                <option value="overtime">Lembur</option>
            </select>
        </div>
    </div>

    <div class="card-surface-flush">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal Keputusan</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Pemohon</th>
                        <th style="text-align:center;">Keputusan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        @php [$history, $request] = [$row['history'], $row['request']]; @endphp
                        <tr>
                            <td class="text-sm text-slate-500 whitespace-nowrap">{{ $history->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="text-sm font-semibold text-slate-800">{{ $typeLabel($request->type) }}</td>
                            <td class="text-sm text-slate-600">{{ $this->keterangan($request) }}</td>
                            <td class="text-sm text-slate-600">{{ $request->user->name }}</td>
                            <td style="text-align:center;">
                                <span class="badge {{ $actionBadge($history->action) }}">{{ $actionLabel($history->action) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate style="font-size:12.5px;font-weight:700;color:var(--brand);white-space:nowrap;">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-sm text-slate-500 py-6">
                                @if ($search !== '' || $typeFilter !== '')
                                    Tidak ada riwayat approval yang cocok dengan filter ini.
                                @else
                                    Anda belum pernah menyetujui/menolak pengajuan apapun.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->rows->hasPages())
            <div style="padding:14px 20px;border-top:1px solid var(--border-soft);">
                {{ $this->rows->links('livewire.partials.pagination') }}
            </div>
        @endif
    </div>
</div>
