{{-- Riwayat perubahan 1 field/baris config — dipakai bareng oleh halaman yang punya
     $viewingAuditKey/openAuditLog()/closeAuditLog()/$this->auditLogs (Task/Attendance
     Settings, Komponen Gaji, Mapping Komponen Gaji). --}}
@if ($viewingAuditKey)
    <div class="modal-overlay" wire:click="closeAuditLog">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Perubahan</h3>
                <button type="button" wire:click="closeAuditLog" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div style="max-height:60vh;overflow-y:auto;">
                @forelse ($this->auditLogs as $log)
                    <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);font-size:12.5px;">
                        <div style="display:flex;justify-content:space-between;color:var(--text-muted);font-size:11px;margin-bottom:4px;">
                            <span>{{ $log->user?->name ?? 'Sistem' }}</span>
                            <span>{{ $log->created_at->translatedFormat('d M Y, H:i') }}</span>
                        </div>
                        <p style="margin:0;">{{ $log->old_value ?? '(kosong)' }} &rarr; {{ $log->new_value ?? '(kosong)' }}</p>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px 0;">Belum ada riwayat perubahan.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
