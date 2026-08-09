<div>
<x-page-header title="Jabatan" subtitle="Daftar jabatan perusahaan beserta kuota dan status pengajuan" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="{{ route('kepegawaian.jabatan.tambah') }}" wire:navigate class="btn-primary">
        <i class="fa-solid fa-plus"></i> Ajukan Jabatan Baru
    </a>
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
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama jabatan..." style="padding-left:38px;">
        </div>
    </div>
</div>

@php $jabatan = $this->jabatan; @endphp

<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="data-table" style="min-width:680px;font-size:12px;">
            <thead>
                <tr>
                    <th>Nama Jabatan</th>
                    <th>Unit</th>
                    <th style="text-align:center;">Kuota</th>
                    <th style="text-align:center;">Jumlah Karyawan</th>
                    <th style="text-align:center;">Status</th>
                    <th style="text-align:center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($jabatan as $row)
                    @php
                        $badge = match ($row->status) {
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            default => 'badge-pending',
                        };
                    @endphp
                    <tr>
                        <td style="font-weight:600;color:var(--text-heading);white-space:nowrap;">{{ $row->name }}</td>
                        <td>{{ $row->orgStructure?->name ?? '-' }}</td>
                        <td style="text-align:center;">{{ $row->actual_manpower ?? 0 }}</td>
                        <td style="text-align:center;">{{ $row->user_assignments_count }}</td>
                        <td style="text-align:center;"><span class="badge {{ $badge }}">{{ ucfirst($row->status) }}</span></td>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="{{ route('kepegawaian.jabatan.edit', $row) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat/Edit"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" wire:click="openHistory('{{ $row->id }}')" class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Riwayat"><i class="fa-solid fa-clock-rotate-left"></i></button>
                            @if ($row->status === 'approved')
                                @can('Create:ManpowerRequests')
                                    <a href="{{ route('kepegawaian.jabatan.tambah-kuota', $row) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Ajukan Penambahan Kuota"><i class="fa-solid fa-square-plus"></i></a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:32px;text-align:center;color:var(--text-faint);">
                            @if ($search !== '')
                                Tidak ada jabatan yang cocok dengan pencarian "{{ $search }}".
                            @else
                                Belum ada jabatan.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:12px 16px;border-top:1px solid var(--border-soft);">
        {{ $jabatan->links('livewire.partials.pagination') }}
    </div>
</div>

@if ($this->historyRecord)
    <div class="modal-overlay" wire:click="closeHistory">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Pengajuan</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $this->historyRecord->name }}</p>
                </div>
                <button type="button" wire:click="closeHistory" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;">
                @forelse ($this->historyRecord->approvalHistories as $history)
                    @php
                        $actionLabel = match ($history->action) {
                            'requested' => 'Diajukan',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            'completed' => 'Selesai',
                            default => $history->action,
                        };
                        $actionBadge = match ($history->action) {
                            'approved', 'completed' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            default => 'badge-pending',
                        };
                    @endphp
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:10px;background:var(--surface-alt);">
                        <div style="min-width:0;">
                            <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $history->actor_name }}</p>
                            @if ($history->note)
                                <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $history->note }}</p>
                            @endif
                            <p style="font-size:11px;color:var(--text-faint);margin:4px 0 0;">{{ $history->created_at?->translatedFormat('d F Y H:i') }}</p>
                        </div>
                        <span class="badge {{ $actionBadge }}" style="white-space:nowrap;">{{ $actionLabel }}</span>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-faint);text-align:center;padding:16px 0;">Belum ada riwayat.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
</div>
