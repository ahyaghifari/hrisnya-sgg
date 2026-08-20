<div>
    <button type="button" wire:click="openModal" class="btn-ghost jadwal-shift-swap-history-btn" style="width:100%;justify-content:center;font-size:12px;padding:9px 12px;">
        <i class="fa-solid fa-clock-rotate-left"></i> Riwayat &amp; Status
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box modal-box-lg" style="max-height:none;overflow-y:visible;" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Tukar Shift</h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;max-height:65vh;overflow-y:auto;padding-right:2px;">
                    @if ($this->requests->isEmpty())
                        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:24px 0;">Belum ada pengajuan tukar shift.</p>
                    @else
                        <div class="pwa-list">
                            @foreach ($this->requests as $record)
                                <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                                    <div style="flex:1;min-width:0;">
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $this->keterangan($record) }}</p>
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
                                        <span class="badge {{ $this->statusBadgeClass($record->status) }}" style="margin-top:4px;">{{ $this->statusLabel($record->status) }}</span>
                                    </div>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="{{ route('tukar-shift') }}" wire:navigate class="btn-ghost" style="display:block;text-align:center;margin-top:16px;">Lihat Semua Riwayat</a>
            </div>
        </div>
    @endif
</div>
