<div>
    <x-page-header title="Pelatihan Eksternal" subtitle="Pengajuan pelatihan karyawan di luar yang diadakan perusahaan" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;width:fit-content;flex-wrap:wrap;">
            <button type="button" wire:click="setStatus('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === '' ? 'var(--surface)' : 'transparent' }};color:{{ $status === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === '' ? 'var(--shadow-card)' : 'none' }};">Semua</button>
            @foreach (['in_review' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)
                <button type="button" wire:click="setStatus('{{ $value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $value ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
            @endforeach
        </div>

        <div style="position:relative;max-width:280px;">
            <label class="form-label">Cari</label>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Judul pelatihan...">
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;">Judul Pelatihan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal Pengajuan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Ikatan Dinas</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->requests as $record)
                        <tr wire:key="'exttrain-'.{{ $record->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $record->user->name }}</td>
                            <td style="padding:9px 14px;">{{ $record->title }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $record->created_at?->translatedFormat('j F Y') }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span class="badge {{ $this->statusBadgeClass($record->status) }}">{{ $this->statusLabel($record->status) }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                {{ $record->ikatan_dinas_tahun ? rtrim(rtrim(number_format($record->ikatan_dinas_tahun, 2, ',', '.'), '0'), ',') . ' Tahun' : '-' }}
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <a href="{{ route('pelatihan.eksternal.show', $record) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                    <i class="fa-solid fa-file-contract"></i> Ikatan Dinas
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:32px 16px;text-align:center;color:var(--text-muted);">Belum ada pengajuan pelatihan eksternal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $this->requests->links('livewire.partials.pagination') }}</div>
    </div>
</div>
