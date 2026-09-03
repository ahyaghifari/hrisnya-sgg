<div>
<x-page-header title="Daftar Pelamar" :subtitle="$this->headerTitle" :back="$record ? route('rekrutmen.lowongan.show', $record) : route('rekrutmen.lowongan')" />

@if ($this->isUnlinked)
    <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #d97706;border-radius:12px;padding:10px 14px;margin-bottom:16px;font-size:11.5px;color:var(--text-muted);">
        <i class="fa-solid fa-link-slash" style="color:#d97706;"></i> Lowongan ini dibuat manual di sistem Rekrutmen (belum terhubung ke HRIS). Proses seleksi tetap bisa dikelola dari sini.
    </div>
@endif

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

@php $counts = $this->statusCounts; $total = array_sum($counts); @endphp
<div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:10px;">
    <button type="button" wire:click="$set('status', '')"
        class="btn-ghost" style="padding:5px 12px;font-size:11.5px;font-weight:600;{{ $status === '' ? 'background:var(--brand,#7c3aed);color:#fff;' : '' }}">
        Semua <span style="opacity:.7;">{{ $total }}</span>
    </button>
    @foreach ($this->statusOptions as $val => $label)
        <button type="button" wire:click="$set('status', '{{ $val }}')"
            class="btn-ghost" style="padding:5px 12px;font-size:11.5px;font-weight:600;{{ $status === $val ? 'background:var(--brand,#7c3aed);color:#fff;' : '' }}">
            {{ $label }} <span style="opacity:.7;">{{ $counts[$val] ?? 0 }}</span>
        </button>
    @endforeach
    @if ($this->canExport && count($applicants) > 0)
        <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
            style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:11.5px;font-weight:600;border:none;border-radius:10px;cursor:pointer;background:#15803d;color:#fff;">
            <i class="fa-solid fa-file-excel"></i> Export Excel
        </button>
    @endif
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
        @if ($this->canDecide && count($selected) > 0)
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:10px 14px;margin-bottom:12px;">
                <span style="font-size:12px;font-weight:600;">{{ count($selected) }} dipilih</span>
                @foreach (\App\Livewire\Rekrutmen\ApplicantsPage::BULK_TARGETS as $target => $label)
                    <button type="button"
                        wire:click="bulkTransition('{{ $target }}')"
                        wire:confirm="Ubah status {{ count($selected) }} pelamar ke &quot;{{ $label }}&quot;?"
                        wire:loading.attr="disabled"
                        class="btn-ghost"
                        style="padding:6px 14px;font-size:11.5px;font-weight:600;{{ $target === 'rejected' ? 'color:var(--danger-text,#dc2626);' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
                <button type="button" wire:click="$set('selected', [])" class="btn-ghost" style="padding:6px 10px;font-size:11px;color:var(--text-muted);">Batal</button>
                <span wire:loading wire:target="bulkTransition" style="font-size:11px;color:var(--text-muted);">Memproses…</span>
            </div>
        @endif

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        @if ($this->canDecide)
                            <th style="width:38px;padding:10px 0 10px 14px;">
                                <span class="custom-checkbox" style="--accent:#7c3aed;">
                                    <input type="checkbox" wire:click="toggleSelectAll"
                                        @checked(count($applicants) > 0 && count(array_intersect($selected, $this->visibleIds)) === count($this->visibleIds))>
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </span>
                            </th>
                        @endif
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
                            @if ($this->canDecide)
                                <td style="padding:9px 0 9px 14px;">
                                    <span class="custom-checkbox" style="--accent:#7c3aed;">
                                        <input type="checkbox" wire:model.live="selected" value="{{ $a['job_application_id'] }}">
                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                    </span>
                                </td>
                            @endif
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
