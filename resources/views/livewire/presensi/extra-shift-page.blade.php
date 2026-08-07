<div>
    <x-page-header title="Extra Shift" />

    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;margin-bottom:16px;">
        <x-company-filter wire:model.live="companyId" :companies="$this->companies" />

        @if ($this->canCreate)
            <button type="button" wire:click="openAddModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                <i class="fa-solid fa-plus"></i> Tambah Extra Shift
            </button>
        @endif
    </div>

    <div class="card-surface-flush">
        <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border-soft);">
            <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Extra Shift</h4>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Shift</th>
                        <th>Menggantikan</th>
                        <th>Alasan</th>
                        <th>Ditugaskan Oleh</th>
                        @if ($this->canDelete)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->extraShifts as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->date)->translatedFormat('d M Y') }}</td>
                            <td>{{ $row->user?->name }}</td>
                            <td>{{ $row->shiftType?->name }}</td>
                            <td>{{ $row->coveringUser?->name ?? '-' }}</td>
                            <td>{{ $row->reason ?: '-' }}</td>
                            <td>{{ $row->createdBy?->name }}</td>
                            @if ($this->canDelete)
                                <td>
                                    <button type="button" wire:click="delete('{{ $row->id }}')" wire:confirm="Hapus extra shift ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-sm text-slate-500 py-6">Belum ada extra shift tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah --}}
    @if ($showAddModal)
        <div class="modal-overlay" wire:click="closeAddModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">Tambah Extra Shift</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px;">
                    Tugaskan karyawan cover 1 shift tambahan di luar jadwal normalnya hari itu — langsung tersimpan, tidak perlu approval (kamu yang menugaskan).
                </p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Karyawan</label>
                        <select class="form-input" wire:model.live="user_id">
                            <option value="">-- Pilih --</option>
                            @foreach ($this->employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Karyawan yang Diambil Shiftnya</label>
                        <select class="form-input" wire:model.live="covering_user_id" @disabled(!$user_id)>
                            <option value="">{{ $user_id ? '-- Pilih --' : 'Pilih karyawan dulu' }}</option>
                            @foreach ($this->coveringOptions as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        @error('covering_user_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tanggal & Shift yang Diambil</label>
                        <select class="form-input" wire:model.live="roster_id" @disabled(!$covering_user_id)>
                            <option value="">{{ $covering_user_id ? '-- Pilih --' : 'Pilih karyawan yang diambil shiftnya dulu' }}</option>
                            @forelse ($this->rosterOptions as $roster)
                                <option value="{{ $roster->id }}">{{ \Carbon\Carbon::parse($roster->date)->translatedFormat('d M Y') }} - {{ $roster->shiftType?->name }} ({{ \Illuminate\Support\Str::substr($roster->shiftType?->start_time, 0, 5) }}-{{ \Illuminate\Support\Str::substr($roster->shiftType?->end_time, 0, 5) }})</option>
                            @empty
                                @if ($covering_user_id)
                                    <option value="" disabled>Belum ada roster shift ke depan untuk karyawan ini</option>
                                @endif
                            @endforelse
                        </select>
                        @error('roster_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Alasan (opsional)</label>
                        <input type="text" class="form-input" wire:model="reason" placeholder="Mis. rekan shift siang berhalangan hadir">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAddModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="assign" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
