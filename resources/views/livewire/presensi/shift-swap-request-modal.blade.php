<div>
    <button type="button" wire:click="openModal" class="btn-primary jadwal-shift-tukar-btn" style="width:100%;justify-content:center;font-size:12px;padding:9px 12px;background:linear-gradient(130deg,#f59e0b 0%,#b45309 100%);border:none;">
        <i class="fa-solid fa-right-left"></i> Tukar Shift
    </button>

    @if ($showModal)
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box modal-box-lg" style="max-height:none;overflow-y:visible;" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Ajukan Tukar Shift</h3>
                    <button type="button" wire:click="closeModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;max-height:65vh;overflow-y:auto;padding-right:2px;">
                    @if ($this->swapQuota)
                        @php $q = $this->swapQuota; @endphp
                        <div style="font-size:11.5px;border-radius:8px;padding:8px 10px;
                            {{ $q['remaining'] <= 0
                                ? 'background:var(--danger-soft-bg);color:var(--danger-soft-text);'
                                : 'background:var(--info-soft-bg);color:var(--info-soft-text);' }}">
                            @if ($q['remaining'] <= 0)
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Saldo tukar shift Anda <strong>habis</strong> untuk periode
                            @else
                                <i class="fa-solid fa-circle-info"></i>
                                Sisa saldo tukar shift: <strong>{{ $q['remaining'] }}</strong> dari {{ $q['max'] }} untuk periode
                            @endif
                            <strong>{{ $q['start']->translatedFormat('d M') }} &ndash; {{ $q['end']->translatedFormat('d M Y') }}</strong>.
                        </div>
                    @endif

                    <div>
                        <label class="form-label">1. Tanggal Shift Saya yang Mau Ditukar</label>
                        <input type="date" wire:model.live="reqDate" min="{{ $this->minDate }}" class="form-input">
                        @if ($this->minLeadDays > 0)
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Minimal {{ $this->minLeadDays }} hari dari hari ini &mdash; paling cepat {{ \Illuminate\Support\Carbon::parse($this->minDate)->translatedFormat('d M Y') }}.</p>
                        @endif
                        @if ($this->reqShift)
                            <p style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Shift Anda: <strong>{{ $this->reqShift->shiftType?->name }}</strong></p>
                        @elseif ($reqDate)
                            <p style="font-size:11.5px;color:var(--danger-text);margin-top:4px;">Anda tidak punya jadwal shift (atau libur) di tanggal ini.</p>
                        @endif
                        @error('reqDate') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($this->reqShift)
                        <div wire:key="date-mode-field">
                            <label class="form-label">2. Cara Tukar</label>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px;">
                                <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--text-body);cursor:pointer;">
                                    <span class="custom-radio">
                                        <input type="radio" name="dateMode" wire:model.live="dateMode" value="same">
                                        <span class="dot"></span>
                                    </span>
                                    Tanggal sama
                                </label>
                                <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--text-body);cursor:pointer;">
                                    <span class="custom-radio">
                                        <input type="radio" name="dateMode" wire:model.live="dateMode" value="different">
                                        <span class="dot"></span>
                                    </span>
                                    Tanggal berbeda
                                </label>
                                <label style="display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--text-body);cursor:pointer;">
                                    <span class="custom-radio">
                                        <input type="radio" name="dateMode" wire:model.live="dateMode" value="reciprocal">
                                        <span class="dot"></span>
                                    </span>
                                    Rekan sedang libur
                                </label>
                            </div>

                            @if ($dateMode === 'different')
                                <input type="date" wire:model.live="targetDate" min="{{ $this->minDate }}" class="form-input" placeholder="Tanggal shift rekan...">
                                @error('targetDate') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                            @elseif ($dateMode === 'reciprocal')
                                <p style="font-size:11px;color:var(--text-muted);margin:0;">Rekan yang dipilih di bawah harus benar-benar Libur (bukan sekadar tidak punya jadwal) pada tanggal shift Anda di atas. Sebagai gantinya, Anda akan memberikan salah satu tanggal Libur Anda sendiri untuk mengambil alih shift kerja rekan itu.</p>
                            @endif
                        </div>

                        @if ($dateMode === 'reciprocal')
                            <div wire:key="target-user-field-reciprocal">
                                <label class="form-label">3. Rekan yang Sedang Libur</label>
                                <select wire:key="target-user-select-reciprocal" class="form-input" wire:model.live="targetUserId" data-tom-select data-placeholder="Ketik nama atau NIP rekan yang libur...">
                                    <option value="">-- Pilih rekan --</option>
                                    @foreach ($this->offDayCandidates as $candidate)
                                        <option value="{{ $candidate->user_id }}" @selected($targetUserId === $candidate->user_id)>{{ $candidate->user->name }} ({{ $candidate->user->nip }}) — Libur</option>
                                    @endforeach
                                </select>
                                @if ($this->offDayCandidates->isEmpty())
                                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Tidak ada rekan sejadwal yang Libur di tanggal ini.</p>
                                @else
                                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma menampilkan rekan sejadwal yang beneran Libur (bukan kosong/belum berjadwal) di tanggal yang Anda pilih.</p>
                                @endif
                                @error('targetUserId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                            </div>

                            @if ($targetUserId)
                                <div wire:key="target-date-field-reciprocal">
                                    <label class="form-label">4. Tanggal Kompensasi (Libur Anda yang bertepatan shift kerja rekan)</label>
                                    <select wire:key="target-date-select-reciprocal" class="form-input" wire:model.live="targetDate">
                                        <option value="">-- Pilih tanggal --</option>
                                        @foreach ($this->compensationCandidates as $candidate)
                                            <option value="{{ $candidate->date->toDateString() }}" @selected($targetDate === $candidate->date->toDateString())>{{ $candidate->date->translatedFormat('d M Y (D)') }} — {{ $candidate->shiftType?->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($this->compensationCandidates->isEmpty())
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Tidak ada tanggal Libur Anda yang bertepatan dengan shift kerja rekan ini.</p>
                                    @else
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma menampilkan tanggal Libur Anda sendiri yang bertepatan dengan shift kerja rekan.</p>
                                    @endif
                                    @error('targetDate') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        @else
                            @if ($this->effectiveTargetDate)
                                <div wire:key="target-user-field">
                                    <label class="form-label">3. Cari Rekan Kerja</label>
                                    <select wire:key="target-user-select" class="form-input" wire:model.live="targetUserId" data-tom-select data-placeholder="Ketik nama atau NIP rekan sejadwal...">
                                        <option value="">-- Pilih rekan --</option>
                                        @foreach ($this->targetCandidates as $candidate)
                                            <option value="{{ $candidate->user_id }}" @selected($targetUserId === $candidate->user_id)>{{ $candidate->user->name }} ({{ $candidate->user->nip }}) — {{ $candidate->shiftType?->name }}</option>
                                        @endforeach
                                    </select>
                                    @if ($this->targetCandidates->isEmpty())
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Tidak ada rekan sejadwal yang punya shift di tanggal ini.</p>
                                    @else
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma menampilkan rekan sejadwal yang punya shift di tanggal yang dipilih di atas.</p>
                                    @endif
                                    @error('targetUserId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        @endif

                        @if ($targetUserId && $this->effectiveTargetDate)
                            <div wire:key="target-shift-preview">
                                <p style="font-size:12px;font-weight:600;color:var(--text-heading);margin:0;">{{ $dateMode === 'reciprocal' ? 'Shift Rekan yang Anda Ambil Alih' : 'Jadwal Rekan yang Mau Ditukar' }}</p>
                                @if ($this->targetShift)
                                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:2px;"><strong style="color:var(--text-heading);">{{ $this->targetShift->user?->name }}</strong> — {{ $this->targetShift->date->translatedFormat('d M Y (D)') }} — <strong>{{ $this->targetShift->shiftType?->name }}</strong></p>
                                @elseif ($dateMode !== 'reciprocal')
                                    <p style="font-size:11.5px;color:var(--danger-text);margin-top:2px;">Rekan tidak punya jadwal shift (atau libur) di tanggal ini.</p>
                                @endif
                            </div>
                        @endif

                        @if ($this->conflictWarnings)
                            <div style="background:var(--warning-soft-bg,rgba(217,119,6,0.1));border:1px solid var(--warning-soft-border,rgba(217,119,6,0.3));border-radius:8px;padding:8px 10px;">
                                @foreach ($this->conflictWarnings as $warning)
                                    <p style="font-size:11.5px;color:var(--warning-soft-text,#b45309);margin:0;{{ !$loop->last ? 'margin-bottom:4px;' : '' }}">
                                        <i class="fa-solid fa-triangle-exclamation"></i> {{ $warning }}
                                    </p>
                                @endforeach
                            </div>
                        @endif

                        <div>
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea wire:model="notes" class="form-input" rows="2" placeholder="Alasan tukar shift..."></textarea>
                        </div>
                    @endif
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
