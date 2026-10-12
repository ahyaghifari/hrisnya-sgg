@php
    $data = $app->data ?? [];
    $scalar = collect($data)->reject(fn ($v) => is_array($v));
    $repeaters = ['pendidikan' => 'Pendidikan', 'pelatihan' => 'Pelatihan & Sertifikat', 'pengalaman' => 'Pengalaman Kerja'];
    $url = fn (string $path) => route('filament.dokter.lamaran.berkas', ['application' => $app->id, 'path' => $path]);
@endphp
{{-- CSS biasa: panel dokter tidak punya theme vite, utility Tailwind kustom tidak ter-compile. --}}
<style>
    .ld { font-size: .875rem; color: #0f172a; }
    .ld h4 { margin: 1.25rem 0 .5rem; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0f766e; }
    .ld dl { margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .75rem 1.5rem; }
    .ld dt { color: #64748b; font-size: .75rem; }
    .ld dd { margin: 0; font-weight: 600; word-break: break-word; }
    .ld ul { margin: 0; padding-left: 1.25rem; }
    .ld li { margin-bottom: .25rem; }
    .ld a { color: #0f766e; font-weight: 600; text-decoration: underline; }
    .ld .muted { color: #64748b; }
</style>
<div class="ld">
    <p style="margin:0;">
        {{ ucfirst($app->jenis) }} · {{ $app->company?->name ?? '-' }}@if ($app->unit_minat) · Unit: {{ $app->unit_minat }}@endif
    </p>

    <h4>Data Pelamar</h4>
    <dl>
        @foreach ($scalar as $key => $val)
            @continue(! filled($val))
            <div>
                <dt>{{ \Illuminate\Support\Str::headline($key) }}</dt>
                <dd>{{ $val }}</dd>
            </div>
        @endforeach
    </dl>

    @foreach ($repeaters as $key => $label)
        @if (! empty($data[$key]))
            <h4>{{ $label }}</h4>
            <ul>
                @foreach ($data[$key] as $row)
                    <li>
                        {{ collect($row)->except('berkas')->filter()->implode(' · ') }}
                        @if (! empty($row['berkas']['path']))
                            — <a href="{{ $url($row['berkas']['path']) }}" target="_blank">{{ $row['berkas']['name'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endforeach

    <h4>Berkas</h4>
    @forelse ($app->files ?? [] as $key => $f)
        <div>{{ \Illuminate\Support\Str::headline(preg_replace('/^d_/', '', $key)) }}:
            <a href="{{ $url($f['path']) }}" target="_blank">{{ $f['name'] }}</a>
        </div>
    @empty
        <p class="muted" style="margin:0;">Belum ada berkas diunggah.</p>
    @endforelse
</div>
