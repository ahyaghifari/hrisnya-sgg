@php
    // Warna tetap untuk 3 shift utama, sisanya di-hash dari nama (sama pola Rekap Lembur/Cuti).
    $palette = [
        ['color' => '#042c6c', 'bg' => 'rgba(4,44,108,0.1)'],
        ['color' => '#047C54', 'bg' => 'rgba(4,124,84,0.1)'],
        ['color' => '#9a3412', 'bg' => 'rgba(154,52,18,0.1)'],
        ['color' => '#0f766e', 'bg' => 'rgba(15,118,110,0.1)'],
        ['color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
    ];
    $fixed = [
        'pagi' => ['color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)'],
        'siang' => ['color' => '#0f766e', 'bg' => 'rgba(15,118,110,0.1)'],
        'malam' => ['color' => '#042c6c', 'bg' => 'rgba(4,44,108,0.1)'],
    ];
    $typeStyle = fn (string $type) => $fixed[$type] ?? $palette[crc32($type) % count($palette)];
    $typeLabel = fn (string $type) => \App\Livewire\Presensi\ExtraShiftRecapPage::shiftTypeLabel($type);
    $doubleStyle = ['color' => '#b91c1c', 'bg' => 'rgba(185,28,28,0.1)'];
    $isDouble = $tab === \App\Models\Shift\ExtraShiftAssignment::CATEGORY_DOUBLE_SHIFT;
    $tabStyle = $isDouble ? $doubleStyle : ['color' => '#047C54', 'bg' => 'rgba(4,124,84,0.1)'];
    $tabLabel = $isDouble ? 'Double Shift' : 'Extra Shift';
    $time = fn (?string $t) => $t ? substr($t, 0, 5) : null;
@endphp
<div>
<x-page-header title="Rekap Extra Shift" subtitle="Rekap seluruh extra shift yang sudah disetujui dalam 1 periode" />

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

<div class="section-nav" style="margin-bottom:16px;">
    <button type="button" wire:click="$set('tab', 'extra_shift')" class="section-nav-btn teal {{ ! $isDouble ? 'is-active' : '' }}">
        <i class="fa-solid fa-user-clock"></i>
        <span>Extra Shift</span>
        <span style="font-weight:800;margin-left:4px;">{{ $recap['tab_totals']['extra_shift'] }}</span>
    </button>
    <button type="button" wire:click="$set('tab', 'double_shift')" class="section-nav-btn rust {{ $isDouble ? 'is-active' : '' }}">
        <i class="fa-solid fa-clone"></i>
        <span>Double Shift</span>
        <span style="font-weight:800;margin-left:4px;">{{ $recap['tab_totals']['double_shift'] }}</span>
    </button>
</div>
@if ($isDouble)
    <p style="font-size:12px;color:var(--text-muted);margin:-6px 0 14px;">Double shift = akibat tukar shift yang bentrok, bukan penugasan sukarela &mdash; tidak dibayar jasa extra shift.</p>
@endif

@if (! $recap['ready'])
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:36px 24px;text-align:center;color:var(--text-muted);">
        <i class="fa-solid fa-calendar-days" style="font-size:22px;margin-bottom:10px;display:block;color:var(--text-faint);"></i>
        Pilih "Dari Tanggal" dan "Sampai Tanggal" dulu untuk lihat rekap.
    </div>
@else

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px;">
    <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
        <div class="stat-card-deco" style="background:{{ $tabStyle['color'] }};"></div>
        <div style="flex-shrink:0;width:42px;height:42px;background:{{ $tabStyle['bg'] }};border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid {{ $isDouble ? 'fa-clone' : 'fa-repeat' }}" style="color:{{ $tabStyle['color'] }};font-size:15px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['total'] }}</p>
            <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Total {{ $tabLabel }}</p>
        </div>
    </div>

    <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
        <div class="stat-card-deco" style="background:#64748b;"></div>
        <div style="flex-shrink:0;width:42px;height:42px;background:rgba(100,116,139,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid fa-users" style="color:#64748b;font-size:15px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['employee_count'] }}</p>
            <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Karyawan Terlibat</p>
        </div>
    </div>

    @foreach ($recap['types'] as $type)
        @php $style = $typeStyle($type); @endphp
        <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
            <div class="stat-card-deco" style="background:{{ $style['color'] }};"></div>
            <div style="flex-shrink:0;width:42px;height:42px;background:{{ $style['bg'] }};border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-clock" style="color:{{ $style['color'] }};font-size:15px;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['counts'][$type] ?? 0 }}</p>
                <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">{{ $tabLabel }} {{ $typeLabel($type) }}</p>
            </div>
        </div>
    @endforeach
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
                    @foreach ($recap['types'] as $type)
                        @php $style = $typeStyle($type); @endphp
                        <th style="text-align:center;"><i class="fa-solid fa-clock" style="color:{{ $style['color'] }};margin-right:5px;"></i>{{ $typeLabel($type) }}</th>
                    @endforeach
                    <th style="text-align:left;">Total {{ $tabLabel }}</th>
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
                        <td style="white-space:nowrap;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="flex-shrink:0;width:32px;height:32px;border-radius:50%;background:#047C54;color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div>
                                    <p style="font-weight:600;color:var(--text-heading);margin:0;">{{ $row['user']->name }}</p>
                                    <span style="font-size:11px;color:var(--text-muted);">{{ $row['user']->nip }}</span>
                                </div>
                            </div>
                        </td>
                        @foreach ($recap['types'] as $type)
                            @php $style = $typeStyle($type); $n = $row['counts'][$type] ?? 0; @endphp
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
                                    <div style="height:100%;border-radius:99px;background:{{ $tabStyle['color'] }};width:{{ $row['bar_percent'] }}%;"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($recap['types']) + 2 }}" style="padding:32px;text-align:center;color:var(--text-faint);">
                            @if ($search !== '')
                                Tidak ada karyawan yang cocok dengan pencarian "{{ $search }}".
                            @else
                                Belum ada {{ mb_strtolower($tabLabel) }} yang disetujui untuk periode ini.
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
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $selectedRow['total'] }} {{ mb_strtolower($tabLabel) }} dalam periode ini</p>
                </div>
                <button type="button" wire:click="closeDetail" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
                @foreach ($selectedRow['items'] as $item)
                    @php $itemStyle = $typeStyle($item['type']); @endphp
                    <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:10px;background:var(--surface-alt);">
                        <div style="flex-shrink:0;width:30px;height:30px;border-radius:9px;background:{{ $itemStyle['bg'] }};display:flex;align-items:center;justify-content:center;margin-top:1px;">
                            <i class="fa-solid fa-clock" style="color:{{ $itemStyle['color'] }};font-size:12px;"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $item['shift_name'] }}</p>
                                <span style="font-size:11.5px;font-weight:700;color:{{ $itemStyle['color'] }};white-space:nowrap;">{{ $typeLabel($item['type']) }}</span>
                            </div>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">
                                {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') }}
                                @if ($time($item['start_time']) && $time($item['end_time']))
                                    &middot; {{ $time($item['start_time']) }} - {{ $time($item['end_time']) }}
                                @endif
                                @if ($item['source'])
                                    &middot; {{ $item['source'] === 'requested' ? 'Diajukan karyawan' : 'Ditugaskan' }}
                                @endif
                            </p>
                            @if ($time($item['check_in']) || $time($item['check_out']))
                                <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">
                                    Scan: {{ $time($item['check_in']) ?? '-' }} &rarr; {{ $time($item['check_out']) ?? '-' }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
