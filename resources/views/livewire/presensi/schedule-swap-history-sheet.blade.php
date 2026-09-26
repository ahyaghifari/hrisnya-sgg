<div style="display:inline-flex;">
    {{-- Ikon "tukar" emas - warna senada tombol "Tukar Shift" (linear-gradient #f59e0b -> #b45309). --}}
    @if ($showTrigger)
        <button type="button" wire:click="openModal()" title="Riwayat Tukar Shift" aria-label="Riwayat Tukar Shift"
            style="background:none;border:none;cursor:pointer;padding:4px;color:#b45309;font-size:15px;line-height:1;">
            <i class="fa-solid fa-right-left"></i>
        </button>
    @endif

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box modal-box-lg" style="max-height:none;overflow-y:visible;" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                        <i class="fa-solid fa-right-left" style="color:#b45309;margin-right:6px;"></i> @if ($this->isFiltered) Tukar Shift {{ $this->filteredUserName }} · {{ \Carbon\Carbon::parse($filterDate)->translatedFormat('d M Y') }} @else Riwayat Tukar Shift Jadwal @endif
                    </h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;max-height:65vh;overflow-y:auto;padding-right:2px;">
                    @if ($this->requests->isEmpty())
                        <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:24px 0;">{{ $this->isFiltered ? 'Tidak ada pengajuan tukar shift aktif di tanggal ini.' : 'Belum ada pengajuan tukar shift di jadwal ini.' }}</p>
                    @else
                        <div class="pwa-list">
                            @foreach ($this->requests as $record)
                                <a href="{{ route('tukar-shift.show', ['record' => $record]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                                    <div style="flex:1;min-width:0;">
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $record->requester?->name ?? '-' }}</p>
                                        <p style="font-size:12px;color:var(--text-body);margin-top:1px;">{{ $this->keterangan($record) }}</p>
                                        @if ($this->isFiltered)
                                            <div style="font-size:12px;color:var(--text-body);margin-top:6px;display:flex;flex-direction:column;gap:2px;">
                                                <span><strong>Pemohon:</strong> {{ $record->requester?->name ?? '-' }} &mdash; {{ $record->reqSchedule?->shiftType?->name ?? '-' }}, {{ $record->req_date->translatedFormat('d M Y') }}</span>
                                                <span><strong>Rekan:</strong> {{ $record->target?->name ?? '-' }}@if ($record->targetSchedule) &mdash; {{ $record->targetSchedule->shiftType?->name ?? '-' }}, {{ $record->target_date->translatedFormat('d M Y') }}@endif</span>
                                                <span><strong>Tanggapan rekan:</strong> {{ match ($record->target_response) { 'accepted' => 'Setuju', 'declined' => 'Menolak', default => 'Belum menanggapi' } }}</span>
                                                @if ($record->notes)
                                                    <span><strong>Catatan:</strong> {{ $record->notes }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">Diajukan {{ $record->created_at?->translatedFormat('j F Y H:i') }}</p>
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
