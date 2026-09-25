<div class="card-surface accent accent-indigo">
    <x-my-staff.widget-head icon="fa-file-contract" title="Kontrak & Orientasi" sub="Bawahan yang masa kerjanya akan berakhir" />

    @if ($loaded)
        <div class="pwa-list">
            @foreach ($this->rows as $row)
                <button type="button" wire:click="openModal('{{ $row['key'] }}')" class="pwa-list-row" style="width:100%;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;">
                    <div class="pwa-tile-icon {{ $row['tile'] }}"><i class="fa-solid {{ $row['icon'] }}"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $row['count'] }} bawahan <span class="badge {{ $row['typeBadge'] }}" style="margin-left:4px;">{{ $row['type'] }}</span></p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['desc'] }}</p>
                    </div>
                    <span class="badge {{ $row['urgencyBadge'] }}">{{ $row['urgencyLabel'] }}</span>
                </button>
            @endforeach
        </div>

        @if (in_array($activeModal, ['kontrak-urgent', 'orientasi-urgent', 'kontrak-attention'], true))
            <div class="modal-overlay" wire:click="closeModal">
                <div class="modal-box" onclick="event.stopPropagation()">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">
                        {{ $activeModal === 'orientasi-urgent' ? 'Orientasi Akan Berakhir' : 'Kontrak Akan Berakhir' }}
                    </h3>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->detail->count() }} bawahan, diurutkan dari yang paling dekat jatuh tempo.</p>

                    @forelse ($this->detail->sortBy(fn ($item) => $activeModal === 'orientasi-urgent' ? $item->effective_end_date : $item->contract_end_date) as $item)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                            <div>
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $item->user?->name ?? '-' }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $item->user?->profile?->jobPosition?->name ?? '-' }}</p>
                            </div>
                            <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">
                                {{ \Illuminate\Support\Carbon::parse($activeModal === 'orientasi-urgent' ? $item->effective_end_date : $item->contract_end_date)->translatedFormat('d M Y') }}
                            </span>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada yang mendekati jatuh tempo.</p>
                    @endforelse

                    <div style="margin-top:16px;text-align:right;">
                        <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
