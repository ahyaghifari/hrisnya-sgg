<div class="card-surface accent accent-navy">
    <x-my-staff.widget-head icon="fa-users" title="Ringkasan Tim" sub="Komposisi bawahan menurut status kepegawaian" />

    @if ($loaded)
        @php
            $total = $this->total;
            $rows = $this->breakdown;
        @endphp
        <div>
            <p class="ms-total"><span class="ms-total-num">{{ $total }}</span> <span class="ms-total-label">total bawahan</span></p>

            @if ($rows->isNotEmpty())
                <div class="ms-bar" role="img" aria-label="Komposisi tipe kepegawaian">
                    @foreach ($rows as $row)
                        <span class="ms-t-{{ $this->styleForType($row['name'])['tone'] }}" style="flex:{{ $row['count'] }};" title="{{ $row['name'] }}: {{ $row['count'] }}"></span>
                    @endforeach
                </div>
            @endif

            <div class="ms-grid">
                @forelse ($rows as $row)
                    @php $style = $this->styleForType($row['name']); @endphp
                    <button type="button" wire:click="openModal('{{ $row['name'] }}')" class="ms-stat ms-t-{{ $style['tone'] }}">
                        <span class="ms-stat-icon"><i class="fa-solid {{ $style['icon'] }}"></i></span>
                        <div>
                            <p class="ms-stat-num">{{ $row['count'] }}</p>
                            <p class="ms-stat-label">{{ $row['name'] }}</p>
                        </div>
                        <p class="ms-stat-foot">{{ $total ? round($row['count'] / $total * 100) : 0 }}% dari tim</p>
                    </button>
                @empty
                    <div class="ms-empty" style="grid-column:1/-1;">
                        <i class="fa-solid fa-user-slash"></i>
                        <span>Belum ada bawahan dengan tipe kepegawaian terisi.</span>
                    </div>
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
        <p class="ms-loading">Memuat data tim...</p>
    @endif
</div>
