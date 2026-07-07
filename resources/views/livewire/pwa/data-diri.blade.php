<div>
<div class="mb-6">
    <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
    <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">Data Diri</h1>
</div>

@php
    $profile = $record->profile;

    $initials = collect(preg_split('/\s+/', trim($record->name)))
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('');

    $photoPath = $profile?->profile_picture[0] ?? null;
    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;

    $roleLabel = $record->roles->pluck('name')
        ->map(fn ($n) => ucwords(str_replace('_', ' ', $n)))
        ->implode(', ');

    $tanggalLahir = $profile?->place_of_birth || $profile?->date_of_birth
        ? trim(($profile?->place_of_birth ?? '') . ', ' . ($profile?->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->translatedFormat('d F Y') : ''), ', ')
        : null;

    $pribadi = [
        'Nama Panggilan' => $profile?->nickname,
        'Jenis Kelamin' => match ($profile?->gender) { 'laki_laki' => 'Laki-laki', 'perempuan' => 'Perempuan', default => null },
        'Agama' => $profile?->religion?->name,
        'Tempat, Tanggal Lahir' => $tanggalLahir,
        'No. NIK' => $profile?->no_nik,
        'No. KK' => $profile?->no_kk,
        'No. BPJS Kesehatan' => $profile?->no_bpjs_kes,
        'No. BPJS Ketenagakerjaan' => $profile?->no_bpjs_ket,
        'NPWP' => $profile?->npwp,
        'Bank' => $profile?->bank?->name ? "{$profile->bank->name} — {$profile->bank_account_number} a.n. {$profile->bank_account_name}" : null,
    ];

    $kontak = [
        'No. Telepon' => $profile?->phone,
        'No. WhatsApp' => $profile?->whatsapp,
    ];

    $alamatLengkap = collect([$profile?->address, $profile?->village, $profile?->district, $profile?->city, $profile?->province, $profile?->country])
        ->filter()
        ->implode(', ');

    $alamatDetail = [
        'RT/RW' => ($profile?->rt || $profile?->rw) ? "{$profile?->rt}/{$profile?->rw}" : null,
        'Kode Pos' => $profile?->postal_code,
    ];

    $pekerjaan = [
        'Departemen' => $profile?->department?->name,
        'Divisi' => $profile?->division?->name,
        'Sub Divisi' => $profile?->subDivision?->name,
        'Jabatan' => $profile?->jobPosition?->name,
        'Pangkat' => $profile?->pangkat?->name,
        'Golongan' => $profile?->golongan?->name,
        'Kelas' => $profile?->kelas?->name,
        'Tipe Karyawan' => $profile?->employeeType?->name,
        'Tanggal Bergabung' => $profile?->joining_date ? \Carbon\Carbon::parse($profile->joining_date)->translatedFormat('d F Y') : null,
    ];
@endphp

@php
    $renderField = function (string $label, ?string $value, bool $showBorder = true) {
        $borderStyle = $showBorder ? 'border-bottom:1px solid #f1f5f9;' : '';
        $valueHtml = $value
            ? '<span style="font-size:13.5px;font-weight:600;color:#334155;text-align:right;">' . e($value) . '</span>'
            : '<span style="font-size:12.5px;font-style:italic;color:#64748b;text-align:right;">Belum diisi</span>';

        return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
            . '<span style="font-size:12.5px;color:#64748b;flex-shrink:0;">' . e($label) . '</span>'
            . $valueHtml
            . '</div>';
    };

    $sectionHeader = function (string $icon, string $title) {
        return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
            . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
            . '<i class="fa-solid ' . $icon . '" style="color:#042c6c;font-size:13px;"></i>'
            . '</div>'
            . '<h4 style="font-size:13.5px;font-weight:700;color:#1e293b;margin:0;">' . e($title) . '</h4>'
            . '</div>';
    };
@endphp

{{-- Kartu identitas --}}
<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    <div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:14px;">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $record->name }}" style="flex-shrink:0;width:56px;height:56px;border-radius:50%;object-fit:cover;">
        @else
            <div style="flex-shrink:0;width:56px;height:56px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;">
                {{ $initials ?: '?' }}
            </div>
        @endif
        <div style="min-width:0;flex:1;">
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                <h2 style="font-size:17px;font-weight:800;color:#0f172a;letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
                <span class="badge {{ $record->is_active ? 'badge-approved' : 'badge-cancelled' }}">
                    {{ $record->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>
            <p style="font-size:12.5px;color:#64748b;margin-top:3px;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                @if ($profile?->department?->name)
                    <span style="color:#cbd5e1;">·</span> {{ $profile->department->name }}
                @endif
            </p>
            <div style="display:flex;flex-direction:column;gap:5px;margin-top:10px;">
                <span style="font-size:12px;color:#64748b;"><i class="fa-solid fa-id-badge" style="color:#94a3b8;margin-right:6px;width:12px;"></i>{{ $record->nip ?: 'NIP belum diisi' }}</span>
                <span style="font-size:12px;color:#64748b;"><i class="fa-solid fa-envelope" style="color:#94a3b8;margin-right:6px;width:12px;"></i>{{ $record->email }}</span>
                <span style="font-size:12px;color:#64748b;"><i class="fa-solid fa-building" style="color:#94a3b8;margin-right:6px;width:12px;"></i>{{ $record->company?->name }}</span>
                @if ($roleLabel)
                    <span style="font-size:12px;color:#64748b;"><i class="fa-solid fa-user-shield" style="color:#94a3b8;margin-right:6px;width:12px;"></i>{{ $roleLabel }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    {!! $sectionHeader('fa-id-card', 'Informasi Pribadi') !!}
    @foreach ($pribadi as $label => $value)
        {!! $renderField($label, $value, !$loop->last) !!}
    @endforeach
</div>

<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    {!! $sectionHeader('fa-phone', 'Kontak') !!}
    @foreach ($kontak as $label => $value)
        {!! $renderField($label, $value, !$loop->last) !!}
    @endforeach
</div>

<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    {!! $sectionHeader('fa-location-dot', 'Alamat') !!}
    <p style="font-size:13.5px;font-weight:500;color:#334155;line-height:1.6;margin:0;">
        {{ $alamatLengkap ?: 'Alamat belum diisi' }}
    </p>
    @if (collect($alamatDetail)->filter()->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:6px 24px;margin-top:12px;">
            @foreach ($alamatDetail as $label => $value)
                @if ($value)
                    <span style="font-size:12.5px;color:#64748b;"><span style="color:#64748b;">{{ $label }}:</span> <span style="font-weight:600;color:#334155;">{{ $value }}</span></span>
                @endif
            @endforeach
        </div>
    @endif
</div>

<div style="background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06);padding:18px;margin-bottom:16px;">
    {!! $sectionHeader('fa-briefcase', 'Informasi Pekerjaan') !!}
    @foreach ($pekerjaan as $label => $value)
        {!! $renderField($label, $value, !$loop->last) !!}
    @endforeach
</div>

</div>
