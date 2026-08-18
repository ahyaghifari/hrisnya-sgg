<div>
    <button type="button" wire:click="openModal" class="btn-primary jadwal-shift-tukar-btn" style="width:100%;justify-content:center;margin-top:10px;font-size:12px;padding:9px 12px;background:linear-gradient(130deg,#0891b2 0%,#0e7490 100%);border:none;">
        <i class="fa-solid fa-user-plus"></i> Extra Shift
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Tambah Extra Shift</h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;max-height:60vh;overflow-y:auto;padding-right:2px;">
                    <div>
                        <label class="form-label">1. Karyawan yang Dapat Extra Shift</label>
                        <select wire:model.live="user_id" class="form-input">
                            <option value="">Pilih karyawan...</option>
                            @foreach ($this->eligibleEmployees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->nip }})</option>
                            @endforeach
                        </select>
                        @error('user_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($user_id)
                        <div>
                            <label class="form-label">2. Shift Siapa yang Diambil</label>
                            @if ($this->coveringOptions->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);">Tidak ada rekan lain di tim ini yang berjadwal shift.</p>
                            @else
                                <select wire:model.live="covering_user_id" class="form-input">
                                    <option value="">Pilih karyawan...</option>
                                    @foreach ($this->coveringOptions as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->nip }})</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('covering_user_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($covering_user_id)
                        <div>
                            <label class="form-label">3. Shift yang Diambil</label>
                            @if ($this->rosterOptions->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);">Karyawan ini tidak punya jadwal shift ke depan.</p>
                            @else
                                <select wire:model.live="roster_id" class="form-input">
                                    <option value="">Pilih shift...</option>
                                    @foreach ($this->rosterOptions as $roster)
                                        <option value="{{ $roster->id }}">
                                            {{ $roster->date->translatedFormat('d M Y (D)') }} — {{ $roster->shiftType?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            @error('roster_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea wire:model="reason" class="form-input" rows="2" placeholder="Alasan extra shift..."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" wire:click="submit" class="btn-primary" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-check"></i> Simpan
                    </button>
                    <button type="button" wire:click="closeModal" class="btn-ghost" style="flex:1;justify-content:center;">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
