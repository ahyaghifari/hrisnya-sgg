<div>
    <button type="button" wire:click="openModal" class="btn-primary jadwal-shift-tukar-btn" style="width:100%;justify-content:center;margin-top:10px;font-size:12px;padding:9px 12px;background:linear-gradient(130deg,#0891b2 0%,#0e7490 100%);border:none;">
        <i class="fa-solid fa-user-plus"></i> Extra Shift
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Tambah Extra Shift</h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;max-height:60vh;overflow-y:auto;padding-right:2px;">
                    <div>
                        <label class="form-label">1. Karyawan yang Dapat Extra Shift</label>
                        <select wire:model.live="user_id" data-tom-select data-placeholder="Cari nama atau NIP karyawan..." class="form-input">
                            <option value="">Pilih karyawan...</option>
                            @foreach ($this->eligibleEmployees as $emp)
                                <option value="{{ $emp->id }}" @selected($user_id === $emp->id)>{{ $emp->name }} ({{ $emp->nip }})</option>
                            @endforeach
                        </select>
                        @error('user_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($user_id)
                        <div>
                            <label class="form-label">2. Tanggal</label>
                            <input type="date" wire:model.live="date" min="{{ now()->subDay()->toDateString() }}" class="form-input">
                            @error('date') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($user_id && $date)
                        <div>
                            <label class="form-label">Shift Normal di Tanggal Ini</label>
                            @if ($this->normalShift)
                                <p style="font-size:12.5px;margin:0;padding:8px 10px;background:var(--surface-alt);color:var(--text-body);border:1px solid var(--border-soft);border-radius:8px;">{{ $this->normalShift['label'] }}@if (! empty($this->normalShift['time'])) ({{ $this->normalShift['time'] }})@endif @if ($this->teamName) · Regu {{ $this->teamName }}@endif</p>
                            @else
                                <p style="font-size:12px;color:var(--danger-text);margin:0;">Karyawan ini tidak punya jadwal shift (atau libur) di tanggal ini — extra shift butuh basis shift normal, pilih tanggal lain.</p>
                            @endif
                        </div>
                    @endif

                    @if ($this->normalShift)
                        <div>
                            <label class="form-label">3. Shift Tambahan (Extra Shift)</label>
                            @if ($this->shiftTypeOptions->isEmpty() && $this->alreadyHasExtraShift)
                                <p style="font-size:12px;color:var(--text-muted);">Karyawan ini sudah punya extra shift lain di tanggal ini.</p>
                            @elseif ($this->shiftTypeOptions->isEmpty())
                                <p style="font-size:12px;color:var(--text-muted);">Belum ada aturan Extra Shift dari "{{ $this->normalShift['label'] }}" untuk company ini — atur dulu di halaman Extra Shift (Aturan).</p>
                            @else
                                <select wire:model.live="shift_type_id" class="form-input">
                                    <option value="">Pilih shift...</option>
                                    @foreach ($this->shiftTypeOptions as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }} ({{ substr($type->start_time, 0, 5) }}–{{ substr($type->end_time, 0, 5) }})</option>
                                    @endforeach
                                </select>
                            @endif
                            @if ($this->teamName)
                                <p style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Pilihan dibatasi shift jatah Regu {{ $this->teamName }} dan aturan Extra Shift.</p>
                            @endif
                            @error('shift_type_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
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
