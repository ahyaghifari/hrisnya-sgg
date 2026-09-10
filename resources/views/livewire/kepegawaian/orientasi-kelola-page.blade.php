<div>
    <x-page-header title="Orientasi" subtitle="Karyawan orientasi berjalan, riwayat, & indikator penilaian" />

    @php
        $tabs = ['pengajuan' => 'Periode Orientasi', 'riwayat' => 'Riwayat'];
        if ($this->canViewCriteria) {
            $tabs['indikator'] = 'Indikator Penilaian';
        }
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)', 'rgba(148,163,184,0.15)'],
            'diajukan' => ['Diajukan', '#b45309', 'rgba(245,158,11,0.12)'],
            'dinilai' => ['Dinilai', '#1d4ed8', 'rgba(37,99,235,0.12)'],
            'selesai' => ['Selesai', '#047C54', 'rgba(4,124,84,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = ['lanjut_kontrak' => 'Direkomendasikan untuk Kontrak Kerja', 'tidak_dilanjutkan' => 'Tidak Dilanjutkan'];
        $catLabels = ['tetap' => 'Tetap', 'kontrak' => 'Kontrak', 'orientasi' => 'Orientasi'];
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
    </div>

    @if ($tab === 'indikator')
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Indikator yang dipakai penilai saat menilai orientasi karyawan{{ $this->canBrowseAllCompanies ? ' (per perusahaan yang dipilih di atas)' : '' }}.</p>
            @forelse ($criteriaRows as $i => $row)
                <div wire:key="crit-{{ $i }}" style="display:flex;gap:8px;align-items:flex-start;border-bottom:1px solid var(--border-soft);padding:10px 0;">
                    <div style="flex:1;">
                        <input type="text" wire:model="criteriaRows.{{ $i }}.name" class="form-input" placeholder="Nama indikator" @disabled(! $this->canEditCriteria)>
                        @error("criteriaRows.{$i}.name") <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                    </div>
                    @if ($this->canEditCriteria)
                        <div style="display:flex;flex-direction:column;gap:2px;">
                            <button type="button" wire:click="moveCriterionUp({{ $i }})" class="btn-ghost" style="padding:2px 8px;" @disabled($i === 0)><i class="fa-solid fa-chevron-up" style="font-size:10px;"></i></button>
                            <button type="button" wire:click="moveCriterionDown({{ $i }})" class="btn-ghost" style="padding:2px 8px;" @disabled($i === count($criteriaRows) - 1)><i class="fa-solid fa-chevron-down" style="font-size:10px;"></i></button>
                        </div>
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
        @php $rows = $this->currentOrientasi; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $rows->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:640px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Periode Orientasi</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Sisa</th>
                        @if ($this->canBrowseAllCompanies)<th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>@endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Pengajuan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Keputusan</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $u)
                        @php
                            $period = $u->orientations->first();
                            $sub = $u->orientationSubmissions->first();
                            $sisa = $period?->end_date ? now()->startOfDay()->diffInDays($period->end_date, false) : null;
                            [$sl, $sc, $sb] = $sub ? ($statusLabels[$sub->status] ?? [$sub->status, 'var(--text-muted)', 'transparent']) : ['Belum ada', 'var(--text-faint)', 'transparent'];
                        @endphp
                        <tr wire:key="orik-{{ $u->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $u->name }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $u->nip ?? '-' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($period)
                                    {{ $period->start_date?->format('d M Y') }} &ndash; {{ $period->end_date?->format('d M Y') }}
                                    <span style="color:var(--text-faint);">({{ $period->duration_months }} bln)</span>
                                    @if ($u->orientations->count() > 1)
                                        <span style="font-size:10px;color:var(--text-faint);">&middot; periode ke-{{ $u->orientations->count() }}</span>
                                    @endif
                                @else
                                    <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @if ($sisa === null) –
                                @elseif ($sisa < 0) <span style="color:var(--danger-text);font-weight:600;">lewat {{ abs($sisa) }}h</span>
                                @else <span style="font-weight:600;{{ $sisa <= 14 ? 'color:#b45309;' : '' }}">{{ $sisa }}h</span>
                                @endif
                            </td>
                            @if ($this->canBrowseAllCompanies)<td style="padding:9px 14px;white-space:nowrap;">{{ $u->company?->name ?? '-' }}</td>@endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $sb }};color:{{ $sc }};">{{ $sl }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $sub?->decision ? ($decisionLabels[$sub->decision] ?? $sub->decision) : '—' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @if ($sub)
                                    <a href="{{ route('orientasi.show', $sub->id) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat / Nilai"><i class="fa-solid fa-clipboard-check"></i></a>
                                @else
                                    <span style="font-size:11px;color:var(--text-faint);">belum ada</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($rows->isEmpty())
            <div style="padding:32px 16px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);margin:0;">Tidak ada karyawan berstatus Orientasi.</p></div>
        @endif
        <div style="margin-top:16px;">{{ $rows->links('livewire.partials.pagination') }}</div>

    @elseif ($tab === 'riwayat')
        @php $rows = $this->riwayat; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $rows->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:620px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status Sekarang</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Periode Orientasi Terakhir</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Keputusan</th>
                        @if ($this->canBrowseAllCompanies)<th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>@endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $u)
                        @php
                            $period = $u->orientations->first();
                            $sub = $u->orientationSubmissions->firstWhere('status', 'selesai') ?? $u->orientationSubmissions->first();
                            $cat = $u->profile?->employeeType?->category;
                        @endphp
                        <tr wire:key="orih-{{ $u->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $u->name }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $u->nip ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:rgba(4,124,84,0.1);color:#047C54;">{{ $catLabels[$cat] ?? ($cat ?: '-') }}</span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($period)
                                    {{ $period->start_date?->format('d M Y') }} &ndash; {{ $period->end_date?->format('d M Y') }}
                                    @if ($u->orientations->count() > 1)
                                        <span style="font-size:10px;color:var(--text-faint);">&middot; {{ $u->orientations->count() }} periode</span>
                                    @endif
                                @else <span style="color:var(--text-faint);">-</span>
                                @endif
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $sub?->decision ? ($decisionLabels[$sub->decision] ?? $sub->decision) : '—' }}</td>
                            @if ($this->canBrowseAllCompanies)<td style="padding:9px 14px;white-space:nowrap;">{{ $u->company?->name ?? '-' }}</td>@endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @if ($sub)
                                    <a href="{{ route('orientasi.show', $sub->id) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-eye"></i></a>
                                @else
                                    <span style="font-size:11px;color:var(--text-faint);">–</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($rows->isEmpty())
            <div style="padding:32px 16px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada karyawan yang lulus/pindah dari status Orientasi.</p></div>
        @endif
        <div style="margin-top:16px;">{{ $rows->links('livewire.partials.pagination') }}</div>
    @endif
</div>
