@php
    $statusStyle = [
        'approved' => ['color' => '#047C54', 'bg' => 'rgba(4,124,84,0.1)', 'icon' => 'fa-circle-check'],
        'in_review' => ['color' => '#b45309', 'bg' => 'rgba(180,83,9,0.1)', 'icon' => 'fa-hourglass-half'],
        'rejected' => ['color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)', 'icon' => 'fa-circle-xmark'],
        'cancelled' => ['color' => '#64748b', 'bg' => 'rgba(100,116,139,0.1)', 'icon' => 'fa-ban'],
    ];
    $statuses = \App\Livewire\Presensi\ShiftSwapRecapPage::STATUSES;
    // Jenis tukar (warna terpisah dari warna status): tanggal sama / tanggal beda / tukar libur (ambil libur teman).
    $typeStyle = [
        'same' => ['color' => '#1d4ed8', 'bg' => 'rgba(29,78,216,0.1)', 'label' => 'Tanggal Sama'],
        'diff' => ['color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.1)', 'label' => 'Tanggal Beda'],
        'libur' => ['color' => '#db2777', 'bg' => 'rgba(219,39,119,0.1)', 'label' => 'Tukar Libur (Ambil Libur Teman)'],
    ];
@endphp
<div>
<x-page-header title="Rekap Tukar Shift" subtitle="Rekap pengajuan tukar shift dalam 1 periode, berdasarkan tanggal shift pengaju" />

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    @if ($this->canBrowseAllCompanies)
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

    <div style="min-width:220px;">
        <label class="form-label">Departemen</label>
        <select wire:model.live="departmentId" class="form-input" @disabled(! $companyId)>
            <option value="">Semua departemen</option>
            @foreach ($this->departmentOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div style="min-width:220px;">
        <label class="form-label">Divisi</label>
        <select wire:model.live="divisionId" class="form-input" @disabled(! $departmentId)>
            <option value="">Semua divisi</option>
            @foreach ($this->divisionOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div style="min-width:170px;">
        <label class="form-label">Dari Tanggal</label>
        <input type="date" class="form-input" wire:model.live="date_from">
    </div>

    <div style="min-width:170px;">
        <label class="form-label">Sampai Tanggal</label>
        <input type="date" class="form-input" wire:model.live="date_to">
    </div>
</div>

@php $recap = $this->recap; @endphp

<p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Satu pengajuan dihitung atas nama pengaju saja.</p>

@if (! $recap['ready'])
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:36px 24px;text-align:center;color:var(--text-muted);">
        <i class="fa-solid fa-calendar-days" style="font-size:22px;margin-bottom:10px;display:block;color:var(--text-faint);"></i>
        Pilih "Dari Tanggal" dan "Sampai Tanggal" dulu untuk lihat rekap.
    </div>
@else

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px;">
    @foreach ($statuses as $key => $label)
        @php $style = $statusStyle[$key]; @endphp
        <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
            <div class="stat-card-deco" style="background:{{ $style['color'] }};"></div>
            <div style="flex-shrink:0;width:42px;height:42px;background:{{ $style['bg'] }};border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid {{ $style['icon'] }}" style="color:{{ $style['color'] }};font-size:15px;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['counts'][$key] }}</p>
                <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">{{ $label }}</p>
            </div>
        </div>
    @endforeach

    <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
        <div class="stat-card-deco" style="background:#1d4ed8;"></div>
        <div style="flex-shrink:0;width:42px;height:42px;background:rgba(29,78,216,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid fa-users" style="color:#1d4ed8;font-size:15px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['employee_count'] }}</p>
            <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Karyawan Terlibat</p>
        </div>
    </div>
</div>

<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-soft);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
        <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Rincian per Karyawan (kali)</h4>
        <div style="position:relative;min-width:220px;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama atau NIP..." style="padding-left:32px;">
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="data-table" style="min-width:640px;font-size:12px;">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    @foreach ($statuses as $key => $label)
                        @php $style = $statusStyle[$key]; @endphp
                        <th style="text-align:center;"><i class="fa-solid {{ $style['icon'] }}" style="color:{{ $style['color'] }};margin-right:5px;"></i>{{ $label }}</th>
                    @endforeach
                    <th style="text-align:left;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recap['rows'] as $row)
                    @php
                        $initials = collect(preg_split('/\s+/', trim($row['user']->name)))
                            ->map(fn ($w) => mb_substr($w, 0, 1))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <tr wire:click="openDetail('{{ $row['user']->id }}')" style="cursor:pointer;" title="Klik untuk lihat rincian tanggal">
                        <td style="min-width:300px;max-width:400px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="flex-shrink:0;width:32px;height:32px;border-radius:50%;background:#047C54;color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div style="min-width:0;">
                                    <p style="font-weight:600;color:var(--text-heading);margin:0;">{{ $row['user']->name }}</p>
                                    <span style="font-size:11px;color:var(--text-muted);">{{ $row['user']->nip }}</span>
                                    @php $p = $row['user']->profile; $unit = collect([$p?->jobPosition?->name, $p?->division?->name ?? $p?->department?->name])->filter()->implode(' · '); @endphp
                                    @if ($unit !== '')
                                        <span style="display:block;font-size:11px;color:var(--text-muted);">{{ $unit }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        @foreach ($statuses as $key => $label)
                            @php $style = $statusStyle[$key]; $n = $row['counts'][$key]; @endphp
                            <td style="text-align:center;">
                                @if ($n > 0)
                                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:22px;padding:0 7px;border-radius:99px;background:{{ $style['color'] }};color:#fff;font-size:12px;font-weight:700;">{{ $n }}</span>
                                @else
                                    <span style="color:var(--text-faint);">–</span>
                                @endif
                            </td>
                        @endforeach
                        <td style="min-width:140px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:800;color:var(--text-heading);font-size:13.5px;width:30px;">{{ $row['total'] }}</span>
                                <div style="flex:1;height:6px;border-radius:99px;background:var(--border-soft);overflow:hidden;">
                                    <div style="height:100%;border-radius:99px;background:#047C54;width:{{ $row['bar_percent'] }}%;"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($statuses) + 2 }}" style="padding:32px;text-align:center;color:var(--text-faint);">
                            @if ($search !== '')
                                Tidak ada karyawan yang cocok dengan pencarian "{{ $search }}".
                            @else
                                Belum ada pengajuan tukar shift untuk periode ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@php $selectedRow = collect($recap['rows'] ?? [])->firstWhere('user.id', $selectedUserId); @endphp
@if ($selectedRow)
    <div class="modal-overlay" wire:click="closeDetail">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ $selectedRow['user']->name }}</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $selectedRow['total'] }} pengajuan tukar shift dalam periode ini</p>
                </div>
                <button type="button" wire:click="closeDetail" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:6px 12px;margin-bottom:10px;">
                @foreach ($typeStyle as $t)
                    <span style="font-size:10.5px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;">
                        <span style="width:9px;height:9px;border-radius:3px;background:{{ $t['color'] }};display:inline-block;"></span>{{ $t['label'] }}
                    </span>
                @endforeach
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
                @foreach ($selectedRow['items'] as $item)
                    @php $itemStyle = $statusStyle[$item['status']]; $tStyle = $typeStyle[$item['type']]; @endphp
                    <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:10px;background:var(--surface-alt);border-left:4px solid {{ $tStyle['color'] }};">
                        <div style="flex-shrink:0;width:30px;height:30px;border-radius:9px;background:{{ $itemStyle['bg'] }};display:flex;align-items:center;justify-content:center;margin-top:1px;">
                            <i class="fa-solid {{ $itemStyle['icon'] }}" style="color:{{ $itemStyle['color'] }};font-size:12px;"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">
                                    <span style="display:inline-block;padding:1px 8px;margin-right:6px;border-radius:99px;background:{{ $tStyle['bg'] }};color:{{ $tStyle['color'] }};font-size:10.5px;font-weight:700;">{{ $tStyle['label'] }}</span>{{ $item['kind'] }} dengan {{ $item['partner'] }}
                                </p>
                                <span style="font-size:11.5px;font-weight:700;color:{{ $itemStyle['color'] }};white-space:nowrap;">{{ $statuses[$item['status']] }}</span>
                            </div>
                            @php
                                $dFmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y');
                                $tDate = $item['target_date'] ?: $item['date'];
                            @endphp
                            <div style="font-size:11px;color:var(--text-muted);margin:4px 0 0;display:flex;flex-direction:column;gap:2px;">
                                <span><strong style="color:var(--text-heading);">{{ $selectedRow['user']->name }}</strong> (pengaju): <strong>{{ $item['req_shift'] ?? '-' }}</strong> tgl {{ $dFmt($item['date']) }} &rarr; <strong style="color:#b45309;">{{ $item['target_shift'] ?? '-' }}</strong> tgl {{ $dFmt($tDate) }}</span>
                                <span><strong style="color:var(--text-heading);">{{ $item['partner'] }}</strong> (rekan): <strong>{{ $item['target_shift'] ?? '-' }}</strong> tgl {{ $dFmt($tDate) }} &rarr; <strong style="color:#b45309;">{{ $item['req_shift'] ?? '-' }}</strong> tgl {{ $dFmt($item['date']) }}</span>
                                <span style="color:#b45309;"><i class="fa-solid fa-right-left"></i>
                                    @if ($item['type'] === 'libur')
                                        Rekan mengambil shift pengaju, pengaju mengambil alih shift rekan; masing-masing jadi Libur di tanggal asalnya.
                                    @elseif ($item['type'] === 'same')
                                        Shift keduanya saling ditukar di tanggal yang sama.
                                    @else
                                        Pengaju menerima shift rekan, rekan menerima shift pengaju (tanggal saling bertukar).
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
