<div class="card-surface accent accent-navy">
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-users"></i></div>
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Ringkasan Tim</p>
    </div>

    @if ($loaded)
        <div>
            <p style="font-size:22px;font-weight:800;color:var(--text-heading);margin:0 0 12px;">{{ $this->total }} <span style="font-size:11px;font-weight:600;color:var(--text-muted);">total bawahan</span></p>

            <div class="grid grid-cols-3" style="gap:10px;">
                @forelse ($this->breakdown as $row)
                    <button type="button" wire:click="openModal('{{ $row['name'] }}')" style="background:var(--surface-alt);border:none;border-radius:12px;padding:12px;text-align:center;cursor:pointer;font-family:inherit;">
                        <p style="font-size:20px;font-weight:800;color:{{ $this->colorForType($row['name']) }};margin:0;">{{ $row['count'] }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $row['name'] }}</p>
                    </button>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);grid-column:1/-1;">Belum ada bawahan dengan tipe kepegawaian terisi.</p>
                @endforelse
            </div>
        </div>

        @if ($activeModal)
            <div class="modal-overlay" wire:click="closeModal">
                <div class="modal-box" onclick="event.stopPropagation()">
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Bawahan {{ $activeModal }}</h3>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->employeesForType->count() }} orang.</p>

                    @forelse ($this->employeesForType as $employee)
                        <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                            <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $employee->name }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $employee->profile?->jobPosition?->name ?? '-' }}</p>
                        </div>
                    @empty
                        <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada bawahan di tipe ini.</p>
                    @endforelse

                    <div style="margin-top:16px;text-align:right;">
                        <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p style="font-size:12px;color:var(--text-faint);">Memuat data tim...</p>
    @endif
</div>
