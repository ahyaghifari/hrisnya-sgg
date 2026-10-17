<div>
    <x-page-header title="Perpanjangan Kontrak" subtitle="Pantau pengajuan perpanjangan kontrak karyawan" />

    @php
        $decisionLabels = ['perpanjang' => 'Perpanjang Kontrak', 'tidak_perpanjang' => 'Tidak Diperpanjang'];
    @endphp

    {{-- Daftar HR — disembunyikan saat sedang meninjau 1 pengajuan, & untuk penilai yang
         cuma datang dari notifikasi (openId). --}}
    @if (! $this->openSubmission && ! $reviewerOnly)
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
                        <th style="padding:10px 12px;text-align:center;">Diajukan Perpanjangan</th>
                        <th style="padding:10px 12px;">Keputusan</th>
                        <th style="padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $u)
                        @php
                            $c = $u->employee?->currentContract;
                            $sub = $u->contractSubmissions->first();
                            $sisa = $c?->end_date ? now()->startOfDay()->diffInDays($c->end_date, false) : null;
                            $mark = $this->submissionMark($u);
                        @endphp
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:10px 12px;">
                                <span style="font-weight:600;color:var(--text-heading);">{{ $u->name }}</span>
                                <span style="display:block;font-size:11px;color:var(--text-muted);">NIP {{ $u->nip ?? '-' }}</span>
                            </td>
                            <td style="padding:10px 12px;">{{ $c?->start_date?->format('d M Y') ?? '—' }} &ndash; {{ $c?->end_date?->format('d M Y') ?? '—' }}</td>
                            <td style="padding:10px 12px;color:{{ $sisa !== null && $sisa < 0 ? 'var(--danger-text)' : ($sisa !== null && $sisa <= 30 ? '#b45309' : 'var(--text-muted)') }};">
                                {{ $sisa === null ? '—' : ($sisa < 0 ? abs($sisa) . ' hr lalu' : $sisa . ' hr') }}
                            </td>
                            <td style="padding:10px 12px;text-align:center;">
                                @if ($mark === 'yes')
                                    <i class="fa-solid fa-circle-check" style="color:#047C54;font-size:15px;" title="Sudah mengajukan"></i>
                                @elseif ($mark === 'strip')
                                    <span style="color:var(--text-faint);" title="Belum masuk jendela pengajuan">–</span>
                                @else
                                    <i class="fa-regular fa-circle" style="color:var(--text-faint);font-size:14px;" title="Belum mengajukan"></i>
                                @endif
                            </td>
                            <td style="padding:10px 12px;">{{ $sub?->decision ? ($decisionLabels[$sub->decision] ?? $sub->decision) : '—' }}</td>
                            <td style="padding:10px 12px;text-align:right;">
                                @if ($sub && $sub->status !== 'draft')
                                    <button type="button" wire:click="openReview('{{ $sub->id }}')" class="btn-ghost" style="font-size:11.5px;">Tinjau</button>
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
                                    {{ $s->newContract->start_date?->format('d M Y') }} &ndash; {{ $s->newContract->end_date?->format('d M Y') }}
                                @else — @endif
                            </td>
                            <td style="padding:10px 12px;">{{ $s->decided_at?->format('d M Y') ?? '—' }} <span style="color:var(--text-faint);">{{ $s->decidedBy?->name }}</span></td>
                            <td style="padding:10px 12px;text-align:right;"><button type="button" wire:click="openReview('{{ $s->id }}')" class="btn-ghost" style="font-size:11.5px;">Tinjau</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:20px;text-align:center;color:var(--text-faint);">Belum ada riwayat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $rows->links() }}</div>
    @endif
    @endif {{-- ! $reviewerOnly --}}

    {{-- ── Panel tinjau pengajuan (dulu KontrakDetailPage) ─────────────────────── --}}
    @if ($this->openSubmission)
        @php
            $r = $this->openSubmission;
            $decisionColors = ['perpanjang' => '#047C54', 'tidak_perpanjang' => '#dc2626'];
            $statusLabels = [
                'draft' => ['Draf', 'var(--text-muted)'],
                'diajukan' => ['Diajukan', '#b45309'],
                'dinilai' => ['Menunggu Durasi (HR)', '#b45309'],
                'selesai' => ['Disetujui', '#047C54'],
                'ditolak' => ['Tidak Diperpanjang', '#dc2626'],
                'dibatalkan' => ['Dibatalkan', '#dc2626'],
            ];
            [$sLabel, $sColor] = $statusLabels[$r->status] ?? [$r->status, 'var(--text-muted)'];
            $currentContract = $r->user?->employee?->currentContract ?? $r->employeeContract;
        @endphp
        <div style="margin-bottom:14px;">
            <button type="button" wire:click="closeReview" class="btn-ghost" style="font-size:12px;">
                <i class="fa-solid fa-arrow-left"></i> {{ $reviewerOnly ? 'Kembali ke Beranda' : 'Kembali ke Daftar' }}
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- Info karyawan --}}
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                    <div>
                        <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 2px;">{{ $r->user?->name }}</p>
                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">NIP {{ $r->user?->nip ?? '-' }} &middot; {{ $r->user?->profile?->jobPosition?->name ?? '-' }}</p>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:{{ $sColor }};white-space:nowrap;">{{ $sLabel }}</span>
                </div>
                @if ($currentContract)
                    <p style="font-size:12px;color:var(--text-body);margin:0 0 4px;">
                        <span style="color:var(--text-faint);">Kontrak berjalan:</span>
                        {{ $currentContract->start_date?->format('d M Y') }} &ndash; {{ $currentContract->end_date?->format('d M Y') }}
                    </p>
                @endif
                @if ($r->alasan)
                    <p style="font-size:12.5px;color:var(--text-body);margin:0;"><span style="color:var(--text-faint);">Alasan:</span> {{ $r->alasan }}</p>
                @endif
                @if ($r->submitted_at)
                    <p style="font-size:11px;color:var(--text-faint);margin:8px 0 0;">Diajukan {{ $r->submitted_at->translatedFormat('d F Y H:i') }}</p>
                @endif
            </div>

            {{-- Dokumen --}}
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Dokumen Pendukung</p>
                @forelse ($r->documents as $doc)
                    <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;margin-bottom:6px;">
                        <i class="fa-solid fa-paperclip" style="color:var(--text-faint);font-size:12px;"></i>
                        <span style="flex:0 0 auto;font-size:11.5px;font-weight:600;color:var(--text-body);">{{ $doc->label }}</span>
                        <a href="{{ $doc->url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:11.5px;">{{ $doc->original_name }}</a>
                    </div>
                @empty
                    <p style="font-size:12px;color:var(--text-faint);margin:0;">Tidak ada dokumen.</p>
                @endforelse
            </div>

            {{-- Keputusan (kalau sudah) --}}
            @if (in_array($r->status, ['dinilai', 'selesai', 'ditolak']) && $r->decision)
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai — {{ $r->decidedBy?->name }}</p>
                    <p style="font-size:15px;font-weight:700;color:{{ $decisionColors[$r->decision] ?? 'var(--text-muted)' }};margin:0 0 4px;">{{ $decisionLabels[$r->decision] ?? $r->decision }}</p>
                    @if ($r->decision === 'perpanjang' && $r->newContract)
                        <p style="font-size:12.5px;color:var(--text-body);margin:0 0 4px;">Kontrak baru: <strong>{{ $r->newContract->start_date?->format('d M Y') }}</strong> &ndash; <strong>{{ $r->newContract->end_date?->format('d M Y') }}</strong> ({{ $r->decision_durasi_bulan }} bln)</p>
                    @endif
                    @if ($r->decision_note)<p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $r->decision_note }}</p>@endif
                </div>
            @endif

            {{-- FORM PENILAI --}}
            @if ($this->canReview)
                <div style="background:var(--surface);border:2px solid #1d4ed8;border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Keputusan Perpanjangan Kontrak</p>

                    <div style="margin-bottom:10px;">
                        <label class="form-label">Keputusan</label>
                        <select wire:model.live="reviewDecision" class="form-input">
                            <option value="">-- Pilih Keputusan --</option>
                            @foreach ($this->decisionOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reviewDecision') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($reviewDecision === 'perpanjang')
                        <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 10px;">Durasi perpanjangan ditetapkan HR pada langkah berikutnya.</p>
                    @endif

                    <div style="margin-bottom:12px;">
                        <label class="form-label">Catatan Keputusan (wajib)</label>
                        <textarea wire:model="reviewNote" class="form-input" rows="2"></textarea>
                        @error('reviewNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <button type="button" wire:click="submitReview" wire:confirm="Simpan keputusan ini?" class="btn-primary">Simpan Keputusan</button>
                    </div>

                    {{-- Kembalikan ke karyawan buat lengkapi dokumen --}}
                    <div style="border-top:1px dashed var(--border);margin-top:16px;padding-top:12px;">
                        <label class="form-label">Minta Revisi Dokumen (opsional)</label>
                        <textarea wire:model="revisionNote" class="form-input" rows="2" placeholder="Jelaskan dokumen apa yang perlu diperbaiki/dilengkapi karyawan"></textarea>
                        @error('revisionNote') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                            <button type="button" wire:click="requestRevision" wire:confirm="Kembalikan pengajuan ke karyawan untuk revisi dokumen?" class="btn-ghost" style="font-size:12px;color:#b45309;">
                                <i class="fa-solid fa-rotate-left"></i> Kembalikan untuk Revisi
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- TINDAK LANJUT HR — penetapan durasi (setelah penilai memutuskan perpanjang) --}}
            @if ($this->canFollowUpDuration)
                <div style="background:var(--surface);border:2px solid #b45309;border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Tindak Lanjut HR — Durasi Perpanjangan</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Penilai memutuskan <strong>Perpanjang Kontrak</strong>. Tetapkan durasinya; kontrak baru langsung dibuat.</p>

                    <div style="margin-bottom:10px;max-width:220px;">
                        <label class="form-label">Durasi Perpanjangan (bulan)</label>
                        <input type="number" wire:model.live="reviewDurasi" class="form-input" min="1" max="24">
                        @error('reviewDurasi') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    @if ($this->preview)
                        <p style="font-size:12px;color:var(--text-body);margin:0 0 10px;background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:8px;padding:8px 10px;">
                            Kontrak baru: <strong>{{ $this->preview[0]->format('d M Y') }}</strong> &ndash; <strong>{{ $this->preview[1]->format('d M Y') }}</strong>
                        </p>
                    @endif
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="button" wire:click="submitFollowUpDuration" wire:confirm="Tetapkan durasi & buat kontrak baru?" class="btn-primary">Tetapkan Durasi & Buat Kontrak</button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
