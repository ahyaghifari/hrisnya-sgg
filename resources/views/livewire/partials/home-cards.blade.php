<div id="push-notif-banner" style="display:none;background:#eef2fb;border:1px solid #dde3f0;border-radius:14px;padding:12px 14px;margin-bottom:16px;align-items:center;gap:12px;">
    <i class="fa-solid fa-bell" style="color:#042c6c;"></i>
    <p style="flex:1;font-size:12.5px;color:#374151;margin:0;">Aktifkan notifikasi agar tahu saat pengajuan Anda disetujui/ditolak.</p>
    <button type="button" onclick="pwaSubscribeToPush()" style="background:#042c6c;color:#fff;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">Aktifkan</button>
    <button type="button" onclick="pwaDismissPushBanner()" style="background:none;border:none;color:#94a3b8;font-size:12px;cursor:pointer;">Nanti</button>
</div>

<livewire:my-attendance-status-card />

<div class="bento pwa-bento">

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
            <a href="{{ route('organization-structure') }}" wire:navigate class="qa-btn">Struktur Organisasi</a>
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

    {{-- Cuti Tahunan + Jumlah Izin + Jumlah Lembur, berdampingan dengan presensi di layar lebar.
    Dua variasi ukuran (LeaveBalanceCard/AttendanceStatCards menghitung style-nya di server lewat
    prop `compact`, jadi tidak bisa satu markup untuk semua breakpoint) — visibility diatur lewat
    class .bento-side-stats(-compact) di app.css, bukan Tailwind md:hidden (lihat komentar di sana). --}}
    <div class="bento-side-stats-compact">
        <livewire:leave-balance-card
            type-keyword="tahunan"
            label="Cuti Tahunan"
            icon="fa-sun"
            color="#042c6c"
            icon-bg="rgba(4,44,108,0.1)"
            grid-class=""
            :compact="true"
            :key="'leave-balance-tahunan-compact'"
        />
        <livewire:attendance-stat-cards :compact="true" :key="'attendance-stat-cards-compact'" />
    </div>
    <div class="bento-side-stats">
        <livewire:leave-balance-card
            type-keyword="tahunan"
            label="Cuti Tahunan"
            icon="fa-sun"
            color="#042c6c"
            icon-bg="rgba(4,44,108,0.1)"
            grid-class=""
            :key="'leave-balance-tahunan'"
        />
        <livewire:attendance-stat-cards :key="'attendance-stat-cards'" />
    </div>

    {{-- <livewire:recent-requests-card /> --}}

    <!-- TASK & KURSUS: placeholder statis, belum ada modul Task/LMS milik-sendiri yang terhubung.
    Sekelompok dengan welcome/presensi/saldo di grid .bento yang sama (bukan section terpisah di
    luar grid), supaya breakpoint-nya konsisten: 1 kolom di mobile, 2 kolom berdampingan di desktop. -->
    <div class="bento-task-kursus">
        <div class="pwa-list">
            <div class="pwa-card-header pwa-card-header-gold">
                <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                <div class="pwa-card-header-content">
                    <span class="pwa-section-title">Task Saya</span>
                    <a href="#" class="pwa-section-link">Semua <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
                </div>
            </div>
            <div class="pwa-list-row">
                <div class="pwa-tile-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Update laporan rekrutmen Juni</p>
                    <span class="badge badge-rejected" style="margin-top:4px;">Overdue · 1 Jul 2026</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </div>
            <div class="pwa-list-row">
                <div class="pwa-tile-icon warn"><i class="fa-regular fa-clock"></i></div>
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Review dokumen kandidat Finance Staff</p>
                    <span class="badge badge-pending" style="margin-top:4px;">Due 4 Jul 2026</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </div>
            <div class="pwa-list-row">
                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-list-check"></i></div>
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title">Update data absensi mingguan</p>
                    <span class="badge badge-cancelled" style="margin-top:4px;">Rutin · hari ini</span>
                </div>
                <i class="fa-solid fa-chevron-right"></i>
            </div>
            <div class="pwa-list-row" style="opacity:0.6;">
                <div class="pwa-tile-icon done"><i class="fa-solid fa-check"></i></div>
                <div style="flex:1;min-width:0;">
                    <p class="pwa-row-title" style="text-decoration:line-through;">Arsip dokumen onboarding Juni</p>
                    <span class="badge badge-approved" style="margin-top:4px;">Selesai 30 Jun 2026</span>
                </div>
            </div>
        </div>

        <div id="kursus-section" class="pwa-list" style="padding-bottom:16px;">
            <div class="pwa-card-header pwa-card-header-violet" style="margin-bottom:14px;">
                <div class="pwa-card-header-deco-1"></div><div class="pwa-card-header-deco-2"></div>
                <div class="pwa-card-header-content">
                    <span class="pwa-section-title">Kursus Saya</span>
                    <a href="#" class="pwa-section-link">Semua <i class="fa-solid fa-chevron-right" style="font-size:9px;"></i></a>
                </div>
            </div>
            <div class="pwa-scroll-row" style="padding-left:16px;padding-right:16px;">
                <div class="pwa-course-chip">
                    <div class="pwa-tile-icon neutral" style="margin-bottom:10px;"><i class="fa-solid fa-book-open"></i></div>
                    <p class="pwa-course-title">Etika Pelayanan Publik</p>
                    <p class="pwa-course-sub">3 dari 5 modul</p>
                    <div class="progress-track"><div class="progress-fill" style="width:60%;background:#047C54;"></div></div>
                    <span class="badge badge-pending" style="margin-top:8px;">In Progress</span>
                </div>
                <div class="pwa-course-chip" style="opacity:0.8;">
                    <div class="pwa-tile-icon done" style="margin-bottom:10px;"><i class="fa-solid fa-award"></i></div>
                    <p class="pwa-course-title">K3 Perkantoran Dasar</p>
                    <p class="pwa-course-sub" style="color:#15803d;font-weight:500;">Nilai 88</p>
                    <div class="progress-track"><div class="progress-fill" style="width:100%;background:#15803d;"></div></div>
                    <span class="badge badge-approved" style="margin-top:8px;"><i class="fa-solid fa-check text-[9px]"></i> Lulus</span>
                </div>
            </div>
        </div>
    </div>

    {{-- <livewire:pending-approvals-card /> --}}

</div>


@push('scripts')

<script>
function pwaDismissPushBanner() {
    document.getElementById('push-notif-banner').style.display = 'none';
    localStorage.setItem('pushBannerDismissedAt', Date.now());
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function pwaSubscribeToPush() {
    const vapidKey = document.querySelector('meta[name="vapid-public-key"]').content;
    const registration = await navigator.serviceWorker.ready;
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        pwaDismissPushBanner();
        return;
    }

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey),
    });

    await fetch('{{ route('push.subscribe') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(subscription.toJSON()),
    });

    document.getElementById('push-notif-banner').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', async () => {
    const banner = document.getElementById('push-notif-banner');
    const dismissedAt = localStorage.getItem('pushBannerDismissedAt');
    const dismissedRecently = dismissedAt && (Date.now() - dismissedAt) < 7 * 24 * 60 * 60 * 1000;

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || dismissedRecently) {
        return;
    }
    if (Notification.permission !== 'default') {
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    if (!existing) {
        banner.style.display = 'flex';
    }
});
</script>
@endpush
