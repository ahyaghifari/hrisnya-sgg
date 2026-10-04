<div class="{{ $requests->isNotEmpty() ? 'bento-pending-approvals' : '' }}" @if ($requests->isEmpty()) style="display:none;" @endif>
@if ($requests->isNotEmpty())
    {{-- Identitas "Approval Dark": dipakai konsisten di semua kartu domain persetujuan
         (kartu ini + timeline status) supaya user langsung kenali "ini soal approval"
         di mana pun kartunya muncul (Dashboard, MyStaff, dsb), tanpa perlu baca teks. --}}
    <div style="background:var(--surface);border-radius:20px;box-shadow:0 2px 10px rgba(0,0,0,0.08);overflow:hidden;">
        <div class="approval-card" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div class="appr-deco-1"></div><div class="appr-deco-2"></div>
            <div style="position:relative;z-index:1;display:flex;align-items:center;gap:12px;min-width:0;">
                <div style="flex-shrink:0;width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,0.14);display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-clipboard-check" style="color:#fff;font-size:15px;"></i>
                </div>
                <div style="min-width:0;">
                    <h3 style="font-size:14px;font-weight:800;color:#fff;margin:0;">Butuh Persetujuan Anda</h3>
                    <p style="font-size:11.5px;font-weight:600;color:rgba(255,255,255,0.7);margin-top:1px;">Menunggu tindakan Anda</p>
                </div>
            </div>
            <div style="position:relative;z-index:1;flex-shrink:0;display:flex;align-items:center;gap:8px;">
                <span style="background:#fff;color:#9a3412;font-size:13px;font-weight:800;min-width:28px;height:28px;padding:0 9px;border-radius:99px;display:flex;align-items:center;justify-content:center;">
                    {{ $requests->count() }}
                </span>
                <button type="button" wire:click="toggleCollapsed" title="{{ $collapsed ? 'Tampilkan' : 'Sembunyikan' }}"
                    style="background:rgba(255,255,255,0.14);border:none;width:28px;height:28px;border-radius:99px;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;">
                    <i class="fa-solid {{ $collapsed ? 'fa-chevron-down' : 'fa-chevron-up' }} text-xs"></i>
                </button>
            </div>
        </div>

        @if (! $collapsed)
        @php
            // Badge warna beda per modul - kuning buat Izin/Cuti/Lembur (attendance_request),
            // biru sistem buat Perubahan Status Karyawan (user_transition), ungu buat Pelatihan
            // (lms_course_enrollment) - biar approver bisa langsung bedakan tanpa baca teks,
            // kartu ini gabungan lintas-modul.
            $badgeStyle = fn (string $badge) => match ($badge) {
                'blue' => 'background:rgba(4,44,108,0.1);color:#042c6c;',
                'purple' => 'background:rgba(126,34,206,0.1);color:#7e22ce;',
                'red' => 'background:rgba(180,35,24,0.1);color:#b42318;',
                default => 'background:rgba(217,119,6,0.12);color:#92400e;',
            };
            // Warna aksen checkbox ikut badge per baris, sama pola .custom-checkbox yang
            // dipakai halaman Komponen Gaji (component-card.blade.php) - --accent per instance.
            $accentColor = fn (string $badge) => match ($badge) {
                'blue' => '#042c6c',
                'purple' => '#7e22ce',
                'red' => '#b42318',
                default => '#92400e',
            };
        @endphp

        @if (count($this->bulkTypeOptions) > 0)
            {{-- Toolbar approve massal: pilih "Jenis" -> approve SEMUA baris jenis itu, atau
                 biarkan kosong & centang manual per baris (lihat PendingApprovalsCard::
                 confirmBulkApprove()). --}}
            <div style="padding:12px 20px;border-bottom:1px solid var(--border-soft);display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <select wire:model.live="bulkTypeFilter" class="form-input" style="max-width:240px;font-size:12px;padding:6px 10px;">
                    <option value="">Pilih manual (centang baris)</option>
                    @foreach ($this->bulkTypeOptions as $module => $label)
                        <option value="{{ $module }}">Semua "{{ $label }}"</option>
                    @endforeach
                </select>
                <button type="button" wire:click="openBulkConfirm" @disabled($this->selectedCount === 0) class="btn-primary" style="padding:7px 14px;font-size:12px;opacity:{{ $this->selectedCount === 0 ? '0.5' : '1' }};">
                    <i class="fa-solid fa-check-double"></i> Approve Terpilih ({{ $this->selectedCount }})
                </button>
            </div>
        @endif

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @foreach ($requests as $request)
                {{-- Tanpa ikon jenis per baris di mobile (instruksi user 2026-10-05): label berwarna sudah membedakan jenis. --}}
                @php $rowInner = '
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">
                            <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;' . $badgeStyle($request->badge) . '">' . e($request->label) . '</span>
                            ' . e($request->user_name) . '
                        </p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">' . e($request->keterangan) . ' &middot; ' . $request->created_at->translatedFormat('d M Y') . '</p>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                '; @endphp
                <div class="pwa-list-row" style="display:flex;align-items:center;gap:10px;">
                    @if ($request->bulk_supported)
                        <label class="custom-checkbox" style="--accent:{{ $accentColor($request->badge) }};flex-shrink:0;">
                            <input type="checkbox" wire:model.live="selected" value="{{ $request->key }}">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                    @else
                        <span style="width:19px;flex-shrink:0;"></span>
                    @endif
                    @if ($request->modal)
                        <button type="button" wire:click="openReviewModal('{{ $request->id }}')" style="text-decoration:none;flex:1;display:flex;align-items:center;gap:10px;min-width:0;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;">
                            {!! $rowInner !!}
                        </button>
                    @else
                        <a href="{{ $request->url }}" @unless ($request->external) wire:navigate @endunless style="text-decoration:none;flex:1;display:flex;align-items:center;gap:10px;min-width:0;">
                            {!! $rowInner !!}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- DESKTOP: table (font/padding lebih rapat daripada .data-table default — kartu ini
        sengaja lebih padat karena bisa banyak baris menumpuk di dashboard) -->
        <div class="hidden md:block" style="overflow-x:auto;">
            <table class="data-table" style="min-width:560px;">
                <thead>
                    <tr>
                        <th style="padding:8px 14px;font-size:10px;width:28px;"></th>
                        <th style="padding:8px 14px;font-size:10px;">Jenis</th>
                        <th style="padding:8px 14px;font-size:10px;">Keterangan</th>
                        <th style="padding:8px 14px;font-size:10px;">Pemohon</th>
                        <th style="padding:8px 14px;font-size:10px;">Tanggal Pengajuan</th>
                        <th style="padding:8px 14px;font-size:10px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr>
                            <td style="padding:9px 14px;">
                                @if ($request->bulk_supported)
                                    <label class="custom-checkbox" style="--accent:{{ $accentColor($request->badge) }};">
                                        <input type="checkbox" wire:model.live="selected" value="{{ $request->key }}">
                                        <span class="box"><i class="fa-solid fa-check"></i></span>
                                    </label>
                                @endif
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700;{{ $badgeStyle($request->badge) }}">{{ $request->label }}</span>
                            </td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-body);">{{ $request->keterangan }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-muted);">{{ $request->user_name }}</td>
                            <td style="padding:9px 14px;font-size:11.5px;color:var(--text-faint);white-space:nowrap;">{{ $request->created_at->translatedFormat('d M Y') }}</td>
                            <td style="padding:9px 14px;">
                                @if ($request->modal)
                                    <button type="button" wire:click="openReviewModal('{{ $request->id }}')"
                                        style="font-size:11.5px;font-weight:600;color:var(--brand);background:none;border:none;cursor:pointer;padding:0;font-family:inherit;white-space:nowrap;">
                                        Tinjau <i class="fa-solid fa-arrow-right text-xs"></i>
                                    </button>
                                @else
                                    <a href="{{ $request->url }}" @unless ($request->external) wire:navigate @endunless
                                        style="font-size:11.5px;font-weight:600;color:var(--brand);text-decoration:none;white-space:nowrap;">
                                        Tinjau <i class="fa-solid fa-arrow-right text-xs"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @if ($this->reviewingEnrollment)
        <div class="modal-overlay" wire:click="closeReviewModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Tinjau Pendaftaran Pelatihan</h3>
                    <button type="button" wire:click="closeReviewModal" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div style="font-size:13.5px;display:flex;flex-direction:column;gap:8px;margin-bottom:18px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;">
                        <span style="color:var(--text-muted);">Peserta</span>
                        <span style="font-weight:600;text-align:right;">{{ $this->reviewingEnrollment->user?->name }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:12px;">
                        <span style="color:var(--text-muted);">Pelatihan</span>
                        <span style="font-weight:600;text-align:right;">{{ $this->reviewingEnrollment->course?->title }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:12px;">
                        <span style="color:var(--text-muted);">Tanggal Daftar</span>
                        <span style="font-weight:600;text-align:right;">{{ \Illuminate\Support\Carbon::parse($this->reviewingEnrollment->enrolled_at ?? $this->reviewingEnrollment->created_at)->translatedFormat('d M Y, H:i') }}</span>
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="approveEnrollment" wire:confirm="Setujui pendaftaran ini?" class="btn-primary" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-check"></i> Setujui
                    </button>
                    <button type="button" wire:click="rejectEnrollment" wire:confirm="Tolak pendaftaran ini?" class="btn-ghost" style="flex:1;justify-content:center;color:var(--danger-text);border-color:var(--danger-soft-border);">
                        <i class="fa-solid fa-xmark"></i> Tolak
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showBulkConfirm)
        <div class="modal-overlay" wire:click="closeBulkConfirm">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Approve {{ $this->selectedCount }} Pengajuan</h3>
                    <button type="button" wire:click="closeBulkConfirm" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">
                    Pengajuan yang Anda centang akan disetujui. Pengajuan yang butuh tinjauan lebih detail sebaiknya tetap dibuka satu-satu lewat tombol Tinjau.
                </p>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea wire:model="bulkNote" class="form-input" rows="2" placeholder="Mis. Disetujui sesuai pengajuan"></textarea>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" wire:click="confirmBulkApprove" class="btn-primary" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-check"></i> Ya, Setujui Semua
                    </button>
                    <button type="button" wire:click="closeBulkConfirm" class="btn-ghost" style="flex:1;justify-content:center;">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
@endif
</div>
