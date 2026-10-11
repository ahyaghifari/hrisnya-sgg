{{-- Lembur hari ini, ditempel DI DALAM kartu Presensi Hari Ini (today-attendance-card.blade.php) - bukan widget
terpisah lagi. Warna putih di atas gradient kartu, sama pola blok Extra/Double Shift. Kosong = tidak ada output. --}}
@php $requests = $this->requests; @endphp
<div>
    @if ($requests->isNotEmpty())
        {{-- Panel sky sendiri (gradient sama .pwa-card-header-sky), kartu Presensi di luarnya tidak diubah warnanya. --}}
        <div style="position:relative;z-index:1;isolation:isolate;overflow:hidden;margin-top:12px;padding:12px 14px;border-radius:14px;border:1px solid rgba(255,255,255,0.35);background:linear-gradient(130deg, #0ea5e9 0%, #0369a1 100%);">
            {{-- Scrollbar baris lampiran: tipis, putih transparan (Firefox: scrollbar-*, Chrome/Safari: ::-webkit-scrollbar). --}}
            <style>
                .ot-strip { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.45) transparent; }
                .ot-strip::-webkit-scrollbar { height: 4px; }
                .ot-strip::-webkit-scrollbar-track { background: transparent; }
                .ot-strip::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.45); border-radius: 999px; }
                .ot-strip::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.7); }
            </style>
            @if ($isHoliday)
                {{-- Hari ini libur tapi ada lembur: petir besar menyala-nyala di latar. Berkedip tidak beraturan (flicker)
                + glow; dimatikan kalau pengguna minta reduced motion. --}}
                <style>
                    @keyframes ot-bolt-flash {
                        0%, 100% { opacity: .16; filter: drop-shadow(0 0 6px rgba(253,224,71,.4)); }
                        6%  { opacity: .75; filter: drop-shadow(0 0 22px rgba(253,224,71,.95)); }
                        10% { opacity: .18; }
                        16% { opacity: .9;  filter: drop-shadow(0 0 28px rgba(253,224,71,1)); }
                        24% { opacity: .22; }
                        62% { opacity: .2; }
                        66% { opacity: .6;  filter: drop-shadow(0 0 18px rgba(253,224,71,.8)); }
                        72% { opacity: .18; }
                    }
                    @keyframes ot-spark {
                        0%, 100% { opacity: 0; transform: scale(.4); }
                        45%, 55% { opacity: 1; transform: scale(1); }
                    }
                    @media (prefers-reduced-motion: reduce) {
                        .ot-bolt { animation: none !important; opacity: .3 !important; }
                        .ot-spark { animation: none !important; opacity: .6 !important; transform: none !important; }
                    }
                </style>
                {{-- Titik kuning kecil bermunculan/menyala di sekitar petir (posisi % tetap, delay & durasi beda-beda
                supaya kedipnya tidak serempak). --}}
                @foreach ([[8, 20, 4, 0, 2.4], [18, 70, 3, 0.7, 3.1], [30, 35, 5, 1.3, 2.7], [42, 80, 3, 0.4, 3.4], [52, 15, 4, 1.9, 2.2], [63, 55, 3, 0.9, 3.0], [72, 25, 5, 1.6, 2.6], [80, 75, 4, 0.2, 3.3], [88, 12, 3, 1.1, 2.5], [93, 50, 4, 2.2, 2.9], [58, 88, 3, 1.5, 2.3], [24, 10, 3, 2.5, 3.2]] as [$x, $y, $s, $delay, $dur])
                    <span class="ot-spark" aria-hidden="true"
                          style="position:absolute;z-index:0;left:{{ $x }}%;top:{{ $y }}%;width:{{ $s }}px;height:{{ $s }}px;border-radius:50%;background:#fde047;box-shadow:0 0 {{ $s * 2 }}px {{ $s / 2 }}px rgba(253,224,71,.85);pointer-events:none;opacity:0;animation:ot-spark {{ $dur }}s ease-in-out {{ $delay }}s infinite;"></span>
                @endforeach
                <i class="fa-solid fa-bolt ot-bolt" aria-hidden="true"
                   style="position:absolute;z-index:0;right:-4px;bottom:-34px;font-size:170px;line-height:1;color:#fde047;pointer-events:none;transform:rotate(12deg);animation:ot-bolt-flash 3.2s ease-in-out infinite;"></i>
            @endif
            <div style="position:relative;z-index:1;">
            <p style="font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:rgba(255,255,255,0.8);margin:0 0 8px;">
                <i class="fa-solid fa-business-time" style="margin-right:4px;"></i> Lembur Hari Ini
            </p>
            @foreach ($requests as $req)
                @php $approved = $req->status === 'approved'; @endphp
                {{-- Badge status absolut di pojok kanan atas (pola tombol sync di kartu Presensi). Pengajuan pertama
                menempel ke pojok panel (blok tidak relative), pengajuan berikutnya ke pojok blok-nya sendiri. --}}
                <div style="{{ $loop->first ? '' : 'position:relative;border-top:1px dashed rgba(255,255,255,0.25);padding-top:10px;margin-top:10px;' }}">
                    <span style="position:absolute;top:{{ $loop->first ? '0' : '8px' }};right:0;white-space:nowrap;font-size:10.5px;font-weight:700;border-radius:999px;padding:2px 9px;background:rgba(255,255,255,0.22);color:#fff;">
                        {{ $approved ? 'Disetujui' : 'Menunggu persetujuan' }}
                    </span>
                    <div style="{{ $loop->first ? '' : 'padding-right:136px;' }}">
                        <div style="min-width:0;">
                            @foreach ($req->overtimeDetails as $d)
                                <p style="font-size:12.5px;font-weight:700;color:#fff;margin:0;">
                                    {{ $d->start_time ? substr($d->start_time, 0, 5) : '-' }}&ndash;{{ $d->end_time ? substr($d->end_time, 0, 5) : '-' }}
                                    @if ($d->task_description)<span style="font-weight:500;opacity:.85;"> · {{ \Illuminate\Support\Str::limit($d->task_description, 60) }}</span>@endif
                                </p>
                            @endforeach
                        </div>
                    </div>

                    @php $max = \App\Models\Attendance\AttendanceRequestAttachments::MAX_OVERTIME; @endphp
                    {{-- Lampiran berjajar menyamping, tiap kotak 1:1 (gambar = thumbnail, PDF = ikon), geser kalau sempit.
                    Maksimal {{ $max }}; kotak tambah hilang kalau sudah penuh. Gambar dikompres otomatis di server. --}}
                    <div x-data="{ lb: null }" x-on:keydown.escape.window="lb = null" class="ot-strip" style="display:flex;flex-wrap:nowrap;gap:8px;margin-top:10px;overflow-x:auto;padding-bottom:2px;">
                        {{-- Lightbox gambar: overlay fixed di-teleport ke body (panel ini overflow:hidden), jadi tidak pindah halaman.
                        PDF tetap buka tab baru. Klik latar / Esc / tombol X menutup. --}}
                        <template x-teleport="body">
                            {{-- flex ada di elemen DALAM: x-show menghapus properti display inline elemen luar saat tampil. --}}
                            <div x-show="lb" x-cloak x-transition.opacity.duration.150ms x-on:click="lb = null"
                                 style="position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,0.85);">
                                <button type="button" x-on:click.stop="lb = null" aria-label="Tutup"
                                        style="position:absolute;top:14px;right:14px;z-index:1;width:40px;height:40px;border-radius:50%;border:0;background:rgba(255,255,255,0.18);color:#fff;font-size:18px;cursor:pointer;">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                                <div style="width:100%;height:100%;box-sizing:border-box;padding:16px;display:flex;align-items:center;justify-content:center;">
                                    <img :src="lb" alt="Lampiran" x-on:click.stop style="max-width:100%;max-height:100%;object-fit:contain;border-radius:10px;">
                                </div>
                            </div>
                        </template>
                        @foreach ($req->attachments as $att)
                            {{-- Titik tiga di pojok tiap kotak: buka panel kecil DI DALAM kotak (bukan dropdown, strip ini overflow
                            sehingga dropdown terpotong) berisi Hapus / Batal. --}}
                            <div x-data="{ menu: false }" wire:key="att-{{ $att->id }}"
                                 style="position:relative;flex:0 0 76px;width:76px;aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.35);">
                                <a href="{{ $att->file_url }}" target="_blank" rel="noopener" title="Lampiran {{ $loop->iteration }}"
                                   @if ($att->is_image) x-on:click.prevent="lb = @js($att->file_url)" @endif
                                   style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:#fff;text-decoration:none;font-size:10.5px;font-weight:700;">
                                    @if ($att->is_image)
                                        <img src="{{ $att->file_url }}" alt="Lampiran {{ $loop->iteration }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;">
                                    @else
                                        <i class="fa-solid fa-file-pdf" style="font-size:22px;"></i>
                                        <span>PDF {{ $loop->iteration }}</span>
                                    @endif
                                </a>
                                <button type="button" x-on:click="menu = true" aria-label="Menu lampiran"
                                        style="position:absolute;top:3px;right:3px;width:24px;height:24px;border:0;background:none;color:#fff;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-shadow:0 0 3px rgba(2,6,23,0.9),0 0 6px rgba(2,6,23,0.6);filter:drop-shadow(0 0 2px rgba(2,6,23,0.8));">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                {{-- flex di elemen dalam: x-show menghapus display inline elemen yang sama. --}}
                                <div x-show="menu" x-cloak x-on:click.outside="menu = false"
                                     style="position:absolute;inset:0;background:rgba(2,6,23,0.8);">
                                    <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:stretch;justify-content:center;gap:6px;padding:8px;box-sizing:border-box;">
                                        <button type="button" wire:click="deleteAttachment('{{ $req->id }}', '{{ $att->id }}')"
                                                wire:confirm="Hapus lampiran ini?" wire:loading.attr="disabled" wire:target="deleteAttachment"
                                                style="border:0;border-radius:8px;padding:6px 0;background:#dc2626;color:#fff;font-size:11px;font-weight:700;cursor:pointer;">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </button>
                                        <button type="button" x-on:click="menu = false"
                                                style="border:0;border-radius:8px;padding:6px 0;background:rgba(255,255,255,0.2);color:#fff;font-size:11px;font-weight:700;cursor:pointer;">
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Kotak tambah (border putus-putus, 1:1 sama ukuran thumbnail): label membungkus input file tersembunyi,
                        unggah otomatis begitu file dipilih, lalu input dikosongkan supaya file yang sama bisa dipilih lagi. --}}
                        @if ($req->attachments->count() < $max)
                            <label x-data title="Tambah lampiran (maks {{ $max }}, sisa {{ $max - $req->attachments->count() }})"
                                   style="flex:0 0 76px;width:76px;aspect-ratio:1/1;border-radius:12px;border:2px dashed rgba(255,255,255,0.75);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:#fff;font-size:10.5px;font-weight:700;cursor:pointer;text-align:center;opacity:0.6;">
                                <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png" style="display:none;"
                                       wire:model="files.{{ $req->id }}"
                                       x-on:livewire-upload-finish="$wire.addAttachments('{{ $req->id }}'); $el.value = ''">
                                <span wire:loading.remove wire:target="files.{{ $req->id }},addAttachments" style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                    <i class="fa-solid fa-plus" style="font-size:18px;"></i>Tambah
                                </span>
                                <span wire:loading.flex wire:target="files.{{ $req->id }},addAttachments" style="flex-direction:column;align-items:center;gap:4px;">
                                    <i class="fa-solid fa-spinner fa-spin" style="font-size:18px;"></i>Unggah
                                </span>
                            </label>
                        @endif
                    </div>
                    <p style="font-size:10.5px;color:rgba(255,255,255,0.8);margin:6px 0 0;">{{ $req->attachments->count() }}/{{ $max }} lampiran</p>
                    @error("files.{$req->id}") <span style="display:block;margin-top:4px;font-size:11px;font-weight:700;color:#fecaca;">{{ $message }}</span> @enderror
                    @error("files.{$req->id}.*") <span style="display:block;margin-top:4px;font-size:11px;font-weight:700;color:#fecaca;">{{ $message }}</span> @enderror
                </div>
            @endforeach
            </div>
        </div>
    @endif
</div>
