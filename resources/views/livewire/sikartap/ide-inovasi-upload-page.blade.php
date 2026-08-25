<div class="form-page-wrap-wide">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Ide Inovasi</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">💡 Ide Inovasi</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Unggah berkas ide inovasi Anda untuk dinilai atasan unit kerja</p>
        </div>
    </div>

    @php $ideInovasi = $this->ideInovasi; @endphp

    @if ($this->canUpload)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">
                {{ $ideInovasi ? 'Upload Ulang Berkas' : 'Upload Berkas Ide Inovasi' }}
            </p>

            @if ($ideInovasi && $ideInovasi->status_atasan === 'rejected' && $ideInovasi->catatan_penolakan)
                <div style="background:var(--danger-soft-bg,#fef2f2);border:1px solid var(--danger-soft-border,#fecaca);border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:12px;font-weight:600;color:var(--danger-text);margin:0 0 3px;">❌ Ditolak atasan</p>
                    <p style="font-size:12px;color:var(--text-body);margin:0;">{{ $ideInovasi->catatan_penolakan }}</p>
                </div>
            @endif

            <label class="upload-zone" style="display:block;margin-bottom:12px;">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
                <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">
                    {{ $file ? $file->getClientOriginalName() : 'Klik untuk upload berkas ide inovasi' }}
                </p>
                <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, DOC, DOCX, PPT, PPTX &middot; Maks. 10MB</p>
                <input type="file" style="display:none;" accept=".pdf,.doc,.docx,.ppt,.pptx" wire:model="file">
            </label>
            @error('file') <p style="color:var(--danger-text);font-size:12px;margin-bottom:10px;">{{ $message }}</p> @enderror

            <button type="button" wire:click="upload" wire:loading.attr="disabled" wire:target="upload" class="btn-primary">
                <i class="fa-solid fa-upload"></i> Upload
            </button>
        </div>
    @endif

    @if ($ideInovasi)
        @php
            $statusBadge = fn (?string $status) => match ($status) {
                'approved' => ['badge-approved', '✅ Disetujui'],
                'rejected' => ['badge-rejected', '❌ Ditolak'],
                default => ['badge-pending', '⏳ Menunggu'],
            };
            [$atasanColor, $atasanLabel] = $statusBadge($ideInovasi->status_atasan);
        @endphp

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">📎 Berkas Terupload</p>
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($ideInovasi->file_ide_inovasi) }}" target="_blank" class="btn-ghost" style="padding:5px 12px;font-size:12px;">
                    <i class="fa-solid fa-file"></i> Lihat Berkas
                </a>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin:0;">Diupload {{ $ideInovasi->created_at->format('d M Y, H:i') }}</p>
        </div>

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:14px;">Status Penilaian</p>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-soft);">
                <div>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;">1. Atasan Unit Kerja</p>
                    <p style="font-size:11px;color:var(--text-faint);margin:2px 0 0;">Wajib</p>
                </div>
                <span class="badge {{ $atasanColor }}">{{ $atasanLabel }}</span>
            </div>

            @if ($ideInovasi->nilai_akhir_atasan !== null)
                <div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;font-size:12px;">
                    <span style="color:var(--text-muted);">Nilai Akhir</span>
                    <span style="font-weight:600;">{{ $ideInovasi->nilai_akhir_atasan }} ({{ $ideInovasi->klasifikasi_atasan }})</span>
                </div>
            @endif

            @php
                [$direkturColor, $direkturLabel] = $statusBadge($ideInovasi->status_direktur);
                [$komisarisColor, $komisarisLabel] = $statusBadge($ideInovasi->status_komisaris);
            @endphp

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border-soft);">
                <div>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;">2. Direktur</p>
                    <p style="font-size:11px;color:var(--text-faint);margin:2px 0 0;">Opsional, tidak menghalangi proses</p>
                </div>
                <span class="badge {{ $direkturColor }}">{{ $direkturLabel }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;">
                <div>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;">3. Komisaris</p>
                    <p style="font-size:11px;color:var(--text-faint);margin:2px 0 0;">Opsional, tidak menghalangi proses</p>
                </div>
                <span class="badge {{ $komisarisColor }}">{{ $komisarisLabel }}</span>
            </div>
        </div>
    @endif
</div>
