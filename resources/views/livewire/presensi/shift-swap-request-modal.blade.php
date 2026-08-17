<div>
    <button type="button" wire:click="openModal" class="btn-primary jadwal-shift-tukar-btn" style="width:100%;justify-content:center;margin-top:20px;font-size:12px;padding:9px 12px;background:linear-gradient(130deg,#f59e0b 0%,#b45309 100%);border:none;">
        <i class="fa-solid fa-right-left"></i> Tukar Shift
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Ajukan Tukar Shift</h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;max-height:60vh;overflow-y:auto;padding-right:2px;">
                    <div>
                        <label class="form-label">1. Cari Rekan Kerja</label>
                        @if ($targetUserId)
                            @php $selectedUser = \App\Models\User::find($targetUserId); @endphp
                            <div style="display:flex;align-items:center;justify-content:space-between;background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:10px;padding:8px 12px;">
                                <span style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $selectedUser?->name }} <span style="color:var(--text-muted);font-weight:400;">({{ $selectedUser?->nip }})</span></span>
                                <button type="button" wire:click="clearTarget" style="background:none;border:none;color:var(--text-faint);cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.400ms="targetSearch" class="form-input" placeholder="Ketik nama atau NIP rekan sejadwal...">
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma menampilkan rekan yang pakai jadwal shift yang sama dengan Anda.</p>
                            @if ($this->targetCandidates->isNotEmpty())
                                <div style="border:1px solid var(--border-soft);border-radius:10px;margin-top:6px;overflow:hidden;">
                                    @foreach ($this->targetCandidates as $candidate)
                                        <button type="button" wire:click="selectTarget('{{ $candidate->id }}')" style="display:block;width:100%;text-align:left;padding:8px 12px;border:none;background:var(--surface);cursor:pointer;font-size:13px;border-bottom:1px solid var(--border-soft);">
                                            {{ $candidate->name }} <span style="color:var(--text-muted);">({{ $candidate->nip }})</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        @error('targetUserId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($targetUserId)
                        <div>
                            <label class="form-label">2. Jadwal Rekan yang Mau Ditukar</label>
                            @if ($this->targetShifts->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);">Rekan ini tidak punya jadwal shift ke depan.</p>
                            @else
                                <select wire:model.live="targetScheduleId" class="form-input">
                                    <option value="">Pilih jadwal rekan...</option>
                                    @foreach ($this->targetShifts as $shift)
                                        <option value="{{ $shift->id }}">
                                            {{ $shift->date->translatedFormat('d M Y (D)') }} — {{ $shift->shiftType?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            @error('targetScheduleId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($targetScheduleId)
                        <div>
                            <label class="form-label">3. Ditukar dengan Jadwal Saya</label>
                            @if ($this->myShifts->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);">Anda tidak punya jadwal shift di bulan yang sama.</p>
                            @else
                                <select wire:model="reqScheduleId" class="form-input">
                                    <option value="">Pilih jadwal saya...</option>
                                    @foreach ($this->myShifts as $shift)
                                        <option value="{{ $shift->id }}">
                                            {{ $shift->date->translatedFormat('d M Y (D)') }} — {{ $shift->shiftType?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma jadwal bulan yang sama dengan jadwal rekan yang ditampilkan.</p>
                            @endif
                            @error('reqScheduleId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea wire:model="notes" class="form-input" rows="2" placeholder="Alasan tukar shift..."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" wire:click="submit" class="btn-primary" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-paper-plane"></i> Ajukan
                    </button>
                    <button type="button" wire:click="closeModal" class="btn-ghost" style="flex:1;justify-content:center;">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
