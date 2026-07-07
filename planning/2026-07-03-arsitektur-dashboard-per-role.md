# Arsitektur Dashboard per Role (2026-07-03)

## Context

Sistem ini punya 15 nama role unik (11 role dasar yang di-seed ke semua 8 company + 4 role tambahan khusus holding: Accounting Manager, Finance Manager, IT Supervisor, Tax Staff — lihat `knowledge/dev/`). Dashboard yang dilihat tiap orang harus beda sesuai levelnya (karyawan biasa jelas beda dengan supervisor, direktur company beda dengan direktur holding), tapi bikin 1 dashboard unik per nama role (bisa 15+ halaman) tidak akan maintainable dan banyak duplikasi.

4 dashboard demo yang sudah dibuat sebelumnya (`planning/web-attendance/demo/dashboard-hc-manager.html`, `dashboard-direktur.html`, `dashboard-finance.html`, `dashboard-lnd.html`, plus `dashboard.html` untuk karyawan) dipakai untuk eksplorasi gaya visual (neumorphism + bento grid) — bukan untuk ditiru 1:1 jadi 15 halaman terpisah saat masuk ke implementasi Laravel sungguhan. Dokumen ini menjelaskan cara menyusunnya supaya reusable.

## Model: 2 Sumbu, Bukan 1 Dashboard per Role

### Sumbu 1 — Level (menentukan CAKUPAN data)

| Level | Cakupan data | Butuh apa secara teknis |
|---|---|---|
| **Karyawan** | Data diri sendiri | Sudah ada (`dashboard.html` demo) |
| **Supervisor** | Diri sendiri + tim langsung (bawahan) | Fase 3 — `OrgHierarchyService` (belum dibangun) |
| **Manager** | 1 departemen/fungsi (company-scoped) | Fase 1 — company-scoping (sudah jalan sebagian) |
| **Direktur/Eksekutif** | Seluruh company; kalau di holding → lintas company | Fase 1 (company-scoping) + logic khusus holding |

### Sumbu 2 — Domain (menentukan WIDGET apa yang muncul)

| Domain | Widget khas |
|---|---|
| HR / HCGA | Ringkasan absensi, approval cuti/izin/lembur pending, karyawan baru, saldo cuti tim |
| Finance / Accounting | Status pemrosesan payroll, jasa dokter pending, komposisi payroll, biaya lembur |
| L&D | Course aktif, completion rate, jadwal pelatihan, top performer |
| Umum (tanpa domain khusus) | Task saya, pengumuman, jadwal shift |

### Pemetaan Role → Level + Domain

| Role (nama di sistem) | Level | Domain |
|---|---|---|
| Karyawan / Staff | Karyawan | Umum |
| Kasubag / Staff Kepegawaian | Karyawan | HR |
| Staff Finance | Karyawan | Finance |
| Tax Staff *(holding)* | Karyawan | Finance |
| Learning & Development | Karyawan/Supervisor *(tergantung jenjang)* | L&D |
| HC Supervisor | Supervisor | HR |
| IT Supervisor *(holding)* | Supervisor | Umum/IT |
| Supervisor / Kepala Ruangan | Supervisor | Umum |
| HC Manager | Manager | HR |
| Manager / Kepala Bidang | Manager | Umum |
| Finance Manager *(holding)* | Manager | Finance |
| Accounting Manager *(holding)* | Manager | Finance |
| Direktur | Direktur | Semua (ringkasan lintas domain) |
| Admin | — *(bukan level operasional, akses penuh 1 company via Filament)* |
| Super Admin | — *(bukan level operasional, akses penuh semua company via Filament)* |

Catatan: **Admin & Super Admin tetap pakai Filament `/admin`** (sesuai kesepakatan sebelumnya — Filament untuk master data & admin, bukan dashboard operasional harian). Dashboard per-Level+Domain di atas HANYA untuk portal karyawan/manajerial, bukan replacement Filament.

## Cara Bangun Secara Teknis (saat masuk implementasi Laravel, bukan HTML statis)

Jangan bikin 1 Blade/Livewire page per kombinasi Level×Domain (bisa >10 kombinasi). Sebaliknya:

1. **1 layout dashboard generik** (`resources/views/portal/dashboard.blade.php` atau Livewire component) yang punya "slot" bento grid.
2. **Widget library reusable** — tiap widget adalah komponen kecil berdiri sendiri (mis. `AttendanceSummaryWidget`, `PendingApprovalsWidget`, `PayrollStatusWidget`, `CourseCompletionWidget`, `TeamOverviewWidget`), masing-masing query data-nya sendiri dengan scope yang sesuai (company-scope dari Fase 1, hierarki dari Fase 3 kalau widget-nya level Supervisor ke atas).
3. **Resolver Level+Domain dari user login**: fungsi/service kecil yang menentukan widget mana yang ditampilkan berdasarkan role & job_position user (bukan hardcode per nama role — supaya kalau ada role baru dengan kombinasi Level+Domain yang sudah ada, otomatis dapat widget yang sama tanpa nulis kode baru).
4. Urutan widget & welcome banner tetap bisa disesuaikan per Level (Karyawan dapat banner hijau personal, Manager/Direktur dapat banner ringkasan eksekutif) — ini styling saja, bukan struktur data baru.

## Dependency & Urutan Pengerjaan

- **Karyawan** — sudah siap dari sisi desain (demo `dashboard.html`), backend (API attendance) sudah ada.
- **Manager & Direktur** — sudah siap dari sisi desain (4 demo dashboard), tapi baru bisa diimplementasikan dengan benar SETELAH Fase 1 (company-scoping) selesai semua, supaya data yang tampil otomatis terpotong per company.
- **Supervisor** — **belum bisa dibangun dengan benar** sampai Fase 3 (`OrgHierarchyService`, hierarki dinamis dari `parent_id`) selesai, karena widget khasnya ("tim saya") butuh resolusi bawahan yang belum ada.

Rekomendasi urutan: selesaikan sisa Fase 1 (model yang masih bocor) → Fase 3 (hierarki, supaya dashboard Supervisor bisa dibangun akurat) → baru masuk ke implementasi dashboard Laravel sungguhan (bukan HTML statis lagi) memakai widget library di atas.
