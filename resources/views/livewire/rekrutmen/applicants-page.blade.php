<div>
<x-page-header title="Daftar Pelamar" :subtitle="$record->display_name" :back="route('rekrutmen.lowongan.show', $record)" />

@php
    $applicants = $this->applicants;
    $statusMeta = [
        'applied' => ['Lamaran Baru', 'badge-pending'],
        'screening' => ['Seleksi Berkas', 'badge-pending'],
        'shortlisted' => ['Lolos Berkas / Tes', 'badge-info'],
        'interview' => ['Wawancara', 'badge-info'],
        'offering' => ['Offering', 'badge-info'],
        'mcu' => ['MCU', 'badge-info'],
        'onboarding' => ['Onboarding', 'badge-info'],
        'passed' => ['Diterima', 'badge-approved'],
        'rejected' => ['Ditolak', 'badge-rejected'],
    ];
@endphp

<div class="mb-4" style="max-width:280px;">
    <label class="form-label">Status</label>
    <select wire:model.live="status" class="form-input">
        <option value="">Semua status</option>
        @foreach ($this->statusOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
        @endforeach
    </select>
</div>

@if (count($applicants) === 0)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">
            Tidak ada pelamar{{ $status ? ' dengan status ini' : '' }}, atau data dari sistem Rekrutmen tidak bisa dimuat.
        </p>
    </div>
@else
    {{-- MOBILE --}}
    <div class="md:hidden">
        <div class="pwa-list">
            @foreach ($applicants as $a)
                <a href="{{ route('rekrutmen.pelamar.show', $a['job_application_id']) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $a['applicant_name'] ?? '—' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $a['applicant_email'] ?? '' }}</p>
                    </div>
                    @php [$lbl, $cls] = $statusMeta[$a['status']] ?? [$a['status'], 'badge-pending']; @endphp
                    <span class="badge {{ $cls }}">{{ $lbl }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- DESKTOP --}}
    <div class="hidden md:block">
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pelamar</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Email</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Update Terakhir</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($applicants as $a)
                        <tr wire:key="{{ $a['job_application_id'] }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $a['applicant_name'] ?? '—' }}</td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $a['applicant_email'] ?? '—' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                @php [$lbl, $cls] = $statusMeta[$a['status']] ?? [$a['status'], 'badge-pending']; @endphp
                                <span class="badge {{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;color:var(--text-muted);">
                                {{ $a['updated_at'] ? \Carbon\Carbon::parse($a['updated_at'])->translatedFormat('j M Y') : '—' }}
                            </td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <a href="{{ route('rekrutmen.pelamar.show', $a['job_application_id']) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-arrow-right"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
</div>
