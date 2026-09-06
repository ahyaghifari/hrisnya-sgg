<div>
    <x-page-header title="Perpanjangan Kontrak" subtitle="Pantau pengajuan perpanjangan kontrak karyawan" />

    @php
        $statusLabels = [
            'draft' => ['Draf', '#64748b', 'rgba(100,116,139,0.12)'],
            'diajukan' => ['Diananti Penilai', '#b45309', 'rgba(245,158,11,0.12)'],
            'selesai' => ['Disetujui', '#047C54', 'rgba(4,124,84,0.12)'],
            'ditolak' => ['Tidak Diperpanjang', '#dc2626', 'rgba(220,38,38,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = ['perpanjang' => 'Perpanjang Kontrak', 'tidak_perpanjang' => 'Tidak Diperpanjang'];
    @endphp

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px;">
        @if ($this->canBrowseAllCompanies)
            <div>
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input" style="min-width:200px;">
                    <option value="">Semua</option>
                    @foreach ($this->companyOptions as $id => $nm)<option value="{{ $id }}">{{ $nm }}</option>@endforeach
                </select>
            </div>
        @endif
        <div style="flex:1;min-width:180px;">
            <label class="form-label">Cari</label>
            <input type="text" wire:model.live.debounce.400ms="search" class="form-input" placeholder="Nama / NIP">
        </div>
    </div>

    <div style="display:flex;gap:6px;margin-bottom:14px;">
        <button type="button" wire:click="setTab('aktif')" class="btn-ghost" style="{{ $tab === 'aktif' ? 'background:var(--brand);color:#fff;' : '' }}">Kontrak Aktif</button>
        <button type="button" wire:click="setTab('riwayat')" class="btn-ghost" style="{{ $tab === 'riwayat' ? 'background:var(--brand);color:#fff;' : '' }}">Riwayat Pengajuan</button>
    </div>

    @if ($tab === 'aktif')
        @php $rows = $this->kontrakKaryawan; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:720px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);text-align:left;">
                        <th style="padding:10px 12px;">Karyawan</th>
                        <th style="padding:10px 12px;">Kontrak Berjalan</th>
                        <th style="padding:10px 12px;">Sisa</th>
                        <th style="padding:10px 12px;">Pengajuan Terakhir</th>
                        <th style="padding:10px 12px;">Keputusan</th>
                        <th style="padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $u)
                        @php
                            $c = $u->currentContract;
                            $sub = $u->contractSubmissions->first();
                            $sisa = $c?->contract_end_date ? now()->startOfDay()->diffInDays($c->contract_end_date, false) : null;
                            [$sl, $sc, $sb] = $sub ? ($statusLabels[$sub->status] ?? [$sub->status, 'var(--text-muted)', 'transparent']) : ['—', 'var(--text-faint)', 'transparent'];
                        @endphp
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:10px 12px;">
                                <span style="font-weight:600;color:var(--text-heading);">{{ $u->name }}</span>
                                <span style="display:block;font-size:11px;color:var(--text-muted);">NIP {{ $u->nip ?? '-' }}</span>
                            </td>
                            <td style="padding:10px 12px;">{{ $c?->contract_start_date?->format('d M Y') ?? '—' }} &ndash; {{ $c?->contract_end_date?->format('d M Y') ?? '—' }}</td>
                            <td style="padding:10px 12px;color:{{ $sisa !== null && $sisa < 0 ? 'var(--danger-text)' : ($sisa !== null && $sisa <= 30 ? '#b45309' : 'var(--text-muted)') }};">
                                {{ $sisa === null ? '—' : ($sisa < 0 ? abs($sisa) . ' hr lalu' : $sisa . ' hr') }}
                            </td>
                            <td style="padding:10px 12px;"><span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $sb }};color:{{ $sc }};">{{ $sl }}</span></td>
                            <td style="padding:10px 12px;">{{ $sub?->decision ? ($decisionLabels[$sub->decision] ?? $sub->decision) : '—' }}</td>
                            <td style="padding:10px 12px;text-align:right;">
                                @if ($sub)
                                    <a href="{{ route('kontrak.show', $sub) }}" wire:navigate class="btn-ghost" style="font-size:11.5px;">Detail</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-faint);">Tidak ada karyawan kontrak.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $rows->links() }}</div>
    @else
        @php $rows = $this->riwayat; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:640px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);text-align:left;">
                        <th style="padding:10px 12px;">Karyawan</th>
                        <th style="padding:10px 12px;">Keputusan</th>
                        <th style="padding:10px 12px;">Kontrak Baru</th>
                        <th style="padding:10px 12px;">Diputuskan</th>
                        <th style="padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $s)
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:10px 12px;">
                                <span style="font-weight:600;color:var(--text-heading);">{{ $s->user?->name }}</span>
                                <span style="display:block;font-size:11px;color:var(--text-muted);">NIP {{ $s->user?->nip ?? '-' }}</span>
                            </td>
                            <td style="padding:10px 12px;">{{ $decisionLabels[$s->decision] ?? $s->decision ?? '—' }}</td>
                            <td style="padding:10px 12px;">
                                @if ($s->newContract)
                                    {{ $s->newContract->contract_start_date?->format('d M Y') }} &ndash; {{ $s->newContract->contract_end_date?->format('d M Y') }}
                                @else — @endif
                            </td>
                            <td style="padding:10px 12px;">{{ $s->decided_at?->format('d M Y') ?? '—' }} <span style="color:var(--text-faint);">{{ $s->decidedBy?->name }}</span></td>
                            <td style="padding:10px 12px;text-align:right;"><a href="{{ route('kontrak.show', $s) }}" wire:navigate class="btn-ghost" style="font-size:11.5px;">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:20px;text-align:center;color:var(--text-faint);">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $rows->links() }}</div>
    @endif
</div>
