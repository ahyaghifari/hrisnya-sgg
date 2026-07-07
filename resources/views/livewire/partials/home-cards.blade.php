<livewire:my-attendance-status-card />

<div class="bento">

    <!-- WELCOME CARD (server-rendered, bukan domain attendance) -->
    <div class="welcome-card bento-welcome">
        <div class="w-deco-1"></div><div class="w-deco-2"></div><div class="w-deco-4"></div>
        <div style="position:relative;z-index:1;">
            <p class="welcome-greeting">Selamat datang kembali 👋</p>
            <h2 class="welcome-name">{{ auth()->user()->name }}</h2>
            <p class="welcome-meta">
                {{ auth()->user()->profile?->jobPosition?->name ?? 'Karyawan' }}
                &nbsp;·&nbsp; NIP {{ auth()->user()->nip }}
                &nbsp;·&nbsp; {{ auth()->user()->company?->name ?? '-' }}
            </p>
        </div>
        <div style="position:relative;z-index:1;margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('data-diri') }}" wire:navigate class="data-diri-btn">
                <i class="fa-solid fa-id-card"></i> Data Diri
            </a>
            <a href="{{ route('pwa.home') }}" wire:navigate class="data-diri-btn">
                <i class="fa-solid fa-mobile-screen-button"></i> Buka Tampilan Mobile
            </a>
        </div>
        <div style="position:relative;z-index:1;margin-top:22px;">
            <p class="welcome-qa-label">Aksi Cepat</p>
            <div class="qa-btn-group">
                @unless (auth()->user()->canBrowseAllCompanies())
                    <a href="{{ route('my-attendance-requests.leave') }}" wire:navigate class="qa-btn"><span class="qa-dot" style="background:#8db3d1;"></span> Cuti</a>
                    <a href="{{ route('my-attendance-requests.permission') }}" wire:navigate class="qa-btn"><span class="qa-dot" style="background:#f0a8d9;"></span> Izin</a>
                    <a href="{{ route('my-attendance-requests.overtime') }}" wire:navigate class="qa-btn"><span class="qa-dot" style="background:#7ee0bb;"></span> Lembur</a>
                @endunless
                @can('view_struktur_organisasi')
                    <a href="{{ route('organization-structure') }}" wire:navigate class="qa-btn"><span class="qa-dot" style="background:#c9a3f0;"></span> Struktur Organisasi</a>
                @endcan
            </div>
        </div>
    </div>

    <!-- PRESENSI: placeholder statis, belum ada endpoint/data clock-in nyata -->
    <div id="absen-section" class="absen-card bento-absensi">
        <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>
        <div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;">
            <span class="absen-label">
                <i class="fa-regular fa-clock"></i> Presensi Hari Ini
            </span>
        </div>
        <div style="position:relative;z-index:1;">
            <p class="absen-title">Belum terintegrasi otomatis</p>
            <p class="absen-sub">Presensi clock-in/out belum tersambung ke sistem ini.</p>
        </div>
    </div>

    <div class="card-pair">
        <livewire:leave-balance-card
            type-keyword="tahunan"
            label="Cuti Tahunan"
            icon="fa-sun"
            color="#042c6c"
            icon-bg="rgba(4,44,108,0.1)"
            grid-class="bento-saldo-1"
            :key="'leave-balance-tahunan'"
        />

        <livewire:leave-balance-card
            type-keyword="sakit"
            label="Cuti Sakit"
            icon="fa-heart-pulse"
            color="#047C54"
            icon-bg="rgba(4,124,84,0.1)"
            grid-class="bento-saldo-2"
            :key="'leave-balance-sakit'"
        />
    </div>


    <livewire:attendance-stat-cards />

    <livewire:pending-approvals-card />

    <livewire:recent-requests-card />

</div>

<!-- TASK & KURSUS: placeholder statis, belum ada modul Task/LMS milik-sendiri yang terhubung -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;margin-top:16px;">

    <!-- TASK SAYA -->
    <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
        <div style="padding:18px 20px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;">
            <div>
                <h3 style="font-size:14px;font-weight:700;color:#1e293b;">Task Saya</h3>
                <p style="font-size:11.5px;color:#64748b;margin-top:2px;">3 belum selesai · 1 overdue</p>
            </div>
            <a href="#" style="font-size:12.5px;font-weight:600;color:#042c6c;text-decoration:none;display:flex;align-items:center;gap:4px;white-space:nowrap;">Lihat semua <i class="fa-solid fa-arrow-right text-xs"></i></a>
        </div>
        <div style="padding:8px 20px 18px;display:flex;flex-direction:column;gap:2px;">
            <div style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #f8fafc;">
                <i class="fa-regular fa-circle" style="color:#fca5a5;font-size:15px;margin-top:2px;"></i>
                <div style="flex:1;">
                    <p style="font-size:13.5px;font-weight:600;color:#1e293b;">Update laporan rekrutmen Juni</p>
                    <p style="font-size:11.5px;color:#b91c1c;font-weight:600;margin-top:2px;"><i class="fa-solid fa-triangle-exclamation"></i> Overdue · 1 Jul 2026</p>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #f8fafc;">
                <i class="fa-regular fa-circle" style="color:#fcd34d;font-size:15px;margin-top:2px;"></i>
                <div style="flex:1;">
                    <p style="font-size:13.5px;font-weight:600;color:#1e293b;">Review dokumen kandidat Finance Staff</p>
                    <p style="font-size:11.5px;color:#a16207;font-weight:500;margin-top:2px;">Due 4 Jul 2026</p>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #f8fafc;">
                <i class="fa-regular fa-circle" style="color:#cbd5e1;font-size:15px;margin-top:2px;"></i>
                <div style="flex:1;">
                    <p style="font-size:13.5px;font-weight:600;color:#1e293b;">Update data absensi mingguan</p>
                    <p style="font-size:11.5px;color:#64748b;margin-top:2px;">Rutin · hari ini</p>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;opacity:0.55;">
                <i class="fa-solid fa-circle-check" style="color:#15803d;font-size:15px;margin-top:2px;"></i>
                <div style="flex:1;">
                    <p style="font-size:13.5px;font-weight:600;color:#1e293b;text-decoration:line-through;">Arsip dokumen onboarding Juni</p>
                    <p style="font-size:11.5px;color:#15803d;font-weight:500;margin-top:2px;">Selesai 30 Jun 2026</p>
                </div>
            </div>
        </div>
    </div>

    <!-- KURSUS SAYA -->
    <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
        <div style="padding:18px 20px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;">
            <div>
                <h3 style="font-size:14px;font-weight:700;color:#1e293b;">Kursus Saya</h3>
                <p style="font-size:11.5px;color:#64748b;margin-top:2px;">Pelatihan & sertifikasi aktif</p>
            </div>
            <a href="#" style="font-size:12.5px;font-weight:600;color:#042c6c;text-decoration:none;display:flex;align-items:center;gap:4px;white-space:nowrap;">Lihat semua <i class="fa-solid fa-arrow-right text-xs"></i></a>
        </div>
        <div style="padding:16px 20px 18px;display:flex;flex-direction:column;gap:12px;">
            <div style="border:1px solid #f1f5f9;border-radius:14px;padding:14px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;">
                    <div>
                        <p style="font-size:13.5px;font-weight:700;color:#1e293b;">Etika Pelayanan Publik</p>
                        <p style="font-size:11.5px;color:#64748b;margin-top:2px;">3 dari 5 modul selesai</p>
                    </div>
                    <span class="badge badge-pending" style="white-space:nowrap;">In Progress</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:60%;background:#047C54;"></div></div>
            </div>
            <div style="border:1px solid #f1f5f9;border-radius:14px;padding:14px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;">
                    <div>
                        <p style="font-size:13.5px;font-weight:700;color:#1e293b;">Manajemen Waktu & Prioritas Kerja</p>
                        <p style="font-size:11.5px;color:#64748b;margin-top:2px;">Quiz akhir minggu depan</p>
                    </div>
                    <span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:99px;font-size:11.5px;font-weight:600;background:#eef2fb;color:#042c6c;white-space:nowrap;">Enrolled</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:25%;background:#042c6c;"></div></div>
            </div>
            <div style="border:1px solid #f1f5f9;border-radius:14px;padding:14px;opacity:0.7;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                    <div>
                        <p style="font-size:13.5px;font-weight:700;color:#1e293b;">K3 Perkantoran Dasar</p>
                        <p style="font-size:11.5px;color:#15803d;font-weight:500;margin-top:2px;">Selesai 12 Jun 2026 · Nilai 88</p>
                    </div>
                    <span class="badge badge-approved" style="white-space:nowrap;"><i class="fa-solid fa-check text-[9px]"></i> Lulus</span>
                </div>
            </div>
        </div>
    </div>

</div>
