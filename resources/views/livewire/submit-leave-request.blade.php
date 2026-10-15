<div class="form-page-wrap">
    <div class="page-hero">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);font-size:11px;">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.75);font-weight:500;">Pengajuan</span>
                <span style="color:rgba(255,255,255,0.4);font-size:11px;">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Cuti</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Cuti</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Isi formulir berikut untuk mengajukan permohonan cuti</p>
        </div>
    </div>

    @if ($this->minimumTenureNote)
        <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--danger-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger-soft-text);font-size:14px;"></i>
            </div>
            <div style="font-size:12.5px;font-weight:600;color:var(--danger-soft-text);">
                <p style="margin:0;">{{ $this->minimumTenureNote }}</p>
                @if ($this->cutiTahunanClosedNote)
                    <p style="margin:6px 0 0;font-weight:500;">{{ $this->cutiTahunanClosedNote }}</p>
                @endif
            </div>
        </div>
    @endif

    @if ($this->cutiTahunanClosedNote && ! $this->minimumTenureNote)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--info-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:14px;"></i>
            </div>
            <p style="font-size:12.5px;font-weight:600;color:var(--info-soft-text);margin:0;">{{ $this->cutiTahunanClosedNote }}</p>
        </div>
    @endif

    @if ($balance)
        <div style="background:var(--info-soft-bg);border:1px solid var(--info-soft-border);border-radius:14px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
            <div style="width:36px;height:36px;background:var(--info-soft-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-circle-info" style="color:var(--info-soft-text);font-size:14px;"></i>
            </div>
            @php
                $ketType = \App\Enum\Attendance\LeaveTypeCategoryEnum::tryFrom($leave_type);
                $ketPeriod = $ketType?->usageWindow() === 'monthly' ? 'bulan ini' : 'tahun ini';
            @endphp
            <div>
                <p style="font-size:12.5px;font-weight:700;color:var(--info-soft-text);margin:0 0 6px;">Ketentuan {{ $ketType?->label() }}</p>
                <p style="font-size:12px;color:var(--info-soft-text);margin:0;line-height:1.55;">
                    @if ($ketType?->usageWindow() !== 'per_request')
                        @if ($balance['used'] > 0)
                            Pada {{ $ketPeriod }}, Anda sudah menggunakan {{ $ketType?->label() }} sebanyak <strong>{{ $balance['used'] }} hari</strong>.
                        @else
                            Pada {{ $ketPeriod }}, Anda belum pernah menggunakan {{ $ketType?->label() }}.
                        @endif
                    @endif
                    Cuti diajukan sesuai kebutuhan, jadi mohon gunakan dengan bijak dan tetap koordinasikan dengan atasan Anda.
                </p>
            </div>
        </div>
    @endif

    @unless ($this->minimumTenureNote)
    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-clipboard-list" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Keterangan Cuti</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Jenis Cuti <span style="color:var(--danger-text);">*</span></label>
                    <select class="form-input" wire:model.live="leave_type">
                        <option value="">Pilih jenis cuti</option>
                        @foreach ($this->leaveTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('leave_type') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">Tanggal Cuti <span style="color:var(--danger-text);">*</span></label>
                    <div class="date-grid">
                        <div>
                            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 5px;">Tanggal Mulai</p>
                            <input type="date" class="form-input" wire:model.live="start_date" @if ($this->minLeaveDate) min="{{ $this->minLeaveDate }}" @endif>
                            @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 5px;">Tanggal Selesai</p>
                            <input type="date" class="form-input" wire:model.live="end_date" @disabled($this->selectedCategory?->lockedDays()) @if ($start_date || $this->minLeaveDate) min="{{ $start_date ?: $this->minLeaveDate }}" @endif>
                            @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:6px 0 0;">1 hari cuti? Isi tanggal mulai dan selesai sama.@if ($leave_type === \App\Enum\Attendance\LeaveTypeCategoryEnum::TAHUNAN->value) Cuti Tahunan wajib diajukan minimal 30 hari sebelum tanggal mulai.@endif</p>
                </div>

                @if ($this->workingDays !== null)
                    <div style="background:var(--surface-alt);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--text-body);">
                        <i class="fa-solid fa-calendar-check" style="margin-right:6px;color:var(--brand);"></i>
                        @if ($this->selectedCategory?->countsCalendarDays())
                            Total <strong>{{ $this->workingDays }} hari kalender</strong>
                        @else
                            Total <strong>{{ $this->workingDays }} hari kerja</strong> (hari libur sesuai jadwal tidak dihitung)
                        @endif
                    </div>
                @endif

                @if ($this->selectedCategory?->isCutiKhusus())
                    <div>
                        <label class="form-label">Lampiran Surat Dokter / Bidan @if ($this->requiresAttachment)<span style="color:var(--danger-text);">*</span>@else<span style="color:var(--text-muted);">(opsional)</span>@endif</label>
                        <input type="file" class="form-input" wire:model="attachments" multiple accept=".pdf,.jpg,.jpeg,.png">
                        <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">PDF/JPG/PNG, maks 10 MB per file.</p>
                        <div wire:loading wire:target="attachments" style="font-size:12px;color:var(--text-muted);margin-top:4px;">Mengunggah…</div>
                        @error('attachments') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                        @error('attachments.*') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="form-label">Alasan / Keterangan @if ($this->reasonRequired)<span style="color:var(--danger-text);">*</span>@endif</label>
                    <textarea class="form-input" rows="3" placeholder="{{ $this->reasonRequired ? 'Tambahkan keterangan' : 'Tambahkan keterangan (opsional)' }}" wire:model="reason"></textarea>
                    @error('reason') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if ($this->substituteEnabled)
            <!-- Pengganti -->
            <div class="form-card" style="margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div class="section-head" style="margin-bottom:0;">
                        <div class="section-icon"><i class="fa-solid fa-user-group" style="color:var(--brand);font-size:12px;"></i></div>
                        <span class="section-title">Pengganti Tugas</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" wire:click="toggleSubstitute">
                        <span style="font-size:12px;color:var(--text-muted);font-weight:500;">Tambahkan</span>
                        <div class="toggle {{ $with_substitute ? 'on' : '' }}"></div>
                    </div>
                </div>

                @if ($with_substitute)
                    <div style="display:flex;flex-direction:column;gap:16px;">
                    <div>
                        <label class="form-label">Pengganti</label>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @foreach ($substitute_user_ids as $i => $selectedId)
                                <div wire:key="substitute-row-{{ $i }}" style="display:flex;align-items:center;gap:8px;">
                                    <div style="flex:1;min-width:0;">
                                        <select class="form-input" wire:model.live="substitute_user_ids.{{ $i }}" data-tom-select data-placeholder="Cari nama pengganti...">
                                            {{-- `selected` eksplisit: morph Livewire mengembalikan <select> ke nilai dari HTML server,
                                                 tanpa atribut ini pilihan baris lain "kepental" saat Tambah/Hapus Pengganti. --}}
                                            <option value="" @selected($selectedId === '' || $selectedId === null)>Pilih pengganti</option>
                                            @foreach ($this->substituteOptions as $id => $name)
                                                {{-- Orang yang sudah dipilih di baris lain disembunyikan supaya tidak dobel. --}}
                                                @continue($id !== $selectedId && in_array($id, $substitute_user_ids, true))
                                                <option value="{{ $id }}" @selected($id === $selectedId)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if (count($substitute_user_ids) > 1)
                                        <button type="button" class="btn-ghost" wire:click="removeSubstitute({{ $i }})" title="Hapus pengganti"><i class="fa-solid fa-xmark"></i></button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if (count($substitute_user_ids) < 5)
                            <button type="button" class="btn-ghost" style="margin-top:8px;" wire:click="addSubstitute"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tambah Pengganti</button>
                        @endif
                        <p style="font-size:11.5px;color:var(--text-muted);margin:5px 0 0;">Rekan di unit yang sama yang menggantikan tugas Anda selama cuti. Maksimal 5 orang.</p>
                        @error('substitute_user_ids') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Tugas yang Diberikan</label>
                        <textarea class="form-input" rows="2" placeholder="Deskripsikan tugas yang dikerjakan pengganti..." wire:model="substitute_task"></textarea>
                        @error('substitute_task') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    </div>
                @else
                    <div class="pengganti-placeholder">
                        <i class="fa-solid fa-user-plus" style="color:var(--text-faint);font-size:20px;"></i>
                        <p style="font-size:13px;color:var(--text-muted);margin:0;">Aktifkan toggle untuk menambahkan pengganti tugas</p>
                    </div>
                @endif
            </div>
        @endif

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('my-attendance-requests') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit" @disabled($submitted)>
                {{-- Sedang kirim (wire:loading) atau sudah sukses & menunggu pindah halaman ($submitted):
                teks "Diajukan..." + ikon berputar, tombol mati. Normal: ikon kirim + label. --}}
                @if ($submitted)
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...
                @else
                    <span wire:loading.remove wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Cuti</span>
                    <span wire:loading wire:target="submit"><i class="fa-solid fa-spinner fa-spin" style="font-size:12px;"></i> Diajukan...</span>
                @endif
            </button>
        </div>
    </form>
    @endunless
</div>
