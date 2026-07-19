<div class="p-4 md:p-6">
    <x-page-header title="Hari Libur & Cuti Bersama" />

    <style>
        .holidays-layout { display:grid; grid-template-columns:1fr; gap:16px; align-items:start; }
        @media (min-width: 1024px) { .holidays-layout { grid-template-columns:1fr 340px; } }
    </style>

    <div class="mt-4 holidays-layout">
        {{-- Kolom kiri (lebih lebar): tabel + CRUD, cari/company lewat Livewire (server-side). --}}
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <div>
                        <label class="form-label">Perusahaan</label>
                        <select class="form-input" style="min-width:220px;" wire:model.live="companyId">
                            <option value="">Semua Perusahaan</option>
                            @foreach ($this->companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="position:relative;">
                        <label class="form-label">Cari</label>
                        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama hari libur..." style="min-width:200px;">
                    </div>
                </div>

                <button type="button" wire:click="openCreateModal" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-plus"></i> Tambah Hari Libur
                </button>
            </div>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tanggal</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Cuti Bersama</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->holidays as $holiday)
                            <tr wire:key="'holiday-'.{{ $holiday->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $holiday->date->translatedFormat('d M Y') }}</td>
                                <td style="padding:9px 14px;font-weight:600;white-space:nowrap;">{{ $holiday->name }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $holiday->company?->name ?? 'Semua Perusahaan' }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    @if ($holiday->is_collective_leave)
                                        <i class="fa-solid fa-circle-check" style="color:#047C54;"></i>
                                    @else
                                        <i class="fa-solid fa-circle-xmark" style="color:var(--text-faint);"></i>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <button type="button" wire:click="openEditModal('{{ $holiday->id }}')" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <button type="button" wire:click="removeHoliday('{{ $holiday->id }}')" wire:confirm="Hapus hari libur ini?" class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada hari libur untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Kolom kanan: kalender bulan berjalan, tanggal libur ditandai. --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:14px;position:sticky;top:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <button type="button" wire:click="prevMonth" class="btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fa-solid fa-chevron-left"></i></button>
                <span style="font-size:13px;font-weight:700;color:var(--text-heading);">{{ $this->calendarMonthLabel }}</span>
                <button type="button" wire:click="nextMonth" class="btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fa-solid fa-chevron-right"></i></button>
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;font-size:10px;font-weight:700;color:var(--text-faint);text-align:center;margin-bottom:4px;">
                <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;">
                @foreach ($this->calendarDays as $cell)
                    @if ($cell === null)
                        <div></div>
                    @else
                        @php $hasHoliday = $cell['holidays']->isNotEmpty(); @endphp
                        <div
                            @if ($hasHoliday) title="{{ $cell['holidays']->pluck('name')->implode(', ') }}" @endif
                            style="aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:8px;font-size:11px;
                                {{ $hasHoliday ? 'background:rgba(220,38,38,0.12);color:#dc2626;font-weight:700;' : 'color:var(--text-muted);' }}
                                {{ $cell['date']->isToday() ? 'box-shadow:inset 0 0 0 1.5px #042c6c;' : '' }}">
                            {{ $cell['date']->day }}
                            @if ($hasHoliday)
                                <span style="width:4px;height:4px;border-radius:50%;background:#dc2626;margin-top:1px;"></span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
                @forelse ($this->calendarDays as $cell)
                    @continue($cell === null || $cell['holidays']->isEmpty())
                    @foreach ($cell['holidays'] as $h)
                        <div style="font-size:11px;display:flex;gap:6px;align-items:baseline;">
                            <span style="font-weight:700;color:#dc2626;white-space:nowrap;">{{ $cell['date']->format('d') }}</span>
                            <span style="color:var(--text-muted);">{{ $h->name }}</span>
                        </div>
                    @endforeach
                @empty
                    <p style="font-size:11px;color:var(--text-faint);margin:0;">Tidak ada hari libur bulan ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal Tambah/Ubah Hari Libur --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                    {{ $editingId ? 'Ubah Hari Libur' : 'Tambah Hari Libur' }}
                </h3>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-input" wire:model="formName" placeholder="mis. Hari Kemerdekaan">
                        @error('formName') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-input" wire:model="formDate">
                        @error('formDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Perusahaan</label>
                        <select class="form-input" wire:model="formCompanyId">
                            <option value="">Semua Perusahaan</option>
                            @foreach ($this->companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('formCompanyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer;">
                        <input type="checkbox" wire:model="formIsCollectiveLeave">
                        Cuti Bersama (mengurangi jatah Cuti Tahunan karyawan)
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
