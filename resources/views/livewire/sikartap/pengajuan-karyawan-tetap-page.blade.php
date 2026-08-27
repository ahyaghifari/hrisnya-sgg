<div class="form-page-wrap-wide">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Karyawan Tetap</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Unggah berkas administratif untuk pengajuan status karyawan tetap</p>
        </div>
    </div>

    @php $sikartap = $this->sikartap; @endphp

    @if (! $sikartap)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Anda belum memiliki pengajuan karyawan tetap.</p>
            <button type="button" wire:click="startApplication" class="btn-primary">
                <i class="fa-solid fa-file-circle-plus"></i> Mulai Pengajuan
            </button>
        </div>
    @else
        @if ($sikartap->is_diskualifikasi)
            <div style="background:#6f42c1;color:#fff;border-radius:16px;padding:20px 24px;margin-bottom:16px;display:flex;align-items:flex-start;gap:16px;">
                <span style="font-size:28px;line-height:1;flex-shrink:0;">🚫</span>
                <div>
                    <strong style="font-size:15px;display:block;margin-bottom:6px;">Pengajuan Anda Telah Didiskualifikasi</strong>
                    <p style="margin:0;font-size:12.5px;opacity:0.9;line-height:1.6;">Proses pengajuan karyawan tetap Anda telah dihentikan dan tidak dapat dilanjutkan kembali.</p>
                    @if ($sikartap->alasan_diskualifikasi)
                        <p style="margin:10px 0 0;font-size:12px;background:rgba(255,255,255,.15);padding:10px 14px;border-radius:6px;line-height:1.6;">
                            <strong>Alasan:</strong><br>{{ $sikartap->alasan_diskualifikasi }}
                        </p>
                    @endif
                </div>
            </div>
        @elseif ($this->canStartNew)
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">
                    ℹ️ Pengajuan terakhir Anda berakhir dengan keputusan <strong style="color:var(--danger-text);">Gagal</strong>. Anda bisa mengajukan kembali.
                </p>
                <button type="button" wire:click="startApplication" wire:confirm="Buat pengajuan baru? Pengajuan lama tetap tersimpan sebagai riwayat." class="btn-primary">
                    <i class="fa-solid fa-file-circle-plus"></i> Buat Pengajuan Baru
                </button>
            </div>
        @endif
        @php
            $statusBadge = fn (string $status) => match ($status) {
                'approved', 'fit' => ['badge-approved', '✅ Disetujui'],
                'lulus', 'disarankan' => ['badge-approved', '✅ Selesai'],
            'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => ['badge-rejected', '❌ Ditolak'],
                default => ['badge-pending', '⏳ Menunggu'],
            };
            [$berkasColor, $berkasLabel] = $statusBadge($sikartap->status_berkas);

            $hasilBadge = fn (string $status) => match ($status) {
                'lulus' => ['badge-approved', '✅ Lulus'],
                'tidak_lulus' => ['badge-rejected', '❌ Tidak Lulus'],
                'disarankan' => ['badge-approved', '✅ Disarankan'],
                'tidak_disarankan' => ['badge-rejected', '❌ Tidak Disarankan'],
                'fit' => ['badge-approved', '✅ Fit'],
                'unfit' => ['badge-rejected', '❌ Tidak Fit'],
                default => ['badge-pending', '⏳ Menunggu'],
            };
        @endphp

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">Status Verifikasi Berkas</p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Diajukan {{ $sikartap->created_at->format('d M Y') }}</p>
            </div>
            <span class="badge {{ $berkasColor }}">{{ $berkasLabel }}</span>
        </div>

        @if ($sikartap->status_berkas === 'approved')
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Status Tahapan Seleksi</p>

                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;{{ $sikartap->status_tes === 'lulus' ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                    <span style="font-size:12.5px;color:var(--text-muted);">📝 Tes Bidang & Psikotes</span>
                    @php [$tesColor, $tesLabel] = $hasilBadge($sikartap->status_tes); @endphp
                    <span class="badge {{ $tesColor }}">{{ $tesLabel }}</span>
                </div>

                @if ($sikartap->status_tes === 'lulus')
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;{{ $sikartap->status_wawancara === 'disarankan' ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                        <span style="font-size:12.5px;color:var(--text-muted);">🎤 Wawancara</span>
                        @php [$wawancaraColor, $wawancaraLabel] = $hasilBadge($sikartap->status_wawancara); @endphp
                        <span class="badge {{ $wawancaraColor }}">{{ $wawancaraLabel }}</span>
                    </div>
                @endif

                @if ($sikartap->status_wawancara === 'disarankan' && $sikartap->requiresIdeInovasi())
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;{{ $sikartap->ideInovasiApproved() ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                        <span style="font-size:12.5px;color:var(--text-muted);">💡 Ide Inovasi</span>
                        @php
                            $ideStatus = $sikartap->ideInovasi?->status_atasan;
                            [$ideColor, $ideLabel] = match ($ideStatus) {
                                'approved' => ['badge-approved', '✅ Disetujui'],
                                'rejected' => ['badge-rejected', '❌ Ditolak, upload ulang'],
                                default => ['badge-pending', $ideStatus ? '⏳ Menunggu Penilaian' : '⏳ Belum Diupload'],
                            };
                        @endphp
                        <a href="{{ route('sikartap.ide-inovasi') }}" wire:navigate class="badge {{ $ideColor }}" style="text-decoration:none;">{{ $ideLabel }}</a>
                    </div>
                @endif

                {{-- MCU baru relevan setelah Ide Inovasi (kalau disyaratkan) disetujui atasan -
                     sebelumnya baris ini muncul begitu wawancara disarankan tanpa cek Ide Inovasi
                     dulu, bikin karyawan kira MCU sudah giliran padahal masih harus isi Ide
                     Inovasi lebih dulu (2026-08-26, laporan user). --}}
                @if ($sikartap->status_wawancara === 'disarankan' && $sikartap->requiresMcu() && $sikartap->ideInovasiApproved())
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;{{ $sikartap->isEligibleForDirectorDecision() ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                        <span style="font-size:12.5px;color:var(--text-muted);">🏥 Medical Check-Up</span>
                        @php [$mcuColor, $mcuLabel] = $hasilBadge($sikartap->status_mcu); @endphp
                        <span class="badge {{ $mcuColor }}">{{ $mcuLabel }}</span>
                    </div>
                @endif

                @if ($sikartap->isEligibleForDirectorDecision())
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;">
                        <span style="font-size:12.5px;color:var(--text-muted);">🏛️ Persetujuan Direktur</span>
                        @php [$direkturColor, $direkturLabel] = $statusBadge($sikartap->status_keputusan_direktur); @endphp
                        <span class="badge {{ $direkturColor }}">{{ $direkturLabel }}</span>
                    </div>
                @endif
            </div>
        @endif

        @if ($sikartap->status_wawancara === 'disarankan' && $sikartap->requiresIdeInovasi())
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px 20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">💡 Ide Inovasi</p>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Wajib diisi sebelum lanjut ke tahap berikutnya</p>
                </div>
                <a href="{{ route('sikartap.ide-inovasi') }}" wire:navigate class="btn-primary" style="padding:8px 16px;font-size:12.5px;">
                    <i class="fa-solid fa-arrow-right"></i> Buka
                </a>
            </div>
        @endif

        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;">
            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:14px;">Dokumen Administratif</p>

            <div class="pwa-list">
                @foreach ($this->documentTypes as $docType)
                    @php
                        $doc = $sikartap->documents->firstWhere('doc_type', $docType);
                        $docStatus = $doc?->status ?? null;
                        [$docColor, $docLabel] = $docStatus ? $statusBadge($docStatus) : ['badge-cancelled', 'Belum Upload'];
                        $locked = $sikartap->status_berkas === 'approved' || $sikartap->isGagal() || $sikartap->is_diskualifikasi;
                    @endphp
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">
                                {{ $docType->getLabel() }}
                                @if ($docType->isRequired())
                                    <span style="color:var(--danger-text);">*</span>
                                @endif
                            </p>
                            <div style="margin-top:4px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span class="badge {{ $docColor }}">{{ $docLabel }}</span>
                                @if ($doc?->file_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="btn-ghost" style="padding:4px 10px;font-size:11px;">
                                        <i class="fa-solid fa-file"></i> Lihat Berkas
                                    </a>
                                @endif
                            </div>

                            @if ($doc?->status === 'rejected' && $doc->catatan_penolakan)
                                <p style="font-size:12px;color:var(--danger-text);margin-top:6px;">{{ $doc->catatan_penolakan }}</p>
                            @endif

                            @unless ($locked)
                                <div style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap;">
                                    <label class="btn-ghost" style="padding:6px 14px;font-size:12px;cursor:pointer;margin:0;">
                                        <i class="fa-solid fa-paperclip"></i> Pilih File
                                        <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="uploads.{{ $docType->value }}">
                                    </label>

                                    @if (isset($uploads[$docType->value]))
                                        <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-body);min-width:0;">
                                            <i class="fa-solid fa-file-circle-check" style="color:var(--success-text,#16a34a);flex-shrink:0;"></i>
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;">{{ $uploads[$docType->value]->getClientOriginalName() }}</span>
                                        </span>
                                        <button type="button" wire:click="uploadDocument('{{ $docType->value }}')" wire:loading.attr="disabled" class="btn-primary" style="padding:6px 14px;font-size:12px;">
                                            Upload
                                        </button>
                                    @else
                                        <span style="font-size:11.5px;color:var(--text-faint);">Belum ada file dipilih &middot; PDF, JPG, PNG, maks. 5MB</span>
                                    @endif
                                </div>
                                @error("uploads.{$docType->value}") <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
