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
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-muted);">
        <i class="fa-solid fa-calendar-days" style="font-size:20px;margin-bottom:8px;display:block;color:var(--text-faint);"></i>
        Pilih "Dari Tanggal" dan "Sampai Tanggal" dulu untuk lihat rekap.
    </div>
@else

{{-- Card perhitungan keseluruhan — total per kategori + grand total, sekilas pandang sebelum
lihat rincian per karyawan di tabel bawah. --}}
<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px 18px;margin-bottom:16px;">
    <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">
        <i class="fa-solid fa-chart-simple"></i> Total Keseluruhan
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <div style="flex:1;min-width:110px;padding:10px 14px;border-radius:10px;background:rgba(4,44,108,0.06);">
            <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">Total Izin</p>
            <p style="margin:2px 0 0;font-size:22px;font-weight:800;color:var(--brand);">{{ $recap['overall']['total'] }}</p>
        </div>
        <div style="flex:1;min-width:110px;padding:10px 14px;border-radius:10px;background:var(--surface-alt);">
            <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">Karyawan Terlibat</p>
            <p style="margin:2px 0 0;font-size:22px;font-weight:800;color:var(--text-heading);">{{ $recap['overall']['employee_count'] }}</p>
        </div>
        @foreach ($recap['categories'] as $key => $label)
            <div style="flex:1;min-width:110px;padding:10px 14px;border-radius:10px;background:var(--surface-alt);">
                <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">{{ $label }}</p>
                <p style="margin:2px 0 0;font-size:22px;font-weight:800;color:var(--text-heading);">{{ $recap['overall']['counts'][$key] }}</p>
            </div>
        @endforeach
        <div style="flex:1;min-width:110px;padding:10px 14px;border-radius:10px;background:var(--surface-alt);">
            <p style="margin:0;font-size:10px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">Lainnya</p>
            <p style="margin:2px 0 0;font-size:22px;font-weight:800;color:var(--text-heading);">{{ $recap['overall']['counts']['lainnya'] }}</p>
        </div>
    </div>
</div>

<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
    <table class="data-table" style="min-width:640px;">
        <thead>
            <tr>
                <th>Karyawan</th>
                @foreach ($recap['categories'] as $label)
                    <th style="text-align:center;">{{ $label }}</th>
                @endforeach
                <th style="text-align:center;">Lainnya</th>
                <th style="text-align:center;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recap['rows'] as $row)
                <tr wire:click="openDetail('{{ $row['user']->id }}')" style="cursor:pointer;" title="Klik untuk lihat rincian tanggal">
                    <td style="font-weight:600;color:var(--text-heading);white-space:nowrap;">
                        {{ $row['user']->name }}
                        <span style="display:block;font-size:11px;font-weight:400;color:var(--text-muted);">{{ $row['user']->nip }}</span>
                    </td>
                    @foreach (array_keys($recap['categories']) as $key)
                        <td style="text-align:center;">{{ $row['counts'][$key] > 0 ? $row['counts'][$key] : '-' }}</td>
                    @endforeach
                    <td style="text-align:center;">{{ $row['counts']['lainnya'] > 0 ? $row['counts']['lainnya'] : '-' }}</td>
                    <td style="text-align:center;font-weight:700;color:var(--brand);">{{ $row['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($recap['categories']) + 3 }}" style="padding:24px;text-align:center;color:var(--text-faint);">
                        Belum ada izin yang disetujui untuk periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- Modal rincian tanggal — muncul saat baris karyawan diklik, isinya kapan aja izin
diajukan (bukan cuma angka rekap di tabel). --}}
@php $selectedRow = collect($recap['rows'])->firstWhere('user.id', $selectedUserId); @endphp
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
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:10px;background:var(--surface-alt);">
                        <div style="min-width:0;">
                            <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $item['type_name'] }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $item['category_label'] }} &middot; {{ $item['start_time'] }} - {{ $item['end_time'] }}</p>
                        </div>
                        <span style="font-size:11.5px;font-weight:700;color:var(--brand);white-space:nowrap;">
                            {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
