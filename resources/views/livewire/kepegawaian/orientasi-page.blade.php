<div>
    <x-page-header title="Orientasi Saya" subtitle="Ajukan penilaian masa orientasi & unggah bukti kegiatan" />

    @php
        $current = $this->current;
        $period = $this->period;
        $pastPeriods = $this->pastPeriods;
        $windowOpensAt = $this->windowOpensAt;
    @endphp

    @if ($period)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:12px;padding:12px 16px;margin-bottom:8px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center;">
            <span style="font-size:11.5px;font-weight:700;color:var(--info-soft-text);text-transform:uppercase;letter-spacing:.04em;">Periode Orientasi Aktif</span>
            <span style="font-size:13px;color:var(--text-body);">{{ $period->start_date?->format('d M Y') }} &ndash; {{ $period->end_date?->format('d M Y') }} ({{ $period->duration_months }} bln)</span>
            @php $sisa = $period->end_date ? now()->startOfDay()->diffInDays($period->end_date, false) : null; @endphp
            @if ($sisa !== null)
                <span style="font-size:12px;font-weight:600;color:{{ $sisa < 0 ? 'var(--danger-text)' : ($sisa <= 14 ? '#b45309' : 'var(--text-muted)') }};">
                    {{ $sisa < 0 ? 'Berakhir ' . abs($sisa) . ' hari lalu' : $sisa . ' hari lagi' }}
                </span>
            @endif
        </div>
    @else
        <div style="background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:12.5px;color:var(--warn-soft-text);">
            Periode orientasimu belum tercatat. Pengajuan penilaian belum bisa dibuat — hubungi HR/HC.
        </div>
    @endif

    @if ($pastPeriods->isNotEmpty())
        <details style="margin-bottom:16px;">
            <summary style="cursor:pointer;font-size:11.5px;font-weight:600;color:var(--text-muted);padding:4px 0;">Periode orientasi lama ({{ $pastPeriods->count() }}) — nonaktif</summary>
            <div style="display:flex;flex-direction:column;gap:4px;margin-top:6px;">
                @foreach ($pastPeriods as $pp)
                    <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:8px;padding:7px 12px;font-size:12px;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:4px 12px;">
                        <span>{{ $pp->start_date?->format('d M Y') }} &ndash; {{ $pp->end_date?->format('d M Y') }} ({{ $pp->duration_months }} bln)</span>
                        <span style="color:var(--text-faint);text-transform:capitalize;">{{ $pp->source }}</span>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    @php
        $statusLabels = [
            'draft' => ['Draf', 'var(--text-muted)', 'rgba(148,163,184,0.15)'],
            'diajukan' => ['Diajukan', '#b45309', 'rgba(245,158,11,0.12)'],
            'dinilai' => ['Dinilai', '#1d4ed8', 'rgba(37,99,235,0.12)'],
            'selesai' => ['Selesai', '#047C54', 'rgba(4,124,84,0.12)'],
            'dibatalkan' => ['Dibatalkan', '#dc2626', 'rgba(220,38,38,0.12)'],
        ];
        $decisionLabels = [
            'lanjut_kontrak' => ['Direkomendasikan untuk Kontrak Kerja', '#047C54'],
            'tidak_dilanjutkan' => ['Tidak Dilanjutkan', '#dc2626'],
        ];
    @endphp

    @if (! $current)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">Anda belum mengajukan penilaian orientasi.</p>
            <button type="button" wire:click="startApplication" class="btn-primary" @disabled(! $this->canStartNew)>
                <i class="fa-solid fa-plus"></i> Mulai Pengajuan Orientasi
            </button>
            @if ($period && ! $this->canStartNew && $windowOpensAt)
                <p style="font-size:11.5px;color:#b45309;margin:10px 0 0;">Pengajuan dibuka mulai {{ $windowOpensAt->format('d M Y') }} ({{ $this->leadDays }} hari sebelum periode berakhir).</p>
            @endif
        </div>
    @else
        @php [$sLabel, $sColor, $sBg] = $statusLabels[$current->status] ?? [$current->status, 'var(--text-muted)', 'transparent']; @endphp
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
            <div style="margin-bottom:14px;">
                <span style="display:inline-block;padding:4px 12px;border-radius:99px;font-size:11.5px;font-weight:700;background:{{ $sBg }};color:{{ $sColor }};">{{ $sLabel }}</span>
            </div>

            @if ($current->status === 'draft' && $current->decision_note)
                <div style="border:1px solid var(--warn-soft-border);background:var(--warn-soft-bg);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;font-weight:700;color:var(--warn-soft-text);margin:0 0 3px;">Penilai meminta revisi bukti</p>
                    <p style="font-size:12.5px;color:var(--text-body);margin:0;">{{ $current->decision_note }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:6px 0 0;">Perbaiki/lengkapi bukti di bawah lalu kirim ulang.</p>
                </div>
            @endif

            @if (in_array($current->status, ['dinilai', 'selesai']) && $current->decision)
                @php [$dLabel, $dColor] = $decisionLabels[$current->decision] ?? [$current->decision, 'var(--text-muted)']; @endphp
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Keputusan Penilai</p>
                    <p style="font-size:14px;font-weight:700;color:{{ $dColor }};margin:0;">{{ $dLabel }}</p>
                    @if ($current->decision_note)
                        <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">{{ $current->decision_note }}</p>
                    @endif
                    @if ($current->status === 'dinilai')
                        <p style="font-size:11.5px;color:#b45309;margin:8px 0 0;">Menunggu tindak lanjut HR ({{ $current->decision === 'tidak_dilanjutkan' ? 'pencatatan tanggal efektif berakhir' : 'pembuatan kontrak' }}).</p>
                    @endif
                </div>
            @endif

            @if ($current->status !== 'draft' && $current->presentation_proposed_at)
                <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 3px;">Jadwal Presentasi Kegiatan</p>
                    @php $slot = $current->presentation_confirmed_at ?? $current->presentation_proposed_at; @endphp
                    @if ($current->presentationFullyApproved())
                        <p style="font-size:14px;font-weight:700;color:var(--brand-success,#047C54);margin:0;">{{ $slot->translatedFormat('l, d F Y H:i') }}</p>
                        @if ($current->presentation_note)
                            <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">{{ $current->presentation_note }}</p>
                        @endif
                    @else
                        <p style="font-size:13px;color:#b45309;margin:0;">{{ $slot->translatedFormat('l, d F Y H:i') }} — menunggu persetujuan HR &amp; jabatan pendukung ({{ $current->presentation_hr_at ? 'HR ✓' : 'HR ○' }}, {{ $current->presentation_support_at ? 'pendukung ✓' : 'pendukung ○' }}).</p>
                    @endif
                </div>
            @endif

            {{-- Bukti kegiatan --}}
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Bukti Kegiatan Orientasi</p>
            @if (empty($current->attachments))
                <p style="font-size:12px;color:var(--text-faint);margin:0 0 10px;">Belum ada bukti diunggah.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;">
                    @foreach ($current->attachments as $i => $att)
                        <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-soft);border-radius:8px;padding:7px 10px;">
                            <i class="fa-solid fa-paperclip" style="color:var(--text-faint);font-size:12px;"></i>
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($att['path']) }}" target="_blank" rel="noopener" style="flex:1;min-width:0;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $att['name'] ?? 'Lampiran' }}</a>
                            @if ($current->status === 'draft')
                                <button type="button" wire:click="removeBukti({{ $i }})" class="btn-ghost" style="padding:3px 7px;font-size:11px;color:#dc2626;"><i class="fa-solid fa-xmark"></i></button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($current->status === 'draft')
                <p style="font-size:11px;color:var(--text-faint);margin:0 0 6px;">Format: PDF atau PowerPoint (.pdf, .ppt, .pptx), maks 20 MB.</p>
                <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:14px;">
                    <input type="file" wire:model="buktiFile" class="form-input" style="flex:1;" accept=".pdf,.ppt,.pptx">
                    <button type="button" wire:click="uploadBukti" wire:loading.attr="disabled" wire:target="buktiFile,uploadBukti" class="btn-ghost" style="white-space:nowrap;"><i class="fa-solid fa-upload"></i> Unggah</button>
                </div>
                <div wire:loading wire:target="buktiFile" style="font-size:11px;color:var(--text-muted);margin-top:-8px;margin-bottom:10px;">Mengunggah...</div>
                @error('buktiFile') <p style="font-size:11px;color:#dc2626;margin-top:-6px;margin-bottom:10px;">{{ $message }}</p> @enderror

                {{-- Usulan jadwal presentasi kegiatan --}}
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:14px 0 4px;">Usulan Jadwal Presentasi Kegiatan</p>
                <p style="font-size:11px;color:var(--text-faint);margin:0 0 8px;">Wajib. Jadwal final ditetapkan HR (bisa sama atau berbeda).</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;margin-bottom:14px;">
                    <div style="min-width:150px;">
                        <input type="date" wire:model="presentasiDate" class="form-input">
                        @error('presentasiDate') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                    </div>
                    <div style="min-width:120px;">
                        <input type="time" wire:model="presentasiTime" class="form-input">
                        @error('presentasiTime') <p style="font-size:11px;color:#dc2626;margin-top:3px;">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="savePresentasi" class="btn-ghost" style="white-space:nowrap;"><i class="fa-solid fa-calendar-check"></i> Simpan Usulan</button>
                </div>
                @if ($current->presentation_proposed_at)
                    <p style="font-size:11.5px;color:var(--brand-success,#047C54);margin:-6px 0 12px;">Usulan tersimpan: {{ $current->presentation_proposed_at->translatedFormat('l, d F Y H:i') }}</p>
                @endif

                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;">Batalkan</button>
                    <button type="button" wire:click="submit" wire:confirm="Kirim pengajuan orientasi untuk dinilai atasan?" class="btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan
                    </button>
                </div>
            @elseif ($current->status === 'diajukan')
                <div style="display:flex;justify-content:flex-end;">
                    <button type="button" wire:click="cancel" wire:confirm="Batalkan pengajuan ini?" class="btn-ghost" style="color:#dc2626;font-size:12px;">Batalkan Pengajuan</button>
                </div>
            @elseif ($this->canReopen)
                <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--border-soft);padding-top:12px;margin-top:4px;">
                    <button type="button" wire:click="reopenApplication" wire:confirm="Buka lagi pengajuan ini untuk diajukan ulang?" class="btn-primary" style="font-size:12.5px;">
                        <i class="fa-solid fa-rotate-left"></i> Ajukan Ulang
                    </button>
                </div>
            @elseif ($this->canStartNew)
                <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--border-soft);padding-top:12px;margin-top:4px;">
                    <button type="button" wire:click="startApplication" wire:confirm="Mulai pengajuan penilaian orientasi baru?" class="btn-primary" style="font-size:12.5px;">
                        <i class="fa-solid fa-plus"></i> Ajukan Lagi
                    </button>
                </div>
            @endif
        </div>

        @if ($current->status === 'selesai' && $current->assessments->isNotEmpty())
            <div style="margin-top:16px;background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;overflow-x:auto;">
                <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Rincian Skor per Bulan</p>
                <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:360px;">
                    @foreach ($current->assessments as $a)
                        @php $vals = $a->scores->pluck('score')->filter(fn ($v) => $v !== null); @endphp
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:6px 4px;">{{ $a->label }}</td>
                            @foreach ($a->scores as $sc)
                                <td style="padding:6px 6px;text-align:center;white-space:nowrap;">{{ \Carbon\Carbon::parse($sc->period_month)->translatedFormat('M') }}: <strong>{{ $sc->score ?? '–' }}</strong></td>
                            @endforeach
                            <td style="padding:6px 4px;text-align:right;font-weight:700;">{{ $vals->count() ? number_format($vals->avg(), 2) : '–' }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif

    {{-- Riwayat pengajuan periode LAMA — selalu di-collapse --}}
    @php
        $past = $this->submissions->filter(fn ($s) => $s->id !== optional($current)->id)->values();
    @endphp
    @if ($past->isNotEmpty())
        <details style="margin-top:20px;">
            <summary style="cursor:pointer;font-size:11.5px;font-weight:600;color:var(--text-muted);padding:4px 0;">Riwayat pengajuan lama ({{ $past->count() }})</summary>
            <div class="pwa-list" style="margin-top:6px;">
                @foreach ($past as $s)
                    @php [$rl, $rc, $rb] = $statusLabels[$s->status] ?? [$s->status, 'var(--text-muted)', 'transparent']; @endphp
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-user-graduate"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $s->submitted_at?->format('d M Y') ?? 'Draft' }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $s->decision ? ($decisionLabels[$s->decision][0] ?? $s->decision) : '—' }}</p>
                        </div>
                        <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:600;background:{{ $rb }};color:{{ $rc }};">{{ $rl }}</span>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
