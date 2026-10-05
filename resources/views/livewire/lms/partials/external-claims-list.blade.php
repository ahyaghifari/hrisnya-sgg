{{-- Daftar pelatihan eksternal terlaksana (disetujui) satu karyawan - isi modal Detail di Laporan Pelatihan
     (portal & Filament). $claims: Collection<TrainingClaim> dengan relasi sessions. --}}
@php $disk = \Illuminate\Support\Facades\Storage::disk('public'); @endphp
@forelse ($claims as $claim)
    <div style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div style="min-width:0;">
                <div style="font-size:13.5px;font-weight:700;color:var(--text-heading);">{{ $claim->title }}</div>
                <div style="font-size:12px;color:var(--text-muted);">{{ $claim->organizer ?: 'Penyelenggara tidak diisi' }}</div>
            </div>
            <span class="badge badge-approved" style="white-space:nowrap;">{{ $claim->jp }} JP</span>
        </div>
        <div style="margin-top:6px;display:flex;flex-direction:column;gap:2px;">
            @foreach ($claim->sessions as $s)
                <span style="font-size:12px;color:var(--text-body);">
                    <i class="fa-regular fa-calendar" style="color:var(--text-faint);width:14px;"></i>
                    {{ $s->date->translatedFormat('j F Y') }}, {{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }}
                </span>
            @endforeach
        </div>
        <a href="{{ $disk->url($claim->certificate_path) }}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;font-weight:600;color:var(--brand);text-decoration:none;">
            <i class="fa-solid fa-certificate"></i> Lihat sertifikat
        </a>
    </div>
@empty
    <p style="padding:24px 0;text-align:center;color:var(--text-muted);font-size:13px;margin:0;">Belum ada pelatihan eksternal terlaksana yang disetujui pada periode ini.</p>
@endforelse
