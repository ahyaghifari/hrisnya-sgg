<div>
    <x-page-header title="Baseline Pelatihan" subtitle="Target jam & jumlah pelatihan minimum per perusahaan" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;justify-content:flex-end;">
            @if ($this->canCreateBaseline)
                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Baseline
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Periode</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Kursus Minimum</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah Jam Minimum</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->baselines as $baseline)
                        <tr wire:key="'baseline-'.{{ $baseline->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $baseline->company?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                {{ \Illuminate\Support\Carbon::parse($baseline->start_date)->translatedFormat('d M Y') }}
                                @if ($baseline->end_date)
                                    &ndash; {{ \Illuminate\Support\Carbon::parse($baseline->end_date)->translatedFormat('d M Y') }}
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $baseline->min_courses }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $baseline->min_hours }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @can('update', $baseline)
                                    <button type="button" wire:click="toggleActive('{{ $baseline->id }}')" class="badge" style="border:none;cursor:pointer;background:{{ $baseline->is_active ? 'rgba(4,124,84,0.12)' : 'var(--surface-muted)' }};color:{{ $baseline->is_active ? 'var(--brand-success)' : 'var(--text-muted)' }};">
                                        {{ $baseline->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </button>
                                @else
                                    <span class="badge" style="background:{{ $baseline->is_active ? 'rgba(4,124,84,0.12)' : 'var(--surface-muted)' }};color:{{ $baseline->is_active ? 'var(--brand-success)' : 'var(--text-muted)' }};">
                                        {{ $baseline->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                @endcan
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    @can('update', $baseline)
                                        <button type="button" wire:click="openEditModal('{{ $baseline->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    @endcan
                                    @can('delete', $baseline)
                                        <button type="button" wire:click="removeBaseline('{{ $baseline->id }}')" wire:confirm="Hapus baseline ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada baseline pelatihan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah/Ubah Baseline --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Baseline' : 'Tambah Baseline' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    @if ($this->isGlobalRole)
                        <div>
                            <label class="form-label">Perusahaan *</label>
                            <div wire:ignore>
                                <select wire:model="formCompanyId" data-tom-select data-placeholder="Pilih perusahaan" class="form-input" required>
                                    <option value="">Pilih perusahaan</option>
                                    @foreach ($this->companyOptions as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('formCompanyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="form-label">Tanggal Mulai *</label>
                            <input type="date" wire:model="formStartDate" class="form-input">
                            @error('formStartDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label">Tanggal Selesai *</label>
                            <input type="date" wire:model="formEndDate" class="form-input">
                            @error('formEndDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label class="form-label">Jumlah Kursus Minimum *</label>
                            <input type="number" wire:model="formMinCourses" class="form-input" min="0" placeholder="0">
                            @error('formMinCourses') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label">Jumlah Jam Minimum *</label>
                            <input type="number" wire:model="formMinHours" class="form-input" min="0" placeholder="0">
                            @error('formMinHours') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
