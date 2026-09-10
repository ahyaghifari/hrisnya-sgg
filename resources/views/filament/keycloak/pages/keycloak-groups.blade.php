<x-filament-panels::page>
    <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap">
        {{-- KIRI: pohon grup --}}
        <div style="width:340px;flex:1 1 280px;max-width:380px">
            <x-filament::section>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
                    <span style="font-weight:600;font-size:.875rem">Grup</span>
                    <div style="display:flex;gap:.375rem">
                        <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshTree">
                            Muat Ulang
                        </x-filament::button>
                        <x-filament::button size="xs" icon="heroicon-o-plus" wire:click="newRootGroup">
                            Grup
                        </x-filament::button>
                    </div>
                </div>

                @if ($treeError)
                    <div style="border-radius:.5rem;padding:.75rem;font-size:.8125rem;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca">
                        Gagal memuat grup: {{ $treeError }}
                    </div>
                @elseif (empty($tree))
                    <p style="font-size:.8125rem;color:#6b7280">Belum ada grup.</p>
                @else
                    @foreach ($tree as $node)
                        @include('filament.keycloak.pages._group-node', ['node' => $node, 'depth' => 0, 'selectedId' => $selectedId])
                    @endforeach
                @endif
            </x-filament::section>
        </div>

        {{-- KANAN: anggota grup terpilih --}}
        <div style="flex:2 1 420px;min-width:0">
            <x-filament::section>
                @if (! $selectedId)
                    <p style="font-size:.875rem;color:#6b7280">Pilih grup di kiri untuk melihat &amp; assign anggota.</p>
                @else
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem">
                        <div>
                            <div style="font-weight:600">{{ $selectedName }}</div>
                            <div style="font-size:.75rem;color:#6b7280">{{ count($members) }} anggota</div>
                        </div>
                        <div style="display:flex;gap:.375rem;flex-wrap:wrap">
                            <x-filament::button size="xs" icon="heroicon-o-user-plus" wire:click="mountAction('addMember')">
                                Assign Anggota
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-plus" wire:click="newSubgroup">
                                Sub-grup
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-pencil-square" wire:click="editSelected">
                                Ubah Nama
                            </x-filament::button>
                            <x-filament::button size="xs" color="danger" icon="heroicon-o-trash" wire:click="deleteSelected">
                                Hapus
                            </x-filament::button>
                        </div>
                    </div>

                    @if (empty($members))
                        <p style="font-size:.875rem;color:#6b7280">Belum ada anggota.</p>
                    @else
                        <div style="overflow-x:auto">
                            <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                                <thead>
                                    <tr style="text-align:left;color:#6b7280;border-bottom:1px solid rgba(0,0,0,.12)">
                                        <th style="padding:.5rem .25rem">Username</th>
                                        <th style="padding:.5rem .25rem">Nama</th>
                                        <th style="padding:.5rem .25rem">Email</th>
                                        <th style="padding:.5rem .25rem;width:1%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($members as $m)
                                        <tr wire:key="mbr-{{ $m['id'] }}" style="border-bottom:1px solid rgba(0,0,0,.06)">
                                            <td style="padding:.5rem .25rem">{{ $m['username'] ?? $m['id'] }}</td>
                                            <td style="padding:.5rem .25rem">{{ trim(($m['firstName'] ?? '') . ' ' . ($m['lastName'] ?? '')) ?: '—' }}</td>
                                            <td style="padding:.5rem .25rem;color:#6b7280">{{ $m['email'] ?? '—' }}</td>
                                            <td style="padding:.5rem .25rem">
                                                <button type="button"
                                                    style="color:#dc2626;background:none;border:0;cursor:pointer;font-size:.75rem;white-space:nowrap"
                                                    wire:click="promptRemoveMember('{{ $m['id'] }}')">
                                                    Keluarkan
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </x-filament::section>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
