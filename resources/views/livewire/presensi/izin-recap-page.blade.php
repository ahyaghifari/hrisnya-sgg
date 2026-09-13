@php
    // Identitas visual per kategori — dipakai di kartu ringkasan, kolom matrix, dan modal
    // rincian, jadi tiap kategori punya warna+ikon yang sama persis di mana pun ia muncul.
    $categoryStyle = [
        'izin_terlambat_masuk'        => ['color' => '#d97706', 'bg' => 'rgba(217,119,6,0.1)', 'icon' => 'fa-clock'],
        'izin_pulang_cepat'           => ['color' => '#0f766e', 'bg' => 'rgba(15,118,110,0.1)', 'icon' => 'fa-door-open'],
        'izin_seharian'               => ['color' => '#9a3412', 'bg' => 'rgba(154,52,18,0.1)', 'icon' => 'fa-calendar-day'],
        'izin_meninggalkan_jam_kerja' => ['color' => '#047C54', 'bg' => 'rgba(4,124,84,0.1)', 'icon' => 'fa-person-walking-arrow-right'],
        'lainnya'                     => ['color' => '#64748b', 'bg' => 'rgba(100,116,139,0.1)', 'icon' => 'fa-ellipsis'],
    ];
@endphp
<div>
<x-page-header title="Rekap Izin" subtitle="Rekap seluruh pengajuan izin yang sudah disetujui dalam 1 periode" />

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

@if (! $recap['ready'])
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:36px 24px;text-align:center;color:var(--text-muted);">
        <i class="fa-solid fa-calendar-days" style="font-size:22px;margin-bottom:10px;display:block;color:var(--text-faint);"></i>
        Pilih "Dari Tanggal" dan "Sampai Tanggal" dulu untuk lihat rekap.
    </div>
@else

{{-- Ringkasan: 1 tile per metrik/kategori, ikon+angka sejajar horizontal supaya proporsional
di lebar berapa pun (bukan numpuk vertikal di kartu lebar). --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px;">
    <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
        <div class="stat-card-deco" style="background:#042c6c;"></div>
        <div style="flex-shrink:0;width:42px;height:42px;background:rgba(4,44,108,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid fa-clipboard-list" style="color:#042c6c;font-size:15px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['total'] }}</p>
            <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Total Izin</p>
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

    @foreach ($recap['categories'] as $key => $label)
        @php $style = $categoryStyle[$key] ?? $categoryStyle['lainnya']; @endphp
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

    @php $style = $categoryStyle['lainnya']; @endphp
    <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
        <div class="stat-card-deco" style="background:{{ $style['color'] }};"></div>
        <div style="flex-shrink:0;width:42px;height:42px;background:{{ $style['bg'] }};border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid {{ $style['icon'] }}" style="color:{{ $style['color'] }};font-size:15px;"></i>
        </div>
        <div style="min-width:0;">
            <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $recap['overall']['counts']['lainnya'] }}</p>
            <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Lainnya</p>
        </div>
    </div>
</div>

<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-soft);display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
        <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Rincian per Karyawan</h4>
        <div style="position:relative;min-width:220px;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama atau NIP..." style="padding-left:32px;">
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="data-table" style="min-width:720px;font-size:12px;">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    @foreach ($recap['categories'] as $key => $label)
                        @php $style = $categoryStyle[$key] ?? $categoryStyle['lainnya']; @endphp
                        <th style="text-align:center;"><i class="fa-solid {{ $style['icon'] }}" style="color:{{ $style['color'] }};margin-right:5px;"></i>{{ $label }}</th>
                    @endforeach
                    <th style="text-align:center;"><i class="fa-solid {{ $categoryStyle['lainnya']['icon'] }}" style="color:{{ $categoryStyle['lainnya']['color'] }};margin-right:5px;"></i>Lainnya</th>
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
                        <td style="white-space:nowrap;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="flex-shrink:0;width:32px;height:32px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div>
                                    <p style="font-weight:600;color:var(--text-heading);margin:0;">{{ $row['user']->name }}</p>
                                    <span style="font-size:11px;color:var(--text-muted);">{{ $row['user']->nip }}</span>
                                </div>
                            </div>
                        </td>
                        @foreach (array_keys($recap['categories']) as $key)
                            @php $style = $categoryStyle[$key] ?? $categoryStyle['lainnya']; @endphp
                            <td style="text-align:center;">
                                @if ($row['counts'][$key] > 0)
                                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:22px;padding:0 7px;border-radius:99px;background:{{ $style['color'] }};color:#fff;font-size:12px;font-weight:700;">{{ $row['counts'][$key] }}</span>
                                @else
                                    <span style="color:var(--text-faint);">–</span>
                                @endif
                            </td>
                        @endforeach
                        <td style="text-align:center;">
                            @if ($row['counts']['lainnya'] > 0)
                                <span style="display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:22px;padding:0 7px;border-radius:99px;background:{{ $categoryStyle['lainnya']['color'] }};color:#fff;font-size:12px;font-weight:700;">{{ $row['counts']['lainnya'] }}</span>
                            @else
                                <span style="color:var(--text-faint);">–</span>
                            @endif
                        </td>
                        <td style="min-width:140px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:800;color:var(--text-heading);font-size:13.5px;width:20px;">{{ $row['total'] }}</span>
                                <div style="flex:1;height:6px;border-radius:99px;background:var(--border-soft);overflow:hidden;">
                                    <div style="height:100%;border-radius:99px;background:#042c6c;width:{{ $row['bar_percent'] }}%;"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($recap['categories']) + 3 }}" style="padding:32px;text-align:center;color:var(--text-faint);">
                            @if ($search !== '')
                                Tidak ada karyawan yang cocok dengan pencarian "{{ $search }}".
                            @else
                                Belum ada izin yang disetujui untuk periode ini.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Modal rincian tanggal — muncul saat baris karyawan diklik, isinya kapan aja izin
diajukan (bukan cuma angka rekap di tabel). --}}
@php $selectedRow = collect($recap['rows'] ?? [])->firstWhere('user.id', $selectedUserId); @endphp
@if ($selectedRow)
    <div class="modal-overlay" wire:click="closeDetail">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ $selectedRow['user']->name }}</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $selectedRow['total'] }} izin dalam periode ini</p>
                </div>
                <button type="button" wire:click="closeDetail" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
                @foreach ($selectedRow['items'] as $item)
                    @php
                        $itemStyle = collect($categoryStyle)->first(fn ($s, $k) => ($recap['categories'][$k] ?? null) === $item['category_label']) ?? $categoryStyle['lainnya'];
                    @endphp
                    <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:10px;background:var(--surface-alt);">
                        <div style="flex-shrink:0;width:30px;height:30px;border-radius:9px;background:{{ $itemStyle['bg'] }};display:flex;align-items:center;justify-content:center;margin-top:1px;">
                            <i class="fa-solid {{ $itemStyle['icon'] }}" style="color:{{ $itemStyle['color'] }};font-size:12px;"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $item['type_name'] }}</p>
                                <span style="font-size:11.5px;font-weight:700;color:{{ $itemStyle['color'] }};white-space:nowrap;">
                                    {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') }}
                                    @if ($item['end_date'])
                                        &ndash; {{ \Illuminate\Support\Carbon::parse($item['end_date'])->translatedFormat('d M Y') }}
                                    @endif
                                </span>
                            </div>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">
                                {{ $item['category_label'] }}
                                @if ($item['end_date'])
                                    &middot; {{ $item['total_days'] }} hari
                                @else
                                    &middot; {{ $item['start_time'] }} - {{ $item['end_time'] }}
                                @endif
                            </p>
                            @if ($item['purpose'])
                                <p style="font-size:11px;color:var(--text-body);margin:4px 0 0;font-style:italic;">"{{ $item['purpose'] }}"</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
