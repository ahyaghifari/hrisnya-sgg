// ponytail: network-first with cache fallback — every successful same-origin GET
// (page navigations + build assets) is cached, so offline reloads show the last-seen
// version instead of the browser's no-connection page. Not stale-while-revalidate or
// precaching; upgrade to Workbox if per-route strategies are needed later.
const CACHE = 'hris-runtime-v8';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    // /login jangan di-cache: token CSRF basi -> 419 "Page Expired" saat submit.
    if (req.method !== 'GET' || !req.url.startsWith(self.location.origin) || new URL(req.url).pathname === '/login') {
        return;
    }

    event.respondWith(
        fetch(req)
            .then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((cache) => cache.put(req, copy));
                }
                return res;
            })
            .catch(() => caches.match(req).then((cached) => cached || Response.error()))
    );
});

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'HRIS Syifa Global', {
            body: payload.body || '',
            icon: payload.icon || '/pwa/icons/icon-192-v3.png',
            // badge-192.png: cuma mark "S" (di-crop dari icon-512-v3.png, warna asli
            // dipertahankan), background BENAR-BENAR transparan — WAJIB begini, bukan
            // icon-192-v3.png (background putih solid/opaque). Android render badge status-bar
            // cuma dari channel alpha PNG ini sebagai siluet monokrom (warnanya diabaikan di situ,
            // dipakai apa adanya kalau browser tampilkan versi berwarna); kalau background-nya
            // opaque, seluruh kotak dianggap "terisi" dan tampil kotak putih polos, bukan logo.
            badge: payload.badge || '/pwa/icons/badge-192-v2.png',
            data: payload.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const requestId = event.notification.data?.request_id;
    const requestType = event.notification.data?.request_type;
    const payrollId = event.notification.data?.payroll_id;
    const projectId = event.notification.data?.project_id;
    const courseId = event.notification.data?.course_id;
    const employeeSpId = event.notification.data?.employee_sp_id;

    const documentSignId = event.notification.data?.document_sign_id;
    const spReportUrl = event.notification.data?.sp_report_url;
    const invitationUrl = event.notification.data?.invitation_url;

    const announcementId = event.notification.data?.announcement_id;

    let url = '/';
    if (announcementId) {
        url = `/pengumuman/${announcementId}`;
    } else if (documentSignId) {
        url = `/dokumen/tandatangani/${documentSignId}`;
    } else if (invitationUrl) {
        // Undangan (klarifikasi, coaching, dst.) -> halaman baca-saja undangan.
        url = invitationUrl;
    } else if (spReportUrl) {
        url = spReportUrl;
    } else if (employeeSpId) {
        // SpIssued -> halaman detail SP milik karyawan sendiri.
        url = `/surat-peringatan/${employeeSpId}`;
    } else if (payrollId) {
        // Notifikasi Gaji Sudah Dibayar -> halaman detail slip gaji karyawan sendiri.
        url = `/profil-saya/penggajian/${payrollId}`;
    } else if (courseId) {
        // CoursePublished/CourseParticipantEnrolled/CourseEnrollmentApproved -> halaman detail
        // pelatihan (self-service karyawan, bukan halaman kelola admin).
        url = `/pelatihan/${courseId}`;
    } else if (requestType === 'task') {
        // Task di dalam Project/Board -> langsung ke board-nya; task pribadi/penugasan individu
        // (tidak ada project_id) -> halaman Tugas, scope "Tugas Saya". URL papan sendiri
        // (bukan lagi query string di /tugas) - lihat routes/tugas.php.
        // Diskusi Board -> tab Diskusi; notifikasi kartu -> kartu langsung terbuka (?task=).
        const taskId = event.notification.data?.task_id;
        const query = event.notification.data?.discussion ? '?boardFilter=diskusi' : (taskId ? `?task=${taskId}` : '');
        url = (projectId ? `/tugas/papan/board/${projectId}` : '/tugas/papan/saya') + query;
    } else if (requestId) {
        // request_type 'leave'/'permission'/'overtime' (Izin/Cuti/Lembur) -> halaman detail
        // pengajuan absensi; 'user_transition' (Perubahan Status Karyawan) -> halaman detail
        // transisi, beda route sama sekali.
        url = requestType === 'user_transition' ? `/kepegawaian/perubahan-status/${requestId}` : `/pengajuan-absensi/${requestId}`;
    }

    event.waitUntil(clients.openWindow(url));
});
