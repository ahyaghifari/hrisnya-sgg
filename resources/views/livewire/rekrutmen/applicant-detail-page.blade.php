<div>
@php $app = $this->application; @endphp

@if (! $app)
    <x-page-header title="Detail Pelamar" :back="url()->previous()" />
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Data pelamar tidak bisa dimuat dari sistem Rekrutmen.</p>
    </div>
@else
    <x-page-header :title="$app['applicant_name'] ?? 'Detail Pelamar'" :subtitle="$app['job_opening']['title'] ?? null" :back="url()->previous()" />

    @php
        $stages = \App\Livewire\Rekrutmen\ApplicantDetailPage::STAGES;
        $stageLabel = ['applied'=>'Lamar','screening'=>'Berkas','shortlisted'=>'Tes','interview'=>'Wawancara','offering'=>'Offering','mcu'=>'MCU','onboarding'=>'Onboarding','passed'=>'Diterima'];
        $current = $app['status'];
        $rejected = $current === 'rejected';
        $currentIdx = array_search($current, $stages, true);
    @endphp

    {{-- Status stepper --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        @if ($rejected)
            <span class="badge badge-rejected" style="font-size:12px;">❌ Ditolak</span>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach ($stages as $i => $s)
                    @php $done = $currentIdx !== false && $i <= $currentIdx; @endphp
                    <span style="font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;
                        background:{{ $done ? 'var(--brand,#7c3aed)' : 'var(--surface-alt,rgba(0,0,0,.05))' }};
                        color:{{ $done ? '#fff' : 'var(--text-faint)' }};">
                        {{ $stageLabel[$s] ?? $s }}
                    </span>
                @endforeach
            </div>
        @endif
        <p style="font-size:11px;color:var(--text-muted);margin:10px 0 0;">{{ $app['applicant_email'] ?? '' }}</p>
    </div>

    {{-- Aksi Seleksi Berkas (Fase 3) --}}
    @if ($this->canDecide && in_array($current, ['applied', 'screening'], true))
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Seleksi Berkas</p>
            @if ($current === 'applied')
                <button type="button" wire:click="prosesSeleksi" wire:loading.attr="disabled" class="btn-primary">
                    <i class="fa-solid fa-folder-open"></i> Proses Seleksi Berkas
                </button>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <button type="button" wire:click="loloskanBerkas" wire:loading.attr="disabled" wire:confirm="Loloskan pelamar ini dari seleksi berkas?" class="btn-primary">
                        <i class="fa-solid fa-check"></i> Loloskan
                    </button>
                    <button type="button" wire:click="$toggle('showTolakForm')" class="btn-ghost" style="color:#dc2626;">
                        <i class="fa-solid fa-xmark"></i> Tolak
                    </button>
                </div>
                @if ($showTolakForm)
                    <div style="margin-top:12px;padding:12px;border:1px solid var(--border);border-radius:12px;">
                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">Pelamar akan ditandai <strong>Ditolak</strong>. Aksi ini tidak bisa dibatalkan dari HRIS.</p>
                        <div style="display:flex;gap:8px;">
                            <button type="button" wire:click="tolak" wire:loading.attr="disabled" class="btn-primary" style="background:#dc2626;">Ya, Tolak</button>
                            <button type="button" wire:click="$set('showTolakForm', false)" class="btn-ghost">Batal</button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Wawancara (Fase 4) --}}
    @if ($this->canDecide && in_array($current, ['shortlisted', 'interview'], true))
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Wawancara</p>

            @if ($current === 'interview' && ! empty($app['interview']))
                <p style="font-size:12.5px;color:var(--text-body);margin:0 0 12px;">
                    <i class="fa-solid fa-calendar-check" style="color:var(--text-faint);"></i>
                    {{ \Carbon\Carbon::parse($app['interview']['date'])->translatedFormat('j M Y') }}{{ $app['interview']['time'] ? ', ' . $app['interview']['time'] : '' }}
                    @if ($app['interview']['location']) &middot; {{ $app['interview']['location'] }} @endif
                </p>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:10px;">
                <div><label class="form-label">Tanggal</label><input type="date" wire:model="intvDate" class="form-input"></div>
                <div><label class="form-label">Jam</label><input type="time" wire:model="intvTime" class="form-input"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Lokasi</label><input type="text" wire:model="intvLocation" class="form-input" placeholder="Ruang / tautan meeting"></div>
                <div style="grid-column:1/-1;"><label class="form-label">Catatan</label><textarea wire:model="intvNotes" class="form-input" rows="2"></textarea></div>
            </div>
            @error('intvDate') <p style="color:#dc2626;font-size:11px;margin:0 0 8px;">{{ $message }}</p> @enderror

            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <button type="button" wire:click="jadwalkanWawancara" wire:loading.attr="disabled" class="btn-primary">
                    <i class="fa-solid fa-calendar-plus"></i> {{ $current === 'interview' ? 'Ubah Jadwal' : 'Jadwalkan Wawancara' }}
                </button>
                @if ($current === 'shortlisted')
                    <button type="button" wire:click="tolak" wire:confirm="Tandai pelamar TIDAK LULUS tes?" class="btn-ghost" style="color:#dc2626;">Tidak Lulus Tes</button>
                @endif
            </div>

            @if ($current === 'interview')
                <hr style="border:none;border-top:1px solid var(--border-soft);margin:14px 0;">
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 8px;">Hasil wawancara:</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <button type="button" wire:click="catatHasilWawancara('lulus')" wire:loading.attr="disabled" wire:confirm="Loloskan pelamar ke tahap Offering?" class="btn-primary"><i class="fa-solid fa-check"></i> Lulus → Offering</button>
                    <button type="button" wire:click="catatHasilWawancara('tidak_lulus')" wire:loading.attr="disabled" wire:confirm="Tolak pelamar setelah wawancara?" class="btn-ghost" style="color:#dc2626;"><i class="fa-solid fa-xmark"></i> Tidak Lulus</button>
                </div>
            @endif
        </div>
    @endif

    {{-- Offering Letter (Fase 5) --}}
    @if ($this->canDecide && $current === 'offering')
        @php $off = $app['offering'] ?? []; @endphp
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Offering Letter</p>

            <div style="margin-bottom:12px;">
                <label class="form-label">{{ ! empty($off['recruiter_uploaded_at']) ? 'Ganti surat penawaran' : 'Unggah surat penawaran (PDF/DOC/DOCX, maks 5MB)' }}</label>
                <input type="file" wire:model="offeringFile" class="form-input" accept=".pdf,.doc,.docx">
                @error('offeringFile') <p style="color:#dc2626;font-size:11px;margin:4px 0 0;">{{ $message }}</p> @enderror
                <div wire:loading wire:target="offeringFile" style="font-size:11px;color:var(--text-muted);margin-top:4px;">Mengunggah…</div>
                <button type="button" wire:click="unggahOfferingLetter" wire:loading.attr="disabled" wire:target="unggahOfferingLetter,offeringFile" class="btn-primary" style="margin-top:8px;">
                    <i class="fa-solid fa-upload"></i> Kirim
                </button>
            </div>

            @if (! empty($off['recruiter_uploaded_at']))
                <p style="font-size:12.5px;margin:0 0 6px;">
                    <a href="{{ $this->offeringLetterUrl('recruiter') }}" target="_blank" style="color:var(--brand,#7c3aed);text-decoration:none;"><i class="fa-solid fa-file-arrow-down"></i> Surat dari recruiter</a>
                    <span style="color:var(--text-faint);"> · {{ \Carbon\Carbon::parse($off['recruiter_uploaded_at'])->translatedFormat('j M Y') }}</span>
                </p>
            @endif

            @if (! empty($off['signed_uploaded_at']))
                <p style="font-size:12.5px;margin:0 0 12px;">
                    <a href="{{ $this->offeringLetterUrl('signed') }}" target="_blank" style="color:#15803d;text-decoration:none;"><i class="fa-solid fa-file-circle-check"></i> Surat bertanda tangan (dari kandidat)</a>
                    <span style="color:var(--text-faint);"> · {{ \Carbon\Carbon::parse($off['signed_uploaded_at'])->translatedFormat('j M Y') }}</span>
                </p>
                <button type="button" wire:click="lanjutSetelahOffering" wire:loading.attr="disabled" wire:confirm="Lanjutkan kandidat ke tahap berikutnya?" class="btn-primary">
                    <i class="fa-solid fa-arrow-right"></i> {{ ($app['requires_mcu'] ?? true) ? 'Lanjut ke MCU' : 'Lanjut ke Onboarding' }}
                </button>
            @elseif (! empty($off['recruiter_uploaded_at']))
                <p style="font-size:12px;color:var(--text-muted);margin:0;">Menunggu kandidat mengunggah surat bertanda tangan dari portal Rekrutmen.</p>
            @endif
        </div>
    @endif

    {{-- MCU (Fase 6) --}}
    @if ($current === 'mcu')
        @php $mcu = $this->mcuState; @endphp
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 12px;">Medical Check Up</p>

            @php
                $mcuFilled = collect(['nurse','lab','radiology','doctor'])->filter(fn ($k) => ! empty(($mcu ?? [])[$k]))->values()->all();
                $mcuLinks = \App\Livewire\Rekrutmen\McuFormPage::availableStationLinks($jobApplicationId, $mcuFilled);
            @endphp
            @if (count($mcuLinks) > 0)
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
                    @foreach ($mcuLinks as $link)
                        <a href="{{ $link['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                            <i class="fa-solid {{ $link['filled'] ? 'fa-pen' : 'fa-notes-medical' }}"></i>
                            {{ $link['filled'] ? 'Edit ' : 'Isi ' }}{{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($mcu)
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 12px;">
                    @foreach (['nurse'=>'Perawat','lab'=>'Lab','radiology'=>'Radiologi','doctor'=>'Dokter'] as $k => $lbl)
                        <span class="badge {{ empty($mcu[$k]) ? 'badge-pending' : 'badge-approved' }}" style="font-size:10px;">{{ $lbl }}{{ empty($mcu[$k]) ? ' —' : ' ✓' }}</span>
                    @endforeach
                </div>

                @if ($this->canDecide)
                    @if (empty($mcu['doctor']))
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">Keputusan fit/unfit tersedia setelah pemeriksaan dokter diisi.</p>
                    @else
                        <label class="form-label">Catatan keputusan (opsional)</label>
                        <textarea wire:model="mcuCatatan" class="form-input" rows="2" style="margin-bottom:8px;"></textarea>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <button type="button" wire:click="putuskanMcu('fit')" wire:loading.attr="disabled" wire:confirm="Tandai FIT → lanjut ke Onboarding?" class="btn-primary"><i class="fa-solid fa-check"></i> FIT → Onboarding</button>
                            <button type="button" wire:click="putuskanMcu('unfit')" wire:loading.attr="disabled" wire:confirm="Tandai UNFIT → pelamar ditolak?" class="btn-ghost" style="color:#dc2626;"><i class="fa-solid fa-xmark"></i> UNFIT</button>
                        </div>
                    @endif
                @endif
            @else
                <p style="font-size:12px;color:var(--text-muted);margin:0;">Data MCU tidak bisa dimuat dari sistem Rekrutmen.</p>
            @endif
        </div>
    @endif

    {{-- TTD & Onboarding (Fase 7) --}}
    @if ($this->canDecide && $current === 'onboarding')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">TTD &amp; Onboarding</p>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Pastikan kontrak sudah ditandatangani &amp; orientasi selesai sebelum menandai Diterima.</p>
            <button type="button" wire:click="selesaikanOnboarding" wire:loading.attr="disabled" wire:confirm="Tandai pelamar SELESAI (Diterima)? Status jadi passed." class="btn-primary">
                <i class="fa-solid fa-flag-checkered"></i> Tandai Selesai (Diterima)
            </button>
        </div>
    @endif

    {{-- Passed → buat akun karyawan HRIS (Fase 7) --}}
    @if ($current === 'passed')
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">✅ Diterima</p>
            @if ($this->canCreateEmployee)
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px;">Tarik data kandidat dari Rekrutmen jadi akun karyawan HRIS (dedupe by email). Data tabel anak — keluarga/pendidikan/dll — belum ikut, isi manual setelahnya.</p>
                <button type="button" wire:click="buatAkunKaryawan" wire:loading.attr="disabled" wire:confirm="Buat / sinkron akun karyawan HRIS dari data kandidat ini?" class="btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Buat / Sinkron Akun Karyawan
                </button>
            @else
                <p style="font-size:12px;color:var(--text-muted);margin:0;">Pelamar sudah diterima. (Perlu izin buat karyawan untuk membuat akunnya.)</p>
            @endif
        </div>
    @endif

    {{-- Riwayat status --}}
    @if (! empty($app['status_logs']))
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Riwayat Status</p>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach ($app['status_logs'] as $log)
                    <div style="font-size:12px;color:var(--text-body);display:flex;justify-content:space-between;gap:12px;">
                        <span>{{ $log['from'] ?? '—' }} → <strong>{{ $log['to'] }}</strong></span>
                        <span style="color:var(--text-faint);">{{ $log['at'] ? \Carbon\Carbon::parse($log['at'])->translatedFormat('j M Y H:i') : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Data diri kandidat --}}
    @php $c = $this->candidate['candidate'] ?? null; @endphp
    @if (! $c)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Data diri lengkap kandidat baru tersedia setelah tahap Onboarding.</p>
        </div>
    @else
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Data Diri</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 20px;font-size:12.5px;">
                @foreach (['name'=>'Nama','nik'=>'NIK','no_kk'=>'No. KK','religion'=>'Agama','status_marital'=>'Status Kawin','mother_name'=>'Nama Ibu','ktp_address'=>'Alamat KTP','province'=>'Provinsi','city'=>'Kota','no_bpjskes'=>'BPJS Kesehatan','no_bpjstk'=>'BPJS TK','bsi_bank_number'=>'No. Rekening'] as $key => $label)
                    @if (! empty($c[$key]))
                        <div><span style="color:var(--text-faint);">{{ $label }}</span><br><span style="font-weight:600;color:var(--text-body);">{{ $c[$key] }}</span></div>
                    @endif
                @endforeach
            </div>
        </div>

        @php
            $sections = [
                'family_members' => ['Keluarga', ['name'=>'Nama','relationship'=>'Hubungan','occupation'=>'Pekerjaan']],
                'educations' => ['Pendidikan', ['level'=>'Jenjang','institution'=>'Institusi','major'=>'Jurusan','graduation_year'=>'Lulus']],
                'work_experiences' => ['Pengalaman Kerja', ['company_name'=>'Perusahaan','position'=>'Posisi','duration'=>'Lama']],
                'trainings' => ['Pelatihan', ['name'=>'Nama','organizer'=>'Penyelenggara','year'=>'Tahun']],
                'organizations' => ['Organisasi', ['name'=>'Organisasi','position'=>'Jabatan','period'=>'Periode']],
                'references' => ['Referensi', ['name'=>'Nama','relationship'=>'Hubungan','phone'=>'Telepon']],
            ];
        @endphp
        @foreach ($sections as $rel => [$title, $cols])
            @php $rows = $c[$rel] ?? []; @endphp
            @if (! empty($rows))
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;overflow-x:auto;">
                    <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">{{ $title }}</p>
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead><tr style="border-bottom:1px solid var(--border);">
                            @foreach ($cols as $label) <th style="text-align:left;padding:5px 10px;white-space:nowrap;">{{ $label }}</th> @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr style="border-bottom:1px solid var(--border-soft);">
                                    @foreach (array_keys($cols) as $ck) <td style="padding:5px 10px;">{{ $row[$ck] ?? '—' }}</td> @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach

        @if (! empty($c['documents']))
            <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Dokumen</p>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach ($c['documents'] as $doc)
                        <a href="{{ $this->documentUrl($doc['id']) }}" target="_blank" style="font-size:12.5px;color:var(--brand,#7c3aed);text-decoration:none;">
                            <i class="fa-solid fa-file-arrow-down"></i> {{ $doc['document_label'] ?? $doc['doc_type'] ?? 'Dokumen' }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endif
</div>
