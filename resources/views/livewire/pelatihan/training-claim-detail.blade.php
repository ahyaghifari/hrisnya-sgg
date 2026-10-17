<div>
<x-page-header title="Detail Pelatihan Terlaksana" :back="route('pelatihan.klaim')" />

@php
    $statusBadge = ['approved' => 'badge-approved', 'pending' => 'badge-pending', 'rejected' => 'badge-rejected'][$record->status] ?? 'badge-cancelled';
    $statusLabel = ['approved' => 'Disetujui', 'pending' => 'Menunggu Persetujuan', 'rejected' => 'Ditolak'][$record->status] ?? $record->status;
    $minutes = \App\Models\LMS\TrainingClaim::minutesOf($record->sessions);
    $perJp = \App\Models\LMS\TrainingClaim::minutesPerJp();
    $suggestedJp = \App\Models\LMS\TrainingClaim::jpFor($minutes);
    $hoursOf = fn (int $m) => intdiv($m, 60) . ' jam ' . ($m % 60) . ' menit';

    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $certUrl = $disk->url($record->certificate_path);
    $certExt = strtolower(pathinfo($record->certificate_path, PATHINFO_EXTENSION));
    $certIsPdf = $certExt === 'pdf';
    $certIsImage = in_array($certExt, ['jpg', 'jpeg', 'png'], true);
    $certName = basename($record->certificate_path);

    $initials = collect(preg_split('/\s+/', trim($record->user->name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

<style>
    .tcd-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (min-width: 1024px) { .tcd-grid { grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); } }
    .tcd-card { background: var(--surface); border-radius: 16px; box-shadow: var(--shadow-card); padding: 18px; }
    .tcd-title { display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0 0 12px; }
    .tcd-title i { width: 30px; height: 30px; border-radius: 9px; background: rgba(4,44,108,0.08); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
    .tcd-row { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px; }
    .tcd-row:last-child { border-bottom: 0; }
    .tcd-row span:first-child { color: var(--text-muted); font-size: 12.5px; flex-shrink: 0; }
    .tcd-row span:last-child { font-weight: 600; color: var(--text-body); text-align: right; }
    /* Sertifikat = fokus utama reviewer: bingkai besar dengan header & aksi sendiri. */
    .tcd-cert { padding: 0; overflow: hidden; border: 1.5px solid var(--brand); }
    .tcd-cert-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 18px; background: linear-gradient(130deg, #115e59 0%, #0d9488 100%); color: #fff; }
    .tcd-cert-head h3 { margin: 0; font-size: 14px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
    .tcd-cert-head small { display: block; font-size: 11.5px; font-weight: 500; opacity: 0.85; margin-top: 2px; word-break: break-all; }
    .tcd-cert-actions { display: flex; gap: 8px; }
    .tcd-cert-actions a { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; background: rgba(255,255,255,0.16); color: #fff; font-size: 12px; font-weight: 600; text-decoration: none; }
    .tcd-cert-actions a:hover { background: rgba(255,255,255,0.28); }
    .tcd-cert-body { background: var(--surface-alt); }
    .tcd-cert-body iframe { display: block; width: 100%; height: 640px; border: 0; background: #fff; }
    .tcd-cert-body img { display: block; max-width: 100%; max-height: 640px; margin: 0 auto; padding: 12px; object-fit: contain; }
    .tcd-cert-empty { padding: 48px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
    @media (max-width: 767.98px) { .tcd-cert-body iframe { height: 420px; } }
    .tcd-avatar { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(130deg, #115e59 0%, #0d9488 100%); color: #fff; font-weight: 800; font-size: 15px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .tcd-jp { text-align: center; padding: 14px; border-radius: 12px; background: var(--surface-alt); margin-bottom: 8px; }
    .tcd-jp strong { display: block; font-size: 30px; line-height: 1.1; color: var(--text-heading); font-weight: 800; }
    .tcd-jp span { font-size: 12px; color: var(--text-muted); }
    .tcd-session { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border-soft); }
    .tcd-session:last-child { border-bottom: 0; }
    .tcd-date { width: 44px; flex-shrink: 0; text-align: center; border-radius: 10px; background: var(--surface-alt); padding: 5px 0; line-height: 1.15; }
    .tcd-date b { display: block; font-size: 16px; color: var(--text-heading); }
    .tcd-date small { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
</style>

<x-approval-timeline :timeline="$this->timeline" />

@if ($this->canApprove)
    <div class="tcd-card" style="margin-bottom:16px;">
        <h3 class="tcd-title"><i class="fa-solid fa-gavel"></i> Aksi Persetujuan</h3>

        @if ($this->canEditJp)
            <div style="margin-bottom:14px;max-width:220px;">
                <label class="form-label">JP Final</label>
                <input type="number" min="1" class="form-input" wire:model="jp">
                <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Saran sistem {{ $suggestedJp }} JP = {{ $perJp }} menit.</p>
                @error('jp') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($pendingAction)
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label class="form-label">Catatan <span style="color:var(--danger-text);">*</span></label>
                <textarea class="form-input" rows="3" placeholder="Tambahkan catatan..." wire:model="note"></textarea>
                @error('note') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror

                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmAction" class="btn-primary">Konfirmasi</button>
                    <button type="button" wire:click="cancelChoice" class="btn-ghost">Batal</button>
                </div>
            </div>
        @else
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="button" wire:click="chooseAction('approve')" class="btn-primary"><i class="fa-solid fa-check"></i> Setujui</button>
                <button type="button" wire:click="chooseAction('reject')" class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-soft-border);"><i class="fa-solid fa-xmark"></i> Tolak</button>
            </div>
        @endif
    </div>
@endif

<div class="tcd-grid">
    {{-- Kiri: sertifikat (fokus reviewer) --}}
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
        <div class="tcd-card tcd-cert">
            <div class="tcd-cert-head">
                <div style="min-width:0;">
                    <h3><i class="fa-solid fa-certificate"></i> Sertifikat Pelatihan</h3>
                    <small>{{ $certName }}</small>
                </div>
                <div class="tcd-cert-actions">
                    <a href="{{ $certUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> Buka</a>
                    <a href="{{ $certUrl }}" download="{{ $certName }}"><i class="fa-solid fa-download"></i> Unduh</a>
                </div>
            </div>
            <div class="tcd-cert-body">
                @if (! $disk->exists($record->certificate_path))
                    <div class="tcd-cert-empty"><i class="fa-solid fa-file-circle-xmark" style="font-size:28px;display:block;margin-bottom:8px;"></i>File sertifikat tidak ditemukan.</div>
                @elseif ($certIsPdf)
                    <iframe src="{{ $certUrl }}#view=FitH" title="Sertifikat {{ $record->title }}"></iframe>
                @elseif ($certIsImage)
                    <a href="{{ $certUrl }}" target="_blank" rel="noopener"><img src="{{ $certUrl }}" alt="Sertifikat {{ $record->title }}"></a>
                @else
                    <div class="tcd-cert-empty">Pratinjau tidak tersedia untuk tipe file ini. Gunakan tombol Buka atau Unduh.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Kanan: pemohon, ringkasan pelatihan & JP --}}
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0;">
        <div class="tcd-card">
            <h3 class="tcd-title"><i class="fa-solid fa-user"></i> Pemohon</h3>
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="tcd-avatar">{{ $initials }}</span>
                <div style="min-width:0;">
                    <div style="font-size:14.5px;font-weight:700;color:var(--text-heading);">{{ $record->user->name }}</div>
                    <div style="font-size:12px;color:var(--text-muted);">NIP {{ $record->user->nip ?? '-' }}</div>
                </div>
            </div>
            <div class="tcd-row" style="margin-top:10px;"><span>Diajukan</span><span>{{ $record->created_at?->translatedFormat('j F Y, H:i') }}</span></div>
        </div>

        <div class="tcd-card">
            <h3 class="tcd-title"><i class="fa-solid fa-graduation-cap"></i> Detail Pelatihan</h3>
            <div style="font-size:15px;font-weight:800;color:var(--text-heading);line-height:1.3;margin-bottom:4px;">{{ $record->title }}</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">{{ $record->organizer ?: 'Penyelenggara tidak diisi' }}</div>

            <div class="tcd-jp">
                <strong>{{ $record->jp ?? '-' }} JP</strong>
                <span>{{ $record->jp ? '≈ ' . number_format($record->jp * $perJp / 60, 1, ',', '.') . ' jam (1 JP = ' . $perJp . ' menit)' : 'Belum ditetapkan' }}</span>
            </div>
            @if ($record->jp !== $suggestedJp)
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 8px;text-align:center;">Saran sistem dari jam yang diinput: {{ $suggestedJp }} JP</p>
            @endif

            <div class="tcd-row"><span>Total jam diinput</span><span>{{ $hoursOf($minutes) }}</span></div>
            <div class="tcd-row"><span>Status</span><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></div>
        </div>
    </div>

    {{-- Jam pelaksanaan: selebar 2 kolom di bawah sertifikat & ringkasan --}}
    <div class="tcd-card" style="grid-column:1 / -1;">
        <h3 class="tcd-title" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:10px;"><i class="fa-solid fa-clock"></i> Jam Pelaksanaan</span>
            @if ($this->canEdit && ! $editing)
                <button type="button" wire:click="startEdit" class="btn-ghost" style="padding:4px 12px;font-size:11.5px;"><i class="fa-solid fa-pen"></i> Ubah</button>
            @endif
        </h3>

        @if ($editing)
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach ($sessionsForm as $i => $form)
                    <div wire:key="edit-session-{{ $i }}" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                        <div style="flex:1;min-width:140px;">
                            <input type="date" class="form-input" wire:model="sessionsForm.{{ $i }}.date">
                            <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Tanggal</p>
                            @error("sessionsForm.$i.date") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="flex:1;min-width:110px;">
                            <input type="time" class="form-input" wire:model="sessionsForm.{{ $i }}.start_time">
                            <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Jam Mulai</p>
                            @error("sessionsForm.$i.start_time") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                        </div>
                        <div style="flex:1;min-width:110px;">
                            <input type="time" class="form-input" wire:model="sessionsForm.{{ $i }}.end_time">
                            <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Jam Selesai</p>
                            @error("sessionsForm.$i.end_time") <p style="color:var(--danger-text);font-size:12px;margin-top:2px;">{{ $message }}</p> @enderror
                        </div>
                        @if (count($sessionsForm) > 1)
                            <button type="button" class="btn-ghost" wire:click="removeSession({{ $i }})" title="Hapus" style="padding:8px 10px;"><i class="fa-solid fa-trash"></i></button>
                        @endif
                    </div>
                @endforeach
            </div>
            @error('sessionsForm') <p style="color:var(--danger-text);font-size:12px;margin-top:8px;">{{ $message }}</p> @enderror
            <button type="button" class="btn-ghost" wire:click="addSession" style="margin-top:12px;"><i class="fa-solid fa-plus" style="font-size:11px;"></i> Tambah Hari</button>

            @if ($record->status === 'approved')
                <div style="margin-top:14px;max-width:220px;">
                    <label class="form-label">JP Final</label>
                    <input type="number" min="1" class="form-input" wire:model="jp">
                    <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Sudah disetujui, jadi JP diisi manual. Laporan Pelatihan ikut berubah.</p>
                    @error('jp') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            @else
                <p style="font-size:11.5px;color:var(--text-muted);margin:12px 0 0;">Setelah disimpan, JP disegarkan ke saran sistem dari jam baru. Koreksi lagi di kotak Aksi Persetujuan bila perlu.</p>
            @endif

            <div style="display:flex;gap:10px;margin-top:14px;">
                <button type="button" wire:click="saveEdit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveEdit"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                <button type="button" wire:click="cancelEdit" class="btn-ghost">Batal</button>
            </div>
        @else
        @foreach ($record->sessions as $s)
            <div class="tcd-session">
                <div class="tcd-date"><b>{{ $s->date->format('d') }}</b><small>{{ $s->date->translatedFormat('M Y') }}</small></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-body);">{{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }}</div>
                    <div style="font-size:11.5px;color:var(--text-muted);">{{ $s->date->translatedFormat('l, j F Y') }}</div>
                </div>
                <span class="badge" style="background:var(--surface-muted);color:var(--text-muted);white-space:nowrap;">{{ $hoursOf(\App\Models\LMS\TrainingClaim::minutesOf([$s])) }}</span>
            </div>
        @endforeach
        <div class="tcd-row" style="margin-top:6px;border-top:1.5px solid var(--border);">
            <span>Total {{ $record->sessions->count() }} hari</span><span>{{ $hoursOf($minutes) }}</span>
        </div>
        @endif
    </div>

    <x-approval-history-list :histories="$record->approvalHistories" style="grid-column:1 / -1;" />
</div>
</div>
