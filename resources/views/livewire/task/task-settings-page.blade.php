<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Tugas" />

    <div class="mt-4">
        <div class="doc-type-layout">
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn doc-type-btn-sky {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>

            <div style="flex:1;min-width:0;">
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:flex;flex-direction:column;">
                        @foreach ($this->settings as $setting)
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;padding:14px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-soft);' : '' }}">
                                <div style="flex:1;min-width:220px;">
                                    <label class="form-label" style="margin:0;">{{ $setting->label() }}</label>
                                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $setting->helperText() }}</p>
                                </div>
                                <label style="display:flex;align-items:center;gap:6px;">
                                    <input type="checkbox"
                                        @checked($values[$setting->value] === '1')
                                        wire:click="$set('values.{{ $setting->value }}', $event.target.checked ? '1' : '0')">
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="justify-content:center;align-self:flex-start;padding-left:20px;padding-right:20px;margin-top:18px;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
