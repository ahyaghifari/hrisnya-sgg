<div>
    <x-page-header title="Pelatihan Terlaksana" subtitle="Pengajuan pelatihan yang sudah dilakukan karyawan. Pemeriksaan JP lewat alur persetujuan." />

    @php
        $badge = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'rejected' => 'badge-rejected'];
        $label = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    @endphp

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;width:fit-content;flex-wrap:wrap;">
            @foreach (['' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $text)
                <button type="button" wire:click="setStatus('{{ $value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $value ? 'var(--shadow-card)' : 'none' }};">{{ $text }}</button>
            @endforeach
        </div>

        <div class="md:hidden pwa-list">
            @forelse ($this->claims as $row)
                <a href="{{ route('pelatihan.klaim.show', $row) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-certificate"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $row->user->name }}</p>
                        <p style="font-size:12px;color:var(--text-heading);margin-top:2px;">{{ $row->title }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row->created_at?->translatedFormat('j F Y') }} &middot; JP {{ $row->jp ?? '-' }}</p>
                        <span class="badge {{ $badge[$row->status] ?? '' }}" style="margin-top:4px;">{{ $label[$row->status] ?? $row->status }}</span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @empty
                <div style="padding:32px 16px;text-align:center;font-size:13px;color:var(--text-muted);">Belum ada pengajuan.</div>
            @endforelse
        </div>

        <div class="hidden md:block" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;">Pelatihan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Diajukan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">JP</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->claims as $row)
                        <tr wire:key="claimrow-{{ $row->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $row->user->name }}</td>
                            <td style="padding:9px 14px;">{{ $row->title }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $row->created_at?->translatedFormat('j F Y') }}</td>
                            <td style="padding:9px 14px;text-align:center;">{{ $row->jp ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;"><span class="badge {{ $badge[$row->status] ?? '' }}">{{ $label[$row->status] ?? $row->status }}</span></td>
                            <td style="padding:9px 14px;text-align:center;">
                                <a href="{{ route('pelatihan.klaim.show', $row) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                    <i class="fa-solid fa-arrow-right"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:32px 16px;text-align:center;color:var(--text-muted);">Belum ada pengajuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $this->claims->links('livewire.partials.pagination') }}</div>
    </div>
</div>
