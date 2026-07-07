# Pengingat: Urutan Implementasi Portal Karyawan (2026-07-03)

Sepakat mulai implementasi Laravel sungguhan (bukan HTML statis lagi) dengan urutan ini, jangan loncat:

1. **Login** — session-based (guard `web`, bukan Sanctum), sekalian benerin `canAccessPanel()` (restrict `/admin` cuma Admin/Super Admin) dan ubah redirect `/` dari `/admin/login` jadi `/login` portal.
2. **Dashboard level Karyawan** — cuma level ini dulu, karena satu-satunya yang datanya sudah pasti benar sekarang (Manager/Direktur nunggu Fase 1 company-scoping kelar total, Supervisor nunggu Fase 3 hierarki).
3. **Attendance** (form cuti/izin/lembur + list + detail) — sambungkan ke endpoint API yang sudah ada, approval flow di baliknya sudah jalan, tidak perlu dibangun ulang.

Detail lengkap & alasan tiap tahap: `planning/2026-07-03-rencana-implementasi-portal-karyawan.md`.
