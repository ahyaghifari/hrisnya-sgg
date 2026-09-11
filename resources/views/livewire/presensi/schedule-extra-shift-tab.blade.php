<div>
    @if ($this->canCreate)
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <button type="button" wire:click="openAddModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                <i class="fa-solid fa-plus"></i> Tambah Extra Shift
            </button>
        </div>
    @endif

    <div class="card-surface-flush">
        <div style="overflow-x:auto;">
            <table class="data-table" style="font-size:12.5px;">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Shift Tambahan</th>
                        <th>Alasan</th>
                        <th>Ditugaskan Oleh</th>
                        @if ($this->canDelete)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->extraShifts as $row)
                        @php $isPast = \Carbon\Carbon::parse($row->date)->lt(now()->startOfDay()); @endphp
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
                            @if ($this->canDelete)
                                <td>
                                    @if ($row->source === 'shift_swap')
                                        {{-- tombol disembunyikan, lihat ikon kunci di kolom Alasan --}}
                                    @elseif ($isPast)
                                        <span title="Tanggal sudah lewat - hapus lewat halaman Presensi Absensi > Extra Shift" style="color:var(--text-faint);"><i class="fa-solid fa-lock" style="font-size:11px;"></i></span>
                                    @else
                                        <button type="button" wire:click="delete('{{ $row->id }}')" wire:confirm="Hapus extra shift ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-sm text-slate-500 py-6">Belum ada extra shift tercatat untuk jadwal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah - alur & validasi sama dengan ExtraShiftPage/ExtraShiftRequestModal
         (lihat ResolvesExtraShiftOptions), cuma daftar karyawannya dibatasi ke Schedule ini. --}}
    @if ($showAddModal)
        <div class="modal-overlay" wire:click="closeAddModal">
            <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 6px;">Tambah Extra Shift</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px;">
                    Tugaskan karyawan jadwal ini cover 1 shift tambahan di luar jadwal normalnya hari itu — langsung tersimpan, tidak perlu approval.
                </p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Karyawan</label>
                        <select class="form-input" wire:model.live="user_id" data-tom-select data-placeholder="Ketik nama karyawan...">
                            <option value="">-- Pilih --</option>
                            @foreach ($this->employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-input" wire:model.live="date" min="{{ now()->toDateString() }}" @disabled(!$user_id)>
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
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
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
</div>
