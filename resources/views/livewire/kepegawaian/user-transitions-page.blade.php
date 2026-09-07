<div>
<x-page-header title="Perubahan Status Karyawan" subtitle="Promosi, Demosi, Mutasi Internal, dan PHK" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('kepegawaian.mutasi-karyawan.create', ['type' => $type]) }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Ajukan {{ \App\Enum\UserTransitionTypeEnum::from($type)->label() }}
        </a>
    </div>
@endif

<div class="doc-type-layout">
    {{-- Kiri: pilih jenis transisi — sama pola .doc-type-nav yang dipakai halaman Dokumen. --}}
    <div class="doc-type-nav">
        @php
            $typeIcons = [
                'promotion' => 'fa-arrow-up',
                'demotion' => 'fa-arrow-down',
                'mutation' => 'fa-right-left',
                'termination' => 'fa-user-slash',
            ];
        @endphp
        @foreach ($this->types as $t)
            <button type="button" wire:click="setType('{{ $t->value }}')" class="doc-type-btn doc-type-btn-navy {{ $type === $t->value ? 'is-active' : '' }}">
                <i class="fa-solid {{ $typeIcons[$t->value] ?? 'fa-right-left' }}"></i>
                <span>{{ $t->label() }}</span>
                <span class="doc-type-count">{{ $this->transitionCounts->get($t->value, 0) }}</span>
            </button>
        @endforeach
    </div>

    {{-- Kanan: filter + tabel jenis terpilih --}}
    <div style="flex:1;min-width:0;">
        {{-- Filter status approval: sengaja kecil/plain (bukan .section-nav-btn), ini cuma
             filter sekunder — beda dari Dokumen yang statusnya Aktif/Tidak Aktif (soft-delete),
             di sini status approval (Menunggu/Disetujui/Ditolak). --}}
        <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:14px;flex-wrap:wrap;">
            <button type="button" wire:click="setStatus('')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === '' ? 'var(--surface)' : 'transparent' }};color:{{ $status === '' ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === '' ? 'var(--shadow-card)' : 'none' }};">Semua</button>
            @foreach ($this->statuses as $s)
                <button type="button" wire:click="setStatus('{{ $s->value }}')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === $s->value ? 'var(--surface)' : 'transparent' }};color:{{ $status === $s->value ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $status === $s->value ? 'var(--shadow-card)' : 'none' }};">{{ $s->getLabel() }}</button>
            @endforeach
        </div>

        <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
            @if ($this->canBrowseAllCompanies)
                <div style="min-width:220px;">
                    <label class="form-label">Perusahaan</label>
                    <select wire:model.live="companyId" class="form-input">
                        <option value="">Semua perusahaan</option>
                        @foreach ($this->companyOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div style="min-width:220px;flex:1;">
                <label class="form-label">Cari</label>
                <div style="position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nomor / nama karyawan..." style="padding-left:38px;">
                </div>
            </div>
        </div>

        @php $transitions = $this->transitions; @endphp

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @if ($transitions->isEmpty())
                <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan {{ mb_strtolower(\App\Enum\UserTransitionTypeEnum::from($type)->label()) }}.</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($transitions as $transition)
                        @php
                            $s = \App\Enum\UserTransitionStatusEnum::from($transition->status);
                            $badge = match ($s) {
                                \App\Enum\UserTransitionStatusEnum::APPROVED => 'badge-approved',
                                \App\Enum\UserTransitionStatusEnum::REJECTED => 'badge-rejected',
                                default => 'badge-pending',
                            };
                        @endphp
                        <a href="{{ route('kepegawaian.mutasi-karyawan.show', $transition) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                            <div class="pwa-tile-icon neutral"><i class="fa-solid {{ $typeIcons[$transition->transition_type] ?? 'fa-right-left' }}"></i></div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $transition->user?->name ?? '-' }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $transition->number }} &middot; {{ $transition->created_at?->translatedFormat('d M Y') }}</p>
                            </div>
                            <span class="badge {{ $badge }}">{{ $s->getLabel() }}</span>
                        </a>
                    @endforeach
                </div>

                <div style="margin-top:16px;">
                    {{ $transitions->links('livewire.partials.pagination') }}
                </div>
            @endif
        </div>

        <!-- DESKTOP: tabel -->
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $transitions->isEmpty() ? 'display:none;' : '' }}">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nomor</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                            @if ($this->canBrowseAllCompanies)
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            @endif
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Diajukan Pada</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transitions as $transition)
                            @php
                                $s = \App\Enum\UserTransitionStatusEnum::from($transition->status);
                                $badge = match ($s) {
                                    \App\Enum\UserTransitionStatusEnum::APPROVED => 'badge-approved',
                                    \App\Enum\UserTransitionStatusEnum::REJECTED => 'badge-rejected',
                                    default => 'badge-pending',
                                };
                            @endphp
                            <tr wire:key="'ut-'.{{ $transition->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $transition->number }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $transition->user?->name ?? '-' }}</td>
                                @if ($this->canBrowseAllCompanies)
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $transition->user?->company?->name ?? '-' }}</td>
                                @endif
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;"><span class="badge {{ $badge }}">{{ $s->getLabel() }}</span></td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $transition->created_at?->translatedFormat('d M Y') }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <a href="{{ route('kepegawaian.mutasi-karyawan.show', $transition) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-arrow-right"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($transitions->isEmpty())
                <div style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan jenis ini.</p>
                </div>
            @endif

            <div style="margin-top:16px;">
                {{ $transitions->links('livewire.partials.pagination') }}
            </div>
        </div>
    </div>
</div>
</div>
