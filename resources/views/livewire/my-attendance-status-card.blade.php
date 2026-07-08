<div>
@if ($request)
    <x-approval-timeline
        :timeline="$timeline"
        :title="'Status Pengajuan ' . (['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$request->type] ?? $request->type) . ' Anda'"
    >
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div style="font-size:12.5px;color:#64748b;">
                <span style="font-weight:600;color:#374151;">{{ $summary['type_name'] }}</span>
                &nbsp;&middot;&nbsp; {{ $summary['date_label'] }}
            </div>
            <a href="{{ route('my-attendance-requests.show', ['record' => $request->id]) }}" wire:navigate class="btn-primary" style="padding:8px 16px;font-size:12.5px;text-decoration:none;">
                Lihat Detail <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </div>
    </x-approval-timeline>
@endif
</div>
