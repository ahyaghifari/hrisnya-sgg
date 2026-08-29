<div style="display:inline-flex;">
    {{-- Ikon "tukar" emas - warna senada tombol "Tukar Shift" (linear-gradient #f59e0b -> #b45309). --}}
    <button type="button" wire:click="openModal" title="Riwayat Tukar Shift" aria-label="Riwayat Tukar Shift"
        style="background:none;border:none;cursor:pointer;padding:4px;color:#b45309;font-size:15px;line-height:1;">
        <i class="fa-solid fa-right-left"></i>
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box modal-box-lg" style="max-height:none;overflow-y:visible;" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                        <i class="fa-solid fa-right-left" style="color:#b45309;margin-right:6px;"></i> Riwayat Tukar Shift Jadwal
                    </h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;max-height:65vh;overflow-y:auto;padding-right:2px;">
                    @if ($this->requests->isEmpty())
                        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:24px 0;">Belum ada pengajuan tukar shift di jadwal ini.</p>
                    @else
                        <div class="pwa-list">
                            @foreach ($this->requests as $record)
                                <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                                    <div style="flex:1;min-width:0;">
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $record->requester?->name ?? '-' }}</p>
                                        <p style="font-size:12px;color:var(--text-body);margin-top:1px;">{{ $this->keterangan($record) }}</p>
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
                                        <span class="badge {{ $this->statusBadgeClass($record->status) }}" style="margin-top:4px;">{{ $this->statusLabel($record->status) }}</span>
                                    </div>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
