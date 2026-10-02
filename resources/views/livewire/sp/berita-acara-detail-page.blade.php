@use('App\Enum\Sp\SpReportStatus', 'S')
<div>
<x-page-header title="Detail Berita Acara" :back="route('sp')" />

@php
    $status = $record->status;
    $editable = $this->canManage;
    $steps = [S::DRAFT, S::MENUNGGU_KLARIFIKASI, S::KLARIFIKASI_DIAJUKAN, S::MENUNGGU_TTD, S::SELESAI];
    $currentIndex = array_search($status, $steps, true);
    $lanjutSp = $record->keputusan === \App\Enum\Sp\SpReportDecision::LANJUT_SP;
@endphp

<div class="card-surface" style="margin-bottom:16px;">
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
        @foreach ($steps as $i => $step)
            <span class="badge" style="{{ $i === $currentIndex ? 'background:var(--brand);color:#fff;' : ($i < $currentIndex ? 'background:#dcfce7;color:#15803d;' : 'background:var(--surface-alt);color:var(--text-muted);') }}">
                {{ $i + 1 }}. {{ $step->getLabel() }}
            </span>
        @endforeach
        {{-- Langkah 2 alur SP: tampil setelah BA diputuskan lanjut SP (form ada di bagian Keputusan). --}}
        @if ($lanjutSp)
            <span class="badge" style="{{ $record->sp_id ? 'background:#dcfce7;color:#15803d;' : 'background:var(--brand);color:#fff;' }}">
                Langkah 2: Surat Peringatan
            </span>
        @endif
    </div>
    <p style="font-size:13px;margin:0;"><strong>{{ $record->user?->name }}</strong>
        <span style="color:var(--text-muted);">&middot; dibuat oleh {{ $record->createdBy?->name }} &middot; mengetahui {{ $record->knowsBy?->name }}</span></p>
    @if ($record->keputusan)
        <p style="font-size:13px;margin:6px 0 0;font-weight:700;">Keputusan: {{ $record->keputusan->getLabel() }}</p>
    @endif
</div>

{{-- 1. Dasar & kejadian: bisa diedit hanya saat draft --}}
<div class="form-card">
    <div class="section-head">
        <div class="section-icon"><i class="fa-solid fa-file-lines" style="color:var(--brand);font-size:12px;"></i></div>
        <span class="section-title">Dasar &amp; Kejadian</span>
    </div>
    @if ($status === S::DRAFT && $editable)
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label class="form-label">Nomor</label>
                <input type="text" wire:model="nomor" class="form-input">
            </div>
            @foreach ([['dasar_klarifikasi', 'Dasar Klarifikasi'], ['kejadian', 'Kejadian']] as [$field, $label])
                <div>
                    <label class="form-label">{{ $label }} <span style="color:var(--danger-text);">*</span></label>
                    <div wire:ignore>
                        <input type="hidden" id="trix-ba-d-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                        <trix-editor input="trix-ba-d-{{ $field }}" style="min-height:90px;"></trix-editor>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
            <button type="button" wire:click="saveDraft" class="btn-secondary">Simpan Draft</button>
            <button type="button" wire:click="requestClarification" wire:confirm="Minta klarifikasi dari karyawan sekarang? Dasar & kejadian tidak bisa diubah lagi." class="btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Minta Klarifikasi Karyawan
            </button>
        </div>
    @else
        <p class="form-label">Nomor</p><p style="margin:0 0 12px;">{{ $record->nomor ?? '-' }}</p>
        <p class="form-label">Dasar Klarifikasi</p><div class="prose" style="margin-bottom:12px;">{!! $record->dasar_klarifikasi !!}</div>
        <p class="form-label">Kejadian</p><div class="prose">{!! $record->kejadian !!}</div>
    @endif
</div>

{{-- 2. Klarifikasi karyawan (apa adanya) --}}
@if ($status !== S::DRAFT)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-comment-dots" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Klarifikasi Karyawan</span>
        </div>
        @if ($status === S::MENUNGGU_KLARIFIKASI && $record->klarifikasi_diisi_hc && $editable)
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px;">{{ $record->user?->name }} meminta HC yang mengisikan klarifikasinya. Tulis apa adanya sesuai keterangan karyawan.</p>
            <textarea wire:model="klarifikasi_karyawan" rows="7" class="form-input"></textarea>
            <div style="margin-top:12px;">
                <button type="button" wire:click="fillClarification" wire:confirm="Simpan klarifikasi atas nama karyawan dan lanjut ke analisis?" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Klarifikasi Karyawan
                </button>
            </div>
        @elseif ($status === S::MENUNGGU_KLARIFIKASI)
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $record->klarifikasi_diisi_hc ? "{$record->user?->name} meminta HC yang mengisikan klarifikasi." : "Menunggu {$record->user?->name} mengisi dan mengajukan klarifikasi." }}
            </p>
        @else
            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 8px;">
                {{ $record->klarifikasi_diisi_hc ? 'Diisikan HC atas permintaan karyawan' : 'Diajukan karyawan' }} {{ $record->klarifikasi_diajukan_at?->translatedFormat('d M Y H:i') }} &middot; ditampilkan apa adanya
            </p>
            <div class="prose">{!! nl2br(e($record->subject_clarification)) !!}</div>
        @endif

        @foreach ($record->riwayat_klarifikasi ?? [] as $i => $old)
            <details style="margin-top:12px;">
                <summary style="font-size:12.5px;cursor:pointer;">Riwayat pengajuan ulang #{{ $i + 1 }} &middot; {{ $old['at'] ?? '' }}</summary>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Alasan mengajukan ulang</p><p style="font-size:12.5px;margin:0;">{{ $old['alasan_ajukan_ulang'] ?? '' }}</p>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Klarifikasi saat itu</p><p style="font-size:12.5px;margin:0;">{!! nl2br(e($old['klarifikasi'] ?? '')) !!}</p>
                <p style="font-size:12px;margin:8px 0 2px;font-weight:700;">Parafrase HC saat itu</p><p style="font-size:12.5px;margin:0;">{!! nl2br(e($old['parafrase'] ?? '')) !!}</p>
            </details>
        @endforeach
    </div>
@endif

{{-- 3. Parafrase, analisis, kesimpulan, tindak lanjut --}}
@if (in_array($status, [S::KLARIFIKASI_DIAJUKAN, S::MENUNGGU_TTD, S::SELESAI], true))
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Parafrase &amp; Analisis HC</span>
        </div>
        @if ($status === S::KLARIFIKASI_DIAJUKAN && $editable)
            <div style="display:flex;flex-direction:column;gap:16px;">
                @foreach ([['klarifikasi_parafrase', 'Parafrase Klarifikasi'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
                    <div>
                        <label class="form-label">{{ $label }} <span style="color:var(--danger-text);">*</span></label>
                        <div wire:ignore>
                            <input type="hidden" id="trix-ba-a-{{ $field }}" wire:model="{{ $field }}" value="{{ $$field }}">
                            <trix-editor input="trix-ba-a-{{ $field }}" style="min-height:90px;"></trix-editor>
                        </div>
                    </div>
                @endforeach
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                <button type="button" wire:click="saveAnalysis" class="btn-secondary">Simpan</button>
                <button type="button" wire:click="sendForSigning" wire:confirm="Kirim untuk ditandatangani? Isi akan dibekukan, karyawan menandatangani lebih dulu." class="btn-primary">
                    <i class="fa-solid fa-signature"></i> Kirim untuk Tanda Tangan
                </button>
            </div>
        @else
            @foreach ([['klarifikasi_parafrase', 'Parafrase Klarifikasi'], ['analysis', 'Analisis'], ['kesimpulan', 'Kesimpulan'], ['followup_plan', 'Rencana Tindak Lanjut']] as [$field, $label])
                <p class="form-label">{{ $label }}</p><div class="prose" style="margin-bottom:12px;">{!! $record->{$field} !!}</div>
            @endforeach
        @endif
    </div>
@endif

{{-- 4. Tanda tangan --}}
@if (in_array($status, [S::MENUNGGU_TTD, S::SELESAI], true))
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-signature" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Tanda Tangan</span>
        </div>
        @foreach ($record->expectedSignerRoles() as $role => $label)
            @php $sig = $this->signatures->get($role); @endphp
            <p style="font-size:13px;margin:0 0 6px;">
                <i class="fa-solid {{ $sig ? 'fa-circle-check' : 'fa-clock' }}" style="color:{{ $sig ? '#16a34a' : 'var(--text-muted)' }};"></i>
                {{ $label }}:
                @if ($sig) {{ $sig->signer_name }} <span style="color:var(--text-muted);">({{ $sig->signed_at->translatedFormat('d M Y H:i') }})</span>
                @else <span style="color:var(--text-muted);">belum menandatangani</span> @endif
            </p>
        @endforeach
        @if ($status === S::MENUNGGU_TTD)
            <p style="font-size:12px;color:var(--text-muted);margin:8px 0 0;">Karyawan menandatangani lebih dulu, lalu pembuat dan yang mengetahui lewat halaman Tanda Tangan.</p>
        @endif
    </div>
@endif

{{-- 5. Keputusan --}}
@if ($status === S::SELESAI)
    <div class="form-card">
        <div class="section-head">
            <div class="section-icon"><i class="fa-solid fa-gavel" style="color:var(--brand);font-size:12px;"></i></div>
            <span class="section-title">Keputusan</span>
        </div>
        @if ($record->keputusan)
            <p style="margin:0;font-weight:700;">{{ $record->keputusan->getLabel() }}</p>
            @if ($lanjutSp && $record->sp_id)
                <div style="margin-top:12px;">
                    <a href="{{ route('sp.show', $record->sp_id) }}" wire:navigate class="btn-secondary"><i class="fa-solid fa-triangle-exclamation"></i> Lihat SP</a>
                </div>
            @endif
        @elseif ($editable)
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">Berita Acara sudah ditandatangani semua pihak dan terkunci. Putuskan apakah karyawan layak dikenai SP.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" wire:click="decide('tidak_layak_sp')" wire:confirm="Tetapkan TIDAK layak SP? Keputusan tidak bisa diubah." class="btn-secondary">Tidak Layak SP</button>
                <button type="button" wire:click="decide('lanjut_sp')" wire:confirm="Tetapkan LANJUT ke SP? Keputusan tidak bisa diubah." class="btn-primary">Lanjut ke SP</button>
            </div>
        @else
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Menunggu keputusan HC.</p>
        @endif
    </div>
@endif

{{-- 6. Langkah 2: form SP, tertanam (bukan halaman terpisah) --}}
@if ($status === S::SELESAI && $lanjutSp && ! $record->sp_id && $editable)
    <livewire:sp.create-sp-from-report-page :report-id="$record->id" :key="'sp-' . $record->id" />
@endif
</div>
