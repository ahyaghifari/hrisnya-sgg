<div class="card-surface accent accent-navy">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-users"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Status Kepegawaian</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
        @forelse ($this->breakdown as $row)
            <button type="button" wire:click="openModal('{{ $row['name'] }}')" style="background:var(--surface-alt);border:none;border-radius:12px;padding:12px;text-align:center;cursor:pointer;font-family:inherit;">
                <p style="font-size:22px;font-weight:800;color:{{ $this->colorForType($row['name']) }};margin:0;">{{ $row['count'] }}</p>
                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['name'] }}</p>
            </button>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);grid-column:1/-1;">Belum ada karyawan dengan tipe kepegawaian terisi.</p>
        @endforelse
    </div>

    <p class="section-title" style="font-size:12.5px;margin-bottom:8px;">Kontrak / Orientasi Akan Berakhir</p>
    <div class="pwa-list">
        @foreach ($this->expiringStatusRows as $row)
            <button type="button" wire:click="openModal('{{ $row['key'] }}')" class="pwa-list-row" style="width:100%;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;">
                <div class="pwa-tile-icon {{ $row['tile'] }}"><i class="fa-solid {{ $row['icon'] }}"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $row['count'] }} karyawan <span class="badge {{ $row['typeBadge'] }}" style="margin-left:4px;">{{ $row['type'] }}</span></p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['desc'] }}</p>
                </div>
                <span class="badge {{ $row['urgencyBadge'] }}">{{ $row['urgencyLabel'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Modal tile tipe kepegawaian (Tetap/Kontrak/Orientasi, dsb - nama dinamis dari master data) --}}
    @if ($activeModal && collect($this->breakdown)->pluck('name')->contains($activeModal))
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Karyawan {{ $activeModal }}</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->employeesForType->count() }} karyawan.</p>

                @forelse ($this->employeesForType as $employee)
                    <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $employee->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $employee->profile?->jobPosition?->name ?? '-' }}</p>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada karyawan di tipe ini.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal baris "Akan Berakhir" (kontrak-urgent/orientasi-urgent/kontrak-attention) --}}
    @if (in_array($activeModal, ['kontrak-urgent', 'orientasi-urgent', 'kontrak-attention'], true))
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">
                    {{ $activeModal === 'orientasi-urgent' ? 'Orientasi Akan Berakhir' : 'Kontrak Akan Berakhir' }}
                </h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->expiringDetail->count() }} karyawan, diurutkan dari yang paling dekat jatuh tempo.</p>

                @forelse ($this->expiringDetail->sortBy(fn ($item) => $activeModal === 'orientasi-urgent' ? $item->effective_end_date : $item->end_date) as $item)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <div>
                            <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ ($activeModal === 'orientasi-urgent' ? $item->user?->name : $item->employee?->nama_lengkap) ?? '-' }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ ($activeModal === 'orientasi-urgent' ? $item->user?->profile?->jobPosition?->name : $item->employee?->jobPosition?->name) ?? '-' }}</p>
                        </div>
                        <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">
                            {{ \Illuminate\Support\Carbon::parse($activeModal === 'orientasi-urgent' ? $item->effective_end_date : $item->end_date)->translatedFormat('d M Y') }}
                        </span>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada yang mendekati jatuh tempo.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
