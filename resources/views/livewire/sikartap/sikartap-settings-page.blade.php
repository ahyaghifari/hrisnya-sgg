<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Sikartap" subtitle="Wajib Ide Inovasi & wajib MCU - beda-beda per perusahaan" />

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
                            </div>
                        @endforeach
                    </div>

                    @if ($this->canEditSettings)
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="justify-content:center;align-self:flex-start;padding-left:20px;padding-right:20px;margin-top:18px;">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                    @endif
                </div>

                @if (($values[\App\Enum\Sikartap\SikartapSettingEnum::WAJIB_IDE_INOVASI->value] ?? '0') == '1')
                    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-top:20px;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">Reviewer Atasan Ide Inovasi</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:0 0 14px;">Tunjuk 1 reviewer per Departemen — berlaku ke semua karyawan di bawah departemen itu. Semua Departemen wajib terisi sebelum bisa disimpan.</p>

                        @forelse ($this->orgDepartments as $departemen)
                            <div style="padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <p style="font-size:12.5px;font-weight:600;color:var(--text-body);margin:0;min-width:160px;">
                                    {{ $departemen->name }}
                                </p>

                                @if ($this->canEditSettings)
                                    <div wire:ignore wire:key="reviewer-select-{{ $companyId }}-{{ $departemen->id }}" style="min-width:220px;">
                                        <select data-tom-select data-placeholder="-- Pilih reviewer --" wire:model="reviewerUserIds.{{ $departemen->id }}">
                                            <option value="">-- Pilih reviewer --</option>
                                            @foreach ($this->companyUsers as $companyUser)
                                                <option value="{{ $companyUser->id }}">{{ $companyUser->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <span style="font-size:12px;color:var(--text-muted);">
                                        {{ $this->companyUsers->firstWhere('id', $reviewerUserIds[$departemen->id] ?? null)?->name ?? 'Belum ditunjuk' }}
                                    </span>
                                @endif
                            </div>
                        @empty
                            <p style="font-size:12px;color:var(--text-muted);">Belum ada Departemen di org struktur perusahaan ini.</p>
                        @endforelse

                        @if ($this->canEditSettings && $this->orgDepartments->isNotEmpty())
                            <button type="button" wire:click="saveReviewers" wire:loading.attr="disabled" wire:target="saveReviewers" class="btn-primary" style="margin-top:16px;">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Reviewer
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @include('livewire.partials.audit-log-modal')
    </div>
</div>
