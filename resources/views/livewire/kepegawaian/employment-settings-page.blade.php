<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Kepegawaian" subtitle="Max masa kerja kontrak, durasi perpanjangan, usia pensiun" />

    <div class="mt-4">
        <div class="doc-type-layout">
            @if (auth()->user()->canFilterByCompany())
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn doc-type-btn-sky {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>
            @endif

            <div style="flex:1;min-width:0;">
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;flex-direction:column;">
                        @foreach ($this->settings as $setting)
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;padding:14px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                                <div style="flex:1;min-width:220px;">
                                    <label class="form-label" style="margin:0;display:inline-flex;align-items:center;gap:6px;">
                                        {{ $setting->label() }}
                                        <button type="button" wire:click="openAuditLog('{{ $setting->value }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:11px;padding:0;">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    </label>
                                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $setting->helperText() }}</p>
                                </div>
                                @if ($setting->selectOptions())
                                    @if ($this->canEditSettings)
                                        <select class="form-input" style="max-width:220px;" wire:model="values.{{ $setting->value }}">
                                            @foreach ($setting->selectOptions() as $optValue => $optLabel)
                                                <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span style="font-weight:600;min-width:180px;">{{ $setting->selectOptions()[$values[$setting->value] ?? ''] ?? '-' }}</span>
                                    @endif
                                @elseif ($setting->type() === \App\Enum\SettingTypeEnum::BOOLEAN)
                                    @if ($this->canEditSettings)
                                        <label class="custom-checkbox" style="--accent:#7c3aed;flex-shrink:0;">
                                            <input type="checkbox" wire:model="values.{{ $setting->value }}" value="1" @checked(($values[$setting->value] ?? '0') == '1')>
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                    @else
                                        <span class="badge {{ ($values[$setting->value] ?? '0') == '1' ? 'badge-approved' : 'badge-cancelled' }}">
                                            {{ ($values[$setting->value] ?? '0') == '1' ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    @endif
                                @elseif ($this->canEditSettings)
                                    <input type="number" class="form-input" style="max-width:180px;" wire:model="values.{{ $setting->value }}">
                                @else
                                    <span style="font-weight:600;min-width:180px;">{{ $values[$setting->value] ?? '-' }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($this->canEditSettings)
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="justify-content:center;align-self:flex-start;padding-left:20px;padding-right:20px;margin-top:18px;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                    @endif
                </div>

                {{-- Pemberi tugas Surat Perintah Tugas: 1 orang per company, dipakai tab Surat
                     Perintah Tugas di MyStaff. Surat yang sudah dibuat menyimpan snapshot sendiri,
                     jadi mengubah ini hanya berlaku untuk surat berikutnya. --}}
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:14px;">
                    <h3 style="font-size:14px;font-weight:700;margin:0 0 4px;">Penanda Tangan Surat Perintah Tugas</h3>
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Pemberi tugas yang menandatangani (TTE QR) surat perintah tugas. Ganti di sini kalau berhalangan; surat yang sudah dibuat tidak berubah.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3" style="gap:14px;">
                        <div wire:key="signer-user-{{ $companyId }}">
                            <label class="form-label">Akun Penanda Tangan</label>
                            <select data-tom-select data-placeholder="Pilih karyawan..." wire:model="signerUserId" class="form-input" @disabled(! $this->canEditSettings)>
                                <option value=""></option>
                                @foreach ($this->signerOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('signerUserId') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Nama di Surat</label>
                            <input type="text" wire:model="signerNama" class="form-input" @disabled(! $this->canEditSettings)>
                            @error('signerNama') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Jabatan di Surat</label>
                            <input type="text" wire:model="signerJabatan" class="form-input" placeholder="mis. Direktur / Plt. Direktur" @disabled(! $this->canEditSettings)>
                            @error('signerJabatan') <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($this->canEditSettings)
                        <button type="button" wire:click="saveSigner" wire:loading.attr="disabled" wire:target="saveSigner" class="btn-primary" style="margin-top:14px;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Penanda Tangan
                        </button>
                    @endif
                </div>

                {{-- Akun yang tanda tangannya terblokir karena password salah berulang (SigningLockService).
                     Hanya pemegang permission buka_blokir_tanda_tangan; tidak bisa membuka akun sendiri. --}}
                @if ($this->canUnlockSigning)
                    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:14px;">
                        <h3 style="font-size:14px;font-weight:700;margin:0 0 4px;">Akun Terblokir Tanda Tangan</h3>
                        <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">
                            Akun terblokir setelah {{ \App\Services\Document\SigningLockService::MAX_ATTEMPTS }} kali password salah beruntun (sementara, terbuka sendiri) atau berulang
                            {{ \App\Services\Document\SigningLockService::MAX_TEMP_LOCKS }} kali dalam {{ \App\Services\Document\SigningLockService::TEMP_LOCK_WINDOW_HOURS }} jam (keras, harus dibuka petugas).
                            Pastikan identitas pemilik akun sebelum membuka blokir. Setiap pembukaan tercatat.
                        </p>

                        @forelse ($this->lockedSigners as $lock)
                            <div wire:key="locked-{{ $lock->user_id }}" style="border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin-bottom:8px;">
                                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                    <strong style="font-size:13px;">{{ $lock->user?->name }}</strong>
                                    @if ($lock->isHardLocked())
                                        <span class="badge badge-rejected">Diblokir sejak {{ $lock->hard_locked_at->translatedFormat('d M Y, H:i') }}</span>
                                    @else
                                        <span class="badge badge-pending">Sementara sampai {{ $lock->locked_until->format('H:i') }}</span>
                                    @endif
                                </div>
                                @if ($lock->user_id !== auth()->id())
                                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                                        <input type="text" wire:model="unlockReasons.{{ $lock->user_id }}" class="form-input" style="flex:1;min-width:220px;" placeholder="Alasan membuka blokir (wajib)">
                                        <button type="button" wire:click="unlockSigning('{{ $lock->user_id }}')" wire:confirm="Buka blokir tanda tangan akun ini? Pastikan identitas pemiliknya." class="btn-primary btn-sm">
                                            <i class="fa-solid fa-lock-open"></i> Buka Blokir
                                        </button>
                                    </div>
                                    @error("unlockReasons.{$lock->user_id}") <p style="color:#dc2626;font-size:12px;margin:4px 0 0;">{{ $message }}</p> @enderror
                                @else
                                    <p style="font-size:11.5px;color:var(--text-muted);margin:6px 0 0;">Blokir akun sendiri harus dibuka petugas lain atau Super Admin.</p>
                                @endif
                            </div>
                        @empty
                            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Tidak ada akun yang terblokir.</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        @include('livewire.partials.audit-log-modal')
    </div>
</div>
