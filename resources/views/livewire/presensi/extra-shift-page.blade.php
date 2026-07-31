<div>
    <x-page-header title="Extra Shift" />

    <div style="margin-bottom:16px;">
        <x-company-filter wire:model.live="companyId" :companies="$this->companies" />
    </div>

    @if ($this->canManage)
        <div class="card-surface" style="margin-bottom:20px;">
            <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Tambah Extra Shift</h4>
            <p style="font-size:12px;color:var(--text-muted);margin:-6px 0 14px;">
                Tugaskan karyawan cover 1 shift tambahan di luar jadwal normalnya hari itu — langsung tersimpan, tidak perlu approval (kamu yang menugaskan).
            </p>
            <form wire:submit="assign" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:start;">
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
                    <label class="form-label">Shift yang Diambil</label>
                    <select class="form-input" wire:model="shift_type_id" @disabled(!$user_id)>
                        <option value="">{{ $user_id ? '-- Pilih --' : 'Pilih karyawan dulu' }}</option>
                        @foreach ($this->shiftTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} ({{ \Illuminate\Support\Str::substr($type->start_time, 0, 5) }}-{{ \Illuminate\Support\Str::substr($type->end_time, 0, 5) }})</option>
                        @endforeach
                    </select>
                    @error('shift_type_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal</label>
                    <input type="date" class="form-input" wire:model="date">
                    @error('date') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Menggantikan (opsional)</label>
                    <select class="form-input" wire:model="covering_user_id">
                        <option value="">-- Tidak ada --</option>
                        @foreach ($this->employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    @error('covering_user_id') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Alasan (opsional)</label>
                    <input type="text" class="form-input" wire:model="reason" placeholder="Mis. rekan shift siang berhalangan hadir">
                </div>
                <div>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                </div>
            </form>
        </div>
    @endif

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
                        @if ($this->canManage)
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
                            @if ($this->canManage)
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
</div>
