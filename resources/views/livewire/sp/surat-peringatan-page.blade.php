<div>
    <h4 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Surat Peringatan</h4>

    {{-- Berita Acara, Keputusan, dan Surat Peringatan dalam SATU daftar; tahapnya tampil di tiap baris. --}}
    <div class="pwa-list">
        @forelse ($cases as $case)
            @include('livewire.sp.partials.case-row', ['case' => $case, 'mine' => true])
        @empty
            <div class="pwa-list-row" style="opacity:0.7;">
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Belum ada data</p>
                    <span style="font-size:11.5px;color:var(--text-muted);">Riwayat Berita Acara dan Surat Peringatan untuk Anda akan muncul di sini.</span>
                </div>
            </div>
        @endforelse
    </div>
</div>
