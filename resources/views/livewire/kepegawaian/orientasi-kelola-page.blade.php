<div>
    <x-page-header title="Orientasi" subtitle="Periode orientasi, pengajuan penilaian, keputusan & indikator" />

    @php
        $tabs = ['pengajuan' => 'Pengajuan & Penilaian', 'periode' => 'Periode Orientasi'];
        if ($this->canViewCriteria) {
            $tabs['indikator'] = 'Indikator Penilaian';
        }
    @endphp

    {{-- Tab --}}
    <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:14px;">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" style="padding:5px 14px;font-size:12px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $tab === $key ? 'var(--surface)' : 'transparent' }};color:{{ $tab === $key ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $tab === $key ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        @if ($this->canBrowseAllCompanies)
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    @if ($tab !== 'indikator')<option value="">Semua perusahaan</option>@endif
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($tab !== 'indikator')
            <div style="min-width:220px;flex:1;">
                <label class="form-label">Cari</label>
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama karyawan atau NIP..." style="padding-left:38px;">
                </div>
            </div>
        @endif
        @if ($tab === 'pengajuan')
            <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;">
                @foreach (['semua' => 'Semua', 'menunggu' => 'Menunggu', 'dinilai' => 'Dinilai', 'selesai' => 'Selesai'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $key ? 'var(--surface)' : 'transparent' }};color:{{ $status === $key ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $key ? 'var(--shadow-card)' : 'none' }};">{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @php
        $statusLabels = [
            'menunggu_persetujuan' => ['Menunggu', '#b45309', 'rgba(245,158,11,0.12)'],
            'dinilai' => ['Sedang Dinilai', '#1d4ed8', 'rgba(37,99,235,0.12)'],
            'selesai' => ['Selesai', '#047C54', 'rgba(4,124,84,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = ['lulus' => 'Lulus', 'perpanjang' => 'Perpanjang', 'tidak_lulus' => 'Tidak Lulus'];
    @endphp

    @if ($tab === 'indikator')
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Indikator yang dipakai atasan saat menilai orientasi karyawan{{ $this->canBrowseAllCompanies ? ' (per perusahaan yang dipilih di atas)' : '' }}.</p>
            @forelse ($criteriaRows as $i => $row)
                <div wire:key="crit-{{ $i }}" style="display:flex;gap:8px;align-items:flex-start;border-bottom:1px solid var(--border-soft);padding:10px 0;">
                    <div style="width:150px;">
                        <input type="text" wire:model="criteriaRows.{{ $i }}.group_label" class="form-input" placeholder="Aspek (opsional)" @disabled(! $this->canEditCriteria)>
                    </div>
                    <div style="flex:1;">
                        <input type="text" wire:model="criteriaRows.{{ $i }}.name" class="form-input" placeholder="Nama indikator" @disabled(! $this->canEditCriteria)>
                        @error("criteriaRows.{$i}.name") <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                    </div>
                    <div style="width:90px;">
                        <input type="number" step="0.5" min="1" wire:model="criteriaRows.{{ $i }}.max_score" class="form-input" placeholder="Maks" @disabled(! $this->canEditCriteria)>
                    </div>
                    @if ($this->canEditCriteria)
                        <button type="button" wire:click="removeCriterion({{ $i }})" class="btn-ghost" style="color:#dc2626;padding:8px 10px;"><i class="fa-solid fa-trash"></i></button>
                    @endif
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada indikator. Tambahkan minimal satu.</p>
            @endforelse

            @if ($this->canEditCriteria)
                <div style="display:flex;justify-content:space-between;margin-top:14px;">
                    <button type="button" wire:click="addCriterion" class="btn-ghost"><i class="fa-solid fa-plus"></i> Tambah Indikator</button>
                    <button type="button" wire:click="saveCriteria" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            @endif
        </div>

    @elseif ($tab === 'pengajuan')
        @php $rows = $this->submissions; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $rows->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Diajukan</th>
                        @if ($this->canBrowseAllCompanies)<th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>@endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Keputusan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $s)
                        @php [$sl, $sc, $sb] = $statusLabels[$s->status] ?? [$s->status, 'var(--text-muted)', 'transparent']; @endphp
                        <tr wire:key="orik-{{ $s->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $s->user?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $s->user?->nip ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $s->submitted_at?->format('d M Y') ?? '-' }}</td>
                            @if ($this->canBrowseAllCompanies)<td style="padding:9px 14px;white-space:nowrap;">{{ $s->user?->company?->name ?? '-' }}</td>@endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $sb }};color:{{ $sc }};">{{ $sl }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $s->decision ? ($decisionLabels[$s->decision] ?? $s->decision) : '—' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <a href="{{ route('orientasi.show', $s->id) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat / Nilai"><i class="fa-solid fa-clipboard-check"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($rows->isEmpty())
            <div style="padding:32px 16px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);margin:0;">Tidak ada pengajuan.</p></div>
        @endif
        <div style="margin-top:16px;">{{ $rows->links('livewire.partials.pagination') }}</div>

    @elseif ($tab === 'periode')
        @php $periods = $this->periods; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $periods->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Mulai</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Selesai</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Durasi</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Sumber</th>
                        @if ($this->canBrowseAllCompanies)<th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>@endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($periods as $p)
                        @php $aktif = $p->end_date && ! $p->end_date->isPast(); @endphp
                        <tr wire:key="orip-{{ $p->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $p->user?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $p->user?->nip ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $p->start_date?->format('d M Y') }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $p->end_date?->format('d M Y') }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $p->duration_months }} bln</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $p->source === 'extension' ? 'Perpanjangan' : 'Rekrutmen' }}</td>
                            @if ($this->canBrowseAllCompanies)<td style="padding:9px 14px;white-space:nowrap;">{{ $p->user?->company?->name ?? '-' }}</td>@endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $aktif ? 'rgba(4,124,84,0.1)' : 'rgba(148,163,184,0.15)' }};color:{{ $aktif ? '#047C54' : 'var(--text-muted)' }};">{{ $aktif ? 'Berjalan' : 'Berakhir' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($periods->isEmpty())
            <div style="padding:32px 16px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada periode orientasi. Periode dibuat otomatis saat kandidat diterima lewat Rekrutmen.</p></div>
        @endif
        <div style="margin-top:16px;">{{ $periods->links('livewire.partials.pagination') }}</div>
    @endif
</div>
