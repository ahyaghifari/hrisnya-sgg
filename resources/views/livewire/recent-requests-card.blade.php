@php
    $badgeClassByStatus = [
        'approved' => 'badge-approved',
        'submitted' => 'badge-pending',
        'in_review' => 'badge-pending',
        'rejected' => 'badge-rejected',
        'cancelled' => 'badge-cancelled',
        'failed' => 'badge-rejected',
    ];
@endphp

<div class="bento-recent" style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
    <div style="padding:18px 20px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;">
        <h3 style="font-size:14px;font-weight:700;color:#1e293b;">Riwayat Pengajuan Terbaru</h3>
        <a href="#" style="font-size:12.5px;font-weight:600;color:#042c6c;text-decoration:none;display:flex;align-items:center;gap:4px;">
            Lihat semua <i class="fa-solid fa-arrow-right text-xs"></i>
        </a>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Jenis</th><th>Keterangan</th><th>Tanggal</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-sm font-semibold text-slate-800">{{ $row['type'] ?? '-' }}</td>
                        <td class="text-sm text-slate-600">{{ $row['title'] ?? '-' }}</td>
                        <td class="text-sm text-slate-500 whitespace-nowrap">{{ $row['date'] ?? '-' }}</td>
                        <td><span class="badge {{ $badgeClassByStatus[$row['status']] ?? 'badge-cancelled' }}">{{ $row['status_label'] ?? $row['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-sm text-slate-500 py-6">Belum ada pengajuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
