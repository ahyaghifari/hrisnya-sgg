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
            </div>
        </div>

        @include('livewire.partials.audit-log-modal')
    </div>
</div>
