<?php

use App\Livewire\Sikartap\Mcu\AsesmenPerawatPage;
use App\Livewire\Sikartap\Mcu\HasilLaboratoriumPage;
use App\Livewire\Sikartap\Mcu\HasilRadiologiPage;
use App\Livewire\Sikartap\Mcu\PemeriksaanDokterPage;
use App\Livewire\Kepegawaian\KontrakKelolaPage;
use App\Livewire\Kepegawaian\OrientasiKelolaPage;
use App\Livewire\Sikartap\DashboardSikartapPage;
use App\Livewire\Sikartap\DirekturBulkKeputusanPage;
use App\Livewire\Sikartap\IdeInovasiAtasanQueuePage;
use App\Livewire\Sikartap\IdeInovasiAtasanReviewPage;
use App\Livewire\Sikartap\IdeInovasiUploadPage;
use App\Livewire\Sikartap\McuQueuePage;
use App\Livewire\Sikartap\PengajuanDetailPage;
use App\Livewire\Sikartap\PengajuanKaryawanTetapPage;
use App\Livewire\Sikartap\SikartapSettingsPage;
use App\Livewire\Sikartap\TesPage;
use App\Livewire\Sikartap\WawancaraPage;
use Illuminate\Support\Facades\Route;

/**
 * Route portal Sikartap, dipisah dari routes/web.php atas permintaan eksplisit user
 * (2026-08-25) — nyimpang dari konvensi kodebase ini (semua modul lain, termasuk LMS,
 * ada di satu web.php, tidak ada split route per-modul). File ini di-require dari dalam
 * grup middleware auth+portal.access di web.php, jadi tetap kena middleware yang sama.
 */
Route::get('/sikartap', PengajuanKaryawanTetapPage::class)
    ->name('sikartap');

/**
 * Dashboard widget-based (2026-08-25/26, instruksi user) - satu-satunya landing page Sikartap,
 * daftar pengajuan (dulu PengajuanListPage terpisah) dilebur jadi salah satu widget di sini.
 * Tiap widget punya permission sendiri (LIHAT_DASHBOARD_SIKARTAP_*), lihat
 * DashboardSikartapPage::mount().
 */
Route::get('/sikartap/dashboard', DashboardSikartapPage::class)
    ->name('sikartap.dashboard');

Route::get('/sikartap/pengajuan/{sikartap}', PengajuanDetailPage::class)
    ->name('sikartap.pengajuan.show');

/**
 * Keputusan Direktur massal (instruksi user 2026-08-25) — dipicu widget "Butuh Persetujuan
 * Anda" kalau ada LEBIH DARI 1 pengajuan pending sekaligus buat Direktur yang sama, lihat
 * app/Livewire/Sikartap/DirekturBulkKeputusanPage.php.
 */
Route::get('/sikartap/keputusan-direktur', DirekturBulkKeputusanPage::class)
    ->name('sikartap.direktur.bulk');

/**
 * Tahap Tes & Wawancara lintas pengajuan — 1 halaman per tahap. Penjadwalan bisa massal
 * (centang beberapa orang), isi hasil per orang. Semuanya juga tetap bisa lewat
 * PengajuanDetailPage. MCU sengaja tidak ikut (tidak punya tabel jadwal).
 */
Route::get('/sikartap/tes', TesPage::class)
    ->name('sikartap.tes');

Route::get('/sikartap/wawancara', WawancaraPage::class)
    ->name('sikartap.wawancara');

/**
 * Antrian MCU (dibikin ulang 2026-08-28) — daftar pengajuan yang nunggu input MCU + tombol
 * per stasiun sesuai permission staff. Gate canInputSikartapMcu()/EDIT_INPUT_MCU.
 */
Route::get('/mcu/sikartap', McuQueuePage::class)
    ->name('sikartap.mcu.queue');

Route::get('/mcu/sikartap/{sikartap}/perawat', AsesmenPerawatPage::class)
    ->name('sikartap.mcu.perawat');

Route::get('/mcu/sikartap/{sikartap}/laboratorium', HasilLaboratoriumPage::class)
    ->name('sikartap.mcu.laboratorium');

Route::get('/mcu/sikartap/{sikartap}/radiologi', HasilRadiologiPage::class)
    ->name('sikartap.mcu.radiologi');

Route::get('/mcu/sikartap/{sikartap}/dokter', PemeriksaanDokterPage::class)
    ->name('sikartap.mcu.dokter');

/**
 * Toggle per company: wajib Ide Inovasi & wajib MCU (App\Enum\Sikartap\SikartapSettingEnum).
 */
Route::get('/sikartap/pengaturan', SikartapSettingsPage::class)
    ->name('sikartap.pengaturan');

/**
 * Fase 5 — Ide Inovasi. Karyawan upload+lihat status sendiri; atasan (tahap 1, wajib, lihat
 * Sikartap::ideInovasiAtasanCandidates()) punya antrian+form skoring sendiri. Direktur/Komisaris
 * (tahap opsional) tidak punya halaman terpisah - aksinya nempel di PengajuanDetailPage.
 */
Route::get('/sikartap/ide-inovasi', IdeInovasiUploadPage::class)
    ->name('sikartap.ide-inovasi');

Route::get('/sikartap/ide-inovasi/atasan', IdeInovasiAtasanQueuePage::class)
    ->name('sikartap.ide-inovasi.atasan.queue');

Route::get('/sikartap/ide-inovasi/atasan/{sikartap}', IdeInovasiAtasanReviewPage::class)
    ->name('sikartap.ide-inovasi.atasan');

/**
 * Sisi HR alur perjalanan status karyawan (kelas Livewire tetap di namespace Kepegawaian).
 * Data kontrak/orientasi berjalan ada di dropdown Kepegawaian; ini kelola PENGAJUAN-nya:
 *  - sikartap.kontrak   : kelola pengajuan perpanjangan kontrak (dulu kepegawaian.perpanjangan-kontrak)
 *  - sikartap.orientasi : kelola pengajuan penilaian orientasi (dulu kepegawaian.orientasi)
 */
Route::get('/sikartap/kontrak', KontrakKelolaPage::class)
    ->name('sikartap.kontrak');

Route::get('/sikartap/orientasi', OrientasiKelolaPage::class)
    ->name('sikartap.orientasi');
