<div>
    <x-page-header title="Orientasi Karyawan" subtitle="Periode & masa berlaku orientasi karyawan" />

    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:12px;margin-bottom:14px;">
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;">
            @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'berakhir' => 'Berakhir'] as $val => $label)
                <button type="button" wire:click="setStatus('{{ $val }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $val ? 'var(--surface)' : 'transparent' }};color:{{ $status === $val ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $val ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        @if ($canBrowseAllCompanies)
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    <option value="">Semua perusahaan</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div style="min-width:220px;flex:1;">
            <label class="form-label">Cari</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama karyawan atau NIP..." style="padding-left:38px;">
            </div>
        </div>
    </div>

    @php
        $rows = $this->orientations;
        $sourceLabels = ['recruitment' => 'Rekrutmen', 'extension' => 'Perpanjangan', 'manual' => 'Manual'];
    @endphp

    <!-- MOBILE: card manual -->
    <div class="md:hidden">
        @if ($rows->isEmpty())
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada data orientasi.</p>
            </div>
        @else
            <div class="pwa-list">
                @foreach ($rows as $o)
                    @php $isActive = $o->end_date && $o->end_date >= now()->startOfDay(); @endphp
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-user-graduate"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $o->employee?->nama_lengkap ?? '-' }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                {{ $o->start_date?->format('d M Y') }} - {{ $o->end_date?->format('d M Y') }} &middot; {{ $o->duration_months }} bln
                            </p>
                            <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                                {{ $sourceLabels[$o->source] ?? ($o->source ?? '-') }}
                                @if ($canBrowseAllCompanies && $o->employee?->company?->name) &middot; {{ $o->employee->company->name }} @endif
                            </p>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $isActive ? 'rgba(4,124,84,0.1)' : 'rgba(220,38,38,0.1)' }};color:{{ $isActive ? '#047C54' : '#dc2626' }};">{{ $isActive ? 'Aktif' : 'Berakhir' }}</span>
                            @if ($o->file_url)
                                <a href="{{ $o->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:5px 9px;font-size:11px;" title="Lihat Surat"><i class="fa-solid fa-download"></i></a>
                            @endif
                            <button type="button" wire:click="openEditModal('{{ $o->id }}')" class="btn-ghost" style="padding:5px 9px;font-size:11px;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:16px;">{{ $rows->links('livewire.partials.pagination') }}</div>
        @endif
    </div>

    <!-- DESKTOP: tabel -->
    <div class="hidden md:block">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $rows->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Mulai</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Sampai</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Durasi</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Sumber</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        @if ($canBrowseAllCompanies)
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                        @endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Surat</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $o)
                        @php $isActive = $o->end_date && $o->end_date >= now()->startOfDay(); @endphp
                        <tr wire:key="orientation-{{ $o->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $o->employee?->nama_lengkap ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $o->start_date?->format('d M Y') }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $o->end_date?->format('d M Y') }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $o->duration_months }} bln</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $sourceLabels[$o->source] ?? ($o->source ?? '-') }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $isActive ? 'rgba(4,124,84,0.1)' : 'rgba(220,38,38,0.1)' }};color:{{ $isActive ? '#047C54' : '#dc2626' }};">{{ $isActive ? 'Aktif' : 'Berakhir' }}</span>
                            </td>
                            @if ($canBrowseAllCompanies)
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $o->employee?->company?->name ?? '-' }}</td>
                            @endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @if ($o->file_url)
                                    <a href="{{ $o->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat Surat"><i class="fa-solid fa-download"></i></a>
                                @else
                                    <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <button type="button" wire:click="openEditModal('{{ $o->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($rows->isEmpty())
            <div style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada data orientasi.</p>
            </div>
        @endif

        <div style="margin-top:16px;">{{ $rows->links('livewire.partials.pagination') }}</div>
    </div>

    {{-- Modal Edit — koreksi tanggal/durasi saja; karyawan & perusahaan terkunci. --}}
    @if ($showFormModal)
        <div class="modal-overlay" wire:click="closeFormModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Edit Periode Orientasi</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $editingUserName }}</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div style="display:flex;gap:12px;">
                        <div style="flex:1;">
                            <label class="form-label">Mulai</label>
                            <input type="date" class="form-input" wire:model="formStartDate">
                            @error('formStartDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="flex:1;">
                            <label class="form-label">Sampai</label>
                            <input type="date" class="form-input" wire:model="formEndDate">
                            @error('formEndDate') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div style="max-width:160px;">
                        <label class="form-label">Durasi (bulan)</label>
                        <input type="number" class="form-input" wire:model="formDurationMonths" min="1" max="24">
                        @error('formDurationMonths') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Surat Orientasi (PDF/Gambar, opsional)</label>
                        @if ($editingExistingFilePath)
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 6px;">
                                Surat saat ini: <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($editingExistingFilePath) }}" target="_blank" rel="noopener">lihat</a> - upload file baru untuk mengganti.
                            </p>
                        @endif
                        <input type="file" class="form-input" wire:model="formFile" accept=".pdf,.jpg,.jpeg,.png">
                        <div wire:loading wire:target="formFile" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah...</div>
                        @error('formFile') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeFormModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveForm" wire:loading.attr="disabled" wire:target="saveForm" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
