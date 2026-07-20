<div>
    <x-page-header title="Penerima Komponen Gaji" />

    <p style="font-size:11.5px;color:var(--text-muted);margin:8px 0 16px;max-width:720px;">
        Centang tipe karyawan yang berhak menerima tiap komponen gaji. <strong>Komponen yang belum
        dicentang tipe karyawan apa pun TIDAK AKAN menghasilkan apa-apa untuk siapa pun</strong> saat
        payroll digenerate — bukan berlaku untuk semua tipe secara default.
    </p>

    <div class="doc-type-layout">
        {{-- Kolom kiri: perusahaan --}}
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                    {{ $company->name }}
                </button>
            @endforeach
        </div>

        {{-- Kolom tengah: komponen gaji --}}
        <div class="doc-type-nav" style="width:240px;">
            @foreach ($this->components as $component)
                @php $count = count($selected[$component->value] ?? []); @endphp
                <button type="button" wire:click="selectComponent('{{ $component->value }}')" class="doc-type-btn {{ $activeComponent === $component->value ? 'is-active' : '' }}">
                    {{ $component->label() }}
                    <span class="doc-type-count">{{ $count > 0 ? $count : '—' }}</span>
                </button>
            @endforeach
        </div>

        {{-- Kolom kanan: checklist tipe karyawan --}}
        <div style="flex:1;min-width:0;">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
                @if (! $activeComponent)
                    <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:24px 0;">
                        <i class="fa-solid fa-arrow-left"></i> Pilih komponen gaji di sebelah kiri dulu.
                    </p>
                @elseif ($this->employeeTypes->isEmpty())
                    <p style="font-size:12px;color:var(--text-faint);text-align:center;padding:24px 0;">
                        Belum ada data Tipe Karyawan untuk perusahaan ini — isi dulu di Master &gt; Tipe Karyawan.
                    </p>
                @else
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em;color:var(--text-muted);margin:0 0 10px;">
                        Penerima — {{ collect($this->components)->first(fn ($c) => $c->value === $activeComponent)?->label() }}
                    </p>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        @foreach ($this->employeeTypes as $type)
                            <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--border-soft);border-radius:8px;cursor:pointer;">
                                <span class="custom-checkbox" style="--accent:#042c6c;">
                                    <input type="checkbox" wire:model="selected.{{ $activeComponent }}" value="{{ $type->id }}">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </span>
                                <span style="font-size:12.5px;color:var(--text-body);">{{ $type->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="margin-top:14px;padding:9px 20px;font-size:12.5px;">
                Simpan — {{ $this->companies->firstWhere('id', $companyId)?->name }}
            </button>
        </div>
    </div>
</div>
