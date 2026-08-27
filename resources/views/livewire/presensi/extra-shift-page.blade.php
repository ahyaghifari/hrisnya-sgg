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
                        <th>Shift Tambahan</th>
                        <th>Alasan</th>
                        <th>Ditugaskan Oleh</th>
                        <th>Absensi</th>
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
                            <td>
                                {{ $row->reason ?: '-' }}
                                @if ($row->source === 'shift_swap')
                                    <span title="Otomatis dari Tukar Shift yang disetujui, tidak bisa diubah/dihapus" style="margin-left:4px;color:var(--text-faint);"><i class="fa-solid fa-lock" style="font-size:10px;"></i></span>
                                @endif
                            </td>
                            <td>{{ $row->createdBy?->name }}</td>
                            <td>
                                @if ($row->attendanceRecord)
                                    <span style="font-size:12px;">{{ \Illuminate\Support\Str::substr($row->attendanceRecord->check_in_time, 0, 5) }} - {{ $row->attendanceRecord->check_out_time ? \Illuminate\Support\Str::substr($row->attendanceRecord->check_out_time, 0, 5) : '..' }}</span>
                                @else
                                    <span style="font-size:12px;color:var(--text-faint);">Belum diisi</span>
                                @endif
                                @if ($this->canRecordAttendance)
                                    <button type="button" wire:click="openAttendanceModal('{{ $row->id }}')" class="btn-ghost" style="padding:2px 8px;font-size:11px;margin-left:6px;">
                                        <i class="fa-solid fa-clock"></i> Isi
                                    </button>
                                @endif
                            </td>
                            @if ($this->canDelete)
                                <td>
                                    @if ($row->source !== 'shift_swap')
                                    <button type="button" wire:click="delete('{{ $row->id }}')" wire:confirm="Hapus extra shift ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    @endif
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
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-input" wire:model.live="date" @disabled(!$user_id)>
                        @error('date') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($user_id && $date)
                        <div>
                            <label class="form-label">Shift Normal di Tanggal Ini</label>
                            @if ($this->normalShift)
                                <p style="font-size:12.5px;margin:0;padding:8px 10px;background:var(--surface-soft,#f8fafc);border-radius:8px;">{{ $this->normalShift['label'] }}</p>
                            @else
                                <p style="font-size:12px;color:var(--danger-text);margin:0;">Karyawan ini tidak punya jadwal shift (atau libur) di tanggal ini — extra shift butuh basis shift normal, pilih tanggal lain.</p>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label class="form-label">Shift Tambahan (Extra Shift)</label>
                        <select class="form-input" wire:model.live="shift_type_id" @disabled(!$this->normalShift)>
                            <option value="">{{ $this->normalShift ? '-- Pilih --' : 'Pilih karyawan & tanggal dulu' }}</option>
                            @forelse ($this->shiftTypeOptions as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ \Illuminate\Support\Str::substr($type->start_time, 0, 5) }}-{{ \Illuminate\Support\Str::substr($type->end_time, 0, 5) }})</option>
                            @empty
                                @if ($this->normalShift && $this->alreadyHasExtraShift)
                                    <option value="" disabled>Karyawan ini sudah punya extra shift lain di tanggal ini</option>
                                @elseif ($this->normalShift)
                                    <option value="" disabled>Belum ada aturan Extra Shift dari "{{ $this->normalShift['label'] }}" untuk company ini</option>
                                @endif
                            @endforelse
                        </select>
                        @error('shift_type_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
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

    {{-- Modal Isi Absensi --}}
    @if ($attendanceExtraShiftId)
        <div class="modal-overlay" wire:click="closeAttendanceModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">Isi Absensi Extra Shift</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px;">
                    Input manual jam masuk/keluar aktual extra shift ini (belum ada integrasi otomatis dari mesin absen untuk extra shift).
                </p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Jam Masuk</label>
                        <input type="time" class="form-input" wire:model="check_in_time">
                        @error('check_in_time') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Jam Keluar (opsional)</label>
                        <input type="time" class="form-input" wire:model="check_out_time">
                        @error('check_out_time') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeAttendanceModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="recordAttendance" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
