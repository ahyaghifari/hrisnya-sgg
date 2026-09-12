<div>
    <x-page-header title="Akses Filter Perusahaan" />

    <div style="background:rgba(4,44,108,0.07);border:1px solid rgba(4,44,108,0.2);border-radius:10px;padding:12px 16px;font-size:13px;color:#042c6c;margin-top:16px;">
        Lapisan tambahan di atas permission "Lihat Perusahaan Lain" — pilih perusahaan, lalu role atau user, lalu centang sistem mana saja yang boleh dipakai buat filter lintas perusahaan. Sistem yang tidak dicentang (atau belum ada konfigurasi sama sekali) otomatis dikunci ke perusahaan sendiri.
    </div>

    <div class="mt-4 doc-type-layout">
        {{-- Kiri: perusahaan --}}
        @if (auth()->user()->canFilterByCompany())
        <div class="doc-type-nav">
            @foreach ($this->companies as $company)
                <button type="button" wire:click="$set('companyId', '{{ $company->id }}')" class="doc-type-btn doc-type-btn-navy {{ $companyId === $company->id ? 'is-active' : '' }}">
                    <i class="fa-solid fa-building"></i>
                    <span>{{ $company->name }}</span>
                </button>
            @endforeach
        </div>
        @endif

        {{-- Tengah: role atau user --}}
        <div style="display:flex;flex-direction:column;gap:8px;min-width:0;">
            <input type="search" wire:model.live.debounce.300ms="userSearch" class="form-input" placeholder="Cari user...">

            <div class="doc-type-nav" style="width:auto;">
                @forelse ($this->grantees as $grantee)
                    <button type="button" wire:click="selectGrantee('{{ $grantee['key'] }}')" class="doc-type-btn {{ $granteeKey === $grantee['key'] ? 'is-active' : '' }}">
                        <i class="fa-solid {{ $grantee['type'] === 'role' ? 'fa-user-tag' : 'fa-user' }}"></i>
                        <span>{{ $grantee['name'] }}</span>
                    </button>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);padding:8px;">Belum ada role/user di perusahaan ini.</p>
                @endforelse
            </div>
        </div>

        {{-- Kanan: sistem --}}
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:8px;">
            <div class="doc-type-nav" style="width:auto;">
                @foreach ($this->systemOptions as $system)
                    <button type="button" wire:click="toggleSystem('{{ $system->value }}')" class="doc-type-btn doc-type-btn-sky {{ in_array($system->value, $checkedSystems, true) ? 'is-active' : '' }}" @disabled(! $granteeKey)>
                        <i class="fa-solid {{ in_array($system->value, $checkedSystems, true) ? 'fa-square-check' : 'fa-square' }}"></i>
                        <span>{{ $system->label() }}</span>
                    </button>
                @endforeach
            </div>

            <button type="button" class="btn-primary" wire:click="save" style="align-self:flex-start;margin-top:8px;" @disabled(! $granteeKey)>
                Simpan
            </button>
        </div>
    </div>
</div>
