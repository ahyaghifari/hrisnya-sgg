<div>
    <x-page-header title="Jadwal" />

    {{--
        List.js (headless, lihat resources/js/app.js) urus cari/sortir/pagination instan di
        browser. Company tetap lewat Livewire (ganti data dari database) — wire:ignore.self
        cuma mengunci atribut container ini sendiri, anak-anaknya (select company) tetap
        ke-bind Livewire normal.
    --}}
    <div id="schedules-list" data-list-table data-list-value-names="name,type,unit" data-list-page-size="15" wire:ignore.self class="mt-4">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                <x-company-filter :companies="$this->companies" wire:model.live="companyId" />
                <div>
                    <label class="form-label">Cari Jadwal</label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
                        <input type="search" class="form-input search" style="min-width:200px;padding-left:32px;" placeholder="Nama, tipe, atau unit...">
                    </div>
                </div>
            </div>

            <a href="{{ route('presensi.jadwal.tambah') }}" wire:navigate class="btn-primary" style="text-decoration:none;">
                <i class="fa-solid fa-plus"></i> Tambah Jadwal
            </a>
        </div>

        <div class="table-wrap" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;{{ $this->schedules->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th class="sort" data-sort="name" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Nama Jadwal <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tipe</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Unit/Divisi</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @foreach ($this->schedules as $schedule)
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($schedule->color)
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $schedule->color }};margin-right:6px;"></span>
                                @endif
                                <span class="name" style="font-weight:600;">{{ $schedule->name }}</span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span class="type" style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:var(--info-soft-bg);color:var(--info-soft-text);">
                                    {{ $this->scheduleTypeLabel($schedule->schedule_type) }}
                                </span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;"><span class="unit">{{ $schedule->orgStructure?->name ?? '-' }}</span></td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span class="btn-ghost" style="padding:5px 12px;font-size:11.5px;opacity:0.5;cursor:not-allowed;" title="Menyusul">Kelola</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="list-empty-state" style="padding:32px 16px;text-align:center;{{ $this->schedules->isEmpty() ? '' : 'display:none;' }}">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada jadwal untuk perusahaan ini.</p>
        </div>

        <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:14px;">
            <button type="button" class="list-prev btn-ghost" style="padding:6px 14px;font-size:12px;">
                <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i> Sebelumnya
            </button>
            <ul class="pagination" style="display:flex;flex-wrap:wrap;gap:4px;list-style:none;padding:0;margin:0;"></ul>
            <button type="button" class="list-next btn-ghost" style="padding:6px 14px;font-size:12px;">
                Selanjutnya <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
            </button>
        </div>
    </div>
</div>
