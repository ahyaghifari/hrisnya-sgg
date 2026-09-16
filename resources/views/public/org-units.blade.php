<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Organisasi - Syifa HRIS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">

    <div class="max-w-5xl mx-auto px-5 py-10">
        <div class="flex items-center gap-3 mb-2">
            <img src="{{ asset('assets/logo-sgg.png') }}" alt="Syifa Global Group" class="w-10 h-10 rounded-lg object-contain">
            <div>
                <h1 class="text-lg font-bold text-slate-900">Unit Organisasi</h1>
                <p class="text-xs text-slate-500">Syifa Global Group — daftar publik Departemen, Divisi &amp; Sub Divisi beserta kode integrasi</p>
            </div>
        </div>

        <p class="text-sm text-slate-500 mb-8 max-w-2xl">
            Kode di bawah ini kunci pemersatu unit organisasi antar sistem (dipakai buat
            sinkronisasi grup Keycloak, mapping departemen lokal, dsb). Data ini publik &amp;
            read-only — kode ditentukan &amp; dikelola di HRIS, tidak berubah kecuali unit
            direstrukturisasi. Versi mesin: <a href="{{ route('public.org-units.json') }}" class="text-primary-600 underline">/publik/unit-organisasi.json</a>.
        </p>

        @forelse ($companies as $c)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm mb-6 overflow-hidden">
                <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 font-semibold text-slate-900">
                    {{ $c['company'] }}
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-5 py-2 font-medium">Unit</th>
                            <th class="px-5 py-2 font-medium">Level</th>
                            <th class="px-5 py-2 font-medium">Kode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($c['departments'] as $dept)
                            <tr class="border-b border-slate-50">
                                <td class="px-5 py-2 font-medium text-slate-900">{{ $dept->name }}</td>
                                <td class="px-5 py-2 text-slate-500">Departemen</td>
                                <td class="px-5 py-2 font-mono text-xs">
                                    @if ($dept->code)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="bg-primary-50 text-primary-700 px-2 py-0.5 rounded">{{ $dept->code }}</span>
                                            <button type="button" class="copy-code-btn text-slate-400 hover:text-primary-600" data-code="{{ $dept->code }}" title="Salin kode">
                                                <span class="copy-icon">⧉</span>
                                            </button>
                                        </span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>

                            @foreach ($dept->divisions as $div)
                                <tr class="border-b border-slate-50">
                                    <td class="px-5 py-2 pl-9 text-slate-700">{{ $div->name }}</td>
                                    <td class="px-5 py-2 text-slate-500">Divisi</td>
                                    <td class="px-5 py-2 font-mono text-xs">
                                        @if ($div->code)
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="bg-primary-50 text-primary-700 px-2 py-0.5 rounded">{{ $div->code }}</span>
                                                <button type="button" class="copy-code-btn text-slate-400 hover:text-primary-600" data-code="{{ $div->code }}" title="Salin kode">
                                                    <span class="copy-icon">⧉</span>
                                                </button>
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>

                                @foreach ($div->subdivisions as $sub)
                                    <tr class="border-b border-slate-50">
                                        <td class="px-5 py-2 pl-14 text-slate-600">{{ $sub->name }}</td>
                                        <td class="px-5 py-2 text-slate-500">Sub Divisi</td>
                                        <td class="px-5 py-2 font-mono text-xs">
                                            @if ($sub->code)
                                                <span class="inline-flex items-center gap-1.5">
                                                    <span class="bg-primary-50 text-primary-700 px-2 py-0.5 rounded">{{ $sub->code }}</span>
                                                    <button type="button" class="copy-code-btn text-slate-400 hover:text-primary-600" data-code="{{ $sub->code }}" title="Salin kode">
                                                        <span class="copy-icon">⧉</span>
                                                    </button>
                                                </span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada data.</p>
        @endforelse
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.copy-code-btn');
            if (! btn) return;

            navigator.clipboard.writeText(btn.dataset.code).then(function () {
                const icon = btn.querySelector('.copy-icon');
                const original = icon.textContent;
                icon.textContent = '✓';
                setTimeout(function () { icon.textContent = original; }, 1200);
            });
        });
    </script>
</body>
</html>
