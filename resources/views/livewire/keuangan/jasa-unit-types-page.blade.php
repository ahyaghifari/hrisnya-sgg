<div>
    <x-page-header title="Jasa Unit" />

    <div class="mt-4 doc-type-layout">
        @if (auth()->user()->canFilterByCompany())
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>
        @endif

        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-hospital-user"></i> Katalog Jasa Unit</p>
                <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Jasa/tunjangan per divisi (mis. Jasa Perujuk Rawat, Jasa Ambulan, Jasa Paket Infus) untuk perusahaan ini, plus divisi mana saja yang berhak menerimanya. Nominal per karyawan per periode diisi di halaman <a href="{{ route('keuangan.jasa-unit') }}" wire:navigate style="color:#042c6c;font-weight:600;">Input Jasa Unit</a>, bukan di sini.</p>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach ($rows as $i => $row)
                        <div style="border:1px solid var(--border-soft);border-radius:10px;padding:10px 12px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input type="text" class="form-input" style="max-width:280px;font-size:12.5px;padding:7px 10px;" wire:model="rows.{{ $i }}.name" placeholder="Nama jasa (mis. Jasa Ambulan)">
                                @if ($row['id'])
                                    <button type="button" wire:click="openAuditLog('jasa_unit_type:{{ $row['id'] }}')" title="Riwayat" style="border:none;background:none;color:var(--text-faint);cursor:pointer;padding:6px;font-size:11px;"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                @endif
                                @if ($this->canEdit)
                                    <button type="button" wire:click="removeRow({{ $i }})" style="border:none;background:none;color:#dc2626;cursor:pointer;padding:6px;"><i class="fa-solid fa-trash"></i></button>
                                @endif
                            </div>
                            @error("rows.{$i}.name") <p style="font-size:11px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                            <div style="margin-top:8px;">
                                <label class="form-label" style="font-size:10.5px;">Divisi yang berhak</label>
                                <div wire:ignore>
                                    <select multiple data-tom-select data-placeholder="-- Pilih divisi --" wire:model="rows.{{ $i }}.org_structure_ids">
                                        @foreach ($this->divisions as $division)
                                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if (empty($rows))
                        <p style="font-size:11.5px;color:var(--text-faint);">Belum ada jasa. Klik "Tambah Jasa" di bawah.</p>
                    @endif
                    @if ($companyId && $this->divisions->isEmpty())
                        <p style="font-size:11.5px;color:var(--text-faint);"><i class="fa-solid fa-circle-info"></i> Belum ada data Divisi untuk perusahaan ini.</p>
                    @endif
                </div>

                <div style="display:flex;gap:10px;margin-top:14px;">
                    @if ($this->canEdit)
                        <button type="button" wire:click="addRow" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;"><i class="fa-solid fa-plus"></i> Tambah Jasa</button>
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('livewire.partials.audit-log-modal')
</div>
