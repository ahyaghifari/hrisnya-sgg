<div class="p-4 md:p-6">
    <x-page-header title="Pengaturan Absensi" />

    <div class="mt-4">
        <div class="doc-type-layout">
            {{-- Kiri: perusahaan — pola sama halaman Komponen Gaji. --}}
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Kanan: form flat, semua AttendanceSettingEnum company-scoped, tanpa lapis
            toggle/expand komponen (beda dari Komponen Gaji yang punya banyak komponen). --}}
            <div style="flex:1;min-width:0;">
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                        @foreach ($this->settings as $setting)
                            <div>
                                <label class="form-label">{{ $setting->label() }}</label>
                                <input type="number" class="form-input" wire:model="values.{{ $setting->value }}">
                                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">{{ $setting->helperText() }}</p>
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
