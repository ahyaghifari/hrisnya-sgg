@php($p = $preview)

@if (! ($p['ok'] ?? false))
    <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
        {{ $p['message'] ?? 'Data kandidat belum siap untuk dibuatkan akun.' }}
    </div>
@else
    <div class="space-y-4 text-sm">
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">Perusahaan</div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ $p['company'] ?? '—' }}</div>
            </div>
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">Jabatan yang dituju</div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ $p['jabatan'] ?? '—' }}</div>
                @if ($p['divisi'] ?? null)
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p['divisi'] }}</div>
                @endif
            </div>
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">Email akun</div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ $p['email'] }}</div>
                @if ($p['existingUser'] ?? false)
                    <div class="text-xs text-warning-600 dark:text-warning-400">Email ini sudah punya akun — profil &amp; periode orientasi akan diperbarui.</div>
                @endif
            </div>
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">Tipe karyawan</div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ $p['orientationType'] ?? '—' }}</div>
            </div>
            <div class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="text-xs text-gray-500 dark:text-gray-400">Tanggal Bergabung (dari jadwal onboarding)</div>
                @if ($p['onboardingDate'] ?? null)
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $p['onboardingDate'] }}</div>
                @else
                    <div class="font-semibold text-warning-600 dark:text-warning-400">Belum diatur — akan memakai tanggal hari ini</div>
                @endif
            </div>
        </div>

        @if (! empty($p['missing']))
            <div class="rounded-lg bg-warning-50 p-3 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                <div class="font-semibold">Data diri belum lengkap</div>
                <div class="mt-0.5 text-xs">Akun tetap bisa dibuat, tapi kolom ini akan kosong di profil karyawan &amp; perlu dilengkapi manual: {{ implode(', ', $p['missing']) }}.</div>
            </div>
        @endif

        <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">Data diri kandidat</div>
            <table class="w-full">
                @foreach ($p['profile'] as $row)
                    <tr class="border-t border-gray-950/5 dark:border-white/10">
                        <td class="w-1/2 px-3 py-1.5 align-top text-gray-500 dark:text-gray-400">
                            {{ $row['label'] }}@if ($row['required']) <span class="text-danger-500">*</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-gray-900 dark:text-white">
                            @if ($row['value'] !== null)
                                {{ $row['value'] }}
                            @elseif ($row['required'])
                                <span class="text-xs font-medium text-danger-500">belum diisi</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endif
