<div>
<x-page-header title="Antrian MCU" subtitle="Pelamar Rekrutmen yang menunggu / sedang MCU" />

@php $antrian = $this->antrian; @endphp

@if (count($antrian) === 0)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Tidak ada pelamar di tahap MCU, atau data dari sistem Rekrutmen tidak bisa dimuat.</p>
    </div>
@else
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pelamar</th>
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Lowongan</th>
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Stasiun Terisi</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">MCU</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($antrian as $a)
                    <tr wire:key="{{ $a['job_application_id'] }}" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $a['applicant_name'] ?? '—' }}</td>
                        <td style="padding:9px 14px;white-space:nowrap;">{{ $a['job_opening_title'] ?? '—' }}</td>
                        <td style="padding:9px 14px;white-space:nowrap;">
                            @forelse ($a['stations_filled'] ?? [] as $s)
                                <span class="badge badge-approved" style="font-size:10px;">{{ ['nurse'=>'Perawat','lab'=>'Lab','radiology'=>'Radiologi','doctor'=>'Dokter'][$s] ?? $s }}</span>
                            @empty
                                <span style="color:var(--text-faint);">—</span>
                            @endforelse
                        </td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <span class="badge {{ ['pending'=>'badge-pending','fit'=>'badge-approved','unfit'=>'badge-rejected'][$a['status_mcu']] ?? 'badge-pending' }}">
                                {{ ['pending'=>'Belum','fit'=>'FIT','unfit'=>'UNFIT'][$a['status_mcu']] ?? $a['status_mcu'] }}
                            </span>
                        </td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            @php $links = \App\Livewire\Rekrutmen\McuFormPage::availableStationLinks($a['job_application_id'], $a['stations_filled'] ?? []); @endphp
                            @forelse ($links as $link)
                                <a href="{{ $link['url'] }}" wire:navigate class="btn-ghost" style="padding:4px 8px;font-size:10.5px;margin:1px;" title="{{ $link['filled'] ? 'Edit ' : 'Isi ' }}{{ $link['label'] }}">
                                    <i class="fa-solid {{ $link['filled'] ? 'fa-pen' : 'fa-notes-medical' }}"></i>
                                    {{ ['nurse'=>'Perawat','lab'=>'Lab','radiology'=>'Radiologi','doctor'=>'Dokter'][$link['station']] }}
                                </a>
                            @empty
                                <span style="color:var(--text-faint);font-size:11px;">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</div>
