<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1rem">
        <x-filament::section>
            <div style="display:flex;flex-direction:column;gap:.25rem;max-width:28rem">
                <span style="font-size:.75rem;color:#6b7280">Client</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="selectedClientUuid">
                        <option value="">— Pilih client —</option>
                        @foreach ($this->clientOptions() as $uuid => $label)
                            <option value="{{ $uuid }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <span style="font-size:.75rem;color:#9ca3af">
                    Role Keycloak berlaku per client — pilih dulu client sistemnya, baru kelola role di bawah.
                </span>
            </div>
        </x-filament::section>

        @if ($selectedClientUuid)
            <x-filament::section>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem">
                    <div style="font-weight:600">{{ $selectedClientLabel }}</div>
                    <x-filament::button size="xs" icon="heroicon-o-plus" wire:click="newRole">
                        Buat Role
                    </x-filament::button>
                </div>

                @if ($rolesError)
                    <div style="border-radius:.5rem;padding:.75rem;font-size:.8125rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca">
                        Gagal memuat role: {{ $rolesError }}
                    </div>
                @elseif (empty($roles))
                    <p style="font-size:.875rem;color:#6b7280">Client ini belum punya role.</p>
                @else
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                            <thead>
                                <tr style="text-align:left;color:#6b7280;border-bottom:1px solid rgba(0,0,0,.12)">
                                    <th style="padding:.5rem .25rem">Nama Role</th>
                                    <th style="padding:.5rem .25rem">Deskripsi</th>
                                    <th style="padding:.5rem .25rem;width:1%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roles as $role)
                                    <tr wire:key="role-{{ $role['id'] }}" style="border-bottom:1px solid rgba(0,0,0,.06)">
                                        <td style="padding:.5rem .25rem;font-family:monospace">{{ $role['name'] }}</td>
                                        <td style="padding:.5rem .25rem;color:#6b7280">{{ $role['description'] ?? '—' }}</td>
                                        <td style="padding:.5rem .25rem;white-space:nowrap">
                                            <button type="button"
                                                style="color:#2563eb;background:none;border:0;cursor:pointer;font-size:.75rem;margin-inline-end:.75rem"
                                                wire:click="promptEditRole('{{ $role['name'] }}', @js($role['description'] ?? null))">
                                                Ubah
                                            </button>
                                            <button type="button"
                                                style="color:#dc2626;background:none;border:0;cursor:pointer;font-size:.75rem"
                                                wire:click="promptDeleteRole('{{ $role['name'] }}')">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
